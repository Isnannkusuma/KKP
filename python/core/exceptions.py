class ProviderRateLimitError(Exception):
    """Dinaikkan saat provider LLM mengembalikan 429."""
    pass

class ProviderServerError(Exception):
    """Dinaikkan saat provider LLM mengembalikan 5xx."""
    pass

class ExtractionError(Exception):
    """Dinaikkan saat file gagal diekstrak (format rusak, dll)."""
    pass