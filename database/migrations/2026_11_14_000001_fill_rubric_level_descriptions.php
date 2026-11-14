<?php

use App\Services\Onboarding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Organizations created before default rubric texts existed have empty descriptions; fill only those, never overwrite an admin's text.
    public function up(): void
    {
        foreach (Onboarding::RUBRIC_TEXT as $score => $text) {
            DB::table('rubric_levels')->where('score', $score)->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))->update(['description' => $text]);
        }
    }

    public function down(): void {}
};
