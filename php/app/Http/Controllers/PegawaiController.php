<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PegawaiController extends Controller
{
    /**
     * Ambil data pegawai milik user yang sedang login.
     * Dicocokkan lewat kolom 'nip' di tabel users <-> 'nip' di tabel m_peg.
     */
    public function me(Request $request)
    {
        $user = Auth::user();

        if (empty($user->nip)) {
            return response()->json([
                'message' => 'Akun Anda belum terhubung dengan NIP. Silakan lengkapi profil terlebih dahulu.',
            ], 422);
        }

        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (!$pegawai) {
            return response()->json([
                'message' => 'Data pegawai dengan NIP tersebut tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => $pegawai,
        ]);
    }
}