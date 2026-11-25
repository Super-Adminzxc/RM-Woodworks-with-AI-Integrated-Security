@props(['status'])

@php
    $normalized = (string) $status;
    $classes = [
        'Needs Review' => 'bg-amber-100 text-amber-800 border-amber-300',
        'Confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'Rejected' => 'bg-rose-100 text-rose-800 border-rose-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-full border text-[10px] font-extrabold uppercase tracking-wider ' . ($classes[$normalized] ?? 'bg-slate-100 text-slate-700 border-slate-300')]) }}>
    {{ $normalized ?: 'Needs Review' }}
</span>
