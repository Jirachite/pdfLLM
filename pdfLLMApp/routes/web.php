<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', function () {
    return view('upload');
})->name('file.upload.form');

Route::post('/upload', [FileController::class, 'upload'])->name('file.upload');