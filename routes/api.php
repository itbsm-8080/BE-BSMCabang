<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarangController;
use App\Http\Controllers\Api\DoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/cabang', [AuthController::class, 'getCabangList']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (perlu middleware cabang)
Route::middleware(['set.cabang'])->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/v1/barang', [BarangController::class, 'index']);

    Route::prefix('do')->group(function () {
        Route::get('/', [DoController::class, 'index']);
        Route::get('/generate-number', [DoController::class, 'generateNumber']);
        Route::get('/{nomor}', [DoController::class, 'show']);
        Route::get('/{nomor}/detail', [DoController::class, 'getDetail']);
        Route::post('/', [DoController::class, 'store']);
        Route::put('/{nomor}', [DoController::class, 'update']);
        Route::delete('/{nomor}', [DoController::class, 'destroy']);
    });

    // Gudang
    Route::get('/gudang', function () {
        $gudang = DB::connection('mysql')
            ->table('tgudang')
            ->select('gdg_kode as kode', 'gdg_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $gudang]);
    });

    // SO Available (belum di-closed)
    Route::get('/so/available', function (Request $request) {
        $search = $request->query('search');
        $query = DB::connection('mysql')
            ->table('tso_hdr')
            ->select([
                'so_nomor as Nomor',
                'so_tanggal as Tanggal',
                'cus_nama as Customer',
                'sls_nama as Salesman',
                'cus_alamat as Alamat',
                'cus_shipaddress as ShipAddress',
                'so_cus_kode as CustomerKode'
            ])
            ->join('tcustomer', 'cus_kode', '=', 'so_cus_kode')
            ->join('tsalesman', 'sls_kode', '=', 'so_sls_kode')
            ->where('so_isclosed', 0);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('so_nomor', 'LIKE', "%{$search}%")
                ->orWhere('cus_nama', 'LIKE', "%{$search}%");
            });
        }
        
        $soList = $query->orderBy('so_tanggal', 'desc')->limit(50)->get();
        return response()->json(['success' => true, 'data' => $soList]);
    });

    // SO Items (barang yang bisa di-DO)
    Route::get('/so/{soNomor}/items', function ($soNomor) {
        $items = DB::connection('mysql')
            ->table('tso_dtl')
            ->select([
                'sod_brg_kode as sku',
                'brg_nama as nama_barang',
                'brg_satuan as satuan',
                'sod_qty as qty_po',
                DB::raw("IFNULL(sod_qty_kirim, 0) as sudah_kirim"),
                DB::raw("sod_qty - IFNULL(sod_qty_kirim, 0) as kurang")
            ])
            ->join('tbarang', 'sod_brg_kode', '=', 'brg_kode')
            ->where('sod_so_nomor', $soNomor)
            ->whereRaw('sod_qty - IFNULL(sod_qty_kirim, 0) > 0')
            ->get();
        
        return response()->json(['success' => true, 'data' => $items]);
    });

    // Barang Available untuk DO
    Route::get('/barang/available', function (Request $request) {
        $soNomor = $request->query('so_nomor');
        $gudang = $request->query('gudang');
        $search = $request->query('search');
        
        $query = DB::connection('mysql')
            ->table('tso_dtl')
            ->select([
                'sod_brg_kode as sku',
                'brg_nama as nama_barang',
                'brg_satuan as satuan',
                DB::raw("sod_qty - IFNULL(sod_qty_kirim, 0) as kurang"),
                DB::raw("0 as stok")
            ])
            ->join('tbarang', 'sod_brg_kode', '=', 'brg_kode')
            ->where('sod_so_nomor', $soNomor)
            ->whereRaw('sod_qty - IFNULL(sod_qty_kirim, 0) > 0');
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('sod_brg_kode', 'LIKE', "%{$search}%")
                ->orWhere('brg_nama', 'LIKE', "%{$search}%");
            });
        }
        
        $items = $query->get();
        return response()->json(['success' => true, 'data' => $items]);
    });
});