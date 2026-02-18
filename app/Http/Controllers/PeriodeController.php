<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use App\Jobs\SynchronizePeriodesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PeriodeController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/period');
        $this->title = 'Master Data Periode';

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
        $periodes = Periode::query()
            ->with(['branch'])
            ->when($request->search, function ($query, $search) {
                return $query->where('period_code', 'like', '%' . $search . '%')
                    ->orWhere('period_name', 'like', '%' . $search . '%')
                    ->orWhere('branch_code', 'like', '%' . $search . '%')
                    ->orWhereHas('branch', function ($q) use ($search) {
                        $q->where('branch_name', 'like', '%' . $search . '%');
                    });
            })
            ->when($request->branch_code, function ($query, $branchCode) {
                return $query->where('branch_code', $branchCode);
            })
            ->orderBy('period_code')
            ->paginate(15);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'periodes' => $periodes,
        ];

        return view($this->callbackfolder . '.master-data.period.index', $data);
    }

    public function synchronize(Request $request)
    {
        try {
            Cache::forget('sync_periodes_progress');

            SynchronizePeriodesJob::dispatch()
                ->onQueue('default');

            Log::info('Periode synchronization job dispatched to queue');

            return redirect()->back()->with('success', 'Sinkronisasi data sedang diproses di background. Data akan disinkronkan secara bertahap. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil.');
        } catch (\Exception $e) {
            Log::error('Synchronize Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error sinkronisasi: ' . $e->getMessage());
        }
    }

    public function clearAndSync(Request $request)
    {
        try {
            Cache::forget('sync_periodes_progress');

            $deletedCount = Periode::count();
            Periode::truncate();

            Log::info("Cleared {$deletedCount} periodes before synchronization");

            SynchronizePeriodesJob::dispatch()
                ->onQueue('default');

            Log::info('Periode clear and synchronization job dispatched to queue');

            return redirect()->back()->with('success', "Semua data periode ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
        } catch (\Exception $e) {
            Log::error('Clear and Sync Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error clear and sync: ' . $e->getMessage());
        }
    }
}
