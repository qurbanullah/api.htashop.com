<?php

namespace App\Http\Controllers\V1\Email;

use App\Http\Controllers\Controller;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmailController extends Controller
{
    /**
     * Get all email addresses for the authenticated user
     */
    public function index(\App\Http\Requests\V1\Email\IndexRequest $request): JsonResponse
    {
        $user = $request->user();

        $emails = $user->emails()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'emails' => $emails,
        ]);
    }

    /**
     * Add a new email address
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        

        try {
            $email = $user->addEmail(
                $request->email,
                $request->boolean('is_primary', false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Email address added successfully',
                'email' => $email,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add email address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set an email as primary
     */
    public function setPrimary(Request $request, Email $email): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($email->emailable_id !== $user->id || $email->emailable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $user->setPrimaryEmail($email);

            return response()->json([
                'success' => true,
                'message' => 'Primary email updated successfully',
                'email' => $email->fresh(),
                'user' => $user->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update primary email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an email address
     */
    public function destroy(Request $request, Email $email): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($email->emailable_id !== $user->id || $email->emailable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Prevent deletion of primary email if it's the only email
        if ($email->is_primary && $user->emails()->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your only email address',
            ], 422);
        }

        // If deleting primary email, set another email as primary
        if ($email->is_primary) {
            $newPrimary = $user->emails()
                ->where('id', '!=', $email->id)
                ->first();

            if ($newPrimary) {
                $user->setPrimaryEmail($newPrimary);
            }
        }

        try {
            $email->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email address deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send verification email
     */
    public function sendVerification(Request $request, Email $email): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($email->emailable_id !== $user->id || $email->emailable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($email->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified',
            ], 422);
        }

        try {
            // Generate verification token
            $email->verification_token = \Str::random(64);
            $email->save();

            // TODO: Send verification email notification
            // Mail::to($email->email)->send(new EmailVerification($email));

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
