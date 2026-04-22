<?php

namespace App\Helpers;

use App\Models\IssueReport;
use App\Services\NotificationService;
use Illuminate\Support\Str;  // ← THIS IS THE IMPORTANT FIX
use Illuminate\Support\Facades\Log;

class IssueReportNotificationHelper
{
    /**
     * Send notification to all admins when a new report is submitted
     */
    public static function sendNewReportNotificationToAdmins($report)
    {
        try {
            $notificationService = app(NotificationService::class);
            
            $typeDisplay = ucfirst($report->type);
            $statusDisplay = ucfirst($report->status);
            
            // Now Str::limit() will work correctly
            $description = Str::limit($report->description ?? '', 100);
            
            $body = "A new {$report->type} report has been submitted and needs review.\n\n" .
                    "Report #{$report->id}\n" .
                    "Type: {$typeDisplay}\n" .
                    "Subject: {$report->subject}\n" .
                    "Status: {$statusDisplay}\n\n" .
                    "Description: " . $description;
            
            return $notificationService->sendToRole('admin', [
                'type' => 'new_report_submitted',
                'title' => 'New Issue Report Submitted! ⚠️',
                'body' => $body,
                'data' => [
                    'report_id' => $report->id,
                    'report_type' => $report->type,
                    'report_subject' => $report->subject,
                    'report_status' => $report->status,
                    'reporter_id' => $report->reporter_id,
                    'reporter_name' => $report->reporter?->name,
                    'reporter_email' => $report->reporter?->email,
                    'submitted_at' => $report->created_at?->toDateTimeString(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in sendNewReportNotificationToAdmins: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send notification to user when admin responds to their report
     */
    public static function sendAdminResponseNotificationToUser($report)
    {
        try {
            $notificationService = app(NotificationService::class);
            
            $user = $report->reporter;
            
            if (!$user) {
                Log::warning('No reporter found for report: ' . $report->id);
                return null;
            }
            
            $statusDisplay = ucfirst($report->status);
            $statusIcon = $report->status === 'resolved' ? '✅' : ($report->status === 'dismissed' ? 'ℹ️' : '🔍');
            
            $body = "Your report #{$report->id} has been updated.\n\n" .
                    "Subject: {$report->subject}\n" .
                    "Status: {$statusDisplay} {$statusIcon}\n\n" .
                    "Admin Response:\n" .
                    "{$report->admin_response}\n\n";
            
            if ($report->status === 'resolved') {
                $body .= "Thank you for helping us improve U-Connect!";
            } elseif ($report->status === 'dismissed') {
                $body .= "If you have additional information, please submit a new report.";
            } else {
                $body .= "Our team is investigating this matter and will update you soon.";
            }
            
            return $notificationService->sendToUser($user, [
                'type' => 'report_admin_response',
                'title' => "Report Update: {$report->subject} {$statusIcon}",
                'body' => $body,
                'data' => [
                    'report_id' => $report->id,
                    'report_subject' => $report->subject,
                    'report_status' => $report->status,
                    'admin_response' => $report->admin_response,
                    'resolved_at' => $report->resolved_at?->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in sendAdminResponseNotificationToUser: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send notification to user when their report is under investigation
     */
    public static function sendReportUnderInvestigationNotification($report)
    {
        try {
            $notificationService = app(NotificationService::class);
            
            $user = $report->reporter;
            
            if (!$user) {
                Log::warning('No reporter found for report: ' . $report->id);
                return null;
            }
            
            return $notificationService->sendToUser($user, [
                'type' => 'report_investigation',
                'title' => 'Your Report Is Under Investigation 🔍',
                'body' => "Your report #{$report->id} about '{$report->subject}' is now under investigation.\n\n" .
                          "Our team is looking into the matter and will provide an update soon.\n\n" .
                          "Thank you for your patience.",
                'data' => [
                    'report_id' => $report->id,
                    'report_subject' => $report->subject,
                    'investigation_started_at' => now()->toDateTimeString(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in sendReportUnderInvestigationNotification: ' . $e->getMessage());
            throw $e;
        }
    }
}