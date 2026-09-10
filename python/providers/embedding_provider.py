import structlog
import asyncio
import torch
import time
from typing import List
from sentence_transformers import SentenceTransformer
from providers.base import EmbeddingProvider

logger = structlog.getLogger()

class LocalEmbeddingProvider(EmbeddingProvider):
    def __init__(self, model_name: str):
        # Deteksi otomatis: pakai GPU kalau tersedia, fallback ke CPU kalau tidak.
        # Ini penting supaya kode tetap jalan di komputer lain 
        # yang tidak punya GPU NVIDIA, tanpa perlu ubah kode manual.
        device = "cuda" if torch.cuda.is_available() else "cpu"

        if device == "cuda":
            gpu_name = torch.cuda.get_device_name(0)
            logger.info(f"🚀 GPU terdeteksi: {gpu_name}. Model akan dimuat ke GPU.")
        else:
            logger.info("⚠️  GPU tidak terdeteksi, fallback ke CPU.")

        logger.info(f"🔄 Memuat model embedding dari: {model_name}")

        self.model = SentenceTransformer(model_name, device=device, local_files_only=True)
        self.device = device
        logger.info(f"✅ Model embedding lokal berhasil dimuat ke memori ({device}).")

    async def embed(self, texts: List[str]) -> List[List[float]]:
        loop = asyncio.get_running_loop()

        start = time.perf_counter()

        def _encode_texts():
            return self.model.encode(
                texts,
                batch_size=4,
                convert_to_numpy=True,
                show_progress_bar=False,
            )

        embeddings = await loop.run_in_executor(
            None,
            _encode_texts
        )

        elapsed = time.perf_counter() - start

        logger.info(
            "embedding_completed",
            texts=len(texts),
            elapsed=f"{elapsed:.3f}s",
            device=self.device,
        )

        return embeddings.tolist()












# untuk cpu
# import structlog
# import asyncio
# from typing import List
# from sentence_transformers import SentenceTransformer
# from providers.base import EmbeddingProvider

# logger = structlog.getLogger()


# class LocalEmbeddingProvider(EmbeddingProvider):
#     def __init__(self, model_name: str):
#         logger.info(f"🔄 Memuat model embedding dari: {model_name}")
       
#         self.model = SentenceTransformer(model_name, device="cpu", local_files_only=True)
#         logger.info("✅ Model embedding lokal berhasil dimuat ke memori.")

#     async def embed(self, texts: List[str]) -> List[List[float]]:
       
#         loop = asyncio.get_running_loop()
        
#         def _encode_texts():
#             return self.model.encode(
#                 texts, 
#                 convert_to_numpy=True, 
#                 show_progress_bar=False
#             )
        
#         embeddings = await loop.run_in_executor(None, _encode_texts)
        
#         return embeddings.tolist()