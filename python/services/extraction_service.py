import os
import tempfile
import uuid
import fitz  # PyMuPDF
import pdfplumber
import pandas as pd
from docx import Document as DocxDocument
from typing import List, Dict, Any
from core.exceptions import ExtractionError

class ExtractionService:
    async def extract(self, file_path: str) -> List[Dict[str, Any]]:
        ext = os.path.splitext(file_path)[1].lower()
        
        if ext == '.pdf':
            return self._extract_pdf(file_path)
        elif ext == '.docx':
            return self._extract_docx(file_path)
        elif ext in ['.xlsx', '.csv']:
            return self._extract_spreadsheet(file_path, ext)
        else:
            raise ExtractionError(f"Unsupported file extension: {ext}")

    def _extract_pdf(self, file_path: str) -> List[Dict[str, Any]]:
        elements = []
        doc = fitz.open(file_path)
        
        # Gunakan pdfplumber khusus untuk ekstraksi tabel yang lebih akurat
        with pdfplumber.open(file_path) as pdf_plumber:
            for i, page in enumerate(doc):
                page_text = page.get_text("text").strip()
                
                # Deteksi kebutuhan OCR: jika teks digital sangat sedikit (< 50 chars)
                needs_ocr = len(page_text) < 50 
                
                if needs_ocr:
                    # Simpan halaman sebagai gambar untuk OCR service
                    img_path = os.path.join(tempfile.gettempdir(), f"ocr_{uuid.uuid4().hex}_{i}.png")
                    pix = page.get_pixmap(dpi=200)
                    pix.save(img_path)
                    elements.append({
                        'type': 'image', 
                        'text': '', 
                        'needs_ocr': True, 
                        'image_path': img_path
                    })
                else:
                    elements.append({
                        'type': 'text', 
                        'text': page_text, 
                        'needs_ocr': False
                    })
                
                # Ekstraksi tabel dari halaman yang sama
                pdf_page = pdf_plumber.pages[i]
                tables = pdf_page.extract_tables()
                for table in tables:
                    if table:
                        df = pd.DataFrame(table[1:], columns=table[0])
                        # Konversi ke Markdown table
                        md_table = df.to_markdown(index=False)
                        elements.append({
                            'type': 'table', 
                            'text': md_table, 
                            'needs_ocr': False
                        })
        doc.close()
        return elements

    def _extract_docx(self, file_path: str) -> List[Dict[str, Any]]:
        elements = []
        doc = DocxDocument(file_path)
        current_text = []
        
        for para in doc.paragraphs:
            # Pertahankan struktur heading untuk chunking legal nanti
            if para.style.name.startswith('Heading'):
                if current_text:
                    elements.append({'type': 'text', 'text': '\n'.join(current_text), 'needs_ocr': False})
                    current_text = []
                current_text.append(f"\n{para.style.name.upper()}: {para.text}\n")
            else:
                if para.text.strip():
                    current_text.append(para.text)
                    
        if current_text:
            elements.append({'type': 'text', 'text': '\n'.join(current_text), 'needs_ocr': False})
            
        return elements

    def _extract_spreadsheet(self, file_path: str, ext: str) -> List[Dict[str, Any]]:
        elements = []
        if ext == '.csv':
            df = pd.read_csv(file_path)
        else:
            df = pd.read_excel(file_path)
            
        # Untuk spreadsheet, kita ubah tiap baris menjadi teks kontekstual
        # agar saat di-chunk (row-level), konteks header tidak hilang.
        headers = df.columns.tolist()
        
        for _, row in df.iterrows():
            row_text = " | ".join([f"{h}: {v}" for h, v in zip(headers, row) if pd.notna(v)])
            elements.append({
                'type': 'table_row', 
                'text': row_text, 
                'needs_ocr': False
            })
        return elements