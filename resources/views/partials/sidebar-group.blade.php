<div class="app-nav-group" role="group" aria-labelledby="nav-group-{{ Str::slug($group) }}">
    <p class="app-nav-group__label" id="nav-group-{{ Str::slug($group) }}">{{ $group }}</p>
    <ul class="app-nav" role="list">
        @foreach ($items as $item)
            @include('partials.sidebar-item', ['item' => $item])
        @endforeach
    </ul>
</div>
