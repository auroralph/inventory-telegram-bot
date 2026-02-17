<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_transaksis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barang_id')
                  ->constrained('barangs')
                  ->onDelete('cascade');

            $table->enum('jenis', ['masuk', 'keluar']);
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_transaksis');
    }
};