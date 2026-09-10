import os

import httpx
from providers.base import TextCleanerProvider
from core.config import settings
from core.exceptions import ProviderRateLimitError, ProviderServerError

class GroqProvider(TextCleanerProvider):
    def __init__(self):
        self.client = httpx.AsyncClient(timeout=30.0)
        self.api_key = settings.GROQ_API_KEY
        self.url = "https://api.groq.com/openai/v1/chat/completions"

    async def clean(self, text: str) -> str:
        system_prompt = """
        Kamu adalah mesin perbaikan teks OCR tingkat tinggi untuk dokumen resmi pemerintah Indonesia. 
        Tugas kamu HANYA memperbaiki kesalahan baca OCR dan format spasi. 

        ATURAN WAJIB (HARUS DITAATI 100%):
        1. PERBAIKI typo OCR (contoh: 'bhw' -> 'bahwa', 'N0m0r' -> 'Nomor', 'Vll' -> 'VII').
        2. PERBAIKI spasi yang salah (contoh: 'dr . Budi' -> 'dr. Budi', 'Rp . 100' -> 'Rp100').
        3. JANGAN MENGHAPUS SATU KATA PUN. Panjang teks output harus sama persis dengan teks input.
        4. JANGAN meringkas, JANGAN menggabungkan paragraf, JANGAN mengubah susunan kalimat.
        5. Pertahankan semua nama orang, gelar (dr., S.Kom.), angka, tanggal, dan kop surat apa adanya (hanya perbaiki typo/spasinya).
        6. Jika ada teks yang tidak kamu mengerti, biarkan apa adanya. Jangan menebak atau menghapusnya.

        Output HANYA teks yang sudah diperbaiki. Jangan berikan penjelasan, jangan ada kata "Berikut adalah teks yang diperbaiki:".
        """
        headers = {"Authorization": f"Bearer {self.api_key}"}
        payload = {
            "model": os.getenv("GROQ_MODEL", "openai/gpt-oss-20b"),
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": text}
            ],
            "temperature": 0.0
        }
        
        response = await self.client.post(self.url, json=payload, headers=headers)
        
        if response.status_code == 429:
            raise ProviderRateLimitError("Groq rate limit exceeded")
        if response.status_code >= 500:
            raise ProviderServerError(f"Groq server error: {response.status_code}")
        if response.status_code != 200:
            raise Exception(f"Groq failed: {response.text}")
            
        return response.json()["choices"][0]["message"]["content"]