from fastapi import APIRouter, HTTPException, Request
from slowapi import Limiter
from slowapi.util import get_remote_address
from pydantic import BaseModel
from typing import List
import structlog

from services.embedding_service import EmbeddingService

router = APIRouter()
logger = structlog.get_logger()
limiter = Limiter(key_func=get_remote_address)
embedding_service = EmbeddingService()


class EmbedQueriesRequest(BaseModel):
    queries: List[str]


class EmbedQueriesResponse(BaseModel):
    embeddings: List[List[float]]


@router.post("/embed/queries", response_model=EmbedQueriesResponse)
@limiter.limit("20/minute")
async def embed_queries(request: Request, payload: EmbedQueriesRequest):
    """
    Endpoint khusus untuk generate embedding dari batch pertanyaan (query),
    tanpa menjalankan hybrid search penuh. Dipakai untuk:
    - Backfill query_embedding pada data lama di search_audit_logs
    - Kebutuhan lain yang hanya butuh vector representasi query
    """
    try:
        if not payload.queries:
            return EmbedQueriesResponse(embeddings=[])

        logger.info("embed_queries_requested", count=len(payload.queries))

        embeddings = []
        for query in payload.queries:
            vector = await embedding_service.embed_query(query)
            embeddings.append(vector)

        return EmbedQueriesResponse(embeddings=embeddings)

    except Exception as e:
        logger.error("embed_queries_failed", error=str(e))
        raise HTTPException(status_code=500, detail="Internal embedding error")