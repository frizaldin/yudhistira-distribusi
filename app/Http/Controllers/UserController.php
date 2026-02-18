<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Authority;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;
    protected $userType; // 'pusat' or 'cabang'

    public function __construct()
    {
        if (Auth::check()) {
            $user = Auth::user();
            $this->role = $user->authority_id ?? 1;
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
     * Display a listing of User Pusat (authority_id = 1)
     */
    public function indexPusat(Request $request)
    {
        $this->base_url = url('/user-pusat');
        $this->title = 'User Pusat';
        $this->userType = 'pusat';

        $users = User::query()
            ->where('authority_id', 1) // User Pusat
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->paginate(15);

        $authorities = Authority::all();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'users' => $users,
            'authorities' => $authorities,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-pusat.index', $data);
    }

    /**
     * Display a listing of User Cabang (authority_id = 2)
     */
    public function indexCabang(Request $request)
    {
        $this->base_url = url('/user-cabang');
        $this->title = 'User Cabang';
        $this->userType = 'cabang';

        $users = User::query()
            ->where('authority_id', 2) // User Cabang
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('branch_code', 'like', '%' . $search . '%');
            })
            ->when($request->branch_code, function ($query, $branchCode) {
                return $query->where('branch_code', $branchCode);
            })
            ->orderBy('name')
            ->paginate(15);

        $authorities = Authority::all();
        $branches = Branch::orderBy('branch_name')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'users' => $users,
            'authorities' => $authorities,
            'branches' => $branches,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-cabang.index', $data);
    }

    /**
     * Show the form for creating a new User Pusat
     */
    public function createPusat()
    {
        $this->base_url = url('/user-pusat');
        $this->title = 'Tambah User Pusat';
        $this->userType = 'pusat';

        $authorities = Authority::all();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'authorities' => $authorities,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-pusat.create', $data);
    }

    /**
     * Show the form for creating a new User Cabang
     */
    public function createCabang()
    {
        $this->base_url = url('/user-cabang');
        $this->title = 'Tambah User Cabang';
        $this->userType = 'cabang';

        $authorities = Authority::all();
        $branches = Branch::orderBy('branch_name')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'authorities' => $authorities,
            'branches' => $branches,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-cabang.create', $data);
    }

    /**
     * Store a newly created User Pusat
     */
    public function storePusat(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'authority_id' => 'required|exists:authorities,id',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'authority_id' => $request->authority_id,
            'branch_code' => null, // User Pusat tidak punya branch_code
        ]);

        return redirect()->route('user-pusat.index')
            ->with('success', 'User Pusat berhasil ditambahkan.');
    }

    /**
     * Store a newly created User Cabang
     */
    public function storeCabang(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'authorities_id' => 'required|exists:authorities,id',
            'branch_code' => 'required|exists:branches,branch_code',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'authorities_id' => $request->authorities_id,
            'branch_code' => $request->branch_code,
        ]);

        return redirect()->route('user-cabang.index')
            ->with('success', 'User Cabang berhasil ditambahkan.');
    }

    /**
     * Show the form for editing User Pusat
     */
    public function editPusat($id)
    {
        $this->base_url = url('/user-pusat');
        $this->title = 'Edit User Pusat';
        $this->userType = 'pusat';

        $user = User::findOrFail($id);
        
        // Ensure this is a pusat user
        if ($user->authority_id != 1) {
            return redirect()->route('user-pusat.index')
                ->with('error', 'User ini bukan User Pusat.');
        }

        $authorities = Authority::all();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'user' => $user,
            'authorities' => $authorities,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-pusat.edit', $data);
    }

    /**
     * Show the form for editing User Cabang
     */
    public function editCabang($id)
    {
        $this->base_url = url('/user-cabang');
        $this->title = 'Edit User Cabang';
        $this->userType = 'cabang';

        $user = User::findOrFail($id);
        
        // Ensure this is a cabang user
        if ($user->authority_id != 2) {
            return redirect()->route('user-cabang.index')
                ->with('error', 'User ini bukan User Cabang.');
        }

        $authorities = Authority::all();
        $branches = Branch::orderBy('branch_name')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'user' => $user,
            'authorities' => $authorities,
            'branches' => $branches,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-cabang.edit', $data);
    }

    /**
     * Update User Pusat
     */
    public function updatePusat(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Ensure this is a pusat user
        if ($user->authority_id != 1) {
            return redirect()->route('user-pusat.index')
                ->with('error', 'User ini bukan User Pusat.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'authority_id' => 'required|exists:authorities,id',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'authority_id' => $request->authority_id,
            'branch_code' => null, // User Pusat tidak punya branch_code
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('user-pusat.index')
            ->with('success', 'User Pusat berhasil diperbarui.');
    }

    /**
     * Update User Cabang
     */
    public function updateCabang(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Ensure this is a cabang user
        if ($user->authority_id != 2) {
            return redirect()->route('user-cabang.index')
                ->with('error', 'User ini bukan User Cabang.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'authorities_id' => 'required|exists:authorities,id',
            'branch_code' => 'required|exists:branches,branch_code',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'authorities_id' => $request->authorities_id,
            'branch_code' => $request->branch_code,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('user-cabang.index')
            ->with('success', 'User Cabang berhasil diperbarui.');
    }

    /**
     * Remove User Pusat
     */
    public function destroyPusat($id)
    {
        $user = User::findOrFail($id);
        
        // Ensure this is a pusat user
        if ($user->authority_id != 1) {
            return redirect()->route('user-pusat.index')
                ->with('error', 'User ini bukan User Pusat.');
        }

        $user->delete();

        return redirect()->route('user-pusat.index')
            ->with('success', 'User Pusat berhasil dihapus.');
    }

    /**
     * Remove User Cabang
     */
    public function destroyCabang($id)
    {
        $user = User::findOrFail($id);
        
        // Ensure this is a cabang user
        if ($user->authority_id != 2) {
            return redirect()->route('user-cabang.index')
                ->with('error', 'User ini bukan User Cabang.');
        }

        $user->delete();

        return redirect()->route('user-cabang.index')
            ->with('success', 'User Cabang berhasil dihapus.');
    }

    /**
     * Display a listing of User ADP (authority_id = 3)
     */
    public function indexAdp(Request $request)
    {
        $this->base_url = url('/user-adp');
        $this->title = 'User ADP';
        $this->userType = 'adp';

        $users = User::query()
            ->where('authority_id', 3) // User ADP
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->paginate(15);

        $authorities = Authority::all();
        $branchesMap = Branch::all()->keyBy('branch_code'); // untuk tampilan nama cabang di index

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'users' => $users,
            'authorities' => $authorities,
            'branchesMap' => $branchesMap,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-adp.index', $data);
    }

    /**
     * Show the form for creating a new User ADP
     */
    public function createAdp()
    {
        $this->base_url = url('/user-adp');
        $this->title = 'Tambah User ADP';
        $this->userType = 'adp';

        $authorities = Authority::where('id', 3)->get(); // Hanya authority ADP
        $branches = Branch::orderBy('branch_name')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'authorities' => $authorities,
            'branches' => $branches,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-adp.create', $data);
    }

    /**
     * Store a newly created User ADP
     */
    public function storeAdp(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'authority_id' => 'required|in:3',
            'branch' => 'nullable|array',
            'branch.*' => 'exists:branches,branch_code',
        ]);

        $branchCodes = $request->input('branch', []);
        if (!is_array($branchCodes)) {
            $branchCodes = [];
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'authority_id' => 3, // Selalu ADP
            'branch_code' => null,
            'branch' => array_values($branchCodes),
        ]);

        return redirect()->route('user-adp.index')
            ->with('success', 'User ADP berhasil ditambahkan.');
    }

    /**
     * Show the form for editing User ADP
     */
    public function editAdp($id)
    {
        $this->base_url = url('/user-adp');
        $this->title = 'Edit User ADP';
        $this->userType = 'adp';

        $user = User::findOrFail($id);

        if ($user->authority_id != 3) {
            return redirect()->route('user-adp.index')
                ->with('error', 'User ini bukan User ADP.');
        }

        $authorities = Authority::where('id', 3)->get();
        $branches = Branch::orderBy('branch_name')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'user' => $user,
            'authorities' => $authorities,
            'branches' => $branches,
            'userType' => $this->userType,
        ];

        return view($this->callbackfolder . '.master-data.user-adp.edit', $data);
    }

    /**
     * Update User ADP
     */
    public function updateAdp(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->authority_id != 3) {
            return redirect()->route('user-adp.index')
                ->with('error', 'User ini bukan User ADP.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'authority_id' => 'required|in:3',
            'branch' => 'nullable|array',
            'branch.*' => 'exists:branches,branch_code',
        ]);

        $branchCodes = $request->input('branch', []);
        if (!is_array($branchCodes)) {
            $branchCodes = [];
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'authority_id' => 3,
            'branch_code' => null,
            'branch' => array_values($branchCodes),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('user-adp.index')
            ->with('success', 'User ADP berhasil diperbarui.');
    }

    /**
     * Remove User ADP
     */
    public function destroyAdp($id)
    {
        $user = User::findOrFail($id);

        if ($user->authority_id != 3) {
            return redirect()->route('user-adp.index')
                ->with('error', 'User ini bukan User ADP.');
        }

        $user->delete();

        return redirect()->route('user-adp.index')
            ->with('success', 'User ADP berhasil dihapus.');
    }
}
