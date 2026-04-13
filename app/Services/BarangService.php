<?php

namespace App\Services;

use App\Models\Barang;
use Illuminate\Support\Facades\DB;

class BarangService
{
    public function getMasterBarang($search = null, $perPage = 15)
    {
        $query = DB::table('tbarang as b')
            ->select([
                'b.brg_kode as Kode',
                'b.brg_nama as Nama',
                'b.brg_satuan as Satuan',
                'kt.ktg_nama as Kategori',
                'g.gr_nama as Tipe',
                'b.brg_hrgjual as HargaJual',
                'b.brg_min_stok as Min',
                'b.brg_disc_sales as Disc_Salesman',
                'b.brg_merk as Merk',
                'b.brg_isproductfocus as Product_Focus',
                'b.brg_tanggal_in as IN_Terakhir',
                DB::raw("(
                    SELECT SUM(mst_stok_in - mst_stok_out)
                    FROM tmasterstok
                    WHERE mst_brg_kode = b.brg_kode
                ) as Stok"),
                DB::raw("(
                    SELECT SUM(mst_stok_in - mst_stok_out)
                    FROM tmasterstok
                    WHERE mst_brg_kode = b.brg_kode
                    AND mst_gdg_kode = 'WH-01'
                ) as Stok_Baik"),
                DB::raw("(
                    SELECT mst_tanggal
                    FROM tmasterstok
                    WHERE mst_brg_kode = b.brg_kode
                    AND mst_noreferensi LIKE '%DO%'
                    ORDER BY mst_tanggal DESC
                    LIMIT 1
                ) as Last_Sale"),
                DB::raw("(
                    SELECT IFNULL(SUM(fpd_qty), 0) / 3
                    FROM tfp_dtl
                    INNER JOIN tfp_hdr ON fp_nomor = FPd_FP_nomor
                    WHERE FPd_brg_kode = b.brg_kode
                    AND fp_tanggal >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ) as Avgs_Sales_per_Bulan")
            ])
            ->join('tkategori as kt', 'b.brg_ktg_kode', '=', 'kt.ktg_kode')
            ->join('tgroup as g', 'b.brg_gr_kode', '=', 'g.gr_kode');
        
        // Filter search
        if ($search) {
            $query->where('b.brg_nama', 'LIKE', "%{$search}%");
        }
        
        // Ambil data
        $barangs = $query->paginate($perPage);
        
        // Hitung TOR untuk setiap item
        $barangs->getCollection()->transform(function ($item) {
            $avgSales = $item->Avgs_Sales_per_Bulan ?? 0;
            $item->TOR = $avgSales > 0 ? round($item->Stok_Baik / $avgSales, 2) : 0;
            return $item;
        });
        
        return $barangs;
    }
    
    public function getBarangByKode($kode)
    {
        return Barang::with(['kategori', 'group'])->find($kode);
    }
}