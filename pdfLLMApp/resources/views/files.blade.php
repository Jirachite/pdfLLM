<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploaded Files - pdfLLM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Uploaded Files</h1>
            <a href="{{ route('upload') }}" class="button is-primary mb-4">Upload New File</a>
            @if ($files->isEmpty())
                <p>No files uploaded yet.</p>
            @else
                <table class="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>File Type</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>{{ $file->filename }}</td>
                                <td>{{ $file->file_type }}</td>
                                <td>{{ $file->created_at }}</td>
                                <td>
                                    <a href="{{ route('files.debug', $file->id) }}" class="button is-info">Debug</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</body>
</html>