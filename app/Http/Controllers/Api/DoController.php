<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Validator;
use App\Models\DoHeader;
use App\Models\DoDetail;

class DoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $search = $request->query('search');
            $perPage = $request->query('per_page', 15);
            $sortBy = $request->query('sort_by', 'do_tanggal');
            $sortOrder = $request->query('sort_order', 'desc');
            
            // Default tanggal: 30 hari terakhir
            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }
            
            $query = DB::connection('mysql')
                ->table('tdo_hdr')
                ->select([
                    'do_nomor as Nomor',
                    'do_tanggal as Tanggal',
                    'do_so_nomor as Nomor_SO',
                    'sls_nama as Salesman',
                    'cus_nama as Customer',
                    DB::raw("IF(do_isinvoice = 0, 'Belum', 'Sudah') as Invoiced"),
                    'do_iskembali as Kembali'
                ])
                ->join('tso_hdr', 'so_nomor', '=', 'do_so_nomor')
                ->join('tsalesman', 'sls_kode', '=', 'so_sls_kode')
                ->join('tcustomer', 'cus_kode', '=', 'so_cus_kode')
                ->whereBetween('do_tanggal', [$startDate, $endDate]);
            
            // Global search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('do_nomor', 'LIKE', "%{$search}%")
                      ->orWhere('do_so_nomor', 'LIKE', "%{$search}%")
                      ->orWhere('sls_nama', 'LIKE', "%{$search}%")
                      ->orWhere('cus_nama', 'LIKE', "%{$search}%");
                });
            }
            
            $query->orderBy($sortBy, $sortOrder);
            $doList = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $doList->items(),
                'pagination' => [
                    'current_page' => $doList->currentPage(),
                    'per_page' => $doList->perPage(),
                    'total' => $doList->total(),
                    'last_page' => $doList->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($nomor)
    {
        try {
            $do = DB::connection('mysql')
                ->table('tdo_hdr')
                ->select([
                    'do_nomor as Nomor',
                    'do_tanggal as Tanggal',
                    'do_so_nomor as Nomor_SO',
                    'do_cus_kode as CustomerKode',
                    'do_shipaddress as ShipAddress',
                    'do_memo as Memo',
                    'do_gdg_kode as Gudang',
                    'do_isecer as Isecer'
                ])
                ->where('do_nomor', $nomor)
                ->first();
            
            if (!$do) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }
            
            // Ambil customer info
            $customer = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_kode', $do->CustomerKode)
                ->first();
            
            // Ambil detail barang
            $details = DB::connection('mysql')
                ->table('tdo_dtl')
                ->select([
                    'dod_brg_kode as sku',
                    'brg_nama as nama_barang',
                    'dod_brg_satuan as satuan',
                    'dod_qty as qty',
                    'dod_tgl_expired as expired',
                    'dod_status as closed',
                    'dod_gdg_kode as gudang',
                    'dod_idbatch as id_batch'
                ])
                ->join('tbarang', 'dod_brg_kode', '=', 'brg_kode')
                ->where('dod_do_nomor', $nomor)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Nomor' => $do->Nomor,
                    'Tanggal' => $do->Tanggal,
                    'Nomor_SO' => $do->Nomor_SO,
                    'Customer' => $customer->cus_nama ?? '',
                    'Alamat' => $customer->cus_alamat ?? '',
                    'ShipAddress' => $do->ShipAddress,
                    'Memo' => $do->Memo,
                    'Gudang' => $do->Gudang,
                    'Isecer' => $do->Isecer,
                    'details' => $details
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // POST: api/do (store)
    public function store(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            $validator = Validator::make($request->all(), [
                'nomor' => 'required|unique:tdo_hdr,do_nomor',
                'tanggal' => 'required|date',
                'so_nomor' => 'required',
                'gudang' => 'required',
                'details' => 'required|array|min:1'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Insert header
            $doNomor = $request->nomor;
            DB::connection('pusat')->table('tdo_hdr')->insert([
                'do_nomor' => $doNomor,
                'do_tanggal' => $request->tanggal,
                'do_so_nomor' => $request->so_nomor,
                'do_cus_kode' => $request->customer_kode,
                'do_shipaddress' => $request->ship_address,
                'do_memo' => $request->memo,
                'do_gdg_kode' => $request->gudang,
                'do_isecer' => $request->isecer ?? 0,
                'date_create' => now(),
                'user_create' => auth()->user()?->USER_KODE ?? 'admin'
            ]);
            
            // Insert details
            $nourut = 1;
            foreach ($request->details as $item) {
                if (!empty($item['sku']) && $item['qty'] > 0) {
                    DB::connection('pusat')->table('tdo_dtl')->insert([
                        'dod_do_nomor' => $doNomor,
                        'dod_brg_kode' => $item['sku'],
                        'dod_brg_satuan' => $item['satuan'],
                        'dod_qty' => $item['qty'],
                        'dod_tgl_expired' => $item['expired'] ?? null,
                        'dod_nourut' => $nourut++,
                        'dod_status' => $item['closed'] ? 1 : 0,
                        'dod_gdg_kode' => $item['gudang'] ?? $request->gudang,
                        'dod_idbatch' => $item['id_batch'] ?? null
                    ]);
                }
            }
            
            DB::connection('pusat')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'DO berhasil disimpan',
                'data' => ['nomor' => $doNomor]
            ]);
            
        } catch (\Exception $e) {
            DB::connection('pusat')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // PUT: api/do/{nomor}
    public function update(Request $request, $nomor)
    {
        try {
            DB::connection('pusat')->beginTransaction();
            
            // Update header
            DB::connection('pusat')
                ->table('tdo_hdr')
                ->where('do_nomor', $nomor)
                ->update([
                    'do_tanggal' => $request->tanggal,
                    'do_shipaddress' => $request->ship_address,
                    'do_memo' => $request->memo,
                    'do_gdg_kode' => $request->gudang,
                    'do_isecer' => $request->isecer ?? 0,
                    'date_modified' => now(),
                    'user_modified' => auth()->user()?->USER_KODE ?? 'admin'
                ]);
            
            // Delete old details
            DB::connection('pusat')
                ->table('tdo_dtl')
                ->where('dod_do_nomor', $nomor)
                ->delete();
            
            // Insert new details
            $nourut = 1;
            foreach ($request->details as $item) {
                if (!empty($item['sku']) && $item['qty'] > 0) {
                    DB::connection('pusat')->table('tdo_dtl')->insert([
                        'dod_do_nomor' => $nomor,
                        'dod_brg_kode' => $item['sku'],
                        'dod_brg_satuan' => $item['satuan'],
                        'dod_qty' => $item['qty'],
                        'dod_tgl_expired' => $item['expired'] ?? null,
                        'dod_nourut' => $nourut++,
                        'dod_status' => $item['closed'] ? 1 : 0,
                        'dod_gdg_kode' => $item['gudang'] ?? $request->gudang,
                        'dod_idbatch' => $item['id_batch'] ?? null
                    ]);
                }
            }
            
            DB::connection('pusat')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'DO berhasil diupdate'
            ]);
            
        } catch (\Exception $e) {
            DB::connection('pusat')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // DELETE: api/do/{nomor}
    public function destroy($nomor)
    {
        try {
            DB::connection('mysql')
                ->table('tdo_dtl')
                ->where('dod_do_nomor', $nomor)
                ->delete();
            
            DB::connection('mysql')
                ->table('tdo_hdr')
                ->where('do_nomor', $nomor)
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'DO berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // GET: api/do/generate-number
    public function generateNumber(Request $request)
    {
        try {
            $tanggal = $request->query('tanggal', date('Y-m-d'));
            $dateObj = new \DateTime($tanggal);
            $prefix = 'DO.' . $dateObj->format('ym') . '.';
            
            $lastNumber = DB::connection('pusat')
                ->table('tdo_hdr')
                ->where('do_nomor', 'LIKE', "%{$prefix}%")
                ->orderBy('do_nomor', 'desc')
                ->value('do_nomor');
            
            if ($lastNumber) {
                $lastSeq = (int) substr($lastNumber, -4);
                $newSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newSeq = '0001';
            }
            
            $nomor = $prefix . $newSeq;
            
            return response()->json([
                'success' => true,
                'nomor' => $nomor
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // GET: api/do/{nomor}/detail (untuk row expansion)
    public function getDetail($nomor)
    {
        try {
            $details = DB::connection('pusat')
                ->table('tdo_dtl')
                ->select([
                    'dod_brg_kode as Kode',
                    'brg_nama as Nama',
                    'dod_brg_satuan as Satuan',
                    'dod_qty as Jumlah'
                ])
                ->join('tbarang', 'dod_brg_kode', '=', 'brg_kode')
                ->where('dod_do_nomor', $nomor)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $details
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail: ' . $e->getMessage()
            ], 500);
        }
    }
}
