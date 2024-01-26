<?php

namespace App\Http\Controllers;

use App\Models\Antrian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AntrianController extends Controller
{
    public function generateToken(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e], 401);
        }

        return response()->json([
            'response' => ['token' => $token],
            'metadata' => ['message' => 'success', 'code' => 200],
        ]);
    }

    public function showAntrianStatus($kode_poli, $tanggalperiksa)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $antrianStatus = Antrian::where('kodepoli', $kode_poli)
            ->where('tglpriksa', $tanggalperiksa)
            ->get();

        $totalAntrean = $antrianStatus->count();
        $sisaAntrean = $antrianStatus->where('statusdipanggil', 0)->count();
        $antreanPanggil = $antrianStatus->where('statusdipanggil', 1)->first();

        $response = [
            'namapoli' => $antrianStatus->isEmpty() ? '' : $antrianStatus->first()->namapoli,
            'totalantrean' => $totalAntrean,
            'sisaantrean' => $sisaAntrean,
            'antreanpanggil' => $antreanPanggil ? $antreanPanggil->nomorantrean : '',
            'keterangan' => '',
        ];

        return response()->json([
            'response' => $response,
            'metadata' => ['message' => 'success', 'code' => 200],
        ]);
    }

    public function getAntrian(Request $request)
    {
        $dataPoli = [
            'Poli Kandungan'
        ];
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'nomorkartu' => 'required|string',
            'nik' => 'required|string',
            'kodepoli' => 'required|string',
            'tanggalperiksa' => 'required|date',
            'keluhan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'response' => null,
                'metadata' => ['message' => 'Bad Request', 'code' => 400],
            ], 400);
        }

        $isPasienBaru = !Antrian::where('nomorkartu', $request->nomorkartu)->exists();

        $nextAngkaAntrean = Antrian::where('kodepoli', $request->kodepoli)
            ->where('tglpriksa', $request->tanggalperiksa)
            ->max('angkaantrean') + 1;

        // Create a new antrian
        $newAntrian = Antrian::insert([
            'nomorkartu' => $request->nomorkartu,
            'nik' => $request->nik,
            'kodepoli' => $request->kodepoli,
            'tglpriksa' => $request->tanggalperiksa,
            'keluhan' => $request->keluhan,
            'angkaantrean' => $nextAngkaAntrean,
            'namapoli' => $dataPoli[0],
            'statusdipanggil' => 0,
        ]);

        $response = [
            'nomorantrean' => 'A' . $nextAngkaAntrean,
            'angkaantrean' => $nextAngkaAntrean,
            'namapoli' => $dataPoli[0],
            'sisaantrean' => $this->getSisaAntrean($request->kodepoli, $request->tanggalperiksa),
            'antreanpanggil' => '',
            'keterangan' => $isPasienBaru ? 'Pasien baru, harap mengambil antrean kembali.' : '',
        ];

        return response()->json([
            'response' => $response,
            'metadata' => ['message' => 'success', 'code' => 200],
        ]);
    }

    private function getSisaAntrean($kodepoli, $tanggalperiksa)
    {
        $totalAntrean = Antrian::where('kodepoli', $kodepoli)
            ->where('tglpriksa', $tanggalperiksa)
            ->count();

        $sisaAntrean = Antrian::where('kodepoli', $kodepoli)
            ->where('tglpriksa', $tanggalperiksa)
            ->where('statusdipanggil', 0)
            ->count();

        return $totalAntrean - $sisaAntrean;
    }

    public function getSisaAntreanPeserta(Request $request, $nomorkartu_jkn, $kode_poli, $tanggalperiksa)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(compact('nomorkartu_jkn', 'kode_poli', 'tanggalperiksa'), [
            'nomorkartu_jkn' => 'required|string',
            'kode_poli' => 'required|string',
            'tanggalperiksa' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'response' => null,
                'metadata' => ['message' => 'Bad Request', 'code' => 400],
            ], 400);
        }

        $antrianStatus = Antrian::where('nomorkartu', $nomorkartu_jkn)
            ->where('kodepoli', $kode_poli)
            ->where('tglpriksa', $tanggalperiksa)
            ->first();

        if (!$antrianStatus) {
            return response()->json([
                'response' => null,
                'metadata' => ['message' => 'Antrian not found', 'code' => 201],
            ], 201);
        }

        $response = [
            'nomorantrean' => 'A' . $antrianStatus->angkaantrean,
            'namapoli' => $antrianStatus->namapoli,
            'sisaantrean' => $this->getSisaAntrean($kode_poli, $tanggalperiksa),
            'antreanpanggil' => $antrianStatus->statusdipanggil === 1 ? 'A' . $antrianStatus->angkaantrean : '',
            'keterangan' => '',
        ];

        return response()->json([
            'response' => $response,
            'metadata' => ['message' => 'success', 'code' => 200],
        ]);
    }

    public function cancelAntrian(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'nomorkartu' => 'required|string',
            'kodepoli' => 'required|string',
            'tanggalperiksa' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'metadata' => ['message' => 'Bad Request', 'code' => 400],
            ], 400);
        }

        $deletedAntrian = Antrian::where('nomorkartu', $request->nomorkartu)
            ->where('kodepoli', $request->kodepoli)
            ->where('tglpriksa', $request->tanggalperiksa)
            ->delete();

        if ($deletedAntrian) {
            return response()->json([
                'metadata' => ['message' => 'success', 'code' => 200],
            ]);
        } else {
            return response()->json([
                'metadata' => ['message' => 'Antrian not found', 'code' => 201],
            ], 201);
        }
    }
}
