<?php

use App\Http\Controllers\v1\ApplicationController;
use App\Http\Controllers\v1\ChatController;
use App\Http\Controllers\v1\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('applications')->group(function () {
        Route::post('/', [ApplicationController::class, 'store']); // Create application
        Route::get('/', [ApplicationController::class, 'index']); // List all applications
    });

    Route::prefix('applications/{application_token}/chats')->group(function () {
        Route::post('/', [ChatController::class, 'store']); // Create chat
        Route::get('/', [ChatController::class, 'index']); // List all chats for application

        Route::prefix('{chat_number}/messages')->group(function () {
            Route::post('/', [MessageController::class, 'store']); // Create message
            Route::get('/', [MessageController::class, 'index']); // Create message
            Route::get('/search', [MessageController::class, 'searchMessages']); // Search messages
        });
    });


});
