<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {Schema::create('laporan', function (Blueprint $table) {
        $table->id();
        $table->string('judul');
        $table->boolean('ada_jentik')->default(false);
        $table->date('tanggal');
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->string('alamat')->nullable();
        $table->string('gambar')->nullable();
        $table->enum('status_kader', ['Menunggu','Diproses','Selesai','Ditolak'])->default('Menunggu');
        $table->enum('status_petugas', ['Menunggu','Diproses','Selesai','Ditolak'])->default('Menunggu');
        $table->enum('prioritas', ['Tinggi','Sedang','Rendah'])->default('Sedang');
        $table->string('kader')->nullable();
        $table->text('catatan_petugas')->nullable();
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};