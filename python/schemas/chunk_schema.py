from pydantic import BaseModel
from typing import List, Optional
from uuid import UUID

class DocumentChunk(BaseModel):
    id: UUID
    document_id: UUID
    content: str
    content_type: str 
    extraction_method: str
    metadata: dict 
    embedding: Optional[List[float]] = None
    chunk_index: int