<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Validator;

class BarangController extends Controller
{
    /**
     * GET /api/v1/barang
     */
public function index(Request $request)
{
    try {
        $perPage = $request->query('per_page', 9999);
        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                b.brg_kode AS Kode,
                b.brg_nama AS Nama,
                b.brg_satuan AS Satuan,
                kt.ktg_nama AS Kategori,
                g.gr_nama AS Tipe,
                b.brg_hrgjual AS HargaJual,
                b.brg_stok AS Stok,
                b.brg_min_stok AS Min,
                b.brg_merk AS Merk,
                b.brg_isproductfocus AS Product_Focus
            FROM tbarang AS b
            LEFT JOIN tkategori AS kt ON b.brg_ktg_kode = kt.ktg_kode
            LEFT JOIN tgroup AS g ON b.brg_gr_kode = g.gr_kode
            ORDER BY b.brg_nama ASC
        ";

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) AS sub";
        $totalResult = DB::select($countSql);
        $total = $totalResult[0]->total ?? 0;

        // Paginate
        $sql .= " LIMIT ? OFFSET ?";
        $data = DB::select($sql, [(int)$perPage, (int)$offset]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => (int)$perPage,
                'total' => $total,
                'last_page' => (int)ceil($total / $perPage)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * GET /api/v1/barang/max-kode
     */
    public function maxKode()
    {
        try {
            $maxKode = DB::connection('mysql')
                ->table('tbarang')
                ->max('brg_kode');

            $newKode = $maxKode ? (intval($maxKode) + 1) : 10001;

            return response()->json([
                'success' => true,
                'kode' => (string) $newKode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/barang
     */
    public function store(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();

            $validator = Validator::make($request->all(), [
                'Kode' => 'required|unique:tbarang,brg_kode',
                'Nama' => 'required',
                'Satuan' => 'required',
                'Tipe' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::connection('mysql')->table('tbarang')->insert([
                'brg_kode' => $request->Kode,
                'brg_nama' => $request->Nama,
                'brg_satuan' => $request->Satuan,
                'brg_gr_kode' => $request->Tipe,
                'brg_ktg_kode' => $request->Kategori,
                'brg_merk' => $request->Merk,
                'brg_gdg_default' => $request->Gudang,
                'brg_sup_kode' => $request->Pemasok,
                'brg_isaktif' => $request->IsAktif ?? 1,
                'brg_isstok' => $request->IsStok ?? 1,
                'brg_isexpired' => $request->IsExpired ?? 0,
                'brg_isproductfocus' => $request->Product_Focus ?? 0,
                'brg_hrgbeli' => $request->HargaBeli ?? 0,
                'brg_hrgjual' => $request->HargaJual ?? 0,
                'brg_harga_min' => $request->HET ?? 0,
                'brg_min_stok' => $request->MinStok ?? 0,
                'brg_max_stok' => $request->MaxStok ?? 0,
                'brg_disc_sales' => $request->DiscSalesman ?? 0,
                'date_create' => now(),
                'user_create' => 'admin'
            ]);

            if ($request->HargaKhusus && is_array($request->HargaKhusus)) {
                foreach ($request->HargaKhusus as $item) {
                    if ($item['hargajual'] > 0) {
                        DB::connection('mysql')->table('thargajualjenis')->insert([
                            'hjj_brg_kode' => $request->Kode,
                            'hjj_jc_kode' => $item['kode'],
                            'hjj_hargajual' => $item['hargajual']
                        ]);
                    }
                }
            }

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil disimpan',
                'data' => ['Kode' => $request->Kode]
            ], 201);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/barang/{id}
     */
    public function show($id)
    {
        try {
            $barang = DB::connection('mysql')
                ->table('tbarang')
                ->leftJoin('tsupplier', 'sup_kode', '=', 'brg_sup_kode')
                ->select(
                    'tbarang.*',
                    'sup_nama'
                )
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
                    'Tipe' => $barang->brg_gr_kode,
                    'Satuan' => $barang->brg_satuan,
                    'Kategori' => $barang->brg_ktg_kode,
                    'Merk' => $barang->brg_merk,
                    'Gudang' => $barang->brg_gdg_default,
                    'Pemasok' => $barang->brg_sup_kode,
                    'PemasokNama' => $barang->sup_nama ?? '',
                    'IsAktif' => $barang->brg_isaktif ?? 1,
                    'IsStok' => $barang->brg_isstok ?? 1,
                    'IsExpired' => $barang->brg_isexpired ?? 0,
                    'Product_Focus' => $barang->brg_isproductfocus ?? 0,
                    'HargaBeli' => (float) ($barang->brg_hrgbeli ?? 0),
                    'HargaJual' => (float) ($barang->brg_hrgjual ?? 0),
                    'HET' => (float) ($barang->brg_harga_min ?? 0),
                    'MinStok' => (int) ($barang->brg_MIN_STOK ?? 0),
                    'MaxStok' => (int) ($barang->brg_MAX_STOK ?? 0),
                    'DiscSalesman' => (float) ($barang->brg_disc_sales ?? 0),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/v1/barang/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            DB::connection('mysql')->beginTransaction();

            $exists = DB::connection('mysql')->table('tbarang')->where('brg_kode', $id)->exists();
            if (!$exists) {
                return response()->json(['success' => false, 'message' => 'Barang tidak ditemukan'], 404);
            }

            DB::connection('mysql')->table('tbarang')->where('brg_kode', $id)->update([
                'brg_nama' => $request->Nama,
                'brg_satuan' => $request->Satuan,
                'brg_gr_kode' => $request->Tipe,
                'brg_ktg_kode' => $request->Kategori,
                'brg_merk' => $request->Merk,
                'brg_gdg_default' => $request->Gudang,
                'brg_sup_kode' => $request->Pemasok,
                'brg_isaktif' => $request->IsAktif ?? 1,
                'brg_isstok' => $request->IsStok ?? 1,
                'brg_isexpired' => $request->IsExpired ?? 0,
                'brg_isproductfocus' => $request->Product_Focus ?? 0,
                'brg_hrgbeli' => $request->HargaBeli ?? 0,
                'brg_hrgjual' => $request->HargaJual ?? 0,
                'brg_harga_min' => $request->HET ?? 0,
                'brg_min_stok' => $request->MinStok ?? 0,
                'brg_max_stok' => $request->MaxStok ?? 0,
                'brg_disc_sales' => $request->DiscSalesman ?? 0,
                'date_modified' => now(),
                'user_modified' => 'admin'
            ]);

            DB::connection('mysql')->table('thargajualjenis')->where('hjj_brg_kode', $id)->delete();

            if ($request->HargaKhusus && is_array($request->HargaKhusus)) {
                foreach ($request->HargaKhusus as $item) {
                    if ($item['hargajual'] > 0) {
                        DB::connection('mysql')->table('thargajualjenis')->insert([
                            'hjj_brg_kode' => $id,
                            'hjj_jc_kode' => $item['kode'],
                            'hjj_hargajual' => $item['hargajual']
                        ]);
                    }
                }
            }

            DB::connection('mysql')->commit();

            return response()->json(['success' => true, 'message' => 'Barang berhasil diupdate']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengupdate: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/v1/barang/{id}
     */
    public function destroy($id)
    {
        try {
            DB::connection('mysql')->beginTransaction();

            DB::connection('mysql')->table('thargajualjenis')->where('hjj_brg_kode', $id)->delete();

            $deleted = DB::connection('mysql')->table('tbarang')->where('brg_kode', $id)->delete();
            if (!$deleted) {
                DB::connection('mysql')->rollBack();
                return response()->json(['success' => false, 'message' => 'Barang tidak ditemukan'], 404);
            }

            DB::connection('mysql')->commit();
            return response()->json(['success' => true, 'message' => 'Barang berhasil dihapus']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/barang/all-detail-stok
     */
    public function allDetailStok()
    {
        try {
            $details = DB::connection('mysql')
                ->table('tmasterstok as ms')
                ->select([
                    'ms.mst_brg_kode as Kode',
                    'ms.mst_gdg_kode as KD_Gudang',
                    'g.gdg_nama as Gudang',
                    'ms.mst_expired_date as Expired',
                    DB::raw('SUM(ms.mst_stok_in - ms.mst_stok_out) as Stok')
                ])
                ->join('tgudang as g', 'g.gdg_kode', '=', 'ms.mst_gdg_kode')
                ->groupBy('ms.mst_brg_kode', 'ms.mst_gdg_kode', 'g.gdg_nama', 'ms.mst_expired_date')
                ->orderBy('ms.mst_brg_kode')->orderBy('g.gdg_nama')->orderBy('ms.mst_expired_date')
                ->get()
                ->map(function ($item) {
                    $item->Expired = $item->Expired ? date('d/m/Y', strtotime($item->Expired)) : '-';
                    $item->Stok = (float) $item->Stok;
                    return $item;
                });

            return response()->json(['success' => true, 'data' => $details]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil detail stok: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/barang/form-options
     */
    public function formOptions()
    {
        try {
            $tipe = DB::connection('mysql')->table('tgroup')->select('gr_kode as kode', 'gr_nama as nama')->orderBy('gr_nama')->get();
            $kategori = DB::connection('mysql')->table('tkategori')->select('ktg_kode as kode', 'ktg_nama as nama')->orderBy('ktg_nama')->get();
            $gudang = DB::connection('mysql')->table('tgudang')->select('gdg_kode as kode', 'gdg_nama as nama')->orderBy('gdg_nama')->get();
            $supplier = DB::connection('mysql')->table('tsupplier')->select('sup_kode as kode', 'sup_nama as nama')->orderBy('sup_nama')->get();

            return response()->json([
                'success' => true,
                'data' => ['tipe' => $tipe, 'kategori' => $kategori, 'gudang' => $gudang, 'supplier' => $supplier]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil form options: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/barang/{kode}/harga-khusus
     */
    public function hargaKhusus($kode)
    {
        try {
            $data = DB::connection('mysql')
                ->table('tjeniscustomer as jc')
                ->select(['jc.jc_kode as kode', 'jc.jc_nama as nama', DB::raw('IFNULL(hjj.hjj_hargajual, 0) as hargajual')])
                ->leftJoin('thargajualjenis as hjj', function ($join) use ($kode) {
                    $join->on('hjj.hjj_jc_kode', '=', 'jc.jc_kode')->where('hjj.hjj_brg_kode', '=', $kode);
                })
                ->orderBy('jc.jc_nama')->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil harga khusus: ' . $e->getMessage()], 500);
        }
    }
}