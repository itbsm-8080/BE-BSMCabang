<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pusat\Cabang;
use App\Services\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function getCabangList()
    {
        try {
            // Ambil daftar cabang dari database PUSAT (bsm)
            $cabangs = DB::connection('mysql')
                ->table('tcabang')
                // ->where('cbg_aktif', 1)
                ->select('cbg_kode', 'cbg_nama', 'cbg_database')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $cabangs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function login(Request $request)
{
    try {
        $request->validate([
            'cabang_kode' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string'
        ]);
        
        // 1. Ambil info cabang dari database PUSAT
        $cabang = DB::connection('mysql')
            ->table('tcabang')
            ->where('cbg_kode', $request->cabang_kode)
            ->first();
        
        if (!$cabang) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang tidak ditemukan'
            ], 404);
        }
        
        // 2. 🔥 SWITCH ke database CABANG
        DatabaseManager::switchToCabang($cabang->cbg_database);
        
        // 🔥 DEBUG: Cek apakah switch berhasil
        Log::info('Switched to database:', [
            'database' => $cabang->cbg_database,
            'current_db' => DB::connection('mysql')->getDatabaseName()
        ]);
        
        // 3. Cek user di database CABANG
        $user = DB::connection('mysql')
            ->table('tuser')
            ->where('USER_KODE', $request->username)
            ->where('USER_PASSWORD', $request->password)
            ->first();
        
        if (!$user) {
            DatabaseManager::resetToPusat();
            
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
        }
        
        // 4. 🔥 Generate token (SETELAH switch berhasil)
        $token = Str::random(64);
        $refreshToken = Str::random(64);
        $tokenExpiry = now()->addHours(2);
        $refreshExpiry = now()->addDays(1);
        
        // 5. 🔥 Simpan token di database CABANG (koneksi sudah di-switch)
        DB::connection('mysql')->table('tuser_token')->insert([
            'USER_KODE' => $user->USER_KODE,
            'token' => $token,
            'refresh_token' => $refreshToken,
            'token_expiry' => $tokenExpiry,
            'refresh_expiry' => $refreshExpiry,
            'created_at' => now(),
        ]);
        
        // 6. Simpan session
        session()->regenerate();
        session([
            'user' => [
                'kode' => $user->USER_KODE,
                'nama' => $user->USER_NAMA,
                'cabang_kode' => $cabang->cbg_kode,
                'cabang_nama' => $cabang->cbg_nama,
                'cabang_database' => $cabang->cbg_database
            ],
            'cabang_database' => $cabang->cbg_database
        ]);
        session()->save();
        
        Log::info('Login berhasil', [
            'user' => $user->USER_KODE,
            'database' => $cabang->cbg_database,
            'current_db' => DB::connection('mysql')->getDatabaseName()
        ]);
        
        // 7. Reset ke pusat (middleware yang akan set ulang)
        DatabaseManager::resetToPusat();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => session('user'),
                'token' => $token,
                'refresh_token' => $refreshToken,
                'token_expiry' => $tokenExpiry,
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Login error: ' . $e->getMessage());
        DatabaseManager::resetToPusat();
        return response()->json([
            'success' => false,
            'message' => 'Login gagal: ' . $e->getMessage()
        ], 500);
    }
}

    /**
 * POST /api/auth/refresh
 * Refresh token yang expired
 */
public function refreshToken(Request $request)
{
    $refreshToken = $request->header('X-Refresh-Token');
    $cabangDatabase = $request->header('X-Cabang-Database'); // 🔥 Dapatkan dari header
    
    if (!$refreshToken) {
        return response()->json(['success' => false, 'message' => 'Refresh token required'], 401);
    }
    
    // 🔥 Switch ke database cabang
    if ($cabangDatabase) {
        DatabaseManager::switchToCabang($cabangDatabase);
    }
    
    $tokenData = DB::connection('mysql')->table('tuser_token')
        ->where('refresh_token', $refreshToken)
        ->where('refresh_expiry', '>', now())
        ->first();
    
    if (!$tokenData) {
        DatabaseManager::resetToPusat();
        return response()->json(['success' => false, 'message' => 'Invalid refresh token'], 401);
    }
    
    $newToken = Str::random(64);
    $newExpiry = now()->addHours(2);
    
    DB::connection('mysql')->table('tuser_token')->where('id', $tokenData->id)->update([
        'token' => $newToken,
        'token_expiry' => $newExpiry,
    ]);
    
    DatabaseManager::resetToPusat();
    
    return response()->json([
        'success' => true,
        'data' => [
            'token' => $newToken,
            'token_expiry' => $newExpiry,
        ]
    ]);
}
    
    // GET: api/user
    public function getUser(Request $request)
    {
        if (!session('user')) {
            return response()->json([
                'success' => false,
                'message' => 'Belum login'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'data' => session('user')
        ]);
    }
    
    // POST: api/logout
    public function logout(Request $request)
    {
        session()->flush();
        DatabaseManager::resetToPusat();
        
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}
