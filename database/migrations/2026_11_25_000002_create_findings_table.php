<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('scans')->cascadeOnDelete();

            // Original scanner information
            $table->string('title');
            $table->string('severity', 20)->default('Informational');
            $table->string('confidence', 20)->nullable();
            $table->text('affected_url')->nullable();
            $table->string('http_method', 16)->nullable();
            $table->string('parameter')->nullable();
            $table->text('evidence')->nullable();
            $table->longText('description')->nullable();
            $table->longText('solution')->nullable();
            $table->longText('reference')->nullable();
            $table->string('cwe_id', 32)->nullable();
            $table->string('wasc_id', 32)->nullable();
            $table->string('plugin_id', 32)->nullable();
            $table->string('fingerprint', 64);
            $table->json('raw_alert')->nullable();

            // AI-generated, advisory content (never used to confirm a finding)
            $table->longText('ai_explanation')->nullable();
            $table->longText('ai_recommendation')->nullable();
            $table->string('suggested_priority', 20)->nullable();
            $table->timestamp('ai_analysis_generated_at')->nullable();
            $table->timestamp('ai_remediation_generated_at')->nullable();

            // Human verification
            $table->string('verification_status', 20)->default('Needs Review');
            $table->text('assessor_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['scan_id', 'fingerprint']);
            $table->index(['severity', 'verification_status']);
            $table->index('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
