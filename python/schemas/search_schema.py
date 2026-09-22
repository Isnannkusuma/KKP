from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class SearchRequest(BaseModel):
    query: str
    top_k: int = 5
    document_id: Optional[str] = None
    category_slug: Optional[str] = None
    query_embedding: Optional[List[float]] = None

class SearchResponse(BaseModel):
    query: str
    results: List[Dict[str, Any]]
    query_embedding: Optional[List[float]] = None