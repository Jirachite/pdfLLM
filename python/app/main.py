from fastapi import FastAPI, HTTPException, UploadFile, File
from fastapi.responses import JSONResponse
import os
import logging
import PyPDF2
import pytesseract
from PIL import Image
import io
import camelot
import spacy
import psycopg2
from sentence_transformers import SentenceTransformer
import ollama
from pydantic import BaseModel
import redis

app = FastAPI()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Database connection
db_params = {
    "host": os.getenv("DB_HOST", "postgres"),
    "port": os.getenv("DB_PORT", "5432"),
    "dbname": os.getenv("DB_NAME", "pdfLLM"),
    "user": os.getenv("DB_USER", "pdfspear"),
    "password": os.getenv("DB_PASSWORD", "Anubis-Sucks-1!234")
}

# Redis connection
redis_client = redis.Redis(
    host=os.getenv("REDIS_HOST", "redis"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    decode_responses=True
)

# Load spacy model and sentence transformer
nlp = spacy.load("en_core_web_sm")
model = SentenceTransformer("all-MiniLM-L6-v2")

class QueryRequest(BaseModel):
    query: str

def extract_text_from_pdf(file_path: str) -> str:
    text = ""
    try:
        with open(file_path, "rb") as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                text += page.extract_text() or ""
            tables = camelot.read_pdf(file_path, flavor="stream")
            for table in tables:
                text += "\n" + table.df.to_markdown(index=False)
    except Exception as e:
        logger.error(f"Error processing PDF: {e}")
    return text

def extract_text_from_image(file_path: str) -> str:
    try:
        image = Image.open(file_path)
        text = pytesseract.image_to_string(image)
        return text
    except Exception as e:
        logger.error(f"Error processing image: {e}")
        return ""

@app.post("/process")
async def process_file(file: UploadFile = File(...)):
    logger.debug(f"Received request: filename={file.filename}, content_type={file.content_type}")
    try:
        file_path = f"/var/www/laravel/storage/app/public/uploads/{file.filename}"
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        with open(file_path, "wb") as f:
            f.write(await file.read())

        logger.info(f"Received request: {{'filename': '{file.filename}', 'file_type': '{file.content_type}'}}")
        logger.info(f"Processing {file.content_type}: {file_path}")

        processed_text = ""
        if file.content_type == "application/pdf":
            processed_text = extract_text_from_pdf(file_path)
        elif file.content_type.startswith("image/"):
            processed_text = extract_text_from_image(file_path)
        else:
            raise HTTPException(status_code=400, detail="Unsupported file type")

        logger.info(f"Extracted text length: {len(processed_text)}")

        # Convert to markdown
        processed_text = processed_text.replace('\n', '\n\n')
        markdown_text = f"# Processed File: {file.filename}\n\n{processed_text}"

        # Insert into database
        conn = psycopg2.connect(**db_params)
        cur = conn.cursor()
        cur.execute(
            """
            INSERT INTO files (filename, file_type, processed_text, created_at, updated_at)
            VALUES (%s, %s, %s, NOW(), NOW())
            RETURNING id
            """,
            (file.filename, file.content_type, markdown_text)
        )
        file_id = cur.fetchone()[0]

        # Split into sentences and create embeddings
        doc = nlp(processed_text)
        sentences = [sent.text.strip() for sent in doc.sents if sent.text.strip()]
        embeddings = model.encode(sentences)

        for sentence, embedding in zip(sentences, embeddings):
            cur.execute(
                """
                INSERT INTO chunks (file_id, filename, chunk_text, embedding, created_at, updated_at)
                VALUES (%s, %s, %s, %s, NOW(), NOW())
                """,
                (file_id, file.filename, sentence, embedding.tolist())
            )

        conn.commit()
        cur.close()
        conn.close()

        logger.info(f"Inserted file record: {file.filename}")
        return {"status": "success", "filename": file.filename}

    except Exception as e:
        logger.error(f"Error processing file: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/query")
async def query(request: QueryRequest):
    try:
        query_embedding = model.encode([request.query])[0]
        conn = psycopg2.connect(**db_params)
        cur = conn.cursor()
        cur.execute(
            """
            SELECT chunk_text, filename
            FROM chunks
            ORDER BY embedding <-> %s::vector
            LIMIT 5
            """,
            (query_embedding.tolist(),)
        )
        results = cur.fetchall()
        cur.close()
        conn.close()

        context = "\n".join([row[0] for row in results])
        response = ollama.generate(
            model="mistral:7b",
            prompt=f"Based on the following context, answer the query: {request.query}\n\nContext:\n{context}"
        )
        return {"response": response["response"]}

    except Exception as e:
        logger.error(f"Error processing query: {e}")
        raise HTTPException(status_code=500, detail=str(e))
