@props([
    'label',
    'value',
    'icon' => 'bi-graph-up',
    'color' => 'blue', // blue | green | yellow | red | purple
    'hint' => null,
])

<div {{ $attributes->merge(['class' => "card stat-card stat-{$color} h-100"]) }}>
    <div class="card-body">
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if ($hint)
                <div class="small text-muted mt-1">{{ $hint }}</div>
            @endif
        </div>
        <div class="stat-icon"><i class="bi {{ $icon }}"></i></div>
    </div>
</div>
