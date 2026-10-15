import asyncpg
import structlog
from core.config import settings
from typing import List, Dict, Any, Optional
import json
import re

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

def extract_pasal_numbers(query_text: str) -> List[str]:
    """Ekstrak nomor pasal (misal 'pasal 20', 'pasal 60', 'pasal 12a')"""
    matches = re.findall(r"\bpasal\s+(\d+[a-z]?)\b", query_text, flags=re.IGNORECASE)
    # Hapus duplikat menjaga urutan
    return list(dict.fromkeys(matches))

def extract_regulation_doc_regexes(query_text: str) -> List[str]:
    """
    Ekstrak pola regex nama dokumen (sintaks PostgreSQL POSIX ~*) jika pengguna
    menyebut regulasi spesifik. PostgreSQL menggunakan \\y untuk batas kata (word boundary).
    Contoh: 'uu nomor 20 tahun 2023', 'uu 20 tahun 2023', 'uu asn', 'permen kp 2 tahun 2025'.
    """
    regexes = []
    # UU Nomor X Tahun Y
    m_uu = re.findall(r"\buu\s*(?:nomor|no\.?)?\s*(\d+)\s*(?:tahun|thn|\/)?\s*(\d{4})", query_text, re.IGNORECASE)
    for no, th in m_uu:
        regexes.append(rf"\yuu\s+(nomor\s+)?{no}\s+tahun\s+{th}\y")
        regexes.append(rf"\yundang-undang\s+(nomor\s+)?{no}\s+tahun\s+{th}\y")

    # UU ASN
    if re.search(r"\buu\s+asn\b", query_text, re.IGNORECASE):
        regexes.append(r"\yuu\y.*asn")
        regexes.append(r"\yundang-undang\y.*asn")

    # Permen / Peraturan Menteri [KP] No X Tahun Y
    m_permen = re.findall(r"(?:permen(?:kp)?|peraturan\s+menteri)\s*(?:kelautan\s+dan\s+perikanan|kp)?\s*(?:nomor|no\.?)?\s*(\d+)\s*(?:tahun|thn|\/)?\s*(\d{4})", query_text, re.IGNORECASE)
    for no, th in m_permen:
        regexes.append(rf"permen.*{no}.*tahun.*{th}")
        regexes.append(rf"peraturan\s+menteri.*{no}.*tahun.*{th}")

    # PP No X Tahun Y
    m_pp = re.findall(r"\bpp\s*(?:nomor|no\.?)?\s*(\d+)\s*(?:tahun|thn|\/)?\s*(\d{4})", query_text, re.IGNORECASE)
    for no, th in m_pp:
        regexes.append(rf"\ypp\s+(nomor\s+)?{no}\s+tahun\s+{th}\y")
        regexes.append(rf"\yperaturan\s+pemerintah\s+(nomor\s+)?{no}\s+tahun\s+{th}\y")

    return list(dict.fromkeys(regexes))

