# 📄 pdfLLM

A construction-focused document intelligence platform built with Laravel, FastAPI, and containerized microservices. Processes and queries documents (PDF, Excel, Word, images) with a local Retrieval-Augmented Generation (RAG) pipeline.

---
# How It Works
![mermaid-diagram-2025-05-18-175659](https://github.com/user-attachments/assets/08cdc1f4-908a-4c40-8ff3-db4cd55defc6)

---
# Change Log
- File upload and text extraction (PDF, Excel, Word, images)
- Semantic chunking and embedding with `all-MiniLM-L6-v2`
- RAG querying with `cross-encoder` re-ranking and **Llama3.2:3b**
- Nginx proxy configuration
- Redis caching for queries

---

## ✅ Phase 1: Dockerized Core (Completed)

Fully containerized architecture with all services wired via Docker Compose.

### ⚙️ Core Stack
- **Frontend & Backend Gateway**: `Nginx` (proxies `/api/` to FastAPI, `/` to Laravel)
- **Application Backend**: `Laravel (PHP)` with Bulma CSS for uploads, previews, debug
- **API Services**: `FastAPI (Python)` for file processing and querying
- **Database**: `PostgreSQL` with `pgvector` for vector search
- **Caching**: `Redis` for query response caching
- **LLM**: **Llama3.2:3b** via host Ollama

---

## ✅ Phase 2: Construction-Specific File Processing (Completed)

Specialized parsing and text processing for construction documents.

### 📄 File Processing
- **Supported Formats**:
  - **PDF**: `pdfplumber`, `camelot` (tables), `PyPDF2`
  - **Excel**: `pandas`
  - **Word**: `python-docx`
  - **Images**: `pytesseract` OCR with metadata (e.g., dimensions)
- **Text Cleaning**: Removes construction jargon (e.g., ACI, ASTM), headers/footers
- **Markdown Conversion**: `pandoc` for structured output
- **Chunking**: Semantic segmentation with `spaCy`

---

## 🚀 Phase 3: Core RAG Pipeline (In Progress)

Core RAG functionality is live, with tenant-aware and advanced features planned.

### 🔍 Completed RAG Features
- **Embedding**: `sentence-transformers`’ `all-MiniLM-L6-v2` (384 dimensions)
- **Storage**: Postgres tables (`files`, `chunks`, `upload_tokens`) with `pgvector`
- **Retrieval**: Top-10 chunks via `pgvector` cosine similarity
- **Re-ranking**: `cross-encoder/ms-marco-MiniLM-L-6-v2` selects top-5 chunks
- **Response Generation**: **Llama3.2:3b** with ACI/spec references, safety disclaimer
- **Caching**: Redis (1-hour TTL for query responses)
- **API Endpoints**:
  - `POST /api/process`: Processes and stores files
    - Input: Multipart form with `file`
    - Output: `{"status":"success","filename":"sample.pdf","file_id":1}`
  - `POST /api/query`: Queries documents with RAG
    - Input: `{"query":"What is in the PDF?","file_ids":[1]}`
    - Output: `{"response":"...","context":"..."}`

### 📈 Planned RAG Enhancements
- **Multi-Tenancy**: `pgvector` HNSW indexing per tenant
- **Hybrid Search**: Semantic + keyword search with Redis caching
- **LLM Upgrade**: **Mistral-7B** or **Llama3.1:8b** via `vLLM` for faster inference
- **Safety**: Enhanced filtering for completions

---

## 📦 Setup

1. **Prerequisites**:
   - **Ollama**: Install on your host and pull **Llama3.2:3b**:
     ```bash
     # Install Ollama
     curl https://ollama.ai/install.sh | sh
     ollama pull llama3.2:3b
     ```
   - Start Ollama and note its IP/hostname (default: `localhost:11434`):
     ```bash
     ollama serve
     curl http://<your-ollama-host>:11434/api/tags
     ```
   - Update `docker-compose.yml` to point to your Ollama server:
     ```yaml
     services:
       python:
         environment:
           - OLLAMA_HOST=<your-ollama-host>  # e.g., localhost, 10.0.0.5
     ```

2. **Clone and Build**:
   ```bash
   git clone https://github.com/ikantkode/pdfLLM.git
   cd pdfLLM
   docker compose up --build
   ```

3. **Laravel Setup**:
   ```bash
   docker exec -it pdfllm-laravel-1 composer install
   ```

4. **Access**:
   - **UI**: `http://localhost/upload`
   - **pgAdmin**: `http://localhost:8087` (login: `pdfllm@yourock.com:pdfLLMrox`)
   - **API Test**:
     ```bash
     curl -X POST -F "file=@sample.pdf" http://localhost/api/process
     curl -X POST -H "Content-Type: application/json" -d '{"query":"What is in the PDF?","file_ids":[1]}' http://localhost/api/query
     ```

## ⚠️ Notes
- **CSRF**: `/api/query` may require a CSRF token or middleware bypass in Laravel (`VerifyCsrfToken.php`).
- **Ollama**: Ensure your Ollama server is running and accessible from the Docker network.
- **Logs**: Check `python.log` for debugging:
  ```bash
  docker cp pdfllm-python-1:/app/python.log .
  cat python.log
  ```
