<?php

namespace App\Http\Resources\V1\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => OrderStatus::label($this->status),
            'source' => $this->source,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'base_price' => $item->base_price !== null ? (float) $item->base_price : null,
                'total' => (float) $item->total,
                'currency' => $item->currency,
                'image_url' => data_get($item->metadata, 'image_url'),
            ])->values()->all()),
            'payment' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'payment_method' => $payment->payment_method,
                'payment_method_label' => PaymentMethod::label($payment->payment_method),
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'transaction_reference' => $payment->transaction_reference,
                'transactions' => $payment->relationLoaded('transactions')
                    ? $payment->transactions->map(fn ($transaction) => [
                        'uuid' => $transaction->uuid,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'currency' => $transaction->currency,
                        'status' => $transaction->status,
                        'fee_amount' => $transaction->fee_amount !== null ? (float) $transaction->fee_amount : null,
                        'net_amount' => $transaction->net_amount !== null ? (float) $transaction->net_amount : null,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'created_at' => $transaction->created_at,
                    ])->values()->all()
                    : [],
            ])->values()->all()),
        ];
    }
}
