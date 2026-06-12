@php
    // Generate initials from name (same logic as React Avatar component)
    $words    = array_filter(explode(' ', $name ?? '?'));
    $initials = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $words)));
    $initials = substr($initials, 0, 2) ?: '?';

    // Deterministic color from name (same hue calculation as React)
    $hue = array_sum(array_map('ord', str_split($name ?? ''))) % 360;
@endphp

@if(!empty($avatar))
    {{-- Avatar stored in public disk under 'avatars/' (from ProfileController::uploadAvatar) --}}
    <img
        src="{{ asset('storage/' . $avatar) }}"
        alt="{{ $name }}"
        style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;object-fit:cover;flex-shrink:0;"
    />
@else
    <div style="
        width:{{ $size }}px;
        height:{{ $size }}px;
        border-radius:50%;
        background:hsl({{ $hue }},50%,55%);
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        color:#fff;
        font-size:{{ round($size * 0.37) }}px;
        flex-shrink:0;
        font-family:inherit;
        user-select:none;
    ">{{ $initials }}</div>
@endif
