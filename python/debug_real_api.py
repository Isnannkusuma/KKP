import asyncio
import logging
import sys
from dotenv import load_dotenv

# 1. Load environment variables dari .env
load_dotenv()

# 2. Aktifkan logging level DEBUG untuk httpx agar request jaringan terlihat
logging.basicConfig(
    level=logging.DEBUG, # Ubah ke DEBUG untuk melihat detail HTTP
    format='%(asctime)s | %(levelname)-8s | %(name)s | %(message)s',
    stream=sys.stdout
)

# 3. Import service kita
from services.cleaning_service import CleaningService

async def main():
    print("\n" + "="*60)
    print("MULAI DEBUG: Menguji Panggilan API Nyata (Real Network Call)")
    print("="*60 + "\n")

    service = CleaningService()
    
    # Simulasi teks berantakan hasil OCR
    ocr_text = """
    PEMERINTAH KABUPATEN B0G0R S3IATAN
    DINAS KOMUNIKASI DAN INF0RMATIKA
    Jalan Raya Tegar Beriman No. 1, Cibinong
    =================================================================
    
    N0m0r : 800 / 1234 / DISK0MINF0 / Vll / 2024                   Bogor, 25 ]uli 2024
    Sifat : Penting
    Lampiran : 1 (satu) berkas
    Perihal : Permohonan Verifikasi Data
    
    Yth. Kepala Bagian Keuangan
    di
    Tempat
    
    Sehubungan dengan hal tersebut, dengan ini kami sampaikan bahwa Saudara:
    
    Nama          : dr. Budi Santoso, S. Kom. , M. T.
    NIP           : 19850101 201001 1 005
    Jabatan       : Kepala Bidang Infrastruktur
    
    telah melakukan pembayaran biaya layanan sebesar Rp . 15.500.000,- 
    (lima belas juta lima ratus ribu rupiah) yang dibayarkan pada tanggal 
    10 . 05 . 2024 melalui Bank BNI. 
    
    Hal ini sesuai dengan ketentuan dalam Peraturan Menteri N0m0r 12 
    Tahun 2023 tentang Tata Kelola Teknologi Informasi. 
    
    Demikian disampaikan, atas perhatian dan kerjasamanya kami ucapkan 
    t erima kasih .
    
    Kepala Dinas,
    
    ( TTD )
    
    AHMAD RIFAI, S. Sos. , M. Si.
    NIP. 19700505 199903 1 002
    """

    print(f"Teks Asli (Input):\n{ocr_text}\n")
    print("Memproses... (Mohon tunggu, sedang menghubungi AI API)\n")

    # PENTING: extraction_method HARUS "ocr" agar tidak di-skip
    result = await service.clean_text(text=ocr_text, extraction_method="ocr")

    print("\n" + "="*60)
    print("HASIL:")
    print("="*60)
    print(f"Provider yang digunakan: {result['provider_used']}")
    print(f"Teks Bersih (Output):\n{result['cleaned_text']}")
    print("="*60 + "\n")

if __name__ == "__main__":
    asyncio.run(main())