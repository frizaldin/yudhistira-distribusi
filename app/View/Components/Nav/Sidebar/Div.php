<?php

namespace App\View\Components\Nav\Sidebar;

use App\Models\Authority;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Div extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $user = Auth::user();
        $authorityId = $user->authority_id ?? null;
        $authority = $authorityId ? Authority::find($authorityId) : null;
        
        return view('components.nav.sidebar.div', [
            'authority' => $authority,
            'user' => $user,
        ]);
    }
}
