<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

/**
 * Service for logging email sending attempts and tracking delivery status
 */
class EmailLogService
{
    /**
     * Create a new email log entry
     *
     * @param string $recipientEmail
     * @param string $recipientName
     * @param Mailable $mailable
     * @param string|null $contextType
     * @param int|null $contextId
     * @param array $contextData
     * @param int|null $userId
     * @param int|null $triggeredByUserId
     * @param array $metadata
     * @return EmailLog
     */
    public function createLog(
        string $recipientEmail,
        ?string $recipientName,
        Mailable $mailable,
        ?string $contextType = null,
        ?int $contextId = null,
        array $contextData = [],
        ?int $userId = null,
        ?int $triggeredByUserId = null,
        array $metadata = []
    ): EmailLog {
        try {
            // Extract subject from mailable if possible
            $subject = $this->extractSubject($mailable);

            return EmailLog::create([
                'mailable_type' => get_class($mailable),
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'status' => 'pending',
                'context_type' => $contextType,
                'context_id' => $contextId,
                'context_data' => $contextData,
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId,
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create email log', [
                'recipient' => $recipientEmail,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
            ]);

            // Return a basic log entry even if creation fails
            return new EmailLog([
                'mailable_type' => get_class($mailable),
                'recipient_email' => $recipientEmail,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Mark an email log as sent successfully
     *
     * @param EmailLog $emailLog
     * @return void
     */
    public function markAsSent(EmailLog $emailLog): void
    {
        try {
            $emailLog->markAsSent();

            Log::info('Email sent successfully', [
                'email_log_id' => $emailLog->id,
                'recipient' => $emailLog->recipient_email,
                'subject' => $emailLog->subject,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark email log as sent', [
                'email_log_id' => $emailLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark an email log as failed
     *
     * @param EmailLog $emailLog
     * @param \Throwable $exception
     * @return void
     */
    public function markAsFailed(EmailLog $emailLog, \Throwable $exception): void
    {
        try {
            $errorDetails = [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];

            $emailLog->markAsFailed($exception->getMessage(), $errorDetails);

            Log::error('Email sending failed', [
                'email_log_id' => $emailLog->id,
                'recipient' => $emailLog->recipient_email,
                'subject' => $emailLog->subject,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark email log as failed', [
                'email_log_id' => $emailLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log and send an email with tracking
     *
     * @param string $recipientEmail
     * @param string|null $recipientName
     * @param Mailable $mailable
     * @param string|null $contextType
     * @param int|null $contextId
     * @param array $contextData
     * @param int|null $userId
     * @param int|null $triggeredByUserId
     * @return array ['success' => bool, 'emailLog' => EmailLog]
     */
    public function sendAndLog(
        string $recipientEmail,
        ?string $recipientName,
        Mailable $mailable,
        ?string $contextType = null,
        ?int $contextId = null,
        array $contextData = [],
        ?int $userId = null,
        ?int $triggeredByUserId = null
    ): array {
        // Create the log entry
        $emailLog = $this->createLog(
            $recipientEmail,
            $recipientName,
            $mailable,
            $contextType,
            $contextId,
            $contextData,
            $userId,
            $triggeredByUserId
        );

        try {
            // Send the email
            \Illuminate\Support\Facades\Mail::to($recipientEmail)->send($mailable);

            // Mark as sent
            $this->markAsSent($emailLog);

            return [
                'success' => true,
                'emailLog' => $emailLog,
            ];
        } catch (\Exception $e) {
            // Mark as failed
            $this->markAsFailed($emailLog, $e);

            return [
                'success' => false,
                'emailLog' => $emailLog,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get email logs for a specific context
     *
     * @param string $contextType
     * @param int $contextId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsByContext(string $contextType, int $contextId)
    {
        return EmailLog::byContext($contextType, $contextId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent failed emails
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentFailedEmails(int $limit = 50)
    {
        return EmailLog::failed()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Extract subject from mailable
     *
     * @param Mailable $mailable
     * @return string
     */
    private function extractSubject(Mailable $mailable): string
    {
        try {
            // Try to access the subject property
            if (property_exists($mailable, 'subject') && !empty($mailable->subject)) {
                return $mailable->subject;
            }

            // Try to build the mailable and extract subject
            $built = $mailable->build();
            if (isset($built->subject)) {
                return $built->subject;
            }

            // Fallback to class name
            return class_basename($mailable);
        } catch (\Exception $e) {
            return class_basename($mailable);
        }
    }
}
