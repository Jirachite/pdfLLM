<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

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
        ]);

        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $fileType = strtolower($file->extension());
            $path = $file->store('uploads', 'public');

            $client = new Client();
            $response = $client->post('http://nginx:80/api/process', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen(storage_path('app/public/' . $path), 'r'),
                        'filename' => $filename,
                    ],
                ],
                'timeout' => 60
            ]);

            if ($response->getStatusCode() === 200) {
                return response()->json(['success' => 'File processed successfully']);
            }

            Log::error('Python service error', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ]);
            return response()->json(['error' => 'Failed to process file'], 500);
        } catch (\Exception $e) {
            Log::error('File upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function debug($id)
    {
        $file = DB::table('files')->where('id', $id)->first();
        $chunks = DB::table('chunks')->where('file_id', $id)->get();
        return view('debug', ['file' => $file, 'chunks' => $chunks]);
    }

    public function query(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'integer',
        ]);

        try {
            \Log::info('Query payload sent to Python:', ['payload' => $request->all()]);
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