import asyncio
from dotenv import load_dotenv
load_dotenv()

from services.embedding_service import EmbeddingService

async def main():
    print("Memulai tes dimensi embedding...")
    service = EmbeddingService()
    
    # Tes 1: Passage (Dokumen)
    doc_text = "Pasal 1: Dalam peraturan ini yang dimaksud dengan Pemerintah Daerah adalah kepala daerah."
    doc_embeddings = await service.embed_passages([doc_text])
    
    print(f"\n✅ Dimensi Passage: {len(doc_embeddings[0])}")
    assert len(doc_embeddings[0]) == 1024, "Dimensi passage salah!"

    # Tes 2: Query (Pencarian)
    query_text = "Siapa yang dimaksud dengan pemerintah daerah?"
    query_embedding = await service.embed_query(query_text)
    
    print(f"✅ Dimensi Query: {len(query_embedding)}")
    assert len(query_embedding) == 1024, "Dimensi query salah!"

    print("\n🎉 SUKSES! Dimensi embedding sudah 100% sinkron dengan database vector(1024).")

if __name__ == "__main__":
    asyncio.run(main())