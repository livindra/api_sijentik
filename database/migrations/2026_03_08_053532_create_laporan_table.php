<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->boolean('ada_jentik');
            $table->date('tanggal');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('alamat')->nullable();
            $table->string('gambar')->nullable();
            $table->enum('status', ['proses','diterima','ditolak'])->default('proses');
            $table->text('catatan_petugas')->nullable();

            $table->timestamps();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('verifikator_id')->nullable();

            $table->index('user_id', 'fk_laporan_user');
            $table->index('verifikator_id', 'fk_verifikator');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('verifikator_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['verifikator_id']);
        });

        Schema::dropIfExists('laporan');
    }
};