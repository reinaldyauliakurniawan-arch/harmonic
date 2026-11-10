<?php

use App\Services\Onboarding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reason: each leadership item describes what each of the five levels looks like for that item, so assessors rate against it.
        Schema::table('assessment_items', function (Blueprint $table) {
            $table->json('levels')->nullable()->after('text');
        });

        foreach (DB::table('organizations')->pluck('id') as $org) {
            if (! DB::table('assessment_types')->where('organization_id', $org)->exists()) {
                Onboarding::seedLeadership($org);
            }
        }
    }

    public function down(): void
    {
        Schema::table('assessment_items', function (Blueprint $table) {
            $table->dropColumn('levels');
        });
    }
};
