from fastapi import FastAPI, HTTPException, UploadFile, File
from fastapi.responses import JSONResponse
import os
import logging
import PyPDF2
import pytesseract
from PIL import Image
import io
import camelot
import pdfplumber
import spacy
import psycopg2
from sentence_transformers import SentenceTransformer, CrossEncoder
import ollama
from pydantic import BaseModel
import redis
import pandas as pd
from docx import Document
import re
from subprocess import run

app = FastAPI()

# Configure logging
logging.basicConfig(level=logging.INFO, filename="/app/python.log")
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

# Load models
nlp = spacy.load("en_core_web_sm")
embedder = SentenceTransformer("all-MiniLM-L6-v2")
reranker = CrossEncoder("cross-encoder/ms-marco-MiniLM-L-6-v2")

class QueryRequest(BaseModel):
    query: str
    file_ids: list[int] = []

def clean_text(text: str) -> str:
    # Remove construction jargon
    jargon = ['ACI', 'ASTM', 'SPEC', 'DRAWING NO.', 'REVISION']
    for term in jargon:
        text = re.sub(rf'\b{term}\b', '', text, flags=re.IGNORECASE)
    # Remove headers/footers (simplified: lines with page numbers, repeated phrases)
    text = re.sub(r'^(Page \d+|Confidential|Proprietary).*$', '', text, flags=re.MULTILINE)
    # Remove extra whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def to_markdown(text: str, filename: str) -> str:
    try:
        with open("temp.txt", "w") as f:
            f.write(text)
        run(["pandoc", "-f", "plain", "-t", "markdown", "temp.txt", "-o", "temp.md"])
        with open("temp.md", "r") as f:
            markdown = f"# Processed File: {filename}\n\n{f.read()}"
        os.remove("temp.txt")
        os.remove("temp.md")
        return markdown
    except Exception as e:
        logger.error(f"Error converting to markdown: {e}")
        return f"# Processed File: {filename}\n\n{text}"

def extract_text_from_pdf(file_path: str) -> str:
    text = ""
    try:
        with pdfplumber.open(file_path) as pdf:
            for page in pdf.pages:
                text += page.extract_text() or ""
        tables = camelot.read_pdf(file_path, flavor="stream")
        for table in tables:
            text += "\n" + table.df.to_markdown(index=False)
    except Exception as e:
        logger.error(f"Error with pdfplumber/camelot: {e}")
        try:
            with open(file_path, "rb") as file:
                reader = PyPDF2.PdfReader(file)
                for page in reader.pages:
                    text += page.extract_text() or ""
        except Exception as e2:
            logger.error(f"Error with PyPDF2: {e2}")
    return clean_text(text)

def extract_text_from_image(file_path: str) -> str:
    try:
        image = Image.open(file_path)
        text = pytesseract.image_to_string(image)
        # Add basic image metadata (e.g., size)
        metadata = f"Image Dimensions: {image.width}x{image.height}"
        return clean_text(text + "\n" + metadata)
    except Exception as e:
        logger.error(f"Error processing image: {e}")
        return ""

def extract_text_from_excel(file_path: str) -> str:
    try:
        df = pd.read_excel(file_path)
        text = df.to_markdown(index=False)
        return clean_text(text)
    except Exception as e:
        logger.error(f"Error processing Excel: {e}")
        return ""

def extract_text_from_word(file_path: str) -> str:
    try:
        doc = Document(file_path)
        text = "\n".join([para.text for para in doc.paragraphs if para.text])
        return clean_text(text)
    except Exception as e:
        logger.error(f"Error processing Word: {e}")
        return ""

@app.post("/process")
async def process_file(file: UploadFile = File(...)):
    logger.info(f"Received request: filename={file.filename}, content_type={file.content_type}")
    try:
        file_path = f"/var/www/laravel/storage/app/public/uploads/{file.filename}"
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        with open(file_path, "wb") as f:
            f.write(await file.read())

        processed_text = ""
        if file.content_type == "application/pdf":
            processed_text = extract_text_from_pdf(file_path)
        elif file.content_type.startswith("image/"):
            processed_text = extract_text_from_image(file_path)
        elif file.content_type in [
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-excel"
        ]:
            processed_text = extract_text_from_excel(file_path)
        elif file.content_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
            processed_text = extract_text_from_word(file_path)
        else:
            raise HTTPException(status_code=400, detail="Unsupported file type")

        logger.info(f"Extracted text length: {len(processed_text)}")

        # Convert to markdown
        markdown_text = to_markdown(processed_text, file.filename)

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

        # Semantic chunking with spaCy
        doc = nlp(processed_text)
        chunks = [sent.text.strip() for sent in doc.sents if sent.text.strip()]
        embeddings = embedder.encode(chunks)

        for chunk, embedding in zip(chunks, embeddings):
            cur.execute(
                """
                INSERT INTO chunks (file_id, filename, chunk_text, embedding, created_at, updated_at)
                VALUES (%s, %s, %s, %s, NOW(), NOW())
                """,
                (file_id, file.filename, chunk, embedding.tolist())
            )

        conn.commit()
        cur.close()
        conn.close()

        logger.info(f"Inserted file record: {file.filename}")
        return {"status": "success", "filename": file.filename, "file_id": file_id}

    except Exception as e:
        logger.error(f"Error processing file: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/query")
async def query(request: QueryRequest):
    logger.info(f"Received query payload: {request.dict()}")
    try:
        # Check Redis cache
        cache_key = f"query:{request.query}:{','.join(map(str, request.file_ids))}"
        cached = redis_client.get(cache_key)
        if cached:
            logger.info(f"Cache hit for query: {request.query}")
            return {"response": cached, "context": ""}

        # Generate query embedding
        query_embedding = embedder.encode([request.query])[0]
        conn = psycopg2.connect(**db_params)
        cur = conn.cursor()

        # Retrieve top-k chunks with pgvector
        query = """
            SELECT chunk_text, filename
            FROM chunks
            WHERE 1=1
        """
        params = []
        if request.file_ids:
            query += " AND file_id = ANY(%s)"
            params.append(request.file_ids)

        query += " ORDER BY embedding <-> %s::vector LIMIT 10"
        params.append(query_embedding.tolist())

        cur.execute(query, params)
        results = cur.fetchall()
        cur.close()
        conn.close()

        # Re-rank with cross-encoder
        chunks = [row[0] for row in results]
        if chunks:
            pairs = [[request.query, chunk] for chunk in chunks]
            scores = reranker.predict(pairs)
            ranked = sorted(zip(scores, chunks), reverse=True)[:5]
            context = "\n".join([chunk for _, chunk in ranked])
        else:
            context = ""

        # Generate response with Llama3.2:3b
        response = ollama.generate(
            model="llama3.2:3b",
            prompt=f"Based on the following context, answer the query: {request.query}\n\nContext:\n{context}\n\nInclude any relevant ACI/spec references and a safety disclaimer if applicable."
        )
        answer = response["response"]

        # Cache response
        redis_client.setex(cache_key, 3600, answer)

        return {"response": answer, "context": context}

    except Exception as e:
        logger.error(f"Error processing query: {e}")
        raise HTTPException(status_code=500, detail=str(e))