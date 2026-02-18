<?php

namespace App\Http\Controllers;

use App\Models\NppbCentral;
use App\Models\Branch;
use App\Models\CutoffData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NppbCentralController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/nppb-central');
        $this->title = 'Rencana Kirim (NPPB Pusat Ciawi)';

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
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'activeCutoff' => CutoffData::where('status', 'active')->first()
        ];

        return view($this->callbackfolder . '.master-data.nppb-central.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $branchesQuery = Branch::orderBy('branch_name');
        if ($filteredBranchCodes !== null) {
            $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $branches = $branchesQuery->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'branches' => $branches,
        ];

        return view($this->callbackfolder . '.master-data.nppb-central.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_code' => 'required|string|max:100',
                'branch_name' => 'nullable|string',
                'book_code' => 'required|string|max:100',
                'book_name' => 'nullable|string',
                'koli' => 'nullable|numeric|min:0',
                'pls' => 'nullable|numeric|min:0',
                'exp' => 'nullable|numeric|min:0',
                'date' => 'required|date',
            ]);

            // Jika branch_name tidak diisi, ambil dari Branch
            if (empty($validated['branch_name'])) {
                $branch = Branch::where('branch_code', $validated['branch_code'])->first();
                if ($branch) {
                    $validated['branch_name'] = $branch->branch_name;
                }
            }

            NppbCentral::create($validated);

            return redirect()->route('nppb-central.index')
                ->with('success', 'Data rencana kirim berhasil ditambahkan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('NppbCentral Store Error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Gagal menambahkan data rencana kirim: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $nppbCentral = NppbCentral::findOrFail($id);
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $branchesQuery = Branch::orderBy('branch_name');
        if ($filteredBranchCodes !== null) {
            $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $branches = $branchesQuery->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'nppbCentral' => $nppbCentral,
            'branches' => $branches,
        ];

        return view($this->callbackfolder . '.master-data.nppb-central.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $nppbCentral = NppbCentral::findOrFail($id);
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            if ($filteredBranchCodes !== null && !in_array($nppbCentral->branch_code, $filteredBranchCodes)) {
                return redirect()->route('nppb-central.index')
                    ->with('error', 'Anda tidak memiliki wewenang untuk cabang ini.');
            }

            $validated = $request->validate([
                'branch_code' => 'required|string|max:100',
                'branch_name' => 'nullable|string',
                'book_code' => 'required|string|max:100',
                'book_name' => 'nullable|string',
                'koli' => 'nullable|numeric|min:0',
                'pls' => 'nullable|numeric|min:0',
                'exp' => 'nullable|numeric|min:0',
                'date' => 'required|date',
            ]);

            // Jika branch_name tidak diisi, ambil dari Branch
            if (empty($validated['branch_name'])) {
                $branch = Branch::where('branch_code', $validated['branch_code'])->first();
                if ($branch) {
                    $validated['branch_name'] = $branch->branch_name;
                }
            }

            $nppbCentral->update($validated);

            return redirect()->route('nppb-central.index')
                ->with('success', 'Data rencana kirim berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('NppbCentral Update Error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Gagal memperbarui data rencana kirim: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $nppbCentral = NppbCentral::findOrFail($id);
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            if ($filteredBranchCodes !== null && !in_array($nppbCentral->branch_code, $filteredBranchCodes)) {
                return redirect()->back()->with('error', 'Anda tidak memiliki wewenang untuk cabang ini.');
            }
            $nppbCentral->delete();

            return redirect()->back()->with('success', 'Data rencana kirim berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('NppbCentral Delete Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data rencana kirim.');
        }
    }
}
