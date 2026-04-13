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
                // ->where('cbg_aktif', 1)
                ->first();
            
            if (!$cabang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan'
                ], 404);
            }
            
            // 2. 🔥 SWITCH ke database CABANG untuk cek user
            DatabaseManager::switchToCabang($cabang->cbg_database);
            
            // 3. Cek user di database CABANG
            $user = DB::connection('mysql')
                ->table('tuser')
                ->where('USER_KODE', $request->username)
                ->where('USER_PASSWORD', $request->password)
                ->first();
            
            if (!$user) {
                // Reset ke pusat
                DatabaseManager::resetToPusat();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah'
                ], 401);
            }
            
            // 4. 🔥 SIMPAN SESSION dengan nama database cabang
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
                'database' => $cabang->cbg_database
            ]);
            
            // 5. Reset ke pusat dulu (nanti middleware yang set ulang)
            DatabaseManager::resetToPusat();
            
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'user' => session('user')
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
