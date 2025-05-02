<?php
// In ~/pdfLLM/pdfLLMApp/app/Http/Controllers/FileController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ]);

        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $fileType = strtolower($file->extension()); // Normalize to lowercase
            $path = $file->store('uploads', 'public');
            $response = Http::timeout(60)->post("http://nginx:80/api/process", [
                'filename' => basename($path),
                'file_type' => $fileType,
            ]);

            if ($response->successful()) {
                return back()->with('success', 'File processed successfully');
            }

            Log::error('Python service error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return back()->with('error', 'Failed to process file');
        } catch (\Exception $e) {
            Log::error('File upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}