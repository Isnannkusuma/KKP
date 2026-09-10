import types
from unittest.mock import AsyncMock, patch

import pytest

from routers import ingest as ingest_module


@pytest.mark.asyncio
async def test_process_document_pipeline_batches_embeddings_for_memory_safety(tmp_path):
    file_path = tmp_path / "sample.txt"
    file_path.write_text("sample text")

    ingest_module.extraction_service = types.SimpleNamespace(
        extract=AsyncMock(return_value=[{"text": "Alpha beta gamma", "type": "text"}])
    )
    ingest_module.cleaning_service = types.SimpleNamespace(
        clean_text=AsyncMock(return_value={"cleaned_text": "Alpha beta gamma", "provider_used": "test"})
    )
    ingest_module.chunking_service = types.SimpleNamespace(
        chunk_document=lambda text, doc_type, metadata_prefix: [text, text, text, text, text]
    )
    ingest_module.embedding_service = types.SimpleNamespace(
        embed_passages=AsyncMock(return_value=[[0.1, 0.2]] * 5)
    )
    ingest_module.vector_repo = types.SimpleNamespace(insert_chunks=AsyncMock())
    ingest_module.settings.EMBEDDING_BATCH_SIZE = 2

    with patch.object(ingest_module, "get_chunking_strategy", return_value="narrative"):
        await ingest_module.process_document_pipeline("doc-1", str(file_path), "lainnya")

    assert ingest_module.embedding_service.embed_passages.await_count == 3
    inserted = ingest_module.vector_repo.insert_chunks.await_args.args[0]
    assert len(inserted) == 5
    assert all("embedding" in chunk for chunk in inserted)
