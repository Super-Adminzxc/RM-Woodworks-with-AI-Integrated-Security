@props(['severity'])

@php
    $normalized = ucfirst(strtolower((string) $severity));
    $classes = [
        'Critical' => 'bg-red-100 text-red-800 border-red-300',
        'High' => 'bg-orange-100 text-orange-800 border-orange-300',
        'Medium' => 'bg-amber-100 text-amber-800 border-amber-300',
        'Low' => 'bg-blue-100 text-blue-800 border-blue-300',
        'Informational' => 'bg-slate-100 text-slate-700 border-slate-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-full border text-[10px] font-extrabold uppercase tracking-wider ' . ($classes[$normalized] ?? $classes['Informational'])]) }}>
    {{ $normalized ?: 'Informational' }}
</span>
