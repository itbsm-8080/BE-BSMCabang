<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'tbarang';
    protected $primaryKey = 'brg_kode';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    
    protected $fillable = [
        'brg_kode', 'brg_nama', 'brg_satuan', 'brg_ktg_kode', 
        'brg_gr_kode', 'brg_hrgjual', 'brg_min_stok', 
        'brg_disc_sales', 'brg_merk', 'brg_isproductfocus', 
        'brg_tanggal_in'
    ];
    
    // Relasi ke Kategori
    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'brg_ktg_kode', 'ktg_kode');
    }
    
    // Relasi ke Group
    public function group()
    {
        return $this->belongsTo(Group::class, 'brg_gr_kode', 'gr_kode');
    }
    
    // Relasi ke MasterStok
    public function masterStok()
    {
        return $this->hasMany(MasterStok::class, 'mst_brg_kode', 'brg_kode');
    }
    
    // Relasi ke FpDetail
    public function fpDetails()
    {
        return $this->hasMany(FpDetail::class, 'FPd_brg_kode', 'brg_kode');
    }
}
