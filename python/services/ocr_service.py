import pytesseract
from PIL import Image
import structlog

logger = structlog.getLogger()

class OCRService:
    async def extract_text(self, image_path: str) -> str:
        """
        Menjalankan Tesseract OCR pada gambar.
        Konfigurasi bahasa Indonesia + Inggris.
        """
        try:
            img = Image.open(image_path)
            # PSM 6: Asumsikan satu blok teks seragam (cocok untuk halaman dokumen)
            custom_config = r'--oem 3 --psm 6'
            text = pytesseract.image_to_string(img, lang='ind+eng', config=custom_config)
            return text.strip()
        except Exception as e:
            logger.error(f"OCR failed for {image_path}: {str(e)}")
            return "" # Fail-safe: kembalikan string kosong, jangan hentikan pipeline