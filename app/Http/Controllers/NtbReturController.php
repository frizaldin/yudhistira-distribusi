<?php

namespace App\Http\Controllers;

use App\Models\ReceiveBookNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NtbReturController extends Controller
{
    protected $callbackfolder;

    public function __construct()
    {
        if (Auth::check()) {
            $role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($role) {
                1 => 'superadmin',
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    /**
     * Daftar NTB Retur
     */
    public function index(Request $request)
    {
        $query = ReceiveBookNote::query()
            ->where('receive_type', 1)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where(function ($q2) use ($s) {
                    $q2->where('nota_kirim_cab', 'like', '%' . $s . '%')
                        ->orWhere('receive_code', 'like', '%' . $s . '%');
                });
            })
            ->orderBy('retur_date', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.ntb-retur.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    /**
     * Detail NTB Retur
     */
    public function show($receive_code)
    {
        $ntb = ReceiveBookNote::with(['items'])
            ->where('receive_code', $receive_code)
            ->where('receive_type', 1)
            ->firstOrFail();

        return view($this->callbackfolder . '.ntb-retur.show', [
            'ntb' => $ntb,
        ]);
    }
}
