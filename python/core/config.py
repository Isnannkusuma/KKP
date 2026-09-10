import os


class Settings:
    def __init__(self):
        # Database
        self.DB_HOST = os.getenv("DB_HOST", "")
        self.DB_PORT = int(os.getenv("DB_PORT", "5432"))
        self.DB_DATABASE = os.getenv("DB_DATABASE", "")
        self.DB_USERNAME = os.getenv("DB_USERNAME", "")
        self.DB_PASSWORD = os.getenv("DB_PASSWORD", "")

        # API Keys
        self.GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
        self.GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")

        # App Config
        self.MAX_CHUNK_SIZE = int(os.getenv("MAX_CHUNK_SIZE", "500"))
        self.EMBEDDING_BATCH_SIZE = int(os.getenv("EMBEDDING_BATCH_SIZE", "2"))
        self.UPLOAD_DIR = os.getenv("UPLOAD_DIR", "./uploads")


settings = Settings()