<?php

namespace App\Http\Controllers\V1\Message;

use App\Actions\Messages\MessageCreateAction;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Store a message for a ticket (by UUID)
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            $ticket = Ticket::where('uuid', $uuid)->firstOrFail();

            $payload = [
                'user_id' => $request->user()->id,
                'message' => $request->input('message'),
                'messageable_type' => get_class($ticket),
                'messageable_id' => $ticket->id,
            ];

            $message = (new MessageCreateAction())->handle($payload);

            return response()->json([
                'success' => true,
                'message' => 'Message posted',
                'data' => $message,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to post message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a file used in a message (returns public URL)
     */
    public function upload(Request $request, string $uuid): JsonResponse
    {
        try {
            // Allow uploads for new tickets during creation by passing a special uuid 'new'.
            $ticket = null;
            if ($uuid !== 'new') {
                $ticket = Ticket::where('uuid', $uuid)->firstOrFail();
            }

            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 422);
            }

            $file = $request->file('file');

            // Validate file size/type as needed (basic example)
            if ($file->getSize() > 25 * 1024 * 1024) {
                return response()->json(['success' => false, 'message' => 'File too large'], 422);
            }

            $path = $file->store('messages', 'public');
            // Build public URL using the request's host (ensures correct port/environment)
            $url = request()->getSchemeAndHttpHost() . '/storage/' . ltrim($path, '/');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded',
                'data' => ['url' => $url],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
