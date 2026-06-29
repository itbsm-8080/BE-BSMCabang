<?php
namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListJurnalController extends Controller
{
    /**
     * GET /api/report/list-jurnal
     */
    public function index(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-01'));
            $endDate = $request->query('end_date', date('Y-m-d'));
            $additionalFilters = $request->query('filters');
            $filters = $additionalFilters ? json_decode($additionalFilters, true) : [];
            
            $sql = "
                SELECT 
                    MONTH(Tanggal) AS Bulan,
                    YEAR(Tanggal) AS Tahun,
                    Tanggal,
                    Nomor,
                    Referensi,
                    Account,
                    AccountName,
                    Keterangan,
                    Debet,
                    Kredit,
                    Kelompok,
                    CostCenter,
                    Customer,
                    nopol,
                    ekspedisi
                FROM alljurnal
                WHERE tanggal BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            // Additional filters
            if (!empty($filters['Account'])) {
                $sql .= " AND Account = ?";
                $params[] = $filters['Account'];
            }
            if (!empty($filters['Kelompok'])) {
                $sql .= " AND Kelompok = ?";
                $params[] = $filters['Kelompok'];
            }
            if (!empty($filters['CostCenter'])) {
                $sql .= " AND CostCenter LIKE ?";
                $params[] = "%{$filters['CostCenter']}%";
            }
            
            $sql .= " ORDER BY Nomor, Tanggal";
            
            $data = DB::select($sql, $params);
            
            $totalDebet = array_sum(array_column($data, 'Debet'));
            $totalKredit = array_sum(array_column($data, 'Kredit'));
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => [
                    'total_debet' => $totalDebet,
                    'total_kredit' => $totalKredit,
                    'total_data' => count($data)
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
     * GET /api/report/list-jurnal/filters
     */
    public function filters()
    {
        try {
            $accounts = DB::select("SELECT DISTINCT Account as value, AccountName as label FROM alljurnal ORDER BY Account");
            $kelompok = DB::select("SELECT DISTINCT Kelompok as value, Kelompok as label FROM alljurnal WHERE Kelompok IS NOT NULL ORDER BY Kelompok");
            $costCenters = DB::select("SELECT DISTINCT CostCenter as value, CostCenter as label FROM alljurnal WHERE CostCenter IS NOT NULL ORDER BY CostCenter");
            
            return response()->json([
                'success' => true,
                'data' => [
                    'accounts' => $accounts,
                    'kelompok' => $kelompok,
                    'cost_centers' => $costCenters,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}