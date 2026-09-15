import asyncpg
import structlog
from core.config import settings
from typing import List, Dict, Any, Optional
import json

logger = structlog.get_logger()

INDONESIAN_STOPWORDS = {
        'apa', 'itu', 'ini', 'yang', 'dan', 'di', 'ke', 'dari', 'untuk',
        'adalah', 'dengan', 'pada', 'atau', 'juga', 'akan', 'bisa', 'ada',
        'saya', 'kamu', 'anda', 'kami', 'mereka', 'dia', 'nya', 'sih', 'kah',
        'apakah', 'bagaimana', 'mengapa', 'kenapa', 'siapa', 'kapan', 'dimana',
    }

def clean_query_for_keyword_search(query_text: str) -> str:
    words = query_text.lower().split()
    meaningful_words = [w for w in words if w not in INDONESIAN_STOPWORDS]
    # Kalau semua kata ternyata stopword (jarang terjadi), fallback ke query asli
    return ' '.join(meaningful_words) if meaningful_words else query_text

class VectorRepository:
    def __init__(self):
        self.pool: asyncpg.Pool | None = None

    async def connect(self):
        logger.info("initializing_db_pool")
        try:
            self.pool = await asyncpg.create_pool(
                min_size=1,
                max_size=10,
                host=settings.DB_HOST,
                port=settings.DB_PORT,
                database=settings.DB_DATABASE,
                user=settings.DB_USERNAME,
                password=settings.DB_PASSWORD
            )
            
           
            
            logger.info("db_pool_established_with_vector_codec")
        except Exception as e:
            logger.error("db_connection_failed", error=str(e))
            raise

    # ✅ Encode: Python list → PostgreSQL vector string
    def _encode_vector(self, value):
        """Konversi Python list ke format string PostgreSQL vector"""
        if isinstance(value, list):
            # Format: '[0.1, 0.2, 0.3]'
            return '[' + ','.join(str(v) for v in value) + ']'
        return value

    # ✅ Decode: PostgreSQL vector string → Python list
    def _decode_vector(self, value):
        """Konversi PostgreSQL vector string ke Python list"""
        if isinstance(value, str) and value.startswith('[') and value.endswith(']'):
            return [float(x) for x in value[1:-1].split(',')]
        return value

    async def disconnect(self):
        if self.pool:
            await self.pool.close()
            logger.info("db_pool_closed")

    # ==========================================
    # 1. INSERT CHUNKS
    # ==========================================
    async def insert_chunks(self, chunks: List[dict]):
        if not chunks:
            return
        
        if not self.pool:
            raise Exception("Database pool is not initialized. Call connect() first.")
        
        # ✅ KONVERSI EMBEDDING KE STRING MANUAL
        records = [
            (
                chunk['id'],
                chunk['document_id'],
                chunk['chunk_index'],
                chunk['content'],
                chunk['metadata'],
                '[' + ','.join(str(v) for v in chunk['embedding']) + ']'  # ← Konversi list ke string
            )
            for chunk in chunks
        ]
        
        query = """
            INSERT INTO document_chunks 
            (id, document_id, chunk_index, content, metadata, embedding)
            VALUES ($1, $2, $3, $4, $5, $6)
        """
        
        async with self.pool.acquire() as conn:
            await conn.executemany(query, records)
            
        logger.info("✅ bulk_insert_successful", count=len(chunks))

    # ==========================================
    # 2. SEARCH CHUNKS (HYBRID: Vector + BM25)
    # ==========================================
    async def search_chunks(
        self, 
        query_embedding: List[float], 
        query_text: str,
        top_k: int = 5, 
        document_id: Optional[str] = None,
        category_slug: Optional[str] = None
    ) -> List[Dict[str, Any]]:
        
        if not self.pool:
            raise Exception("Database pool is not initialized.")

        # ✅ PERBAIKAN: Konversi query_embedding (list) ke string format vector
        embedding_str = '[' + ','.join(str(v) for v in query_embedding) + ']'
        keyword_query = clean_query_for_keyword_search(query_text)  # <-- tambahan

        async with self.pool.acquire() as conn:
            # Query dasar
            sql = """
                SELECT 
                    id, 
                    document_id, 
                    chunk_index, 
                    content, 
                    metadata,
                    (1 - (embedding <=> $1)) as vector_score,
                    ts_rank(search_vector, plainto_tsquery('simple', $2)) as keyword_score
                FROM document_chunks
                WHERE embedding IS NOT NULL
            """
            
            # ✅ PERBAIKAN: Gunakan embedding_str, bukan query_embedding (list)
            params = [embedding_str, keyword_query]  # <-- pakai keyword_query, bukan query_text
            param_index = 3
            
            # Tambahkan filter document_id jika disediakan
            if document_id is not None:
                sql += f" AND document_id = ${param_index}"
                params.append(document_id)
                param_index += 1
            
            # Tambahkan filter category_slug (mencari di dalam JSON metadata)
            if category_slug is not None:
                sql += f" AND metadata->>'category_slug' = ${param_index}"
                params.append(category_slug)
                param_index += 1
            
            # HYBRID SCORING: Gabungkan skor vector (70%) dan keyword (30%)
            sql += f"""
                ORDER BY ((1 - (embedding <=> $1)) * 0.7 + ts_rank(search_vector, plainto_tsquery('simple', $2)) * 0.3) DESC
                LIMIT ${param_index}
            """
            params.append(top_k)
            
            logger.info("executing_hybrid_search", top_k=top_k, doc_id=document_id, category=category_slug)
            
            rows = await conn.fetch(sql, *params)
            
            # Format hasil agar lebih rapi
            results = []
            for row in rows:
                row_dict = dict(row)
                # Bulatkan dan gabungkan skor untuk transparansi
                row_dict['hybrid_score'] = round(
                    (row_dict['vector_score'] * 0.7) + (row_dict['keyword_score'] * 0.3), 4
                )
                results.append(row_dict)
                
            return results

    # ==========================================
    # 3. UPDATE SUPPORT: Count & Delete
    # ==========================================
    async def count_chunks_by_document_id(self, document_id: str) -> int:
        if not self.pool:
            raise Exception("Database pool is not initialized.")
        
        async with self.pool.acquire() as conn:
            result = await conn.fetchval(
                "SELECT COUNT(*) FROM document_chunks WHERE document_id = $1",
                document_id
            )
            return result or 0

    async def delete_chunks_by_document_id(self, document_id: str):
        if not self.pool:
            raise Exception("Database pool is not initialized.")
        
        async with self.pool.acquire() as conn:
            await conn.execute(
                "DELETE FROM document_chunks WHERE document_id = $1",
                document_id
            )
            logger.info("old_chunks_deleted", document_id=document_id)

# Singleton instance
vector_repo = VectorRepository()
