import os

# WAJIB diset SEBELUM import huggingface_hub, supaya timeout per-chunk
# dinaikkan dari default 10 detik. Koneksi yang agak lambat/tidak stabil
# akan sering gagal di 10 detik, terutama untuk file besar.
os.environ["HF_HUB_DOWNLOAD_TIMEOUT"] = "60"

from huggingface_hub import snapshot_download
from pathlib import Path

# Folder utama models
models_dir = Path("models")
models_dir.mkdir(parents=True, exist_ok=True)

# Folder model yang akan digunakan
local_folder = models_dir / "multilingual-e5-base"
local_folder.mkdir(parents=True, exist_ok=True)


print(f"Mulai mendownload model (HANYA file yang diperlukan) ke: {local_folder}")
print("Mengabaikan file ONNX/OpenVINO dan format .bin duplikat -- hanya butuh .safetensors.\n")

snapshot_download(
    repo_id="intfloat/multilingual-e5-base",
    local_dir=local_folder,
    local_dir_use_symlinks=False,
    # ABAIKAN format yang tidak dibutuhkan sentence-transformers:
    # - onnx/openvino: format alternatif yang tidak dipakai
    # - pytorch_model.bin: format lama, sentence-transformers otomatis
    #   pakai model.safetensors kalau tersedia, jadi .bin cuma buang
    #   waktu & bandwidth karena ukurannya sama besar (~1.1GB)
    ignore_patterns=["*.onnx", "*.onnx_data", "openvino*", "model_qint8*", "pytorch_model.bin"]
)

print("\n✅ Download efisien selesai! Model siap digunakan secara offline.")