<?php
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::get('/upload', function () {
    return view('upload');
})->name('upload');
Route::post('/upload', [FileController::class, 'upload'])->name('file.upload');
Route::get('/files', [FileController::class, 'index'])->name('files.index');
Route::get('/files/{id}/debug', [FileController::class, 'debug'])->name('files.debug');
Route::post('/query', [FileController::class, 'query'])->name('file.query');