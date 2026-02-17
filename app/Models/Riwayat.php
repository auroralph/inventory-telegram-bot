<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Riwayat extends Model
{
    use HasFactory;

    protected $table = 'riwayats';

    protected $fillable = [
        'jenis_barang',
        'nama_product',
        'tipe',
        'jumlah',
        'stok_sebelum',
        'stok_sesudah',
    ];
}