<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterStok extends Model
{
    protected $table = 'tmasterstok';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = [
        'mst_brg_kode', 'mst_gdg_kode', 'mst_stok_in', 
        'mst_stok_out', 'mst_noreferensi', 'mst_tanggal'
    ];
    
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'mst_brg_kode', 'brg_kode');
    }
}
