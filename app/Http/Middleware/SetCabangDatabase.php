<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetCabangDatabase
{
    public function handle(Request $request, Closure $next)
    {
        // 🔥 PRIORITAS: Ambil dari HEADER dulu
        $cabangDatabase = $request->header('X-Cabang-Database');
        
        // Fallback: ambil dari session (kalau ada)
        if (!$cabangDatabase) {
            $cabangDatabase = session('cabang_database');
        }
        
        Log::info('SetCabangDatabase', [
            'from_header' => $request->header('X-Cabang-Database'),
            'from_session' => session('cabang_database'),
            'final' => $cabangDatabase
        ]);
        
        if (!$cabangDatabase) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang tidak dipilih. Silakan login ulang.'
            ], 401);
        }
        
        // Switch ke database cabang
        DatabaseManager::switchToCabang($cabangDatabase);
        
        return $next($request);
    }
}