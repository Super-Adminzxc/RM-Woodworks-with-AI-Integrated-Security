<?php

namespace App\Services\Security;

use App\Models\Security\Finding;
use App\Models\Security\Scan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class ZapReportImporter
{
    /**
     * Maximum accepted report size in bytes (10 MB).
     */
    private const MAX_REPORT_BYTES = 10 * 1024 * 1024;

    /**
     * Import an OWASP ZAP JSON report and persist its alerts as findings.
     *
     * @throws ValidationException
     */
    public function import(
        UploadedFile $file,
        ?string $targetName = null,
        ?string $targetUrl = null,
        ?int $userId = null
    ): Scan {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages([
                'report' => 'The uploaded file is empty or could not be read.',
            ]);
        }

        if (strlen($contents) > self::MAX_REPORT_BYTES) {
            throw ValidationException::withMessages([
                'report' => 'The report is too large. Upload a ZAP JSON report smaller than 10 MB.',
            ]);
        }

        // Tolerate a UTF-8 BOM, which some exporters add and json_decode rejects.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $reportHash = hash('sha256', $contents);

        if (Scan::where('report_hash', $reportHash)->exists()) {
            throw ValidationException::withMessages([
                'report' => 'This exact ZAP report has already been imported.',
            ]);
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'report' => 'The uploaded file is not valid JSON. Export the report from OWASP ZAP in JSON format and try again.',
            ]);
        }

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'report' => 'The uploaded JSON is not a supported OWASP ZAP report.',
            ]);
        }

        $sites = $this->extractSites($data);

        if ($sites === []) {
            throw ValidationException::withMessages([
                'report' => 'The uploaded JSON does not contain any OWASP ZAP site data.',
            ]);
        }

        $siteTarget = $this->firstSiteName($sites);

        $reportMetadata = $this->reportMetadata($data);

        return DB::transaction(function () use ($file, $targetName, $targetUrl, $userId, $reportHash, $reportMetadata, $sites, $siteTarget) {
            $scan = Scan::create([
                'user_id' => $userId,
                'target_name' => $targetName ?: 'RM Woodworks',
                'target_url' => $targetUrl ?: $siteTarget,
                'tool_name' => 'OWASP ZAP',
                'source_filename' => $file->getClientOriginalName(),
                'report_hash' => $reportHash,
                'report_metadata' => $reportMetadata,
                'findings_count' => 0,
                'imported_at' => now(),
            ]);

            $created = 0;
            $seenFingerprints = [];

            foreach ($sites as $site) {
                foreach ($this->siteAlerts($site) as $alert) {
                    foreach ($this->alertInstances($alert, $site) as $instance) {
                        $attributes = $this->findingAttributes($alert, $instance, $site);

                        if (isset($seenFingerprints[$attributes['fingerprint']])) {
                            continue;
                        }

                        $seenFingerprints[$attributes['fingerprint']] = true;
                        $scan->findings()->create($attributes);
                        $created++;
                    }
                }
            }

            $scan->update(['findings_count' => $created]);

            return $scan->fresh();
        });
    }

    /**
     * Preserve top-level report metadata (version, program name, sample labels).
     *
     * @return array<string, mixed>|null
     */
    private function reportMetadata(array $data): ?array
    {
        unset($data['site'], $data['alerts']);

        if ($data === [] || array_is_list($data)) {
            return null;
        }

        $metadata = array_filter($data, fn ($value) => $value !== null && $value !== '');

        return $metadata === [] ? null : $metadata;
    }

    /**
     * Normalize the various JSON shapes produced by ZAP into a list of sites.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractSites(array $data): array
    {
        $sites = [];

        if (isset($data['site'])) {
            $sites = $data['site'];
        } elseif (isset($data['alerts']) || isset($data['@name'])) {
            $sites = [$data];
        } elseif (array_is_list($data)) {
            if ($data === []) {
                return [];
            }

            $first = $data[0] ?? null;

            if (is_array($first) && (isset($first['alerts']) || isset($first['@name']))) {
                $sites = $data;
            } elseif (is_array($first) && isset($first['alert'])) {
                // A flat array of alerts.
                $sites = [['alerts' => $data]];
            }
        }

        if (! is_array($sites)) {
            return [];
        }

        // A single site object may be passed instead of a list of sites.
        if (isset($sites['alerts']) || isset($sites['@name'])) {
            $sites = [$sites];
        }

        return array_values(array_filter($sites, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sites
     */
    private function firstSiteName(array $sites): ?string
    {
        foreach ($sites as $site) {
            $name = $this->stringValue($site['@name'] ?? null);

            if ($name !== null) {
                return Str::limit($name, 2048, '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<int, array<string, mixed>>
     */
    private function siteAlerts(array $site): array
    {
        $alerts = $site['alerts'] ?? [];

        if (! is_array($alerts)) {
            return [];
        }

        // Some API-shaped exports return a single alert object instead of a list.
        if (isset($alerts['alert']) || isset($alerts['name'])) {
            return [$alerts];
        }

        return array_values(array_filter($alerts, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $alert
     * @param  array<string, mixed>  $site
     * @return array<int, array<string, mixed>>
     */
    private function alertInstances(array $alert, array $site): array
    {
        $instances = $alert['instances'] ?? [];

        if (! is_array($instances) || $instances === []) {
            return [[
                'uri' => $this->stringValue($site['@name'] ?? null),
            ]];
        }

        // Some API-shaped exports return a single instance object instead of a list.
        if (isset($instances['uri']) || isset($instances['url']) || isset($instances['method'])) {
            return [$instances];
        }

        return array_values(array_filter($instances, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $alert
     * @param  array<string, mixed>  $instance
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function findingAttributes(array $alert, array $instance, array $site): array
    {
        $title = $this->stringValue(
            $alert['alert'] ?? $alert['name'] ?? $alert['title'] ?? null
        ) ?? 'Untitled ZAP alert';

        $severity = $this->severity($alert);
        $confidence = $this->confidence($alert);
        $affectedUrl = $this->stringValue(
            $instance['uri'] ?? $instance['url'] ?? $site['@name'] ?? null
        );
        $method = $this->stringValue($instance['method'] ?? null);
        $parameter = $this->stringValue($instance['param'] ?? $instance['parameter'] ?? null);
        $evidence = $this->cleanText($instance['evidence'] ?? null, 5000);
        $pluginId = $this->stringValue($alert['pluginid'] ?? $alert['pluginId'] ?? null);

        $fingerprint = hash('sha256', implode('|', [
            $title,
            $severity,
            $affectedUrl ?? '',
            $method ?? '',
            $parameter ?? '',
            $evidence ?? '',
            $pluginId ?? '',
        ]));

        return [
            'title' => Str::limit($title, 255, ''),
            'severity' => $severity,
            'confidence' => $confidence,
            'affected_url' => $affectedUrl,
            'http_method' => $method ? Str::upper(Str::limit($method, 16, '')) : null,
            'parameter' => $parameter ? Str::limit($parameter, 255, '') : null,
            'evidence' => $evidence,
            'description' => $this->cleanText($alert['desc'] ?? $alert['description'] ?? null),
            'solution' => $this->cleanText($alert['solution'] ?? null),
            'reference' => $this->cleanText($alert['reference'] ?? null),
            'cwe_id' => $this->stringValue($alert['cweid'] ?? null),
            'wasc_id' => $this->stringValue($alert['wascid'] ?? null),
            'plugin_id' => $pluginId,
            'fingerprint' => $fingerprint,
            'raw_alert' => [
                'alert' => $alert,
                'instance' => $instance,
                'site' => [
                    '@name' => $site['@name'] ?? null,
                    '@host' => $site['@host'] ?? null,
                    '@port' => $site['@port'] ?? null,
                    '@ssl' => $site['@ssl'] ?? null,
                ],
            ],
            'verification_status' => Finding::STATUS_NEEDS_REVIEW,
        ];
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function severity(array $alert): string
    {
        // ZAP's riskdesc is usually "High (Medium)" where the first value is
        // the risk level and the parenthesised value is confidence.
        $riskDescription = (string) ($alert['riskdesc'] ?? $alert['risk'] ?? '');
        $risk = Str::lower(trim(explode('(', $riskDescription, 2)[0]));

        if (Str::contains($risk, 'critical')) {
            return 'Critical';
        }

        if (Str::contains($risk, 'high')) {
            return 'High';
        }

        if (Str::contains($risk, 'medium')) {
            return 'Medium';
        }

        if (Str::contains($risk, 'low')) {
            return 'Low';
        }

        if (Str::contains($risk, 'informational') || Str::contains($risk, 'info')) {
            return 'Informational';
        }

        // Fall back to ZAP's numeric risk code when riskdesc is absent.
        $riskCode = $alert['riskcode'] ?? null;

        if (is_numeric($riskCode)) {
            return match ((int) $riskCode) {
                3 => 'High',
                2 => 'Medium',
                1 => 'Low',
                default => 'Informational',
            };
        }

        return 'Informational';
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function confidence(array $alert): ?string
    {
        $confidence = $alert['confidence'] ?? null;

        if ($confidence === null) {
            $riskDescription = (string) ($alert['riskdesc'] ?? '');

            if (preg_match('/\(([^)]+)\)/', $riskDescription, $matches)) {
                $confidence = $matches[1];
            }
        }

        if (is_numeric($confidence)) {
            return match ((int) $confidence) {
                0 => 'False Positive',
                1 => 'Low',
                2 => 'Medium',
                3 => 'High',
                4 => 'User Confirmed',
                default => null,
            };
        }

        $confidence = $this->stringValue($confidence);

        return $confidence ? Str::limit(Str::title($confidence), 20, '') : null;
    }

    /**
     * Convert scanner HTML into safe, readable plain text.
     */
    private function cleanText(mixed $value, int $limit = 10000): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = (string) $value;

        if (trim($text) === '') {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(p|div|li|tr|h[1-6])>/i', "\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
        $text = trim((string) $text);

        return $text === '' ? null : Str::limit($text, $limit, '…');
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
