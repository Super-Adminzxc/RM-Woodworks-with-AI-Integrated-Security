<?php

namespace Tests\Feature\Security;

use App\Models\Security\Finding;
use App\Models\Security\Scan;
use App\Models\System\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_security_dashboard(): void
    {
        $response = $this->actingAs($this->admin())->get(route('security.index'));

        $response->assertOk();
        $response->assertSee('RM Woodworks SecureScan');
        $response->assertSee('Import OWASP ZAP JSON Report');
    }

    public function test_non_admin_cannot_access_security_assessment(): void
    {
        $user = User::factory()->create(['role' => 'sales_clerk']);

        $this->actingAs($user)->get(route('security.index'))->assertForbidden();
    }

    public function test_zap_report_import_creates_findings_with_needs_review_status(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('security.import'), [
                'report' => $this->reportUpload(),
                'target_name' => 'RM Woodworks',
            ]);

        $response->assertRedirect(route('security.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('scans', 1);
        $this->assertDatabaseCount('findings', 2);

        $this->assertDatabaseHas('scans', [
            'target_name' => 'RM Woodworks',
            'tool_name' => 'OWASP ZAP',
            'findings_count' => 2,
        ]);

        $this->assertDatabaseHas('findings', [
            'title' => 'SQL Injection',
            'severity' => 'High',
            'confidence' => 'Medium',
            'verification_status' => Finding::STATUS_NEEDS_REVIEW,
            'affected_url' => 'http://localhost:8000/products?q=test',
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $this->assertNotNull($finding->raw_alert);
        $this->assertSame('q', $finding->parameter);
        $this->assertStringContainsString('SQL syntax', $finding->evidence);
    }

    public function test_dashboard_statistics_reflect_imported_findings(): void
    {
        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('security.index'));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats) {
            return $stats['total'] === 2
                && $stats['high'] === 1
                && $stats['medium'] === 1
                && $stats['needs_review'] === 2
                && $stats['confirmed'] === 0
                && $stats['rejected'] === 0;
        });
    }

    public function test_malformed_json_is_rejected_without_creating_data(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('security.index'))
            ->post(route('security.import'), [
                'report' => UploadedFile::fake()->createWithContent('broken.json', '{not valid json'),
            ]);

        $response->assertRedirect(route('security.index'));
        $response->assertSessionHasErrors('report');

        $this->assertDatabaseCount('scans', 0);
        $this->assertDatabaseCount('findings', 0);
    }

    public function test_unsupported_json_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('security.index'))
            ->post(route('security.import'), [
                'report' => UploadedFile::fake()->createWithContent('other.json', json_encode(['hello' => 'world'])),
            ]);

        $response->assertSessionHasErrors('report');
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_import_handles_missing_optional_alert_fields(): void
    {
        $report = [
            'site' => [[
                '@name' => 'http://localhost:8000',
                'alerts' => [[
                    'alert' => 'Minimal Alert',
                    'instances' => [[
                        'uri' => 'http://localhost:8000/minimal',
                    ]],
                ]],
            ]],
        ];

        $response = $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => UploadedFile::fake()->createWithContent('minimal.json', json_encode($report)),
        ]);

        $response->assertSessionHas('success');

        $finding = Finding::where('title', 'Minimal Alert')->firstOrFail();

        $this->assertSame('Informational', $finding->severity);
        $this->assertNull($finding->confidence);
        $this->assertNull($finding->description);
        $this->assertNull($finding->evidence);
        $this->assertSame(Finding::STATUS_NEEDS_REVIEW, $finding->verification_status);
    }

    public function test_duplicate_report_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('security.index'))
            ->post(route('security.import'), [
                'report' => $this->reportUpload(),
            ]);

        $response->assertSessionHasErrors('report');
        $this->assertDatabaseCount('scans', 1);
        $this->assertDatabaseCount('findings', 2);
    }

    public function test_finding_details_show_original_evidence_and_separate_ai_sections(): void
    {
        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($this->admin())->get(route('security.show', $finding));

        $response->assertOk();
        $response->assertSee('Original Scanner Evidence');
        $response->assertSee('Scanner output — not AI');
        $response->assertSee('AI Analysis');
        $response->assertSee('AI-generated — advisory, unverified');
        $response->assertSee('Human Verification');
        $response->assertSee('You have an error in your SQL syntax');
    }

    public function test_human_verification_updates_status_and_notes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('security.verify', $finding), [
            'verification_status' => Finding::STATUS_CONFIRMED,
            'assessor_notes' => 'Manually reproduced with a safe test string.',
        ]);

        $response->assertSessionHas('success');

        $finding->refresh();

        $this->assertSame(Finding::STATUS_CONFIRMED, $finding->verification_status);
        $this->assertSame('Manually reproduced with a safe test string.', $finding->assessor_notes);
        $this->assertSame($admin->id, $finding->verified_by);
        $this->assertNotNull($finding->verified_at);

        $this->actingAs($admin)->get(route('security.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['confirmed'] === 1 && $stats['needs_review'] === 1);
    }

    public function test_ai_actions_report_unconfigured_provider_without_saving_fake_results(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => null,
        ]);

        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('security.analyze', $finding));

        $response->assertSessionHas('error');
        $this->assertNull($finding->fresh()->ai_explanation);
        $this->assertSame(Finding::STATUS_NEEDS_REVIEW, $finding->fresh()->verification_status);
    }

    public function test_ai_analysis_is_saved_and_cannot_confirm_the_finding(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'vulnerability_explanation' => 'The parameter may reach a SQL query.',
                                'technical_interpretation' => 'The scanner saw a database error after a quote was supplied.',
                                'potential_impact' => 'An attacker could read or modify data if the issue is genuine.',
                                'why_it_matters' => 'Database compromise would affect sensitive business records.',
                                'additional_checks' => ['Confirm the query is parameterized.'],
                                'limitations' => 'The evidence is a scanner error message and requires manual review.',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('security.analyze', $finding));

        $response->assertSessionHas('success');

        $finding->refresh();
        $analysis = json_decode($finding->ai_explanation, true);

        $this->assertSame('The parameter may reach a SQL query.', $analysis['vulnerability_explanation']);
        $this->assertSame(['Confirm the query is parameterized.'], $analysis['additional_checks']);
        $this->assertNotNull($finding->ai_analysis_generated_at);
        $this->assertSame(Finding::STATUS_NEEDS_REVIEW, $finding->verification_status);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($prompt, 'SQL Injection')
                && str_contains($prompt, 'You have an error in your SQL syntax');
        });
    }

    public function test_ai_remediation_is_saved_and_cannot_confirm_the_finding(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'corrective_actions' => ['Use parameterized queries.'],
                                'implementation_approach' => 'Refactor the query to use Laravel query bindings.',
                                'security_improvements' => ['Add validation and least-privilege database accounts.'],
                                'suggested_priority' => 'High',
                                'priority_reason' => 'The scanner reported a high-risk SQL error.',
                                'verification_steps' => ['Re-test the parameter with safe input after the fix.'],
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('security.remediate', $finding));

        $response->assertSessionHas('success');

        $finding->refresh();
        $recommendation = json_decode($finding->ai_recommendation, true);

        $this->assertSame(['Use parameterized queries.'], $recommendation['corrective_actions']);
        $this->assertSame('High', $recommendation['suggested_priority']);
        $this->assertSame('High', $finding->suggested_priority);
        $this->assertNotNull($finding->ai_remediation_generated_at);
        $this->assertSame(Finding::STATUS_NEEDS_REVIEW, $finding->verification_status);
    }

    public function test_labeled_sample_fixture_imports_and_covers_all_severities(): void
    {
        $path = public_path('samples/rmwoodworks-zap-sample-report.json');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);

        $fixture = json_decode($contents, true);
        $this->assertTrue($fixture['sample_data'] ?? false);
        $this->assertStringContainsString('SAMPLE FIXTURE', $fixture['@programName'] ?? '');

        $response = $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => UploadedFile::fake()->createWithContent('rmwoodworks-zap-sample-report.json', $contents),
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(9, Finding::count());

        $scan = Scan::firstOrFail();
        $this->assertTrue($scan->isSampleData());
        $this->assertStringContainsString('SAMPLE FIXTURE', $scan->report_metadata['@programName']);

        foreach (['Critical', 'High', 'Medium', 'Low', 'Informational'] as $severity) {
            $this->assertGreaterThan(0, Finding::where('severity', $severity)->count(), "Missing {$severity} fixture finding.");
        }

        $this->assertSame(9, Finding::where('verification_status', Finding::STATUS_NEEDS_REVIEW)->count());

        $this->actingAs($this->admin())->get(route('security.index'))
            ->assertOk()
            ->assertSee('Sample fixture — not a live scan');

        $finding = Finding::firstOrFail();
        $this->actingAs($this->admin())->get(route('security.show', $finding))
            ->assertOk()
            ->assertSee('Sample fixture — not a live scan')
            ->assertSee('Clearly labeled sample data for interface testing only.');
    }

    public function test_invalid_ai_response_is_not_saved(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'This is not JSON.']]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())->post(route('security.import'), [
            'report' => $this->reportUpload(),
        ]);

        $finding = Finding::where('title', 'SQL Injection')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('security.analyze', $finding));

        $response->assertSessionHas('error');
        $this->assertNull($finding->fresh()->ai_explanation);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function reportUpload(): UploadedFile
    {
        $report = [
            'site' => [[
                '@name' => 'http://localhost:8000',
                'alerts' => [
                    [
                        'pluginid' => '40018',
                        'alert' => 'SQL Injection',
                        'riskdesc' => 'High (Medium)',
                        'riskcode' => '3',
                        'confidence' => '2',
                        'desc' => '<p>SQL injection may be possible.</p>',
                        'instances' => [[
                            'uri' => 'http://localhost:8000/products?q=test',
                            'method' => 'GET',
                            'param' => 'q',
                            'evidence' => 'You have an error in your SQL syntax',
                        ]],
                        'solution' => '<p>Use parameterized queries.</p>',
                        'cweid' => '89',
                        'wascid' => '19',
                    ],
                    [
                        'pluginid' => '10020',
                        'alert' => 'Missing Anti-clickjacking Header',
                        'riskdesc' => 'Medium (Medium)',
                        'riskcode' => '2',
                        'confidence' => '2',
                        'desc' => '<p>The response can be framed.</p>',
                        'instances' => [[
                            'uri' => 'http://localhost:8000/dashboard',
                            'method' => 'GET',
                            'evidence' => 'X-Frame-Options header is missing',
                        ]],
                        'solution' => '<p>Set an X-Frame-Options header.</p>',
                        'cweid' => '1021',
                        'wascid' => '15',
                    ],
                ],
            ]],
        ];

        return UploadedFile::fake()->createWithContent('zap-report.json', json_encode($report));
    }
}
