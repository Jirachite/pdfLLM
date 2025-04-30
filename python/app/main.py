from fastapi import FastAPI
import asyncpg
import redis.asyncio as redis

app = FastAPI()

@app.get("/")
async def root():
    # Example PostgreSQL connection
    conn = await asyncpg.connect(
        user="pdfspear", password="Anubis-Sucks-1!234", database="pdfLLM", host="postgres"
    )
    await conn.close()

    # Example Redis connection
    r = redis.Redis(host="redis", port=6379, decode_responses=True)
    await r.ping()
    await r.close()

    return {"message": "Python service is running"}