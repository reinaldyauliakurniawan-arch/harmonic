<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->enum('status', ['pending', 'active', 'suspended', 'deleted'])->index();
            $table->enum('signup_source', ['invite', 'self']);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->unsignedTinyInteger('week_start_day')->default(1);
            $table->date('quarter_start_date')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->unsignedTinyInteger('evaluator_bar_max_mixed')->default(1);
            $table->unsignedTinyInteger('evaluator_bar_max_minus')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->date('purge_after')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['invited', 'active', 'departed'])->index();
            $table->boolean('is_org_admin')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('seats');
            $table->string('title', 120);
            $table->json('responsibilities');
            $table->foreignId('holder_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('team_name', 60)->nullable();
            $table->boolean('is_leadership_team')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unsignedTinyInteger('meeting_weekday')->nullable();
            $table->time('meeting_time')->nullable();
            $table->unsignedSmallInteger('meeting_minutes')->default(90);
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email', 190)->index();
            $table->char('token_hash', 64)->unique();
            $table->boolean('make_org_admin')->default(false);
            $table->foreignId('seat_id')->nullable()->constrained('seats')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('scheduler_runs', function (Blueprint $table) {
            $table->id();
            // Nullable: platform-wide jobs (organization purge) have no organization.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('job', 60);
            $table->timestamp('last_run_at');
            $table->unique(['organization_id', 'job']);
        });
    }

    public function down(): void
    {
        foreach (['scheduler_runs', 'activity_log', 'invitations', 'seats', 'memberships', 'organizations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
