<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Health check endpoint - raíz del dominio
Route::get('/', function () {
    try {
        // Verificar conexión a base de datos
        DB::connection()->getPdo();
        
        return response()->json([
            'status' => 'OK',
            'message' => 'Backend running',
            'timestamp' => now()->toIso8601String()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String()
        ], 500);
    }
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        
        return response()->json([
            'status' => 'OK',
            'message' => 'Backend running',
            'timestamp' => now()->toIso8601String()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String()
        ], 500);
    }
});
