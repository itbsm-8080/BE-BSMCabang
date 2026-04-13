<?php

namespace App\Models\Pusat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cabang extends Model
{
    protected $connection = 'pusat';
    protected $table = 'tcabang';
    protected $primaryKey = 'cbg_kode';
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = ['cbg_kode', 'cbg_nama', 'cbg_database'];

}
