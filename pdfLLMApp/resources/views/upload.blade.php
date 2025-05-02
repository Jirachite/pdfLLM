<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - pdfLLM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Upload PDF or Image</h1>
            <a href="{{ route('files.index') }}" class="button is-info mb-4">View Uploaded Files</a>
            @if (session('success'))
                <div class="notification is-success">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="notification is-danger">
                    {{ session('error') }}
                </div>
            @endif
            <form action="{{ route('file.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label class="label" for="file">Select File (PDF or Image)</label>
                    <div class="control">
                        <input class="input" type="file" name="file" id="file" accept=".pdf,image/*" required>
                    </div>
                    @error('file')
                        <p class="help is-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <div class="control">
                        <button class="button is-primary" type="submit">Upload</button>
                    </div>
                </div>
            </form>
            <h2 class="subtitle mt-5">Query Documents</h2>
            <form action="{{ route('file.query') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="query">Enter Query</label>
                    <div class="control">
                        <input class="input" type="text" name="query" id="query" required>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <button class="button is-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            @if (session('query_result'))
                <div class="notification is-info mt-4">
                    <strong>Result:</strong> {{ session('query_result') }}
                </div>
            @endif
        </div>
    </section>
</body>
</html>