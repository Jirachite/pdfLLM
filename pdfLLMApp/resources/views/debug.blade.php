<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug View</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-2xl font-bold mb-4">Debug View for File: {{ $file->filename ?? 'Unknown' }}</h1>
    <h2 class="text-xl font-semibold mb-2">File Details</h2>
    <ul class="mb-4">
        <li><strong>ID:</strong> {{ $file->id ?? 'N/A' }}</li>
        <li><strong>Filename:</strong> {{ $file->filename ?? 'N/A' }}</li>
        <li><strong>File Type:</strong> {{ $file->file_type ?? 'N/A' }}</li>
        <li><strong>Created At:</strong> {{ $file->created_at ?? 'N/A' }}</li>
    </ul>
    <h2 class="text-xl font-semibold mb-2">Chunks</h2>
    @if($chunks->isEmpty())
        <p class="text-red-500">No chunks found for this file.</p>
    @else
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 p-2">ID</th>
                    <th class="border border-gray-300 p-2">Chunk Text</th>
                    <th class="border border-gray-300 p-2">Embedding</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chunks as $chunk)
                    <tr>
                        <td class="border border-gray-300 p-2">{{ $chunk->id }}</td>
                        <td class="border border-gray-300 p-2">{{ Str::limit($chunk->chunk_text, 100) }}</td>
                        <td class="border border-gray-300 p-2">
                            <?php
                                $embedding = json_decode(str_replace(['[', ']'], ['[', ']'], $chunk->embedding));
                                echo '[' . implode(', ', array_slice($embedding, 0, 5)) . '...]';
                            ?>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <a href="/" class="mt-4 inline-block bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Back to Home</a>
</body>
</html>