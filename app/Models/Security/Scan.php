<?php

namespace App\Models\Security;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'target_name',
        'target_url',
        'tool_name',
        'source_filename',
        'report_hash',
        'report_metadata',
        'findings_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'findings_count' => 'integer',
            'report_metadata' => 'array',
        ];
    }

    /**
     * Whether the imported report identified itself as a labeled sample fixture.
     */
    public function isSampleData(): bool
    {
        $metadata = $this->report_metadata ?? [];

        if (($metadata['sample_data'] ?? false) === true) {
            return true;
        }

        return str_contains(strtoupper((string) ($metadata['@programName'] ?? '')), 'SAMPLE');
    }

    public function sampleNotice(): ?string
    {
        $notice = $this->report_metadata['sample_notice'] ?? null;

        return is_string($notice) && trim($notice) !== '' ? $notice : null;
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
