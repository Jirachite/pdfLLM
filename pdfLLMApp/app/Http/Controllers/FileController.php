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
                     return back()->with('success', 'File processed successfully');
                 }

                 Log::error('Python service error', [
                     'status' => $response->getStatusCode(),
                     'body' => $response->getBody()->getContents(),
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

         public function index()
         {
             $files = DB::table('files')->select('id', 'filename', 'file_type', 'created_at')->get();
             return view('files', ['files' => $files]);
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
             ]);

             try {
                 $response = Http::timeout(60)->post("http://nginx:80/api/query", [
                     'query' => $request->query,
                 ]);

                 if ($response->successful()) {
                     return back()->with([
                         'query_result' => $response->json()['response'],
                         'context' => $response->json()['context'] ?? null
                     ]);
                 }

                 Log::error('Query service error', [
                     'status' => $response->status(),
                     'body' => $response->body(),
                 ]);
                 return back()->with('error', 'Failed to process query');
             } catch (\Exception $e) {
                 Log::error('Query error', [
                     'message' => $e->getMessage(),
                     'trace' => $e->getTraceAsString(),
                 ]);
                 return back()->with('error', 'An error occurred: ' . $e->getMessage());
             }
         }
     }