<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicates so we can safely add a unique constraint.
        // Keep the most recent row (highest id) for each (token, bucket).
        $duplicates = DB::table('dataset_exports')
            ->select('token', 'bucket', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as row_count'))
            ->groupBy('token', 'bucket')
            ->having('row_count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('dataset_exports')
                ->where('token', $dup->token)
                ->where('bucket', $dup->bucket)
                ->where('id', '<>', $dup->keep_id)
                ->delete();
        }

        Schema::table('dataset_exports', function (Blueprint $table) {
            $table->unique(['token', 'bucket'], 'dataset_exports_token_bucket_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_exports', function (Blueprint $table) {
            $table->dropUnique('dataset_exports_token_bucket_unique');
        });
    }
};
