<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PRD F-04: sessions started before this moment are signed out (role change, departure, anonymization).
        // File sessions can't be listed, so each request compares its sign-in time with this column.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sessions_valid_from')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('sessions_valid_from'));
    }
};
