from fastapi import FastAPI
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("/app/python.log")
    ]
)
logger = logging.getLogger(__name__)

logger.info("Starting FastAPI application...")

app = FastAPI()

@app.get("/health")
async def health():
    logger.info("Health check requested")
    return {"status": "healthy", "message": "Bare-minimum FastAPI app running"}

logger.info("FastAPI app initialized")