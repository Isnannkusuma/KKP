from fastapi import APIRouter, HTTPException, Request
from slowapi import Limiter
from slowapi.util import get_remote_address
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
import numpy as np
from sklearn.cluster import DBSCAN
import structlog

router = APIRouter()
logger = structlog.get_logger()
limiter = Limiter(key_func=get_remote_address)


class ClusterItem(BaseModel):
    id: str
    query: str
    embedding: List[float]


class ClusterRequest(BaseModel):
    items: List[ClusterItem]
    eps: float = 0.3
    min_samples: int = 5


class ClusterResult(BaseModel):
    label: int
    member_count: int
    member_ids: List[str]
    sample_questions: List[str]
    representative_id: str
    representative_question: str


class ClusterResponse(BaseModel):
    clusters: List[ClusterResult]
    total_items: int
    total_clustered: int
    total_noise: int


@router.post("/cluster", response_model=ClusterResponse)
@limiter.limit("10/minute")
async def cluster_questions(request: Request, payload: ClusterRequest):
    """
    Mengelompokkan pertanyaan berdasarkan kemiripan embedding-nya (cosine distance)
    memakai DBSCAN. Pertanyaan yang tidak mirip dengan pertanyaan lain manapun
    akan dianggap noise dan tidak masuk cluster manapun (label -1, diabaikan).
    """
    try:
        total_items = len(payload.items)

        if total_items < payload.min_samples:
            return ClusterResponse(
                clusters=[],
                total_items=total_items,
                total_clustered=0,
                total_noise=total_items,
            )

        embeddings = np.array([item.embedding for item in payload.items])

        # Normalisasi supaya cosine distance konsisten
        norms = np.linalg.norm(embeddings, axis=1, keepdims=True)
        norms[norms == 0] = 1e-10  # hindari pembagian dengan nol
        normalized = embeddings / norms

        clustering = DBSCAN(
            eps=payload.eps,
            min_samples=payload.min_samples,
            metric="cosine",
        ).fit(normalized)

        labels = clustering.labels_
        unique_labels = set(labels)

        clusters = []
        total_noise = int(np.sum(labels == -1))

        for label in unique_labels:
            if label == -1:
                continue  # noise, bukan cluster valid

            member_indices = [i for i, l in enumerate(labels) if l == label]
            members = [payload.items[i] for i in member_indices]

            # Ambil anggota yang embedding-nya paling dekat ke centroid sebagai representative
            member_embeddings = normalized[member_indices]
            centroid = member_embeddings.mean(axis=0)
            distances_to_centroid = np.linalg.norm(member_embeddings - centroid, axis=1)
            representative_idx = member_indices[int(np.argmin(distances_to_centroid))]
            representative = payload.items[representative_idx]

            clusters.append(ClusterResult(
                label=int(label),
                member_count=len(members),
                member_ids=[m.id for m in members],
                sample_questions=[m.query for m in members[:15]],
                representative_id=representative.id,
                representative_question=representative.query,
            ))

        clusters.sort(key=lambda c: c.member_count, reverse=True)

        logger.info(
            "clustering_complete",
            total_items=total_items,
            clusters_found=len(clusters),
            total_noise=total_noise,
        )

        return ClusterResponse(
            clusters=clusters,
            total_items=total_items,
            total_clustered=total_items - total_noise,
            total_noise=total_noise,
        )

    except Exception as e:
        logger.error("clustering_failed", error=str(e))
        raise HTTPException(status_code=500, detail="Internal clustering error")