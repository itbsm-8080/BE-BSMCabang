<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kategori extends Model
{
    protected $table = 'tkategori';
    protected $primaryKey = 'ktg_kode';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    
    public function barangs()
    {
        return $this->hasMany(Barang::class, 'brg_ktg_kode', 'ktg_kode');
    }
}
