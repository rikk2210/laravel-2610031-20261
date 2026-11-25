<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private ProfilePhotoService $photoService)
    {
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'no_hp' => 'required|string|max:20',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'no_hp' => $validated['no_hp'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi saat ini yang Anda masukkan salah.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui.',
        ], 200);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'foto' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ], [
            'foto.required' => 'Silakan pilih foto profil terlebih dahulu.',
            'foto.image' => 'File yang diunggah harus berupa gambar.',
            'foto.mimes' => 'Ekstensi gambar hanya mendukung JPG, JPEG, PNG, atau WebP.',
            'foto.mimetypes' => 'Tipe MIME file tidak diizinkan oleh server.',
            'foto.max' => 'Ukuran foto maksimal adalah 5 MB.',
        ]);

        $user = $request->user();

        try {
            $relativePath = $this->photoService->storeAndOptimize(
                $request->file('foto'),
                $user
            );

            $user->update([
                'foto' => $relativePath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diunggah dan dioptimasi.',
                'user' => $user->fresh(),
                'foto_url' => $request->getSchemeAndHttpHost().'/storage/'.$relativePath,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal memproses foto profil.',
            ], 422);
        }
    }
}
