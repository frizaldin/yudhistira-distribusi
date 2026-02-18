@foreach ($feature as $e)
    @if ($authority && $authority->code && in_array($e->id, json_decode($authority->code, true) ?? []))
        <a href="{{ $url }}" class="nav-link @if (Route::currentRouteName() == $key) active @endif">
            <i class="{{ $icon ? $icon : 'bi bi-speedometer2' }} menu-icon"></i>
            <span><small>{{ $e->title }}</small></span>
            @if (isset($badge) && $badge > 0)
                <span class="badge text-bg-danger ms-auto" style="margin-left:8px;">{{ $badge }}</span>
            @endif
        </a>
    @endif
@endforeach
