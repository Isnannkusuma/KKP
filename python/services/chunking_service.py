import re
import structlog
from typing import List
from core.config import settings

logger = structlog.getLogger()

class ChunkingService:
    def __init__(self):
        self.protected_items = {}
        self.counter = 0

    def _protect_and_split_sentences(self, text: str) -> List[str]:
        self.protected_items = {}
        self.counter = 0

        def replacer(match):
            self.counter += 1
            placeholder = f"[[PROTECT_{self.counter}]]"
            self.protected_items[placeholder] = match.group(0)
            return placeholder

        # Pola yang mengandung titik tapi bukan akhir kalimat
        patterns_to_protect = [
            r'\bdr\.\s',             # dr. 
            r'\bS\.Kom\.\s',         # S.Kom. 
            r'\bIr\.\s',             # Ir.
            r'Rp\.\s',               # Rp. 
            r'\bNo\.\s',             # No.
            r'\b\d{1,3}(?:\.\d{3})+(?:,\d+)?\b' # 1.000.000 atau 1.000,50
        ]

        protected_text = text
        for pattern in patterns_to_protect:
            protected_text = re.sub(pattern, replacer, protected_text, flags=re.IGNORECASE)
            
        # Split berdasarkan batas kalimat yang sebenarnya (titik diikuti spasi dan huruf kapital)
        sentences = re.split(r'(?<=[.!?])\s+(?=[A-Z])', protected_text)
        
        # Kembalikan (restore) teks asli dari placeholder
        restored_sentences = []
        for sent in sentences:
            for placeholder, original_text in self.protected_items.items():
                sent = sent.replace(placeholder, original_text)
            restored_sentences.append(sent)
            
        return restored_sentences

    def chunk_document(self, text: str, doc_type: str, metadata_prefix: str) -> List[str]:
        if doc_type == "table" or doc_type == "table_row":
            return self._row_level_chunk(text, metadata_prefix)
        elif doc_type == "legal":
            return self._structure_aware_chunk(text, metadata_prefix)
        else:
            return self._narrative_chunk(text, metadata_prefix)

    def _narrative_chunk(self, text: str, prefix: str) -> List[str]:
        sentences = self._protect_and_split_sentences(text)
        chunks = []
        current_chunk = []
        current_len = 0
        
        for sent in sentences:
            sent_len = len(sent)
            # Jika chunk saat ini + kalimat baru melebihi batas, simpan chunk dan mulai baru
            if current_len + sent_len > settings.MAX_CHUNK_SIZE and current_chunk:
                chunks.append(prefix + " ".join(current_chunk))
                # Overlap: simpan 1 kalimat terakhir ke chunk berikutnya agar konteks tidak putus
                overlap_sent = current_chunk[-1]
                current_chunk = [overlap_sent, sent]
                current_len = len(overlap_sent) + sent_len
            else:
                current_chunk.append(sent)
                current_len += sent_len
                
        if current_chunk:
            chunks.append(prefix + " ".join(current_chunk))
            
        return self._validate_chunks(chunks)

    def _structure_aware_chunk(self, text: str, prefix: str) -> List[str]:
        # Potong berdasarkan BAB/Pasal. 1 Pasal = 1 Chunk utuh
        legal_structure_pattern = re.compile(r'^(BAB\s+[IVXLC]+|Pasal\s+\d+)', re.MULTILINE | re.IGNORECASE)
        sections = legal_structure_pattern.split(text)
        chunks = []
        
        current_header = ""
        for part in sections:
            if legal_structure_pattern.match(part):
                current_header = part
            else:
                if part.strip():
                    chunks.append(f"{prefix} [{current_header.strip()}] {part.strip()}")
                    
        return self._validate_chunks(chunks)

    def _row_level_chunk(self, text: str, prefix: str) -> List[str]:
        # 1 baris = 1 chunk utuh
        lines = text.strip().split('\n')
        return [f"{prefix} {line.strip()}" for line in lines if line.strip()]

    def _validate_chunks(self, chunks: List[str]) -> List[str]:
        """Safety net: Cek apakah ada pola yang terpotong aneh di akhir chunk"""
        validated = []
        # Regex: Berakhir dengan dr, Rp, No, atau S.Kom TANPA titik di belakangnya
        broken_pattern = re.compile(r'\b(dr|Rp|No|S\.Kom)$', re.IGNORECASE) 
        
        for chunk in chunks:
            if broken_pattern.search(chunk.strip()):
                logger.warning(f"Potentially broken chunk detected at boundary: ...{chunk[-30:]}")
            validated.append(chunk)
        return validated