from abc import ABC, abstractmethod
from typing import List

class TextCleanerProvider(ABC):
    @abstractmethod
    async def clean(self, text: str) -> str:
        pass

class EmbeddingProvider(ABC):
    @abstractmethod
    async def embed(self, texts: List[str]) -> List[List[float]]:
        pass