<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug File - pdfLLM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Debug File: {{ $file->filename }}</h1>
            <a href="{{ route('files.index') }}" class="button is-primary mb-4">Back to Files</a>
            <h2 class="subtitle">File Details</h2>
            <p><strong>File Type:</strong> {{ $file->file_type }}</p>
            <p><strong>Uploaded At:</strong> {{ $file->created_at }}</p>
            <h2 class="subtitle">Extracted Text (Markdown)</h2>
            <pre>{{ $file->processed_text }}</pre>
            <h2 class="subtitle">Chunks and Embeddings</h2>
            @if ($chunks->isEmpty())
                <p>No chunks available.</p>
            @else
                <table class="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Chunk Text</th>
                            <th>Embedding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chunks as $chunk)
                            <tr>
                                <td>{{ $chunk->chunk_text }}</td>
                                <td>{{ $chunk->embedding }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</body>
</html>