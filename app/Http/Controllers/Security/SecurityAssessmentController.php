<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Security\Finding;
use App\Models\Security\Scan;
use App\Models\System\SystemActivity;
use App\Services\Security\SecurityAiService;
use App\Services\Security\ZapReportImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SecurityAssessmentController extends Controller
{
    public function index(Request $request, SecurityAiService $ai): View
    {
        $severityCounts = Finding::query()
            ->selectRaw('severity, COUNT(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity');

        $statusCounts = Finding::query()
            ->selectRaw('verification_status, COUNT(*) as aggregate')
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status');

        $stats = [
            'total' => Finding::count(),
            'critical' => (int) ($severityCounts['Critical'] ?? 0),
            'high' => (int) ($severityCounts['High'] ?? 0),
            'medium' => (int) ($severityCounts['Medium'] ?? 0),
            'low' => (int) ($severityCounts['Low'] ?? 0),
            'informational' => (int) ($severityCounts['Informational'] ?? 0),
            'needs_review' => (int) ($statusCounts[Finding::STATUS_NEEDS_REVIEW] ?? 0),
            'confirmed' => (int) ($statusCounts[Finding::STATUS_CONFIRMED] ?? 0),
            'rejected' => (int) ($statusCounts[Finding::STATUS_REJECTED] ?? 0),
        ];

        $severity = $request->query('severity');
        $status = $request->query('status');

        $findings = Finding::query()
            ->with('scan')
            ->when(in_array($severity, Finding::SEVERITIES, true), fn ($query) => $query->where('severity', $severity))
            ->when(in_array($status, Finding::STATUSES, true), fn ($query) => $query->where('verification_status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $recentScans = Scan::query()
            ->with('user')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('Systems.security', [
            'findings' => $findings,
            'stats' => $stats,
            'recentScans' => $recentScans,
            'severity' => $severity,
            'status' => $status,
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->providerLabel(),
            'aiModel' => $ai->modelLabel(),
        ]);
    }

    public function import(
        Request $request,
        ZapReportImporter $importer
    ): RedirectResponse {
        $validated = $request->validate([
            'report' => ['required', 'file', 'max:10240'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'target_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $scan = $importer->import(
            $validated['report'],
            $validated['target_name'] ?? null,
            $validated['target_url'] ?? null,
            $request->user()?->id
        );

        SystemActivity::log(
            'Security',
            'Imported scan report',
            "Imported {$scan->findings_count} finding(s) from {$scan->source_filename}.",
            'amber'
        );

        $message = "Imported {$scan->findings_count} finding(s) from {$scan->source_filename}.";

        if ($scan->findings_count === 0) {
            $message .= ' The report contained no alerts, so there are no findings to review.';
        }

        return redirect()
            ->route('security.index', [], 303)
            ->with('success', $message);
    }

    public function clear(): RedirectResponse
    {
        $scanCount = Scan::count();
        $findingCount = Finding::count();

        if ($scanCount === 0 && $findingCount === 0) {
            return redirect()
                ->route('security.index', [], 303)
                ->with('success', 'SecureScan is already clean. There are no scans or findings to clear.');
        }

        DB::transaction(function () {
            // Delete findings first so this also works if a database does not
            // enforce the cascade relationship.
            Finding::query()->delete();
            Scan::query()->delete();
        });

        SystemActivity::log(
            'Security',
            'Cleared scan data',
            "Cleared {$scanCount} scan(s) and {$findingCount} finding(s) from SecureScan.",
            'red'
        );

        return redirect()
            ->route('security.index', [], 303)
            ->with('success', "Cleared {$scanCount} scan(s) and {$findingCount} finding(s). SecureScan is ready for a new import.");
    }

    public function show(Finding $finding, SecurityAiService $ai): View
    {
        $finding->load(['scan.user', 'verifiedBy']);

        return view('Systems.security-finding', [
            'finding' => $finding,
            'analysis' => $finding->aiExplanation(),
            'recommendation' => $finding->aiRecommendation(),
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->providerLabel(),
            'aiModel' => $ai->modelLabel(),
        ]);
    }

    public function analyze(Finding $finding, SecurityAiService $ai): RedirectResponse
    {
        try {
            $analysis = $ai->analyze($finding);

            // AI output is advisory only. Never change verification_status here.
            $finding->update([
                'ai_explanation' => json_encode(
                    $analysis,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ),
                'ai_analysis_generated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'AI analysis could not be completed. No AI result was saved.');
        }

        return back()->with('success', 'AI scan result interpretation generated. It is advisory and still requires human verification.');
    }

    public function remediate(Finding $finding, SecurityAiService $ai): RedirectResponse
    {
        try {
            $recommendation = $ai->remediate($finding);

            // AI output is advisory only. Never change verification_status here.
            $finding->update([
                'ai_recommendation' => json_encode(
                    $recommendation,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ),
                'suggested_priority' => $recommendation['suggested_priority'] ?? null,
                'ai_remediation_generated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'AI remediation could not be generated. No AI result was saved.');
        }

        return back()->with('success', 'AI remediation suggestion generated. It is advisory and does not confirm the finding.');
    }

    public function verify(Request $request, Finding $finding): RedirectResponse
    {
        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(Finding::STATUSES)],
            'assessor_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $needsReview = $validated['verification_status'] === Finding::STATUS_NEEDS_REVIEW;

        $finding->update([
            'verification_status' => $validated['verification_status'],
            'assessor_notes' => $validated['assessor_notes'] ?? null,
            'verified_by' => $needsReview ? null : $request->user()?->id,
            'verified_at' => $needsReview ? null : now(),
        ]);

        SystemActivity::log(
            'Security',
            'Updated finding verification',
            "Marked \"{$finding->title}\" as {$finding->verification_status}.",
            'amber'
        );

        return back()->with('success', 'Human verification decision saved.');
    }
}
