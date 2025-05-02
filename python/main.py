# In ~/pdfLLM/python/app/main.py
import os
import logging
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import fitz  # PyMuPDF
import psycopg2

app = FastAPI()
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class FileProcessRequest(BaseModel):
    filename: str
    file_type: str

@app.post("/process")
async def process_file(request: FileProcessRequest):
    logger.info(f"Received request: {request.dict()}")
    file_path = f"/var/www/laravel/storage/app/public/uploads/{request.filename}"
    
    if not os.path.exists(file_path):
        logger.error(f"File not found: {file_path}")
        raise HTTPException(status_code=400, detail="File not found")
    
    try:
        processed_text = ""
        if request.file_type.lower() in ["pdf"]:  # Handle case-insensitive file_type
            logger.info(f"Processing PDF: {file_path}")
            with fitz.open(file_path) as doc:
                for page in doc:
                    processed_text += page.get_text() or ""
            logger.info(f"Extracted text length: {len(processed_text)}")
        else:
            logger.error(f"Unsupported file type: {request.file_type}")
            raise HTTPException(status_code=400, detail=f"Unsupported file type: {request.file_type}")
        
        # Database insertion
        conn = psycopg2.connect(
            dbname=os.getenv("DB_NAME", "pdfLLM"),
            user=os.getenv("DB_USER", "pdfspear"),
            password=os.getenv("DB_PASSWORD", "Anubis-Sucks-1!234"),
            host=os.getenv("DB_HOST", "postgres"),
            port=os.getenv("DB_PORT", "5432")
        )
        cursor = conn.cursor()
        cursor.execute(
            "INSERT INTO files (filename, file_type, processed_text) VALUES (%s, %s, %s)",
            (request.filename, request.file_type, processed_text)
        )
        conn.commit()
        cursor.close()
        conn.close()
        logger.info(f"Inserted file record: {request.filename}")
        
        return {"status": "success", "filename": request.filename}
    
    except Exception as e:
        logger.error(f"Processing error: {str(e)}")
        raise HTTPException(status_code=500, detail="Internal Server Error")