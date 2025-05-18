FROM postgres:15

RUN apt-get update && \
    apt-get install -y postgresql-15-pgvector && \
    rm -rf /var/lib/apt/lists/*

COPY initdb.sql /docker-entrypoint-initdb.d/initdb.sql
RUN chmod 644 /docker-entrypoint-initdb.d/initdb.sql