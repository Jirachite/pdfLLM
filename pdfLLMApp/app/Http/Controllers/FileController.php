<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function index(Request $request)
    {
        Log::info('Files endpoint called', [
            'headers' => $request->headers->all(),
            'accept' => $request->header('Accept'),
            'wantsJson' => $request->wantsJson(),
        ]);

        if ($request->wantsJson()) {
            $files = DB::table('files')->select('id', 'filename', 'file_type', 'created_at')->get();
            return response()->json($files);
        }

        return view('home');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'upload_token' => 'required|string',
        ]);

        $filename = $request->file('file')->getClientOriginalName();
        $uploadToken = $request->input('upload_token');

        try {
            $lockKey = crc32($filename);
            $lockAcquired = DB::selectOne("SELECT pg_try_advisory_lock(?) AS acquired", [$lockKey])->acquired;

            if (!$lockAcquired) {
                Log::warning('Failed to acquire advisory lock', ['filename' => $filename, 'lock_key' => $lockKey]);
                return response()->json(['error' => 'Upload in progress for this file'], 429);
            }

            try {
                return DB::transaction(function () use ($request, $filename, $uploadToken, $lockKey) {
                    $tokenExists = DB::table('upload_tokens')
                        ->where('token', $uploadToken)
                        ->where('filename', $filename)
                        ->exists();
                    if (!$tokenExists) {
                        Log::warning('Invalid or duplicate upload token', ['filename' => $filename, 'token' => $uploadToken]);
                        return response()->json(['error' => 'Invalid upload token'], 422);
                    }

                    DB::table('upload_tokens')->where('token', $uploadToken)->delete();

                    Log::info('Checking for existing file', ['filename' => $filename]);
                    $existingFile = DB::table('files')->where('filename', $filename)->first();
                    if ($existingFile) {
                        Log::warning('Duplicate file detected', ['filename' => $filename, 'existing_id' => $existingFile->id]);
                        return response()->json(['error' => 'File already exists'], 409);
                    }

                    $file = $request->file('file');
                    $fileType = strtolower($file->extension());
                    $path = $file->storeAs('uploads', $filename, 'public');

                    $fileId = DB::table('files')->insertGetId([
                        'filename' => $filename,
                        'file_type' => $file->getMimeType(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::info('File inserted', ['file_id' => $fileId, 'filename' => $filename]);

                    // Trigger file processing
                    $this->processFile($fileId);

                    return response()->json(['success' => 'File uploaded, processing started', 'file_id' => $fileId]);
                });
            } finally {
                DB::select("SELECT pg_advisory_unlock(?)", [$lockKey]);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if (strpos($e->getMessage(), 'duplicate key value violates unique constraint') !== false) {
                Log::warning('Duplicate file detected by unique constraint', ['filename' => $filename]);
                return response()->json(['error' => 'File already exists'], 409);
            }
            Log::error('File upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('File upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function processFile($fileId)
    {
        try {
            $file = DB::table('files')->where('id', $fileId)->first();
            if (!$file) {
                Log::error('File not found for processing', ['file_id' => $fileId]);
                return response()->json(['error' => 'File not found'], 404);
            }

            Log::info('Starting file processing', ['file_id' => $fileId, 'filename' => $file->filename]);
            $client = new Client();
            $response = $client->post('http://nginx:80/api/process', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen(storage_path('app/public/uploads/' . $file->filename), 'r'),
                        'filename' => $file->filename,
                    ],
                ],
                'timeout' => 60,
            ]);

            if ($response->getStatusCode() === 200) {
                Log::info('File processed successfully', [
                    'file_id' => $fileId,
                    'response' => $response->getBody()->getContents(),
                ]);
                return response()->json(['success' => 'File processed successfully']);
            }

            Log::error('Python service error', [
                'file_id' => $fileId,
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ]);
            return response()->json(['error' => 'Failed to process file'], 500);
        } catch (\Exception $e) {
            Log::error('File processing error', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function generateUploadToken(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
        ]);

        $filename = $request->input('filename');
        $token = Str::random(32);

        DB::table('upload_tokens')->insert([
            'filename' => $filename,
            'token' => $token,
            'created_at' => now(),
        ]);

        DB::table('upload_tokens')
            ->where('created_at', '<', now()->subMinutes(5))
            ->delete();

        return response()->json(['upload_token' => $token]);
    }

    public function debug($id)
    {
        $file = DB::table('files')->where('id', $id)->first();
        $chunks = DB::table('chunks')->where('file_id', $id)->get();
        Log::info('Debug view accessed', ['file_id' => $id, 'chunk_count' => $chunks->count()]);
        return view('debug', ['file' => $file, 'chunks' => $chunks]);
    }

    public function delete($id)
    {
        try {
            $file = DB::table('files')->where('id', $id)->first();
            if (!$file) {
                return response()->json(['error' => 'File not found'], 404);
            }

            Storage::disk('public')->delete('uploads/' . $file->filename);
            DB::table('chunks')->where('file_id', $id)->delete();
            DB::table('files')->where('id', $id)->delete();

            return response()->json(['success' => 'File deleted successfully']);
        } catch (\Exception $e) {
            Log::error('File delete error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function query(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'integer',
        ]);

        try {
            Log::info('Query payload sent to Python:', ['payload' => $request->all()]);
            $response = Http::timeout(60)->post("http://nginx:80/api/query", [
                'query' => $request->input('query'),
                'file_ids' => $request->input('file_ids', []),
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::error('Query service error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json(['error' => 'Failed to process query'], $response->status());
        } catch (\Exception $e) {
            Log::error('Query error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}