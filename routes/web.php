<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KnowledgeBotController;
// use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/ask', [KnowledgeBotController::class, 'ask']);