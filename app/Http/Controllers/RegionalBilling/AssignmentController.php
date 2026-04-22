<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request) { abort(501); }
    public function manage(Request $request) { abort(501); }
    public function acceptAll(Request $request, $userId) { abort(501); }
    public function rejectAll(Request $request, $userId) { abort(501); }
    public function userRows(Request $request, $userId) { abort(501); }
    public function assignmentDetails(Request $request, $assignmentId) { abort(501); }
    public function claim($id) { abort(501); }
    public function complete($id) { abort(501); }
    public function storeInteraction(Request $request, $assignmentId) { abort(501); }
    public function accept(Request $request, $id) { abort(501); }
    public function reject(Request $request, $id) { abort(501); }
    public function distribute(Request $request, $reportId) { abort(501); }
    public function cancelDistribute(Request $request, $reportId, $token) { abort(501); }
    public function recall(Request $request, $reportId) { abort(501); }
    public function recallPreview(Request $request, $reportId) { abort(501); }
    public function reassign(Request $request, $reportId) { abort(501); }
}
