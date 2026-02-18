<li class="nav-item nav-parent-menu">
    <a class="nav-link" href="#{{ str_replace(' ', '-', strtolower($titlegroup)) }}" data-bs-toggle="collapse"
        role="button" aria-expanded="false" aria-controls="{{ str_replace(' ', '-', strtolower($titlegroup)) }}">
        <i class="{{ $icon ?? 'iconoir-compact-disc' }} menu-icon"></i>
        <span><small>{{ $titlegroup }}</small></span>
    </a>
    <div class="collapse " id="{{ str_replace(' ', '-', strtolower($titlegroup)) }}">
        <ul class="nav flex-column parent-item">
            @foreach ($menu as $item)
                <x-nav.sidebar.menu url="{{ $item['url'] }}" key="{{ $item['key'] }}" icon="{{ $item['icon'] }}"
                    :authority="$authority" :badge="$item['badge'] ?? 0" />
            @endforeach

        </ul><!--end nav-->
    </div><!--end startbarElements-->
</li>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const selector = `#{{ str_replace(' ', '-', strtolower($titlegroup)) }} .parent-item .nav-item-menu`;
        const items = document.querySelectorAll(selector);
        const count = items.length;

        if (count === 0) {
            const parentGroup = document.querySelector(
                `#{{ str_replace(' ', '-', strtolower($titlegroup)) }}`);
            if (parentGroup && parentGroup.parentElement) {
                parentGroup.parentElement.remove();
            }
        }
    });
</script>
