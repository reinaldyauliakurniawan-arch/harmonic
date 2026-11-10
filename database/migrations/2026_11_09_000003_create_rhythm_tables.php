<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->index();
            $table->foreignId('owner_seat_id')->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('unit', ['number', 'percent', 'currency', 'hours']);
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->enum('operator', ['gte', 'lte', 'eq', 'gt', 'lt', 'between']);
            $table->decimal('goal_value', 15, 4);
            $table->decimal('goal_value_max', 15, 4)->nullable();
            $table->enum('quarter_rollup', ['sum', 'average', 'latest'])->default('sum');
            $table->unsignedTinyInteger('auto_problem_after')->nullable()->default(2);
            $table->integer('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('metric_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained();
            $table->date('week_start');
            $table->decimal('value', 15, 4)->nullable();
            $table->enum('operator', ['gte', 'lte', 'eq', 'gt', 'lt', 'between']);
            $table->decimal('goal_value', 15, 4);
            $table->decimal('goal_value_max', 15, 4)->nullable();
            $table->enum('status', ['on', 'off'])->nullable();
            $table->string('note', 280)->nullable();
            $table->foreignId('entered_by')->constrained('memberships');
            $table->timestamps();
            $table->unique(['metric_id', 'week_start']);
        });

        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->enum('level', ['company', 'team', 'personal'])->index();
            $table->foreignId('team_seat_id')->nullable()->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->foreignId('owner_id')->constrained('memberships');
            $table->string('title', 160);
            $table->text('done_definition');
            $table->date('quarter_start')->index();
            $table->enum('status', ['on_track', 'off_track', 'done', 'not_done'])->default('on_track')->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->foreignId('carried_from_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('memberships');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mission_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained();
            $table->string('title', 160);
            $table->date('due_date')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->index();
            $table->enum('type', ['weekly', 'quarterly', 'annual']);
            $table->foreignId('event_id')->nullable()->index();
            $table->dateTime('scheduled_for')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedTinyInteger('segment')->default(0);
            // Facilitator: the member who started the meeting; only they advance segments (PRD ME-02).
            $table->foreignId('facilitator_id')->nullable()->constrained('memberships');
            // Segment start time drives the shared countdown for every participant.
            $table->dateTime('segment_started_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('cascade_message')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_attendance', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained();
            $table->foreignId('member_id')->constrained('memberships');
            $table->boolean('present');
            $table->primary(['meeting_id', 'member_id']);
        });

        Schema::create('meeting_ratings', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained();
            $table->foreignId('member_id')->constrained('memberships');
            $table->unsignedTinyInteger('score');
            $table->primary(['meeting_id', 'member_id']);
        });

        Schema::create('meeting_headlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained();
            $table->foreignId('member_id')->constrained('memberships');
            $table->enum('kind', ['customer', 'employee']);
            $table->string('text', 280);
        });

        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->index();
            $table->foreignId('raised_by')->constrained('memberships');
            $table->foreignId('owner_id')->nullable()->constrained('memberships');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->enum('horizon', ['short', 'long'])->default('short')->index();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->enum('status', ['open', 'solving', 'solved', 'dropped'])->default('open')->index();
            // PRD P-04 requires a drop reason and a duplicate link; the schema in section 6 had no place for them.
            $table->string('drop_reason', 280)->nullable();
            $table->foreignId('duplicate_of_id')->nullable();
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->foreignId('meeting_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('solve_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->unique()->constrained();
            $table->enum('mode', ['quick', 'full']);
            $table->text('ignite')->nullable();
            $table->text('statement')->nullable();
            $table->text('observe')->nullable();
            $table->text('leverage')->nullable();
            $table->text('solution')->nullable();
            $table->date('evaluate_on')->nullable()->index();
            $table->text('evaluation')->nullable();
            $table->enum('evaluation_result', ['worked', 'partly', 'failed'])->nullable();
            // Full-mode stepper position, so a half-finished solve resumes where it stopped.
            $table->unsignedTinyInteger('step')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['solve_records', 'problems', 'meeting_headlines', 'meeting_ratings', 'meeting_attendance', 'meetings',
            'mission_milestones', 'missions', 'metric_values', 'metrics'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
