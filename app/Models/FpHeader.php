<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FpHeader extends Model
{
    protected $table = 'tfp_hdr';
    protected $primaryKey = 'FP_nomor';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    
    protected $fillable = ['FP_nomor', 'FP_tanggal', 'FP_DO_nomor'];
    
    public function details()
    {
        return $this->hasMany(FpDetail::class, 'FPd_FP_nomor', 'FP_nomor');
    }
}
