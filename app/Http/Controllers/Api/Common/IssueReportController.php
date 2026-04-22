<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Helpers\IssueReportNotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IssueReportController extends Controller
{
    /**
     * List reports for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = IssueReport::where('reporter_id', $user->id)
            ->with(['order', 'product', 'reportedUser']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->latest()->paginate(10);

        // Add full image URL to each report
        $reports->getCollection()->transform(function ($report) {
            $report->evidence_image_url = $report->evidence_image_url; // uses accessor
            return $report;
        });

        return response()->json($reports);
    }

    /**
     * Submit a new report.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:order,user,product,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'order_id' => 'nullable|exists:orders,id',
            'product_id' => 'nullable|exists:products,id',
            'reported_user_id' => 'nullable|exists:users,id',
            'evidence_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('evidence_image')) {
            $image = $request->file('evidence_image');
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('reports', $filename, 'public');
            $imagePath = '/storage/' . $path;
        }

        $report = IssueReport::create([
            'reporter_id' => auth()->id(),
            'reported_user_id' => $request->reported_user_id,
            'order_id' => $request->order_id,
            'product_id' => $request->product_id,
            'type' => $request->type,
            'subject' => $request->subject,
            'description' => $request->description,
            'evidence_image' => $imagePath,
            'status' => 'pending',
        ]);

        // Load the reporter relationship for notification
        $report->load('reporter');

        // ========== SEND NOTIFICATION TO ALL ADMINS ==========
        IssueReportNotificationHelper::sendNewReportNotificationToAdmins($report);

        return response()->json([
            'message' => 'Report submitted successfully. Our team will review it shortly.',
            'report' => $report
        ], 201);
    }

    /**
     * Show details of a specific report.
     */
    public function show($id)
    {
        $user = auth()->user();
        $report = IssueReport::with(['order', 'product', 'reportedUser', 'reporter'])
            ->findOrFail($id);

        // Authorization: only the reporter or an admin can view details
        if (!$user->isAdmin() && $report->reporter_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report->evidence_image_url = $report->evidence_image_url;

        return response()->json($report);
    }

    /**
     * ADMIN: List all reports.
     */
    public function adminIndex(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = IssueReport::with(['reporter', 'order', 'product', 'reportedUser']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $reports = $query->latest()->paginate(15);

        $reports->getCollection()->transform(function ($report) {
            $report->evidence_image_url = $report->evidence_image_url;
            return $report;
        });

        return response()->json($reports);
    }

    /**
     * ADMIN: Resolve a report.
     */
    public function adminResolve(Request $request, $id)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:investigation,resolved,dismissed',
            'admin_response' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $report = IssueReport::findOrFail($id);
        
        $oldStatus = $report->status;
        $report->status = $request->status;
        $report->admin_response = $request->admin_response;

        if ($request->status === 'resolved' || $request->status === 'dismissed') {
            $report->resolved_at = now();
        }

        $report->save();

        // Load reporter relationship for notification
        $report->load('reporter');

        // ========== SEND NOTIFICATION TO USER ==========
        // Notify the user who submitted the report
        IssueReportNotificationHelper::sendAdminResponseNotificationToUser($report);
        
        // If status changed to investigation, send additional notification
        if ($request->status === 'investigation' && $oldStatus !== 'investigation') {
            IssueReportNotificationHelper::sendReportUnderInvestigationNotification($report);
        }

        return response()->json([
            'message' => 'Report updated successfully. User has been notified.',
            'report' => $report
        ]);
    }
}