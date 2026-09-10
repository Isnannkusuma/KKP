from fastapi import APIRouter, HTTPException, Request
from slowapi import Limiter
from slowapi.util import get_remote_address
import structlog

from schemas.search_schema import SearchRequest, SearchResponse
from services.embedding_service import EmbeddingService
from repositories.vector_repository import vector_repo

router = APIRouter()
logger = structlog.get_logger()
limiter = Limiter(key_func=get_remote_address)
embedding_service = EmbeddingService()


@router.post("/search", response_model=SearchResponse)
@limiter.limit("60/minute")
async def search_documents(request: Request, payload: SearchRequest):
    try:
        logger.info("search_requested", query=payload.query, top_k=payload.top_k)
        
        # 1. Embed query
        query_vector = await embedding_service.embed_query(payload.query)
        
        # 2. Hybrid Search
        db_results = await vector_repo.search_chunks(
            query_embedding=query_vector,
            query_text=payload.query,
            top_k=payload.top_k,
            document_id=payload.document_id,
            category_slug=payload.category_slug
        )
        
        # 3. Format hasil
        formatted_results = [
            {
                "chunk_id": str(row['id']),
                "document_id": str(row['document_id']),
                "chunk_index": row['chunk_index'],
                "content": row['content'],
                "hybrid_score": row['hybrid_score'],
                "metadata": row['metadata'] or {}
            }
            for row in db_results
        ]
        
        return SearchResponse(query=payload.query, results=formatted_results)
        
    except Exception as e:
        logger.error("search_failed", error=str(e))
        raise HTTPException(status_code=500, detail="Internal search error")