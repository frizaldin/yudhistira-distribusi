<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Nkb;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeliveryOrderController extends Controller
{
    protected $base_url;
    protected $callbackfolder;

    public function __construct()
    {
        $this->base_url = url('/delivery-orders');
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

    public function index(Request $request)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $query = DeliveryOrder::with(['senderBranch', 'recipientBranch', 'creator:id,name'])
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where(function ($q2) use ($s) {
                    $q2->where('number', 'like', '%' . $s . '%')
                        ->orWhere('sender_code', 'like', '%' . $s . '%')
                        ->orWhere('recipient_code', 'like', '%' . $s . '%')
                        ->orWhere('expedition', 'like', '%' . $s . '%')
                        ->orWhere('driver', 'like', '%' . $s . '%');
                });
            })
            ->orderBy('id', 'desc');

        $perPage = (int) $request->get('per_page', 20);
        if (!in_array($perPage, [20, 50, 100])) {
            $perPage = 20;
        }
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.delivery-orders.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branches = Branch::orderBy('branch_code')->get(['branch_code', 'branch_name']);
        if ($filteredBranchCodes !== null) {
            $branches = $branches->whereIn('branch_code', $filteredBranchCodes);
        }

        $nkbs = Nkb::orderBy('id', 'desc')
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->get(['id', 'number', 'recipient_code']);

        return view($this->callbackfolder . '.delivery-orders.create', [
            'branches' => $branches,
            'nkbs' => $nkbs,
            'nextNumber' => DeliveryOrder::generateNextNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:200', Rule::unique('delivery_orders', 'number')],
            'sender_code' => ['required', 'string', 'max:100'],
            'recipient_code' => ['required', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'expedition' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'driver' => ['nullable', 'string', 'max:200'],
            'driver_phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nkb_id' => ['required', 'integer', 'exists:nkbs,id'],
            'items.*.koli' => ['required', 'numeric', 'min:0'],
            'items.*.ex' => ['required', 'numeric', 'min:0'],
            'items.*.total_ex' => ['required', 'numeric', 'min:0'],
        ]);

        $do = new DeliveryOrder();
        $do->number = $validated['number'];
        $do->sender_code = $validated['sender_code'];
        $do->recipient_code = $validated['recipient_code'];
        $do->date = $validated['date'] ?? null;
        $do->expedition = $validated['expedition'] ?? null;
        $do->plate_number = $validated['plate_number'] ?? null;
        $do->driver = $validated['driver'] ?? null;
        $do->driver_phone = $validated['driver_phone'] ?? '';
        $do->note = $validated['note'] ?? '';
        $do->created_by = Auth::id();
        $do->save();

        foreach ($validated['items'] as $row) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $do->id,
                'nkb_id' => (int) $row['nkb_id'],
                'koli' => $row['koli'],
                'ex' => $row['ex'],
                'total_ex' => $row['total_ex'],
            ]);
        }

        return redirect()->route('delivery-orders.index')->with('success', 'Surat Jalan berhasil dibuat.');
    }

    public function show($id)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $do = DeliveryOrder::with(['items.nkb.recipientBranch', 'senderBranch', 'recipientBranch', 'creator:id,name'])
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->findOrFail($id);

        return view($this->callbackfolder . '.delivery-orders.show', ['deliveryOrder' => $do]);
    }

    /**
     * Halaman cetak TANDA TERIMA SURAT JALAN BUKU.
     */
    public function print($id)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $do = DeliveryOrder::with(['items.nkb.recipientBranch', 'senderBranch', 'recipientBranch', 'creator:id,name'])
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->findOrFail($id);

        return view('pdf.delivery-order', ['deliveryOrder' => $do]);
    }

    public function edit($id)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $do = DeliveryOrder::with(['items.nkb'])
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->findOrFail($id);

        $branches = Branch::orderBy('branch_code')->get(['branch_code', 'branch_name']);
        if ($filteredBranchCodes !== null) {
            $branches = $branches->whereIn('branch_code', $filteredBranchCodes);
        }

        $nkbs = Nkb::orderBy('id', 'desc')
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->get(['id', 'number', 'recipient_code']);

        return view($this->callbackfolder . '.delivery-orders.edit', [
            'deliveryOrder' => $do,
            'branches' => $branches,
            'nkbs' => $nkbs,
        ]);
    }

    public function update(Request $request, $id)
    {
        $do = DeliveryOrder::when($this->getBranchFilterForCurrentUser() !== null, function ($q) {
            $codes = $this->getBranchFilterForCurrentUser();
            return $q->where(function ($q2) use ($codes) {
                $q2->whereIn('sender_code', $codes)->orWhereIn('recipient_code', $codes);
            });
        })->findOrFail($id);

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:200', Rule::unique('delivery_orders', 'number')->ignore($do->id)],
            'sender_code' => ['required', 'string', 'max:100'],
            'recipient_code' => ['required', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'expedition' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'driver' => ['nullable', 'string', 'max:200'],
            'driver_phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nkb_id' => ['required', 'integer', 'exists:nkbs,id'],
            'items.*.koli' => ['required', 'numeric', 'min:0'],
            'items.*.ex' => ['required', 'numeric', 'min:0'],
            'items.*.total_ex' => ['required', 'numeric', 'min:0'],
        ]);

        $do->number = $validated['number'];
        $do->sender_code = $validated['sender_code'];
        $do->recipient_code = $validated['recipient_code'];
        $do->date = $validated['date'] ?? null;
        $do->expedition = $validated['expedition'] ?? null;
        $do->plate_number = $validated['plate_number'] ?? null;
        $do->driver = $validated['driver'] ?? null;
        $do->driver_phone = $validated['driver_phone'] ?? '';
        $do->note = $validated['note'] ?? '';
        $do->save();

        $do->items()->delete();
        foreach ($validated['items'] as $row) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $do->id,
                'nkb_id' => (int) $row['nkb_id'],
                'koli' => $row['koli'],
                'ex' => $row['ex'],
                'total_ex' => $row['total_ex'],
            ]);
        }

        return redirect()->route('delivery-orders.show', $do->id)->with('success', 'Surat Jalan berhasil diubah.');
    }

    public function destroy($id)
    {
        $do = DeliveryOrder::when($this->getBranchFilterForCurrentUser() !== null, function ($q) {
            $codes = $this->getBranchFilterForCurrentUser();
            return $q->where(function ($q2) use ($codes) {
                $q2->whereIn('sender_code', $codes)->orWhereIn('recipient_code', $codes);
            });
        })->findOrFail($id);

        $do->items()->delete();
        $do->delete();

        return redirect()->route('delivery-orders.index')->with('success', 'Surat Jalan berhasil dihapus.');
    }
}
