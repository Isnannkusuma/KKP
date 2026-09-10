import structlog
from tenacity import retry, stop_after_attempt, wait_exponential, retry_if_exception_type
from providers.gemini_provider import GeminiProvider
from providers.groq_provider import GroqProvider
from core.exceptions import ProviderRateLimitError, ProviderServerError

logger = structlog.getLogger()

class CleaningService:
    def __init__(self):
        self.gemini = GeminiProvider()
        self.groq = GroqProvider()

    async def clean_text(self, text: str, extraction_method: str) -> dict:
        if extraction_method == "digital_text":
            return {"cleaned_text": text, "provider_used": "none_skipped"}

        try:
            cleaned = await self._call_gemini_with_retry(text)
            return {"cleaned_text": cleaned, "provider_used": "gemini"}
            
        except Exception as e:
            logger.warning(f"⚠️ Gemini gagal setelah retry ({e}). Beralih ke Groq (Fallback).")
            try:
                cleaned = await self.groq.clean(text)
                return {"cleaned_text": cleaned, "provider_used": "groq"}
                
            except Exception as e2:
                logger.error(f"❌ Groq juga gagal ({e2}). Mengembalikan teks asli (Fail-safe).")
                return {"cleaned_text": text, "provider_used": "none_failed"}

    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=2, max=10),
        retry=retry_if_exception_type((ProviderRateLimitError, ProviderServerError, Exception)) 
    )
    async def _call_gemini_with_retry(self, text: str) -> str:
        return await self.gemini.clean(text)