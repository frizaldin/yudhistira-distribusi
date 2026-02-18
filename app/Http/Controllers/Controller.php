<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Mengembalikan kode cabang untuk filter data berdasarkan user yang login.
     * - authority_id 1 (superadmin): null = tidak filter, tampil semua.
     * - authority_id 2 (cabang): array 1 elemen [branch_code] user.
     * - authority_id 3 (ADP): array branch (otoritas cabang) user.
     */
    protected function getBranchFilterForCurrentUser(): ?array
    {
        if (!Auth::check()) {
            return null;
        }
        $user = Auth::user();
        $role = $user->authority_id ?? 1;
        if ($role === 2) {
            $code = $user->branch_code ?? null;
            return $code !== null && $code !== '' ? [$code] : null;
        }
        if ($role === 3) {
            $branch = $user->branch ?? [];
            return is_array($branch) && count($branch) > 0 ? array_values($branch) : [];
        }
        return null;
    }
}
