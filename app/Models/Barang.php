<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $fillable = [
        'jenis_barang',
        'merk',
        'nama_product',
        'stok',
        'satuan',
        'lokasi'
    ];

    // ✅ RELASI KE LOG TRANSAKSI
    public function logTransaksis()
    {
        return $this->hasMany(LogTransaksi::class, 'barang_id');
    }
}