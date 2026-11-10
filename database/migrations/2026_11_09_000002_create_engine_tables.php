<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->nullable()->index();
            $table->string('title', 120);
            $table->foreignId('created_by')->constrained('memberships');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained();
            $table->string('title', 60);
            $table->boolean('is_done_column')->default(false);
            $table->integer('sort_order')->default(0)->index();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('team_seat_id')->nullable()->index();
            $table->foreignId('board_id')->nullable()->index();
            $table->foreignId('board_column_id')->nullable()->index();
            $table->integer('position')->default(0);
            $table->string('title', 160);
            $table->text('output');
            $table->text('definition_of_done')->nullable();
            $table->date('due_date')->index();
            $table->enum('status', ['todo', 'doing', 'done', 'cancelled'])->default('todo')->index();
            $table->foreignId('responsible_id')->constrained('memberships');
            $table->foreignId('accountable_id')->constrained('memberships');
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('memberships');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('task_people', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained();
            $table->foreignId('member_id')->constrained('memberships');
            $table->enum('role', ['consulted', 'informed']);
            $table->primary(['task_id', 'member_id', 'role']);
        });

        Schema::create('task_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained();
            $table->string('title', 200);
            $table->timestamp('done_at')->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('event_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->enum('rule', ['weekly', 'biweekly', 'monthly', 'quarterly']);
            $table->date('until')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('series_id')->nullable()->index();
            $table->foreignId('team_seat_id')->nullable()->index();
            $table->enum('scope', ['org', 'team', 'pair', 'private'])->index();
            $table->enum('type', ['custom', 'weekly_meeting', 'quarterly_session', 'annual_session', 'guide_checkin',
                'guide_shoutout', 'guide_reflection', 'guide_passion', 'diagnostic', 'assessment']);
            $table->string('title', 160);
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(false);
            $table->foreignId('partner_id')->nullable()->constrained('memberships');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['events', 'event_series', 'task_steps', 'task_people', 'tasks', 'board_columns', 'boards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
