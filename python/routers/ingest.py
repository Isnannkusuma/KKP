from fastapi import APIRouter, HTTPException, Request, UploadFile, File, Form
from slowapi import Limiter
from slowapi.util import get_remote_address
import structlog
import os
import uuid
import json

from services.extraction_service import ExtractionService
from services.cleaning_service import CleaningService
from services.chunking_service import ChunkingService
from services.embedding_service import EmbeddingService
from repositories.vector_repository import vector_repo
from core.config import settings

router = APIRouter()
logger = structlog.get_logger()
limiter = Limiter(key_func=get_remote_address)

extraction_service = ExtractionService()
cleaning_service = CleaningService()
chunking_service = ChunkingService()
embedding_service = EmbeddingService()


@router.post("/ingest")
@limiter.limit("5/minute")
async def ingest_document(
    request: Request,
    file: UploadFile = File(...),
    document_id: str = Form(...),
    category_slug: str = Form(default="lainnya"),
):
    logger.info("ingest_started", document_id=document_id, filename=file.filename)
    
    # 1. Simpan file sementara
    temp_file_path = f"./uploads/{document_id}_{file.filename}"
    os.makedirs("./uploads", exist_ok=True)
    
    with open(temp_file_path, "wb") as buffer:
        buffer.write(await file.read())
    
    try:
        # 2. Cek apakah ini update (chunks lama sudah ada)?
        existing_chunks_count = await vector_repo.count_chunks_by_document_id(document_id)
        
        if existing_chunks_count > 0:
            logger.info("update_detected", document_id=document_id, old_chunks=existing_chunks_count)
            # Hapus semua chunks lama
            await vector_repo.delete_chunks_by_document_id(document_id)
        
        # 3. Process file (Extract → Clean → Chunk → Embed → Insert)
        await process_document_pipeline(
            document_id=document_id,
            file_path=temp_file_path,
            category_slug=category_slug
        )
        
        return {"status": "success", "document_id": document_id}
        
    except Exception as e:
        logger.error("ingest_failed", document_id=document_id, error=str(e))
        raise HTTPException(status_code=500, detail=f"Processing failed: {str(e)}")
        
    finally:
        # 4. Hapus file sementara
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)
            logger.info("temp_file_deleted", path=temp_file_path)


async def process_document_pipeline(
    document_id: str,
    file_path: str,
    category_slug: str
):
    """Pipeline lengkap yang dioptimasi: Extract → Clean & Chunk → Batch Embed → Bulk Insert"""
    
    # 1. Extract
    elements = await extraction_service.extract(file_path)
    logger.info("extraction_complete", document_id=document_id, elements_count=len(elements))
    
    # 2. Kumpulkan SEMUA teks dan metadata terlebih dahulu (Tanpa Embed dulu)
    all_texts_to_embed = []
    chunk_metadata = []
    chunk_index = 0
    
    for element in elements:
        cleaned_result = await cleaning_service.clean_text(
            text=element['text'],
            extraction_method=element.get('extraction_method', 'digital_text')
        )
        
        chunk_strategy = get_chunking_strategy(category_slug, element.get('type', 'text'))
        chunks = chunking_service.chunk_document(
            text=cleaned_result['cleaned_text'],
            doc_type=chunk_strategy,
            metadata_prefix=f"[{category_slug.upper()}] "
        )
        
        if chunks:
            for chunk_text in chunks:
                all_texts_to_embed.append(chunk_text)
                chunk_metadata.append({
                    'content_type': element.get('type', 'text'),
                    'extraction_method': element.get('extraction_method', 'digital_text'),
                    'metadata': json.dumps({
                        'category_slug': category_slug,
                        'cleaning_provider': cleaned_result.get('provider_used', 'none')
                    })
                })
                chunk_index += 1
    
    # 3. EMBEDDING SECARA BERTAHAP (BATCH PROCESSING) agar memori aman saat file besar
    if all_texts_to_embed:
        logger.info("embedding_started", total_chunks=len(all_texts_to_embed), batch_size=settings.EMBEDDING_BATCH_SIZE)

        all_embeddings = []
        for i in range(0, len(all_texts_to_embed), settings.EMBEDDING_BATCH_SIZE):
            batch = all_texts_to_embed[i:i + settings.EMBEDDING_BATCH_SIZE]
            batch_embeddings = await embedding_service.embed_passages(batch)
            all_embeddings.extend(batch_embeddings)
            logger.info("embedding_batch_completed", batch_index=i // settings.EMBEDDING_BATCH_SIZE, batch_size=len(batch))

        logger.info("embedding_complete", total_chunks=len(all_embeddings))

        # 4. Gabungkan teks, embedding, dan metadata menjadi struktur final
        final_chunks = []
        for i in range(len(all_texts_to_embed)):
            final_chunks.append({
                'id': str(uuid.uuid4()),
                'document_id': document_id,
                'chunk_index': i,
                'content': all_texts_to_embed[i],
                'content_type': chunk_metadata[i]['content_type'],
                'extraction_method': chunk_metadata[i]['extraction_method'],
                'metadata': chunk_metadata[i]['metadata'],
                'embedding': all_embeddings[i]
            })

        # 5. Bulk Insert ke Database
        await vector_repo.insert_chunks(final_chunks)
        logger.info("chunks_inserted", document_id=document_id, count=len(final_chunks))
    else:
        logger.warning("no_chunks_generated", document_id=document_id)

def get_chunking_strategy(category_slug: str, element_type: str) -> str:
    """Pilih strategi chunking berdasarkan kategori dan tipe elemen"""
    if element_type in ['table', 'table_row']:
        return 'table'
    
    strategy_map = {
        'peraturan': 'legal',
        'sk': 'legal',
        'surat_dinas': 'narrative',
        'laporan': 'narrative',
        'notulensi': 'narrative',
        'proposal': 'narrative',
        'lainnya': 'narrative',
    }
    
    return strategy_map.get(category_slug, 'narrative')