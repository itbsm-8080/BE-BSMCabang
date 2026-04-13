<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Validator;

class BarangController extends Controller
{
    public function index(Request $request)
{
    try {
        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'brg_nama');
        $sortOrder = $request->query('sort_order', 'asc');
        $filters = $request->query('filters');
        $perPage = $request->query('per_page', 15);
        
        // Parse filters dari JSON
        $filtersArray = [];
        if ($filters) {
            $filtersArray = json_decode($filters, true) ?? [];
        }
        
        $query = DB::connection('mysql')
            ->table('tbarang as b')
            ->select([
                'b.brg_kode as Kode',
                'b.brg_nama as Nama',
                'b.brg_satuan as Satuan',
                'kt.ktg_nama as Kategori',
                'g.gr_nama as Tipe',
                'b.brg_hrgjual as HargaJual',
                'b.brg_min_stok as Min',
            ])
            ->leftJoin('tkategori as kt', 'b.brg_ktg_kode', '=', 'kt.ktg_kode')
            ->leftJoin('tgroup as g', 'b.brg_gr_kode', '=', 'g.gr_kode');
        
        // Global search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('b.brg_nama', 'LIKE', "%{$search}%")
                  ->orWhere('b.brg_kode', 'LIKE', "%{$search}%")
                  ->orWhere('b.brg_satuan', 'LIKE', "%{$search}%");
            });
        }
        
        // Advanced filters per kolom
        foreach ($filtersArray as $field => $value) {
            if ($value && trim($value) !== '') {
                // Map field ke kolom database
                $columnMap = [
                    'Kode' => 'b.brg_kode',
                    'Nama' => 'b.brg_nama',
                    'Satuan' => 'b.brg_satuan',
                    'Kategori' => 'kt.ktg_nama',
                    'Tipe' => 'g.gr_nama',
                    'HargaJual' => 'b.brg_hrgjual',
                    'Min' => 'b.brg_min_stok',
                ];
                
                $column = $columnMap[$field] ?? "b.brg_{$field}";
                $query->where($column, 'LIKE', "%{$value}%");
            }
        }
        
        // Sorting
        $sortColumnMap = [
            'Kode' => 'b.brg_kode',
            'Nama' => 'b.brg_nama',
            'Satuan' => 'b.brg_satuan',
            'Kategori' => 'kt.ktg_nama',
            'Tipe' => 'g.gr_nama',
            'HargaJual' => 'b.brg_hrgjual',
            'Min' => 'b.brg_min_stok',
        ];
        
        $sortColumn = $sortColumnMap[$sortBy] ?? 'b.brg_nama';
        $query->orderBy($sortColumn, $sortOrder);
        
        $barangs = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $barangs->items(),
            'pagination' => [
                'current_page' => $barangs->currentPage(),
                'per_page' => $barangs->perPage(),
                'total' => $barangs->total(),
                'last_page' => $barangs->lastPage()
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data: ' . $e->getMessage()
        ], 500);
    }
}
    
    // POST /api/v1/barang
    public function store(Request $request)
    {
        try {
            $connection = session('cabang_database') ? 'cabang' : 'pusat';
            
            $validator = Validator::make($request->all(), [
                'Kode' => 'required|unique:tbarang,brg_kode',
                'Nama' => 'required',
                'Satuan' => 'required'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::connection($connection)->table('tbarang')->insert([
                'brg_kode' => $request->Kode,
                'brg_nama' => $request->Nama,
                'brg_satuan' => $request->Satuan,
                'brg_ktg_kode' => $request->Kategori,
                'brg_gr_kode' => $request->Tipe,
                'brg_hrgjual' => $request->HargaJual ?? 0,
                'brg_min_stok' => $request->Min ?? 0,
                'brg_disc_sales' => $request->Disc_Salesman ?? 0,
                'brg_merk' => $request->Merk ?? '',
                'brg_isproductfocus' => $request->Product_Focus ?? 0,
                'date_create' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil ditambahkan'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // GET /api/v1/barang/{id}
    public function show($id)
    {
        try {
            $connection = session('cabang_database') ? 'cabang' : 'pusat';
            
            $barang = DB::connection($connection)
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->first();
            
            if (!$barang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $barang->brg_kode,
                    'Nama' => $barang->brg_nama,
                    'Satuan' => $barang->brg_satuan,
                    'Kategori' => $barang->brg_ktg_kode,
                    'Tipe' => $barang->brg_gr_kode,
                    'HargaJual' => $barang->brg_hrgjual,
                    'Min' => $barang->brg_min_stok,
                    'Disc_Salesman' => $barang->brg_disc_sales,
                    'Merk' => $barang->brg_merk,
                    'Product_Focus' => $barang->brg_isproductfocus
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // PUT /api/v1/barang/{id}
    public function update(Request $request, $id)
    {
        try {
            $connection = session('cabang_database') ? 'cabang' : 'pusat';
            
            $validator = Validator::make($request->all(), [
                'Nama' => 'required',
                'Satuan' => 'required'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $exists = DB::connection($connection)
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->exists();
            
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            DB::connection($connection)
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->update([
                    'brg_nama' => $request->Nama,
                    'brg_satuan' => $request->Satuan,
                    'brg_ktg_kode' => $request->Kategori,
                    'brg_gr_kode' => $request->Tipe,
                    'brg_hrgjual' => $request->HargaJual ?? 0,
                    'brg_min_stok' => $request->Min ?? 0,
                    'brg_disc_sales' => $request->Disc_Salesman ?? 0,
                    'brg_merk' => $request->Merk ?? '',
                    'brg_isproductfocus' => $request->Product_Focus ?? 0,
                    'date_modified' => now()
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil diupdate'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // DELETE /api/v1/barang/{id}
    public function destroy($id)
    {
        try {
            $connection = session('cabang_database') ? 'cabang' : 'pusat';
            
            $exists = DB::connection($connection)
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->exists();
            
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            DB::connection($connection)
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }
}