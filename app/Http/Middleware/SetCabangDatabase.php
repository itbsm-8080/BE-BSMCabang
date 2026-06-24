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
        // 🔥 PRIORITAS 1: Baca dari HEADER (dikirim Nuxt)
        $cabangDatabase = $request->header('X-Cabang-Database');
        $cabangKode = $request->header('X-Cabang-Kode');
        
        // 🔥 PRIORITAS 2: Fallback ke SESSION (jika ada)
        if (!$cabangDatabase) {
            $cabangDatabase = session('cabang_database');
        }
        if (!$cabangKode) {
            $cabangKode = session('user.cabang_kode');
        }
        
        Log::info('SetCabangDatabase', [
            'cabang_database' => $cabangDatabase,
            'cabang_kode' => $cabangKode,
            'source' => $request->header('X-Cabang-Database') ? 'header' : 'session'
        ]);
        
        if (!$cabangDatabase) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang tidak dipilih. Silakan login ulang.'
            ], 401);
        }
        
        // Switch ke database cabang
        DatabaseManager::switchToCabang($cabangDatabase);
        
        // Simpan kode cabang ke request untuk digunakan controller
        $request->merge(['cabang_kode' => $cabangKode]);
        
        return $next($request);
    }
}