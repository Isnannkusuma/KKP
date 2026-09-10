FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN apt-get update && apt-get install -y \
    tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng \
    poppler-utils libreoffice build-essential \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# 1. Install PyTorch GPU-only
# 1. Install PyTorch dengan dukungan CUDA (GPU) -- hanya torch saja, tidak perlu torchvision/torchaudio untuk text embedding
RUN pip install --default-timeout=2000 --retries=5 torch --index-url https://download.pytorch.org/whl/cu126
# RUN pip install --no-cache-dir torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu124

# 2. Install requirements Python
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 3. Copy source code
COPY . .

# 4. Siapkan model embedding lokal agar service bisa boot tanpa menunggu download saat runtime.
RUN mkdir -p /app/uploads /app/models && python download_model_efficient_base.py

EXPOSE 8000

CMD ["gunicorn", "main:app", "-w", "2", "-k", "uvicorn.workers.UvicornWorker", "--bind", "0.0.0.0:8000"]