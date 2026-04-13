<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoDetail extends Model
{
    protected $table = 'tdo_dtl';
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = [
        'dod_do_nomor', 'dod_brg_kode', 'dod_brg_satuan', 'dod_qty',
        'dod_tgl_expired', 'dod_nourut', 'dod_status', 'dod_gdg_kode', 'dod_idbatch'
    ];
    
    public function header()
    {
        return $this->belongsTo(DoHeader::class, 'dod_do_nomor', 'do_nomor');
    }
}
