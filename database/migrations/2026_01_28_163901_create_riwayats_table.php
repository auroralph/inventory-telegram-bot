<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayats', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_barang');
            $table->string('nama_product');
            $table->enum('tipe', ['MASUK', 'KELUAR']);
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayats');
    }
};
