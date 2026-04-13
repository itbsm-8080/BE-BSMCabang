<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'tgroup';
    protected $primaryKey = 'gr_kode';
    public $incrementing = true;
    public $timestamps = false;
    
    public function barangs()
    {
        return $this->hasMany(Barang::class, 'brg_gr_kode', 'gr_kode');
    }
}
