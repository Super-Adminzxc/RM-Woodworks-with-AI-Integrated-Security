@extends('layouts.system')

@section('main-content')
<div class="flex-1 flex flex-col overflow-hidden bg-amber-50">
    <!-- Header -->
    <header class="p-6 bg-amber-50 border-b border-amber-200 relative z-10 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-800 to-slate-700 text-amber-400 flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </span>
                    RM Woodworks SecureScan
                </h1>
                <p class="text-sm font-medium text-gray-600 mt-2 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                    OWASP ZAP findings, AI-assisted interpretation, and human verification
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1.5 rounded-xl text-xs font-bold border {{ $aiConfigured ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-300' }}">
                    AI: {{ $aiConfigured ? $aiProvider . ($aiModel ? ' · ' . $aiModel : '') : 'Not configured' }}
                </span>
                @if($stats['total'] > 0 || $recentScans->isNotEmpty())
                    <form action="{{ route('security.clear') }}" method="POST"
                          onsubmit="return confirm('Delete all imported scans and findings? This also removes AI analysis and human verification decisions. This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-white text-red-700 border border-red-200 hover:bg-red-50 hover:border-red-300 transition-colors"
                                title="Clear all scans and findings before importing a new report">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Clear all scans
                        </button>
                    </form>
                @endif
                <a href="{{ asset('samples/rmwoodworks-zap-sample-report.json') }}" download
                   class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white text-slate-700 border border-amber-200 hover:bg-amber-100 transition-colors">
                    Download sample ZAP fixture
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6 custom-scrollbar">
        <div class="max-w-7xl mx-auto space-y-6">

            @if($errors->any())
                <div class="flex flex-col gap-1 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium shadow-sm">
                    @foreach($errors->all() as $error)
                        <span>• {{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <!-- Summary statistics -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl p-4 text-white shadow-xl border border-slate-700">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-300">Total Findings</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['total'] }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">All imported scanner alerts</p>
                </div>
                <div class="bg-gradient-to-br from-red-700 to-red-900 rounded-xl p-4 text-white shadow-xl border border-red-600">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-red-100">Critical Findings</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['critical'] }}</p>
                    <p class="text-[10px] text-red-200 mt-1">Scanner risk level: Critical</p>
                </div>
                <div class="bg-gradient-to-br from-orange-600 to-orange-800 rounded-xl p-4 text-white shadow-xl border border-orange-500">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-orange-100">High Findings</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['high'] }}</p>
                    <p class="text-[10px] text-orange-200 mt-1">Scanner risk level: High</p>
                </div>
                <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl p-4 text-white shadow-xl border border-amber-400">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-100">Medium Findings</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['medium'] }}</p>
                    <p class="text-[10px] text-amber-100 mt-1">Scanner risk level: Medium</p>
                </div>
                <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 text-white shadow-xl border border-blue-500">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Low Findings</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['low'] }}</p>
                    <p class="text-[10px] text-blue-200 mt-1">Scanner risk level: Low</p>
                </div>
                <div class="bg-gradient-to-br from-slate-500 to-slate-700 rounded-xl p-4 text-white shadow-xl border border-slate-400">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-100">Informational</p>
                    <p class="text-3xl font-extrabold mt-1">{{ $stats['informational'] }}</p>
                    <p class="text-[10px] text-slate-200 mt-1">Scanner risk level: Informational</p>
                </div>
                <div class="bg-white rounded-xl p-4 border-2 border-amber-300 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">Needs Review</p>
                    <p class="text-3xl font-extrabold mt-1 text-amber-800">{{ $stats['needs_review'] }}</p>
                    <p class="text-[10px] text-gray-500 mt-1">Awaiting a human decision</p>
                </div>
                <div class="bg-white rounded-xl p-4 border-2 border-emerald-300 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-700">Confirmed</p>
                    <p class="text-3xl font-extrabold mt-1 text-emerald-800">{{ $stats['confirmed'] }}</p>
                    <p class="text-[10px] text-gray-500 mt-1">Verified by an assessor</p>
                </div>
                <div class="bg-white rounded-xl p-4 border-2 border-rose-300 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-700">Rejected</p>
                    <p class="text-3xl font-extrabold mt-1 text-rose-800">{{ $stats['rejected'] }}</p>
                    <p class="text-[10px] text-gray-500 mt-1">Dismissed by an assessor</p>
                </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <!-- Import -->
                <section id="import-report" class="xl:col-span-2 bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-amber-100 bg-amber-50/60">
                        <h2 class="text-base font-extrabold text-gray-800">Import OWASP ZAP JSON Report</h2>
                        <p class="text-xs text-gray-600 mt-1">Findings are stored exactly as reported by ZAP and start as <strong>Needs Review</strong>. Import only reports from authorized assessment targets.</p>
                    </div>
                    <form action="{{ route('security.import') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                        @csrf
                        <div>
                            <label for="report" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">ZAP JSON report</label>
                            <input id="report" name="report" type="file" accept=".json,application/json" required
                                   class="block w-full text-xs text-gray-700 border border-amber-200 rounded-xl bg-amber-50/40 file:mr-4 file:py-2.5 file:px-4 file:rounded-l-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer">
                            @error('report')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="target_name" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Target name</label>
                                <input id="target_name" name="target_name" type="text" value="{{ old('target_name', 'RM Woodworks') }}" maxlength="255"
                                       class="w-full text-sm border border-amber-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                            </div>
                            <div>
                                <label for="target_url" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Authorized target URL <span class="text-gray-400 normal-case font-medium">(optional)</span></label>
                                <input id="target_url" name="target_url" type="url" value="{{ old('target_url') }}" maxlength="2048" placeholder="https://authorized-target.example"
                                       class="w-full text-sm border border-amber-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                @error('target_url')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                            <p class="text-[11px] text-gray-500 leading-relaxed max-w-lg">
                                Accepted: OWASP ZAP JSON exports. Invalid or duplicate reports are rejected without changing stored findings.
                                The linked sample is a <strong>labeled test fixture</strong>, not a live scan.
                            </p>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-700 text-white rounded-xl hover:from-slate-700 hover:to-slate-600 transition-all shadow-md text-sm font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Import report
                            </button>
                        </div>
                    </form>
                </section>

                <!-- AI status and recent scans -->
                <section class="space-y-5">
                    <div class="rounded-2xl border p-5 shadow-sm {{ $aiConfigured ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-300' }}">
                        <div class="flex items-start gap-3">
                            <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $aiConfigured ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-extrabold text-gray-800">AI analysis</h2>
                                @if($aiConfigured)
                                    <p class="text-xs text-emerald-800 mt-1">{{ $aiProvider }} is configured on the backend. AI buttons will analyze the selected finding.</p>
                                    @if($aiModel)
                                        <p class="text-[11px] text-emerald-700 mt-1 font-mono">{{ $aiModel }}</p>
                                    @endif
                                @else
                                    <p class="text-xs text-amber-900 mt-1">No AI provider key is configured. Import, dashboard, details, and human verification still work; AI buttons will explain that analysis is unavailable.</p>
                                    <p class="text-[11px] text-amber-800 mt-2 font-mono">Set GEMINI_API_KEY (or AI_PROVIDER/OPENAI_API_KEY) in the backend .env</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-amber-100 bg-amber-50/60 flex items-center justify-between">
                            <h2 class="text-sm font-extrabold text-gray-800">Recent imports</h2>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ $recentScans->count() }} shown</span>
                        </div>
                        <div class="divide-y divide-amber-100">
                            @forelse($recentScans as $scan)
                                <div class="p-3.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-bold text-gray-800 break-all">{{ $scan->source_filename }}</p>
                                        <span class="shrink-0 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold">{{ $scan->findings_count }} findings</span>
                                    </div>
                                    @if($scan->isSampleData())
                                        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full bg-amber-200 text-amber-900 text-[9px] font-extrabold uppercase tracking-wider border border-amber-300">Sample fixture — not a live scan</span>
                                    @endif
                                    <p class="text-[11px] text-gray-500 mt-1 break-all">{{ $scan->target_url ?: $scan->target_name }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">{{ $scan->imported_at?->format('M d, Y H:i') }} · {{ $scan->tool_name }}</p>
                                </div>
                            @empty
                                <div class="p-5 text-center text-xs text-gray-500">No reports imported yet.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <!-- Findings table -->
            <section class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-amber-100 bg-amber-50/60 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-base font-extrabold text-gray-800">Security Findings</h2>
                        <p class="text-xs text-gray-600 mt-1">Scanner evidence is preserved separately from AI-generated content.</p>
                    </div>
                    <form action="{{ route('security.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                        <select name="severity" class="text-xs font-bold border border-amber-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:ring-2 focus:ring-amber-400">
                            <option value="">All severities</option>
                            @foreach(\App\Models\Security\Finding::SEVERITIES as $option)
                                <option value="{{ $option }}" @selected($severity === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="text-xs font-bold border border-amber-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:ring-2 focus:ring-amber-400">
                            <option value="">All verification states</option>
                            @foreach(\App\Models\Security\Finding::STATUSES as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-700 transition-colors">Filter</button>
                        @if($severity || $status)
                            <a href="{{ route('security.index') }}" class="px-4 py-2 bg-white text-gray-600 border border-amber-200 rounded-xl text-xs font-bold hover:bg-amber-100 transition-colors">Reset</a>
                        @endif
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest">Finding</th>
                                <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest">Severity</th>
                                <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest">Affected URL</th>
                                <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest">Verification</th>
                                <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @forelse($findings as $finding)
                                <tr class="hover:bg-amber-50/70 transition-colors">
                                    <td class="px-4 py-3 align-top">
                                        <a href="{{ route('security.show', $finding) }}" class="text-sm font-bold text-slate-800 hover:text-orange-700 transition-colors">
                                            {{ $finding->title }}
                                        </a>
                                        <p class="text-[10px] text-gray-500 mt-1">
                                            {{ $finding->scan?->source_filename ?? 'Unknown scan' }}
                                            @if($finding->confidence)
                                                · Confidence: {{ $finding->confidence }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <x-severity-badge :severity="$finding->severity" />
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <p class="text-xs text-gray-700 max-w-xs break-all" title="{{ $finding->affected_url }}">
                                            {{ $finding->affected_url ?: '—' }}
                                        </p>
                                        @if($finding->http_method)
                                            <p class="text-[10px] font-mono text-gray-500 mt-1">{{ $finding->http_method }}{{ $finding->parameter ? ' · ' . $finding->parameter : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <x-verification-badge :status="$finding->verification_status" />
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        <a href="{{ route('security.show', $finding) }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white text-[11px] font-bold hover:bg-slate-700 transition-colors">
                                            View details
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-14 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mb-3">
                                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <p class="text-sm font-bold text-gray-700">No findings match this view</p>
                                            <p class="text-xs text-gray-500 mt-1">Import a ZAP JSON report above, or reset the filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($findings->hasPages())
                    <div class="p-4 border-t border-amber-100 bg-amber-50/40">
                        {{ $findings->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</div>
@endsection
