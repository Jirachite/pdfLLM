<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

Route::get('/', [FileController::class, 'index']);
Route::get('/files', [FileController::class, 'index'])->name('files');
Route::post('/upload', [FileController::class, 'upload'])->name('upload');
Route::post('/generate-upload-token', [FileController::class, 'generateUploadToken'])->name('generate-upload-token');
Route::delete('/delete/{id}', [FileController::class, 'delete'])->name('delete');
Route::post('/query', [FileController::class, 'query'])->name('query');
Route::get('/debug/{id}', [FileController::class, 'debug'])->name('debug');
Route::get('/storage/uploads/{filename}', function ($filename) {
    $path = storage_path('app/public/uploads/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
})->name('serve-file');