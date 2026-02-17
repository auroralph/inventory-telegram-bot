<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogTransaksi extends Model
{
    use HasFactory;

    protected $table = 'log_transaksis';

    protected $fillable = [
        'barang_id',
        'jenis',
        'jumlah',
        'stok_sebelum',
        'stok_sesudah'
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id', 'id');
    }
}
