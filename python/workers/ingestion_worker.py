import logging
import os
from uuid import uuid4
from services.extraction_service import ExtractionService
from services.ocr_service import OCRService
from services.cleaning_service import CleaningService
from services.chunking_service import ChunkingService
from services.document_number_service import extract_regulation_numbers
from services.embedding_service import EmbeddingService
from repositories.vector_repository import vector_repo
from schemas.chunk_schema import DocumentChunk

logger = logging.getLogger(__name__)

async def process_document_ingestion(file_path: str, document_id: str, metadata: dict):
    """Pipeline utama yang dijalankan di background."""
    job_id = str(uuid4())
    logger.info(f"Starting ingestion for doc {document_id}, job {job_id}")
    
    try:
        # 1. Ekstraksi
        extraction_service = ExtractionService()
        raw_elements = await extraction_service.extract(file_path)
        
        # 2. OCR & Cleaning
        ocr_service = OCRService()
        cleaning_service = CleaningService()
        
        processed_elements = []
        for elem in raw_elements:
            if elem['type'] == 'image' and elem.get('needs_ocr'):
                elem['text'] = await ocr_service.extract_text(elem['image_path'])
                elem['extraction_method'] = 'ocr'
                try:
                    os.remove(elem['image_path'])
                except OSError:
                    pass
            else:
                elem['extraction_method'] = 'digital_text'
                
            clean_result = await cleaning_service.clean_text(elem['text'], elem['extraction_method'])
            elem['cleaned_text'] = clean_result['cleaned_text']
            elem['provider_used'] = clean_result['provider_used']
            processed_elements.append(elem)
            
        # 3. Chunking
        chunking_service = ChunkingService()
        prefix = f"[{metadata.get('category', 'Umum')}] "
        
        all_chunks = []
        chunk_index = 0
        for elem in processed_elements:
            chunks_text = chunking_service.chunk_document(
                elem['cleaned_text'], elem['type'], prefix
            )
            for c_text in chunks_text:
                # 4. Metadata Extraction
                doc_numbers = extract_regulation_numbers(c_text)
                
                chunk = DocumentChunk(
                    id=uuid4(),
                    document_id=document_id,
                    content=c_text,
                    content_type=elem['type'],
                    extraction_method=elem['extraction_method'],
                    metadata={
                        **metadata,
                        "doc_numbers": doc_numbers,
                        "cleaning_provider": elem['provider_used']
                    },
                    chunk_index=chunk_index
                )
                all_chunks.append(chunk)
                chunk_index += 1
                
        # 5. Embedding
        embedding_service = EmbeddingService()
        texts_to_embed = [c.content for c in all_chunks]
        embeddings = await embedding_service.embed_passages(texts_to_embed)
        
        for i, chunk in enumerate(all_chunks):
            chunk.embedding = embeddings[i]
            
        # 6. Save to pgvector
        await vector_repo.insert_chunks(all_chunks)
        logger.info(f"Successfully ingested {len(all_chunks)} chunks for doc {document_id}")
        
    except Exception as e:
        logger.error(f"Failed to process document {document_id}: {str(e)}", exc_info=True)
        # Update job status to failed in Redis/DB