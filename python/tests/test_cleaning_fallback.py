import pytest
from unittest.mock import AsyncMock, patch
from services.cleaning_service import CleaningService
from core.exceptions import ProviderRateLimitError

@pytest.mark.asyncio
async def test_cleaning_fallback_to_gemini():
    service = CleaningService()
    
    # Mock Groq gagal terus menerus (Rate limit)
    service.groq.clean = AsyncMock(side_effect=ProviderRateLimitError("Rate limit"))
    # Mock Gemini sukses
    service.gemini.clean = AsyncMock(return_value="Teks bersih dari Gemini")
    
    result = await service.clean_text("Teks berantakan", "ocr")
    
    assert result['cleaned_text'] == "Teks bersih dari Gemini"
    assert result['provider_used'] == "gemini"
    # Verifikasi Groq dipanggil 3 kali (sesuai tenacity retry)
    assert service.groq.clean.call_count == 3

@pytest.mark.asyncio
async def test_cleaning_failsafe_returns_original():
    service = CleaningService()
    service.groq.clean = AsyncMock(side_effect=Exception("Down"))
    service.gemini.clean = AsyncMock(side_effect=Exception("Down"))
    
    original_text = "Teks asli yang tidak dibersihkan"
    result = await service.clean_text(original_text, "ocr")
    
    assert result['cleaned_text'] == original_text
    assert result['provider_used'] == "none_failed"