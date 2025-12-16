<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAcceptRejectFlagsToCallCenterRowAssignments extends Migration
{
    public function up()
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_row_assignments', 'accepted')) {
                $table->boolean('accepted')->default(false)->after('status')->index();
            }

            if (! Schema::hasColumn('call_center_row_assignments', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted');
            }

            if (! Schema::hasColumn('call_center_row_assignments', 'rejected')) {
                $table->boolean('rejected')->default(false)->after('accepted_at')->index();
            }

            if (! Schema::hasColumn('call_center_row_assignments', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected');
            }

            if (! Schema::hasColumn('call_center_row_assignments', 'rejected_by')) {
                $table->unsignedInteger('rejected_by')->nullable()->after('rejected_at')->index();
            }

            if (! Schema::hasColumn('call_center_row_assignments', 'rejection_note')) {
                $table->text('rejection_note')->nullable()->after('rejected_by');
            }

            if (Schema::hasColumn('call_center_row_assignments', 'rejected_by')) {
                DB::statement('ALTER TABLE `call_center_row_assignments` MODIFY `rejected_by` INT UNSIGNED NULL');
            }

            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_row_assignments', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }

            $table->dropColumn(array_filter([
                Schema::hasColumn('call_center_row_assignments', 'accepted') ? 'accepted' : null,
                Schema::hasColumn('call_center_row_assignments', 'accepted_at') ? 'accepted_at' : null,
                Schema::hasColumn('call_center_row_assignments', 'rejected') ? 'rejected' : null,
                Schema::hasColumn('call_center_row_assignments', 'rejected_at') ? 'rejected_at' : null,
                Schema::hasColumn('call_center_row_assignments', 'rejected_by') ? 'rejected_by' : null,
                Schema::hasColumn('call_center_row_assignments', 'rejection_note') ? 'rejection_note' : null,
            ]));
        });
    }
}
