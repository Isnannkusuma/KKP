<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    /**
     * Koneksi database yang dipakai (sesuai config/database.php)
     */
    protected $connection = 'pegawai';

    /**
     * Nama tabel di database siantar
     */
    protected $table = 'm_peg';

    /**
     * Primary key tabel ini bukan 'id' default Laravel
     */
    protected $primaryKey = 'id_m_pegawai';

    /**
     * Tabel ini tidak punya kolom created_at / updated_at
     */
    public $timestamps = false;

    /**
     * Kolom yang boleh diisi (jaga-jaga kalau nanti ada fitur create/update)
     */
    protected $fillable = [
        'id_peg_epegawai',
        'id_peg_siasn',
        'nip',
        'nama',
        'tempat_lahir',
        'tgl_lahir',
        'jk',
        'u_th',
        'jenis_peg',
        'tahun_bup',
        'image_name',
        'pangkat',
        'tmt_pangkat',
        'asn_jenjang_jabatan',
        'jabatan_nama',
        'tmt_jabatan',
        'nama_unor',
        'induk_unor_nama',
        'mk_tahun',
        'mk_bulan',
        'last_insert',
    ];
}
