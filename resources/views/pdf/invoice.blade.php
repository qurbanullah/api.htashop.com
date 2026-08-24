<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.5; }
        .page { padding: 36px 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2563eb; padding-bottom: 16px; }
        .brand h1 { font-size: 22px; font-weight: 800; color: #2563eb; letter-spacing: 0.5px; }
        .brand p { color: #6b7280; font-size: 11px; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 24px; font-weight: 700; color: #1f2937; text-transform: uppercase; }
        .doc-title .order-no { color: #2563eb; font-weight: 600; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; gap: 32px; margin-top: 24px; }
        .meta .block { flex: 1; }
        .meta h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .meta p { font-size: 12px; color: #1f2937; }
        .meta .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.items th { background: #eff6ff; color: #2563eb; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; padding: 10px 8px; border-bottom: 2px solid #bfdbfe; }
        table.items td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items .num { text-align: right; }
        .totals { margin-top: 20px; margin-left: auto; width: 280px; }
        .totals .row { display: flex; justify-content: space-between; padding: 5px 0; color: #4b5563; }
        .totals .row.grand { border-top: 2px solid #2563eb; margin-top: 6px; padding-top: 10px; font-weight: 700; color: #1f2937; font-size: 15px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 14px; display: flex; justify-content: space-between; font-size: 10px; color: #9ca3af; }
        .payment-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                <h1>{{ $order->tenant?->name ?? config('app.name', 'HTAShop') }}</h1>
                <p>{{ $order->tenant?->domain ?? '' }}</p>
            </div>
            <div class="doc-title">
                <h2>Invoice</h2>
                <div class="order-no">{{ $order->order_number }}</div>
            </div>
        </div>

        <div class="meta">
            <div class="block">
                <h3>Bill To</h3>
                <p>{{ $order->customer_name ?? '—' }}</p>
                @if ($order->customer_email)<p>{{ $order->customer_email }}</p>@endif
                @if ($order->customer_phone)<p>{{ $order->customer_phone }}</p>@endif
            </div>
            <div class="block">
                <h3>Order Details</h3>
                <p>Order date: <span class="muted">{{ optional($order->placed_at)->format('d M Y') }}</span></p>
                <p>Status: <span class="muted">{{ ucfirst($order->status) }}</span></p>
                <p>Payment: <span class="muted">{{ $paymentMethodLabel }}</span></p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->sku ?? '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                        <td class="num">{{ $order->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">{{ $order->currency }} {{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</span></div>
            @if ((float) $order->shipping_fee > 0)
                <div class="row"><span>Shipping</span><span>{{ $order->currency }} {{ number_format((float) $order->shipping_fee, 2) }}</span></div>
            @endif
            @if ((float) $order->tax > 0)
                <div class="row"><span>Tax</span><span>{{ $order->currency }} {{ number_format((float) $order->tax, 2) }}</span></div>
            @endif
            @if ((float) $order->discount > 0)
                <div class="row"><span>Discount</span><span>-{{ $order->currency }} {{ number_format((float) $order->discount, 2) }}</span></div>
            @endif
            <div class="row grand"><span>Total</span><span>{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</span></div>
            <div class="row"><span>Payment</span><span class="payment-badge">{{ $paymentMethodLabel }} · {{ ucfirst($paymentStatus) }}</span></div>
        </div>

        <div class="footer">
            <span>Thank you for your business.</span>
            <span>Generated {{ now()->format('d M Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
