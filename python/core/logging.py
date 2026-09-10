import structlog
import logging
import sys

def setup_logging():
    # Konfigurasi structlog untuk output JSON yang mudah dianalisis
    structlog.configure(
        processors=[
            structlog.contextvars.merge_contextvars,
            structlog.processors.add_log_level,
            structlog.processors.StackInfoRenderer(),
            structlog.dev.set_exc_info,
            structlog.processors.TimeStamper(fmt="%Y-%m-%d %H:%M:%S"),
            structlog.dev.ConsoleRenderer()  # Gunakan JSONRenderer() untuk production
        ],
        wrapper_class=structlog.make_filtering_bound_logger(logging.INFO),
        context_class=dict,
        logger_factory=structlog.PrintLoggerFactory(),
        cache_logger_on_first_use=False
    )

# Cara penggunaan di kode:
# logger = structlog.get_logger()
# logger.info("Processing document", document_id=123, chunk_count=15)