def extract_unit_names(query_text: str) -> List[str]:
    """
    Ekstrak nama unit kerja utama KKP jika disebutkan dalam pertanyaan.
    Contoh: 'Biro Sumber Daya Manusia Aparatur dan Organisasi', 'Biro SDMAO', dll.
    """
    units = []
    if re.search(r"sumber\s+daya\s+manusia\s+aparatur\s+dan\s+organisasi|sdmao", query_text, re.IGNORECASE):
        units.append(r"Biro\s+Sumber\s+Daya\s+Manusia\s+Aparatur\s+dan\s+Organisasi")
    if re.search(r"\bbiro\s+keuangan\b", query_text, re.IGNORECASE):
        units.append(r"Biro\s+Keuangan")
    if re.search(r"\bbiro\s+hukum\b", query_text, re.IGNORECASE):
        units.append(r"Biro\s+Hukum")
    if re.search(r"\bbiro\s+perencanaan\b", query_text, re.IGNORECASE):
        units.append(r"Biro\s+Perencanaan")
    if re.search(r"\bbiro\s+umum\b", query_text, re.IGNORECASE):
        units.append(r"Biro\s+Umum")
    return list(dict.fromkeys(units))

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
        pasal_numbers = extract_pasal_numbers(query_text)
        doc_regexes = extract_regulation_doc_regexes(query_text)
        unit_names = extract_unit_names(query_text)

        async with self.pool.acquire() as conn:
            # Query dasar dengan join ke documents untuk mengambil nama dokumen
            # dan memungkinkan prioritas pasal/dokumen
            sql = """
                SELECT 
                    dc.id, 
                    dc.document_id, 
                    dc.chunk_index, 
                    dc.content, 
                    dc.metadata,
                    d.name as document_name,
                    (1 - (dc.embedding <=> $1)) as vector_score,
                    ts_rank(dc.search_vector, plainto_tsquery('simple', $2)) as keyword_score,
            """
            
            # Boost khusus jika ada pasal yang ditanyakan
            pasal_boost_clauses = []
            if pasal_numbers:
                for p in pasal_numbers:
                    # Heading pasal misal: [UNDANG-UNDANG] Pasal 20\n atau baris baru Pasal 20
                    heading_regex = rf"(?:\]|\n|^)\s*pasal\s+{p}\s*\n"
                    body_regex = rf"\ypasal\s+{p}\y"
                    clause = f"""
                        CASE 
                            WHEN dc.content ~* '{heading_regex}' THEN 0.50
                            WHEN dc.content ~* '{body_regex}' THEN 0.20
                            ELSE 0.0
                        END
                    """
                    pasal_boost_clauses.append(clause)
                pasal_boost_sql = " + ".join(pasal_boost_clauses)
            else:
                pasal_boost_sql = "0.0"

            # Boost jika nama dokumen cocok dengan regulasi spesifik yang ditanyakan
            doc_boost_clauses = []
            if doc_regexes:
                for reg in doc_regexes:
                    clause = f"CASE WHEN d.name ~* '{reg}' THEN 0.40 ELSE 0.0 END"
                    doc_boost_clauses.append(clause)
                doc_boost_sql = " + ".join(doc_boost_clauses)
            else:
                doc_boost_sql = "0.0"

            # Boost jika isi chunk menyebut nama unit kerja yang ditanyakan
            unit_boost_clauses = []
            if unit_names:
                for u in unit_names:
                    clause = f"CASE WHEN dc.content ~* '{u}' THEN 0.35 ELSE 0.0 END"
                    unit_boost_clauses.append(clause)
                unit_boost_sql = " + ".join(unit_boost_clauses)
            else:
                unit_boost_sql = "0.0"

            sql += f" ({pasal_boost_sql}) as pasal_boost,\n"
            sql += f" ({doc_boost_sql}) as doc_boost,\n"
            sql += f" ({unit_boost_sql}) as unit_boost\n"
            sql += """
                FROM document_chunks dc
                LEFT JOIN documents d ON d.id = dc.document_id
                WHERE dc.embedding IS NOT NULL
            """
            
            params = [embedding_str, keyword_query]
            param_index = 3
            
            # Tambahkan filter document_id jika disediakan
            if document_id is not None:
                sql += f" AND dc.document_id = ${param_index}"
                params.append(document_id)
                param_index += 1
            
            # Tambahkan filter category_slug (mencari di dalam JSON metadata)
            if category_slug is not None:
                sql += f" AND dc.metadata->>'category_slug' = ${param_index}"
                params.append(category_slug)
                param_index += 1
            
            # HYBRID SCORING: Vector (60%) + Keyword (40%) + Pasal Boost + Doc Boost + Unit Boost
            sql += f"""
                ORDER BY (
                    ((1 - (dc.embedding <=> $1)) * 0.6) + 
                    (ts_rank(dc.search_vector, plainto_tsquery('simple', $2)) * 0.4) + 
                    ({pasal_boost_sql}) +
                    ({doc_boost_sql}) +
                    ({unit_boost_sql})
                ) DESC
                LIMIT ${param_index}
            """
            params.append(top_k)
            
            logger.info("executing_hybrid_search", top_k=top_k, doc_id=document_id, category=category_slug, pasals=pasal_numbers, doc_regexes=doc_regexes, units=unit_names)
            
            rows = await conn.fetch(sql, *params)
            
            # Format hasil agar lebih rapi
            results = []
            for row in rows:
                row_dict = dict(row)
                p_boost = float(row_dict.get('pasal_boost') or 0.0)
                d_boost = float(row_dict.get('doc_boost') or 0.0)
                u_boost = float(row_dict.get('unit_boost') or 0.0)
                # Bulatkan dan gabungkan skor untuk transparansi
                row_dict['hybrid_score'] = round(
                    (row_dict['vector_score'] * 0.6) + (row_dict['keyword_score'] * 0.4) + p_boost + d_boost + u_boost, 4
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
