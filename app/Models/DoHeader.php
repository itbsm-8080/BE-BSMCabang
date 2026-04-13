<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoHeader extends Model
{
    protected $table = 'tdo_hdr';
    protected $primaryKey = 'do_nomor';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    
    protected $fillable = [
        'do_nomor', 'do_tanggal', 'do_so_nomor', 'do_cus_kode',
        'do_shipaddress', 'do_memo', 'do_gdg_kode', 'do_isinvoice',
        'date_create', 'user_create', 'date_modified', 'user_modified',
        'do_isclosed', 'do_iskembali', 'do_isecer'
    ];
    
    public function details()
    {
        return $this->hasMany(DoDetail::class, 'dod_do_nomor', 'do_nomor');
    }
}
