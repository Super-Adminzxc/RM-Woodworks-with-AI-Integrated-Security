@extends('layouts.system')

@section('main-content')
@php
    $rawAlert = json_encode(
        $finding->raw_alert,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
@endphp

<div class="flex-1 flex flex-col overflow-hidden bg-amber-50">
    <!-- Header -->
    <header class="p-6 bg-amber-50 border-b border-amber-200 relative z-10 shadow-sm">
        <div class="max-w-7xl mx-auto">
            <a href="{{ route('security.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-orange-700 transition-colors mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to findings dashboard
            </a>

            @if($finding->scan?->isSampleData())
                <div class="mb-4 rounded-xl bg-amber-100 border-2 border-amber-300 px-4 py-3">
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Sample fixture — not a live scan</p>
                    <p class="text-xs text-amber-900 mt-1">
                        {{ $finding->scan->sampleNotice() ?: 'This finding came from clearly labeled sample data used for interface testing. Do not present it as genuine assessment evidence.' }}
                    </p>
                </div>
            @endif

            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <x-severity-badge :severity="$finding->severity" />
                        <x-verification-badge :status="$finding->verification_status" />
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">
                            Finding #{{ $finding->id }} · {{ $finding->scan?->tool_name ?? 'OWASP ZAP' }}
                        </span>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-800 tracking-tight break-words">{{ $finding->title }}</h1>
                    <p class="text-xs text-gray-600 mt-2 break-all">
                        <span class="font-bold text-slate-700">{{ $finding->http_method ?: 'GET' }}</span>
                        {{ $finding->affected_url ?: 'No affected URL supplied by the scanner' }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row xl:flex-col gap-2 shrink-0">
                    <form action="{{ route('security.analyze', $finding) }}" method="POST" data-loading-form>
                        @csrf
                        <button type="submit" @disabled(!$aiConfigured)
                                title="{{ $aiConfigured ? 'Analyze this finding with the configured AI provider' : 'AI provider is not configured' }}"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold shadow-md transition-all {{ $aiConfigured ? 'bg-gradient-to-r from-slate-800 to-slate-700 text-white hover:from-slate-700 hover:to-slate-600' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }}">
                            <svg class="btn-spinner hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span data-loading-label>Run AI Scan Result Interpretation</span>
                        </button>
                    </form>
                    <form action="{{ route('security.remediate', $finding) }}" method="POST" data-loading-form>
                        @csrf
                        <button type="submit" @disabled(!$aiConfigured)
                                title="{{ $aiConfigured ? 'Generate remediation guidance with the configured AI provider' : 'AI provider is not configured' }}"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold shadow-md transition-all {{ $aiConfigured ? 'bg-gradient-to-r from-orange-600 to-amber-600 text-white hover:from-orange-500 hover:to-amber-500' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }}">
                            <svg class="btn-spinner hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span data-loading-label>Run AI Remediation Suggestion</span>
                        </button>
                    </form>
                    @if(!$aiConfigured)
                        <p class="text-[10px] text-amber-800 bg-amber-100 border border-amber-300 rounded-lg px-3 py-2 max-w-xs">
                            AI is not configured. Add a provider key to the backend environment; no mock AI result will be shown.
                        </p>
                    @else
                        <p class="text-[10px] text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 max-w-xs">
                            AI provider: {{ $aiProvider }}@if($aiModel) · {{ $aiModel }}@endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6 custom-scrollbar">
        <div class="max-w-7xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-5">

            <div class="xl:col-span-2 space-y-5">
                <!-- A. Finding information -->
                <section class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/60 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-extrabold text-gray-800">A. Finding Information</h2>
                            <p class="text-[11px] text-gray-500 mt-0.5">Imported scanner metadata</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Scan #{{ $finding->scan_id }}</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Finding title</p>
                            <p class="text-sm font-bold text-gray-800 mt-1">{{ $finding->title }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Scanner severity</p>
                            <div class="mt-1"><x-severity-badge :severity="$finding->severity" /></div>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Affected URL</p>
                            <p class="text-xs font-mono text-gray-800 mt-1 break-all bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">{{ $finding->affected_url ?: 'Not provided' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">HTTP method</p>
                            <p class="text-sm font-mono font-bold text-gray-800 mt-1">{{ $finding->http_method ?: 'Not provided' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Current verification status</p>
                            <div class="mt-1"><x-verification-badge :status="$finding->verification_status" /></div>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Target</p>
                            <p class="text-xs text-gray-700 mt-1 break-all">{{ $finding->scan?->target_name ?? 'RM Woodworks' }}<br><span class="text-gray-500">{{ $finding->scan?->target_url ?: 'No target URL recorded' }}</span></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Imported</p>
                            <p class="text-xs text-gray-700 mt-1">{{ $finding->scan?->imported_at?->format('M d, Y H:i') ?? 'Unknown' }}<br><span class="text-gray-500 break-all">{{ $finding->scan?->source_filename }}</span></p>
                        </div>
                    </div>
                </section>

                <!-- B. Original scanner evidence -->
                <section class="bg-white rounded-2xl border-2 border-amber-300 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-200 bg-amber-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-extrabold text-gray-800">B. Original Scanner Evidence</h2>
                            <p class="text-[11px] text-gray-600 mt-0.5">Unmodified OWASP ZAP alert data</p>
                        </div>
                        <span class="self-start sm:self-auto px-2.5 py-1 rounded-full bg-amber-200 text-amber-900 text-[10px] font-extrabold uppercase tracking-wider border border-amber-300">
                            Scanner output — not AI
                        </span>
                    </div>
                    <div class="p-5 space-y-5">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">ZAP description</p>
                            @if($finding->description)
                                <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $finding->description }}</p>
                            @else
                                <p class="text-xs text-gray-500 italic">No description supplied.</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Confidence</p>
                                <p class="text-sm font-bold text-gray-800 mt-1">{{ $finding->confidence ?: 'Not provided' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Parameter</p>
                                <p class="text-sm font-mono font-bold text-gray-800 mt-1 break-all">{{ $finding->parameter ?: 'Not provided' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">CWE ID</p>
                                <p class="text-sm font-mono text-gray-800 mt-1">{{ $finding->cwe_id ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">WASC ID</p>
                                <p class="text-sm font-mono text-gray-800 mt-1">{{ $finding->wasc_id ?: '—' }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">Evidence</p>
                            @if($finding->evidence)
                                <pre class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words bg-slate-900 text-slate-100 rounded-xl p-4 max-h-64 overflow-y-auto custom-scrollbar">{{ $finding->evidence }}</pre>
                            @else
                                <p class="text-xs text-gray-500 italic">No evidence supplied by the scanner.</p>
                            @endif
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">Original scanner solution</p>
                            @if($finding->solution)
                                <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $finding->solution }}</p>
                            @else
                                <p class="text-xs text-gray-500 italic">No solution supplied.</p>
                            @endif
                        </div>

                        @if($finding->reference)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">References</p>
                                <p class="text-xs text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $finding->reference }}</p>
                            </div>
                        @endif

                        @if($finding->raw_alert)
                            <details class="rounded-xl border border-slate-200 bg-slate-50 overflow-hidden">
                                <summary class="cursor-pointer px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100">Show preserved raw ZAP alert JSON</summary>
                                <pre class="text-[10px] text-slate-700 leading-relaxed whitespace-pre-wrap break-words p-4 max-h-80 overflow-y-auto custom-scrollbar bg-white border-t border-slate-200">{{ $rawAlert }}</pre>
                            </details>
                        @endif
                    </div>
                </section>

                <!-- C. AI analysis -->
                <section class="bg-white rounded-2xl border-2 border-indigo-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-extrabold text-gray-800">C. AI Analysis</h2>
                            <p class="text-[11px] text-gray-600 mt-0.5">AI scan result interpretation</p>
                        </div>
                        <span class="self-start sm:self-auto px-2.5 py-1 rounded-full bg-indigo-200 text-indigo-900 text-[10px] font-extrabold uppercase tracking-wider border border-indigo-300">
                            AI-generated — advisory, unverified
                        </span>
                    </div>
                    <div class="p-5">
                        @if($analysis)
                            <div class="space-y-5">
                                <div>
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-indigo-700 mb-1.5">Vulnerability explanation</h3>
                                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $analysis['vulnerability_explanation'] ?? '' }}</p>
                                </div>
                                <div>
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-indigo-700 mb-1.5">Technical interpretation</h3>
                                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $analysis['technical_interpretation'] ?? '' }}</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="rounded-xl bg-red-50 border border-red-200 p-4">
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-red-700 mb-1.5">Potential impact</h3>
                                        <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $analysis['potential_impact'] ?? '' }}</p>
                                    </div>
                                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-amber-700 mb-1.5">Why it matters</h3>
                                        <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $analysis['why_it_matters'] ?? '' }}</p>
                                    </div>
                                </div>
                                @if(!empty($analysis['additional_checks']))
                                    <div>
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-indigo-700 mb-2">Additional checks for the human assessor</h3>
                                        <ul class="space-y-1.5">
                                            @foreach($analysis['additional_checks'] as $check)
                                                <li class="flex items-start gap-2 text-sm text-gray-800">
                                                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                                    <span class="leading-relaxed">{{ $check }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-slate-600 mb-1.5">Limitations / uncertainties</h3>
                                    <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $analysis['limitations'] ?? '' }}</p>
                                </div>
                                <p class="text-[10px] text-gray-500">
                                    Generated {{ $finding->ai_analysis_generated_at?->format('M d, Y H:i') }}. This AI content is advisory only and does not confirm the vulnerability.
                                </p>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <div class="w-14 h-14 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-bold text-gray-700">No AI interpretation yet</p>
                                <p class="text-xs text-gray-500 mt-1 max-w-md">
                                    @if($aiConfigured)
                                        Use <strong>Run AI Scan Result Interpretation</strong> above to analyze this finding's actual scanner evidence.
                                    @else
                                        AI analysis is not configured on this deployment. No mock result is shown.
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

                <!-- D. AI remediation -->
                <section class="bg-white rounded-2xl border-2 border-orange-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-orange-100 bg-orange-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-extrabold text-gray-800">D. AI Remediation</h2>
                            <p class="text-[11px] text-gray-600 mt-0.5">AI remediation suggestion</p>
                        </div>
                        <span class="self-start sm:self-auto px-2.5 py-1 rounded-full bg-orange-200 text-orange-900 text-[10px] font-extrabold uppercase tracking-wider border border-orange-300">
                            AI-generated — advisory, unverified
                        </span>
                    </div>
                    <div class="p-5">
                        @if($recommendation)
                            <div class="space-y-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Suggested priority</span>
                                    <x-severity-badge :severity="$recommendation['suggested_priority'] ?? 'Informational'" />
                                </div>
                                <div>
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-orange-700 mb-1.5">Priority reason</h3>
                                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $recommendation['priority_reason'] ?? '' }}</p>
                                </div>
                                @if(!empty($recommendation['corrective_actions']))
                                    <div>
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-orange-700 mb-2">Recommended corrective actions</h3>
                                        <ul class="space-y-1.5">
                                            @foreach($recommendation['corrective_actions'] as $action)
                                                <li class="flex items-start gap-2 text-sm text-gray-800">
                                                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>
                                                    <span class="leading-relaxed">{{ $action }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div>
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-orange-700 mb-1.5">Suggested implementation approach</h3>
                                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $recommendation['implementation_approach'] ?? '' }}</p>
                                </div>
                                @if(!empty($recommendation['security_improvements']))
                                    <div>
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-orange-700 mb-2">Security improvements</h3>
                                        <ul class="space-y-1.5">
                                            @foreach($recommendation['security_improvements'] as $improvement)
                                                <li class="flex items-start gap-2 text-sm text-gray-800">
                                                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>
                                                    <span class="leading-relaxed">{{ $improvement }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if(!empty($recommendation['verification_steps']))
                                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                                        <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-slate-600 mb-2">Verification steps after remediation</h3>
                                        <ul class="space-y-1.5">
                                            @foreach($recommendation['verification_steps'] as $step)
                                                <li class="flex items-start gap-2 text-sm text-gray-800">
                                                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-slate-500 shrink-0"></span>
                                                    <span class="leading-relaxed">{{ $step }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <p class="text-[10px] text-gray-500">
                                    Generated {{ $finding->ai_remediation_generated_at?->format('M d, Y H:i') }}. AI suggestions are advisory and must be reviewed by a human assessor.
                                </p>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mb-3">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-bold text-gray-700">No AI remediation yet</p>
                                <p class="text-xs text-gray-500 mt-1 max-w-md">
                                    @if($aiConfigured)
                                        Use <strong>Run AI Remediation Suggestion</strong> above to generate recommendations from this finding's evidence.
                                    @else
                                        AI remediation is not configured on this deployment. No mock result is shown.
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            <!-- E. Human verification -->
            <aside class="space-y-5 xl:self-start">
                <section class="bg-white rounded-2xl border-2 border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50">
                        <h2 class="text-sm font-extrabold text-gray-800">E. Human Verification</h2>
                        <p class="text-[11px] text-gray-600 mt-0.5">Only the assessor can change this decision</p>
                    </div>
                    <form action="{{ route('security.verify', $finding) }}" method="POST" class="p-5 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3">
                            <p class="text-[11px] text-amber-900 leading-relaxed">
                                <strong>AI never confirms findings.</strong> Review the scanner evidence and AI advice, then choose the verification status yourself.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Verification status</p>
                            @foreach(\App\Models\Security\Finding::STATUSES as $option)
                                @php
                                    $styles = match ($option) {
                                        'Confirmed' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                                        'Rejected' => 'border-rose-300 bg-rose-50 text-rose-900',
                                        default => 'border-amber-300 bg-amber-50 text-amber-900',
                                    };
                                @endphp
                                <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all hover:shadow-sm {{ $styles }}">
                                    <input type="radio" name="verification_status" value="{{ $option }}"
                                           @checked(old('verification_status', $finding->verification_status) === $option)
                                           class="mt-0.5 w-4 h-4 text-slate-800 focus:ring-slate-500">
                                    <span>
                                        <span class="block text-xs font-extrabold">{{ $option }}</span>
                                        <span class="block text-[10px] mt-0.5 opacity-80">
                                            @if($option === 'Needs Review')
                                                Not yet verified by a human.
                                            @elseif($option === 'Confirmed')
                                                The assessor verified this as a genuine issue.
                                            @else
                                                The assessor dismissed this as not applicable or a false positive.
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                            @error('verification_status')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="assessor_notes" class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">Assessor notes</label>
                            <textarea id="assessor_notes" name="assessor_notes" rows="5" maxlength="5000"
                                      placeholder="Document why you confirmed or rejected this finding..."
                                      class="w-full text-sm border border-amber-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400">{{ old('assessor_notes', $finding->assessor_notes) }}</textarea>
                            @error('assessor_notes')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-700 to-emerald-600 text-white rounded-xl hover:from-emerald-600 hover:to-emerald-500 transition-all shadow-md text-sm font-bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save human decision
                        </button>

                        @if($finding->verified_at)
                            <div class="pt-3 border-t border-gray-100 text-[11px] text-gray-600">
                                Last verified by <strong>{{ $finding->verifiedBy?->name ?? 'Unknown assessor' }}</strong><br>
                                {{ $finding->verified_at->format('M d, Y H:i') }}
                            </div>
                        @endif
                    </form>
                </section>

                <section class="bg-slate-800 rounded-2xl border border-slate-700 shadow-sm p-5 text-white">
                    <h3 class="text-xs font-extrabold uppercase tracking-widest text-amber-400">Assessment reminder</h3>
                    <p class="text-xs text-slate-300 leading-relaxed mt-2">
                        Scanner alerts are potential findings, not automatically confirmed vulnerabilities. Keep the original ZAP evidence separate from AI interpretation, and record the human decision explicitly.
                    </p>
                    <p class="text-[10px] text-slate-400 mt-3">
                        Scan source: {{ $finding->scan?->source_filename ?? 'Unknown' }}
                    </p>
                </section>
            </aside>
        </div>
    </main>
</div>

<script>
    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.classList.add('opacity-75', 'cursor-wait');

            const label = button.querySelector('[data-loading-label]');
            if (label) {
                label.textContent = 'Working…';
            }

            const spinner = button.querySelector('.btn-spinner');
            if (spinner) {
                spinner.classList.remove('hidden');
            }
        });
    });
</script>
@endsection
