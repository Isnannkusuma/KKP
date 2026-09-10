from pydantic import BaseModel, UUID4
from typing import Optional

class IngestRequest(BaseModel):
    document_id: UUID4
    access_level: str
    department_id: UUID4
    category: str

class IngestResponse(BaseModel):
    status: str
    document_id: UUID4
    message: str

class JobStatusResponse(BaseModel):
    job_id: str
    status: str
    progress: Optional[int] = None
    error_message: Optional[str] = None