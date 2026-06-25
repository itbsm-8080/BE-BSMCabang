<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    // ========== MENU PARENT ==========
     public function parentIndex()
    {
        $data = DB::connection('mysql')->table('tmenuparent')
            ->orderBy('mp_order')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function parentStore(Request $request)
    {
        $id = DB::connection('mysql')->table('tmenuparent')->insertGetId([
            'mp_nama' => $request->mp_nama,
            'mp_icon' => $request->mp_icon ?? 'pi pi-folder',
            'mp_order' => $request->mp_order ?? 0,
            'mp_aktif' => 1,
        ]);
        return response()->json(['success' => true, 'data' => ['mp_id' => $id]]);
    }

   public function parentUpdate(Request $request, $id)
    {
        DB::connection('mysql')->table('tmenuparent')->where('mp_id', $id)->update([
            'mp_nama' => $request->mp_nama,
            'mp_icon' => $request->mp_icon,
            'mp_order' => $request->mp_order,
        ]);
        return response()->json(['success' => true]);
    }

    public function parentDestroy($id)
    {
        DB::connection('mysql')->table('tmenuparent')->where('mp_id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ========== MENU ITEMS ==========
     public function index()
    {
        $data = DB::connection('mysql')->table('tmenu')
            ->leftJoin('tmenuparent', 'mp_id', '=', 'men_parent_id')
            ->select('tmenu.*', 'mp_nama as parent_nama')
            ->orderBy('men_order')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $id = DB::connection('mysql')->table('tmenu')->insertGetId([
            'MEN_NAMA' => $request->MEN_NAMA,
            'MEN_NAMA2' => $request->MEN_NAMA2,
            'men_icon' => $request->men_icon ?? 'pi pi-circle',
            'men_route' => $request->men_route,
            'men_parent_id' => $request->men_parent_id ?? 0,
            'men_order' => $request->men_order ?? 0,
        ]);
        return response()->json(['success' => true, 'data' => ['MEN_ID' => $id]]);
    }

    public function update(Request $request, $id)
    {
        DB::connection('mysql')->table('tmenu')->where('MEN_ID', $id)->update([
            'MEN_NAMA' => $request->MEN_NAMA,
            'MEN_NAMA2' => $request->MEN_NAMA2,
            'men_icon' => $request->men_icon,
            'men_route' => $request->men_route,
            'men_parent_id' => $request->men_parent_id,
            'men_order' => $request->men_order,
        ]);
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::connection('mysql')->table('tmenu')->where('MEN_ID', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ========== USER MENU ==========
public function userMenu($kode)
{
    // Ambil menu IDs dari thakuser
    $menuIds = DB::connection('mysql')->table('thakuser')
        ->where('HAK_USER_KODE', $kode)
        ->pluck('HAK_MEN_ID')->toArray();

    // 🔥 Kalau tidak ada hak sama sekali, return kosong
    if (empty($menuIds)) {
        return response()->json(['success' => true, 'data' => []]);
    }

    // Ambil parent IDs dari menu yang diizinkan
    $allowedParentIds = DB::connection('mysql')->table('tmenu')
        ->whereIn('MEN_ID', $menuIds)
        ->pluck('men_parent_id')
        ->unique()
        ->toArray();

    // Ambil parent yang diizinkan
    $parents = DB::connection('mysql')->table('tmenuparent')
        ->whereIn('mp_id', $allowedParentIds)
        ->where('mp_aktif', 1)
        ->orderBy('mp_order')
        ->get();

    $tree = [];
    foreach ($parents as $parent) {
        $children = DB::connection('mysql')->table('tmenu')
            ->where('men_parent_id', $parent->mp_id)
            ->whereIn('MEN_ID', $menuIds)
            ->orderBy('men_order')
            ->get()
            ->map(function($item) {
                return [
                    'label' => $item->MEN_NAMA2 ?? $item->MEN_NAMA,
                    'icon' => $item->men_icon ?? 'pi pi-circle',
                    'to' => $item->men_route,
                    'tabTitle' => $item->MEN_NAMA2 ?? $item->MEN_NAMA,
                    'tabIcon' => $item->men_icon ?? 'pi pi-circle',
                    'closable' => true,
                ];
            })->toArray();

        if (!empty($children)) {
            $tree[] = [
                'label' => $parent->mp_nama,
                'icon' => $parent->mp_icon,
                'items' => $children,
            ];
        }
    }

    // 🔥 Tambahkan menu yang tidak punya parent (men_parent_id = 0 atau null)
    $orphanMenus = DB::connection('mysql')->table('tmenu')
        ->whereIn('MEN_ID', $menuIds)
        ->where(function($q) {
            $q->where('men_parent_id', 0)
              ->orWhereNull('men_parent_id');
        })
        ->whereNotIn('men_parent_id', $allowedParentIds)
        ->orderBy('men_order')
        ->get()
        ->map(function($item) {
    return [
        'label' => $item->MEN_NAMA2 ?? $item->MEN_NAMA,
        'icon' => $item->men_icon ?? 'pi pi-circle',
        'to' => $item->men_route,
        'tabTitle' => $item->MEN_NAMA2 ?? $item->MEN_NAMA,
        'tabIcon' => $item->men_icon ?? 'pi pi-circle',
        'closable' => true,
    ];
})->toArray();

    // Gabungkan orphan menu ke tree
    foreach ($orphanMenus as $menu) {
        $tree[] = $menu;
    }

    return response()->json(['success' => true, 'data' => $tree]);
}

    // ========== HAK USER ==========
    public function hakUser($kode)
    {
        $hak = DB::connection('mysql')->table('thakuser')
            ->where('HAK_USER_KODE', $kode)
            ->get();
        return response()->json(['success' => true, 'data' => $hak]);
    }

    public function saveHakUser(Request $request)
    {
        $kode = $request->HAK_USER_KODE;
        $items = $request->items;

        DB::connection('mysql')->beginTransaction();
        try {
            DB::connection('mysql')->table('thakuser')->where('HAK_USER_KODE', $kode)->delete();
            foreach ($items as $item) {
                DB::connection('mysql')->table('thakuser')->insert([
                    'HAK_USER_KODE' => $kode,
                    'HAK_MEN_ID' => $item['HAK_MEN_ID'],
                    'hak_men_insert' => $item['hak_men_insert'] ?? '0',
                    'hak_men_edit' => $item['hak_men_edit'] ?? '0',
                    'hak_men_delete' => $item['hak_men_delete'] ?? '0',
                ]);
            }
            DB::connection('mysql')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}