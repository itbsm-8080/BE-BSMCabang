<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FpDetail extends Model
{
    protected $table = 'tfp_dtl';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = [
        'FPd_FP_nomor', 'FPd_brg_kode', 'FPd_qty', 
        'FPd_harga', 'FPd_discpr'
    ];
    
    public function header()
    {
        return $this->belongsTo(FpHeader::class, 'FPd_FP_nomor', 'FP_nomor');
    }
    
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'FPd_brg_kode', 'brg_kode');
    }
}
