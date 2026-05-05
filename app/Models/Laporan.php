<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    protected $table = 'laporan';

    protected $fillable = [
        'user_id',
        'judul',
        'ada_jentik',
        'tanggal',
        'latitude',
        'longitude',
        'alamat',
        'gambar',
        'status',
        'catatan_petugas',
    ];

    protected $casts = [
        'ada_jentik' => 'boolean',
        'tanggal' => 'date',
    ];
}