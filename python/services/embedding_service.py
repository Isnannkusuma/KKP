import os
from pathlib import Path
from typing import List, Union

import structlog
from huggingface_hub import snapshot_download

from providers.embedding_provider import LocalEmbeddingProvider

logger = structlog.getLogger()

MODEL_REPO_ID = "intfloat/multilingual-e5-base"
MODEL_SUBDIR = "multilingual-e5-base"


def resolve_model_path(model_dir: Union[str, Path]) -> str:
    """Pastikan model local tersedia sebelum SentenceTransformer dipakai."""
    model_path = Path(model_dir)
    files_needed = [
        "config_sentence_transformers.json",
        "tokenizer_config.json",
        "model.safetensors",
        "pytorch_model.bin",
    ]

    needs_download = not model_path.exists() or not any((model_path / filename).exists() for filename in files_needed)
    if needs_download:
        logger.warning("📦 Model embedding lokal tidak ditemukan; mulai download otomatis...", path=str(model_path))
        model_path.mkdir(parents=True, exist_ok=True)
        snapshot_download(
            repo_id=MODEL_REPO_ID,
            local_dir=model_path,
            local_dir_use_symlinks=False,
            ignore_patterns=[
                "*.onnx",
                "*.onnx_data",
                "openvino*",
                "model_qint8*",
                "pytorch_model.bin",
            ],
        )

    return str(model_path)


class EmbeddingService:
    def __init__(self):
        local_model_path = Path(__file__).resolve().parent.parent / "models" / MODEL_SUBDIR
        local_model_path = local_model_path.resolve()
        local_model_path_str = resolve_model_path(local_model_path)

        logger.info(f"🔄 Memuat model embedding dari LOKAL: {local_model_path_str}")

        self.provider = LocalEmbeddingProvider(model_name=local_model_path_str)
        logger.info("✅ Model embedding lokal berhasil dimuat.")

    async def embed_passages(self, texts: List[str]) -> List[List[float]]:
        prefixed_texts = [f"passage: {text}" for text in texts]
        return await self.provider.embed(prefixed_texts)

    async def embed_query(self, query: str) -> List[float]:
        prefixed_query = f"query: {query}"
        embeddings = await self.provider.embed([prefixed_query])
        return embeddings[0]