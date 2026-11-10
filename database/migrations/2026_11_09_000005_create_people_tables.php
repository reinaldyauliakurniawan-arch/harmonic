<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('subject_id')->constrained('memberships');
            $table->foreignId('seat_id')->index();
            $table->foreignId('evaluator_id')->constrained('memberships');
            $table->date('evaluated_on')->index();
            $table->boolean('understands');
            $table->boolean('wants');
            $table->boolean('capacity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->foreignId('evaluation_id')->constrained();
            $table->foreignId('belief_id')->constrained();
            $table->enum('score', ['plus', 'mixed', 'minus']);
            $table->primary(['evaluation_id', 'belief_id']);
        });

        Schema::create('diagnostic_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('opened_by')->constrained('memberships');
            $table->timestamp('opened_at');
            $table->timestamp('closes_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
        });

        Schema::create('diagnostic_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('diagnostic_cycles');
            $table->foreignId('member_id')->constrained('memberships');
            $table->timestamp('completed_at');
            $table->unique(['cycle_id', 'member_id']);
        });

        Schema::create('diagnostic_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('cycle_id')->constrained('diagnostic_cycles');
            $table->string('question_key', 20);
            $table->unsignedTinyInteger('score');
        });

        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name', 80);
            $table->integer('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('assessment_types');
            $table->string('text', 255);
            $table->integer('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->unsignedTinyInteger('score');
            $table->string('label', 40);
            $table->string('description', 255)->nullable();
        });

        Schema::create('assessment_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name', 120);
            $table->date('opens_on')->index();
            $table->date('closes_on')->index();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('assessment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('assessment_cycles');
            $table->foreignId('subject_id')->constrained('memberships');
            $table->foreignId('type_id')->constrained('assessment_types');
            $table->unique(['cycle_id', 'subject_id', 'type_id']);
        });

        Schema::create('assessment_assessors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assessment_assignments');
            $table->foreignId('assessor_id')->constrained('memberships');
            $table->boolean('is_self')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unique(['assignment_id', 'assessor_id']);
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('assignment_id')->constrained('assessment_assignments');
            $table->foreignId('item_id')->constrained('assessment_items');
            $table->decimal('score', 3, 2);
            $table->boolean('is_self')->default(false);
        });
    }

    public function down(): void
    {
        foreach (['assessment_answers', 'assessment_assessors', 'assessment_assignments', 'assessment_cycles',
            'rubric_levels', 'assessment_items', 'assessment_types', 'diagnostic_answers', 'diagnostic_completions',
            'diagnostic_cycles', 'evaluation_scores', 'evaluations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
