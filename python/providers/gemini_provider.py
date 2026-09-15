import httpx
from providers.base import TextCleanerProvider
from core.config import settings
from core.exceptions import ProviderRateLimitError, ProviderServerError

class GeminiProvider(TextCleanerProvider):
    def __init__(self):
        self.client = httpx.AsyncClient(timeout=60.0)
        self.api_key = settings.GEMINI_API_KEY
        self.url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={self.api_key}"
    async def clean(self, text: str) -> str:
        system_prompt = """
        Kamu adalah mesin perbaikan teks OCR tingkat tinggi untuk dokumen resmi pemerintah Indonesia. 
        Tugas kamu HANYA memperbaiki kesalahan baca OCR dan format spasi. 

        ATURAN WAJIB (HARUS DITAATI 100%):
        1. PERBAIKI typo OCR (contoh: 'bhw' -> 'bahwa', 'N0m0r' -> 'Nomor', 'Vll' -> 'VII').
        2. PERBAIKI spasi yang salah (contoh: 'dr . Budi' -> 'dr. Budi', 'Rp . 100' -> 'Rp100').
        3. JANGAN MENGHAPUS SATU KATA PUN. Panjang teks output harus sama persis dengan teks input.
        4. JANGAN meringkas, JANGAN menggabungkan paragraf, JANGAN mengubah susunan kalimat.
        5. Pertahankan semua nama orang, gelar (dr., S.Kom.), angka, tanggal, dan kop surat apa adanya.
        6. Jika ada teks yang tidak kamu mengerti, biarkan apa adanya.

        Output HANYA teks yang sudah diperbaiki.
        """

        payload = {
            "contents": [{
                "parts": [
                    {"text": system_prompt},
                    {"text": f"Perbaiki teks di dalam tag <document> berikut:\n\n<document>\n{text}\n</document>"}
                ]
            }],
            "generationConfig": {
                "temperature": 0.0,
                "maxOutputTokens": 8192
            }
        }
        
        response = await self.client.post(self.url, json=payload)
        
        if response.status_code == 429:
            raise ProviderRateLimitError("Gemini rate limit exceeded")
        if response.status_code >= 500:
            raise ProviderServerError(f"Gemini server error: {response.status_code}")
        if response.status_code != 200:
            raise Exception(f"Gemini failed: {response.text}")
            
        data = response.json()
        return data["candidates"][0]["content"]["parts"][0]["text"]
