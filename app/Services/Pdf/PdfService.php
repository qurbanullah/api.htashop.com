<?php

namespace App\Services\Pdf;

use App\Models\Document;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PdfService
{
    public const TYPES = ['invoice', 'packing_slip'];

    private const VIEWS = [
        'invoice' => 'pdf.invoice',
        'packing_slip' => 'pdf.packing-slip',
    ];

    /**
     * Render the Blade template, convert via Gotenberg, store in E2, and
     * record the document against the order.
     */
    public function generateForOrder(Order $order, string $type): Document
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported document type.']);
        }

        $payment = $order->payments->first();

        $html = view(self::VIEWS[$type], [
            'order' => $order,
            'paymentMethodLabel' => $payment?->payment_method === 'cod'
                ? 'Cash on Delivery'
                : ucwords(str_replace('_', ' ', $payment?->payment_method ?? 'cod')),
            'paymentStatus' => $payment?->status ?? 'pending',
        ])->render();

        $pdf = $this->convert($html);

        $filename = $this->filename($order, $type);
        $objectKey = sprintf('documents/orders/%s/%s', $order->uuid, $filename);

        Storage::disk('idrivee2')->put($objectKey, $pdf, 'public');

        return Document::create([
            'documentable_type' => Order::class,
            'documentable_id' => $order->id,
            'type' => $type,
            'filename' => $filename,
            'object_key' => $objectKey,
            'mime' => 'application/pdf',
            'metadata' => [
                'order_number' => $order->order_number,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
            ],
        ]);
    }

    /**
     * POST the HTML to Gotenberg's Chromium HTML→PDF endpoint.
     */
    private function convert(string $html): string
    {
        $base = rtrim((string) config('services.pdf.url', 'http://pdf:3000'), '/');

        try {
            $response = Http::timeout(60)
                ->attach('files', $html, 'index.html')
                ->post("{$base}/forms/chromium/convert/html");
        } catch (ConnectionException $e) {
            throw new \RuntimeException('PDF service unreachable. Is the pdf container running?', 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException('PDF generation failed: ' . $response->status());
        }

        return $response->body();
    }

    private function filename(Order $order, string $type): string
    {
        $slug = strtolower(str_replace([' ', '/', '\\'], '-', $order->order_number));

        return $type . '-' . $slug . '.pdf';
    }
}
