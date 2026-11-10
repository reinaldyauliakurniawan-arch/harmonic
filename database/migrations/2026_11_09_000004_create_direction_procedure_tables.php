<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beliefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fundamentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->unsignedInteger('version');
            $table->enum('status', ['draft', 'published'])->index();
            $table->json('content');
            $table->foreignId('published_by')->nullable()->constrained('memberships');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'version']);
        });

        Schema::create('alignment_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('fundamentals_id')->constrained('fundamentals');
            $table->foreignId('opened_by')->constrained('memberships');
            $table->timestamp('opened_at');
            $table->timestamp('closes_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            // Denominator for "responses / members at round open" (PRD A-03).
            $table->unsignedInteger('member_count')->default(0);
            // One reminder per round (PRD A-04); without email it is a Home banner bump.
            $table->timestamp('reminded_at')->nullable();
        });

        Schema::create('alignment_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('alignment_rounds');
            $table->foreignId('member_id')->constrained('memberships');
            $table->timestamp('completed_at');
            $table->unique(['round_id', 'member_id']);
        });

        Schema::create('alignment_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('round_id')->constrained('alignment_rounds');
            $table->string('part_key', 30);
            $table->boolean('clear');
            $table->boolean('committed')->nullable();
            $table->text('comment')->nullable();
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->index();
            $table->foreignId('owner_seat_id')->index();
            $table->string('title', 160);
            $table->text('purpose');
            $table->mediumText('body')->nullable();
            $table->enum('audience', ['team', 'organization'])->default('team');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->unsignedTinyInteger('review_every_months')->default(12);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('procedure_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained();
            $table->unsignedInteger('version');
            $table->mediumText('body');
            $table->foreignId('published_by')->constrained('memberships');
            $table->timestamp('published_at');
            $table->unique(['procedure_id', 'version']);
        });

        Schema::create('procedure_acks', function (Blueprint $table) {
            $table->foreignId('procedure_id')->constrained();
            $table->unsignedInteger('version');
            $table->foreignId('member_id')->constrained('memberships');
            $table->timestamp('acknowledged_at');
            $table->primary(['procedure_id', 'version', 'member_id']);
        });

        Schema::create('sync_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained();
            $table->unsignedInteger('procedure_version');
            $table->foreignId('auditor_id')->constrained('memberships');
            $table->date('audited_on')->index();
            $table->enum('result', ['followed', 'partly', 'not_followed']);
            $table->text('findings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sync_audits', 'procedure_acks', 'procedure_versions', 'procedures', 'alignment_answers',
            'alignment_completions', 'alignment_rounds', 'fundamentals', 'beliefs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
