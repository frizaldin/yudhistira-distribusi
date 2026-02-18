<?php

namespace App\Http\Controllers;

use App\Models\CutoffData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NppbWarehouseController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/nppb-warehouse');
        $this->title = 'Rencana Kirim Cabang Area';

        if (Auth::check()) {
            $this->role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($this->role) {
                1 => 'superadmin',
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    /**
     * Display NPPB Warehouse page (branches filtered by distinct warehouse_code)
     */
    public function index(Request $request)
    {
        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'activeCutoff' => CutoffData::where('status', 'active')->first(),
        ];

        return view($this->callbackfolder . '.master-data.nppb-warehouse.index', $data);
    }
}
