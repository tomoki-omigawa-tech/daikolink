@props(['status'])
@php
    $color = match ($status) {
        \App\Enums\RequestStatus::Open => 'bg-amber-100 text-amber-800',
        \App\Enums\RequestStatus::Applied => 'bg-sky-100 text-sky-800',
        \App\Enums\RequestStatus::Confirmed => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold {$color}"]) }}>{{ $status->label() }}</span>
