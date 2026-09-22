from dotenv import load_dotenv
load_dotenv()

import structlog
from contextlib import asynccontextmanager
from fastapi import FastAPI
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded
from slowapi.middleware import SlowAPIMiddleware

from core.logging import setup_logging
from repositories.vector_repository import vector_repo
from routers import ingest, search, embed, cluster

setup_logging()
logger = structlog.get_logger()

limiter = Limiter(key_func=get_remote_address)

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("starting_up")
    await vector_repo.connect()
    yield
    logger.info("shutting_down")
    await vector_repo.disconnect()

app = FastAPI(
    title="Document Intelligence Microservice",
    version="1.0.0",
    lifespan=lifespan
)

# Rate limiting
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_middleware(SlowAPIMiddleware)

# Routers
app.include_router(ingest.router, tags=["Ingestion"])
app.include_router(search.router, tags=["Search"])
app.include_router(embed.router, tags=["Embedding"])
app.include_router(cluster.router, tags=["Clustering"])

@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "db_connected": vector_repo.pool is not None
    }