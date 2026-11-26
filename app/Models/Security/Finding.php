<?php

namespace App\Models\Security;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    public const STATUS_NEEDS_REVIEW = 'Needs Review';

    public const STATUS_CONFIRMED = 'Confirmed';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_CONFIRMED,
        self::STATUS_REJECTED,
    ];

    public const SEVERITIES = [
        'Critical',
        'High',
        'Medium',
        'Low',
        'Informational',
    ];

    protected $fillable = [
        'scan_id',
        'title',
        'severity',
        'confidence',
        'affected_url',
        'http_method',
        'parameter',
        'evidence',
        'description',
        'solution',
        'reference',
        'cwe_id',
        'wasc_id',
        'plugin_id',
        'fingerprint',
        'raw_alert',
        'ai_explanation',
        'ai_recommendation',
        'suggested_priority',
        'ai_analysis_generated_at',
        'ai_remediation_generated_at',
        'verification_status',
        'assessor_notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_alert' => 'array',
            'ai_analysis_generated_at' => 'datetime',
            'ai_remediation_generated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Decode the AI interpretation payload for display.
     */
    public function aiExplanation(): ?array
    {
        return $this->decodeAiPayload($this->ai_explanation);
    }

    /**
     * Decode the AI remediation payload for display.
     */
    public function aiRecommendation(): ?array
    {
        return $this->decodeAiPayload($this->ai_recommendation);
    }

    private function decodeAiPayload(?string $payload): ?array
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
