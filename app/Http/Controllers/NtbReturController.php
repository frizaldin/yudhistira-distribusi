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
     * Daftar NTB Retur — dialihkan ke halaman NTB bersatu dengan filter retur.
     */
    public function index(Request $request)
    {
        return redirect()->route('ntb.index', array_filter([
            'search' => $request->get('search'),
            'type' => 'retur',
        ], fn ($v) => $v !== null && $v !== ''));
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
