# 📄 pdfLLM

A tenant-aware, construction-focused document intelligence platform built with Laravel, FastAPI, and containerized microservices.

---
# Change Log
- Upload & Text Extraction
- Nginx Configuration Fix
---


## ✅ Phase 1: Dockerized Core (Completed)

The foundational architecture is fully Dockerized and ready for extension. This phase includes:

### ⚙️ Core Stack
- **Frontend & Backend Gateway:** `Nginx`
- **Application Backend:** `Laravel (PHP)` with Bulma CSS
- **API Services:** `FastAPI (Python)`
- **Database:** `PostgreSQL` with `pgvector` extension for vector search
- **Caching / PubSub:** `Redis`

Everything is containerized and wired up via Docker Compose for local development and production parity.

---

## 🏗️ Phase 2: Construction-Specific File Processing (Upcoming)

This phase will introduce specialized file parsers and text processing:

- 📄 **PDFs:**  
  - `Camelot` for table extraction  
  - `PyMuPDF` for text layer extraction  
- 🖼️ **Images:**  
  - `Tesseract` OCR and `CLIP` for visual context  
- 🧹 **Text Cleaning Pipeline:**  
  - Header/footer detection  
  - Construction jargon removal  
- 📐 **Chunking:**  
  - Layout-aware segmentation using `spaCy` and document structure  

---

## 🤖 Phase 3: Tenant-Aware RAG Pipeline (Planned)

The final phase introduces intelligent, tenant-isolated Retrieval-Augmented Generation (RAG):

- `pgvector` HNSW indexing **per tenant**  
- Hybrid search (semantic + keyword) with Redis caching  
- `Mistral-7B` via `vLLM` for fast, optimized responses  
- Safety-filtered completions  
- Laravel delivers final answers via API  

---

## 📦 Setup

```bash
#ollama set up
Download and install ollama from ollama.com
ollama pull mistral:7b

# Clone the repo
git clone https://github.com/ikantkode/pdfLLM.git
cd pdfLLM

# Start the app
docker compose up --build

# Permissions Command:
docker exec -it pdfllm-laravel-1 composer install

localhost/upload (or whatever server you have it hosted on eg. 192.168.1.107/upload)