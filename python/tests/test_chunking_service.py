import pytest
from services.chunking_service import ChunkingService

def test_dot_protection_in_chunking():
    service = ChunkingService()
    text = "Dokumen ini ditandatangani oleh dr. Budi dan berharga Rp. 1.000.000."
    
    chunks = service._narrative_chunk(text, "[Test] ")
    
    # Pastikan tidak ada chunk yang terpotong di "dr" atau "Rp"
    for chunk in chunks:
        assert not chunk.strip().endswith("dr")
        assert not chunk.strip().endswith("Rp")
        # Pastikan gelar dan nominal utuh
        assert "dr. Budi" in chunk or "dr." in chunk
        assert "Rp. 1.000.000" in chunk or "Rp." in chunk

def test_legal_structure_not_split():
    service = ChunkingService()
    text = """
    BAB I
    KETENTUAN UMUM
    
    Pasal 1
    Dalam peraturan ini yang dimaksud dengan Negara adalah Republik Indonesia.
    Pasal ini sangat panjang dan berisi banyak kalimat. Kalimat kedua. Kalimat ketiga.
    
    Pasal 2
    Ketentuan lebih lanjut diatur dengan Peraturan Menteri.
    """
    
    chunks = service._structure_aware_chunk(text, "[UU] ")
    
    # Pasal 1 tidak boleh terbelah, harus jadi 1 chunk utuh
    pasal_1_chunks = [c for c in chunks if "Pasal 1" in c]
    assert len(pasal_1_chunks) == 1
    assert "Kalimat ketiga" in pasal_1_chunks[0]