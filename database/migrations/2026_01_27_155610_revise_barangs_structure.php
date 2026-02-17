<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            // rename sesuai revisi instansi
            $table->renameColumn('kode_barang', 'jenis_barang');
            $table->renameColumn('nama_barang', 'nama_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->renameColumn('jenis_barang', 'kode_barang');
            $table->renameColumn('nama_product', 'nama_barang');
        });
    }
};
