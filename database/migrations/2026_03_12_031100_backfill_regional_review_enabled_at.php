<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('enable_regional_review', 1)
            ->whereNull('enable_regional_review_enabled_at')
            ->update(['enable_regional_review_enabled_at' => now()]);
    }

    public function down(): void
    {
        // No-op: backfill is irreversible by design.
    }
};
