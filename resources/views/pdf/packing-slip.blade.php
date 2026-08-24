<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing Slip {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.5; }
        .page { padding: 36px 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #16a34a; padding-bottom: 16px; }
        .brand h1 { font-size: 22px; font-weight: 800; color: #16a34a; letter-spacing: 0.5px; }
        .brand p { color: #6b7280; font-size: 11px; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 24px; font-weight: 700; color: #1f2937; text-transform: uppercase; }
        .doc-title .order-no { color: #16a34a; font-weight: 600; margin-top: 4px; }
        .ship-block { margin-top: 24px; padding: 16px 20px; border: 2px dashed #d1d5db; border-radius: 8px; }
        .ship-block h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .ship-block p { font-size: 13px; color: #1f2937; }
        .meta { display: flex; gap: 32px; margin-top: 20px; }
        .meta .block { flex: 1; }
        .meta h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.items th { background: #f0fdf4; color: #15803d; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; padding: 10px 8px; border-bottom: 2px solid #bbf7d0; }
        table.items td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items .num { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 280px; }
        .totals .row { display: flex; justify-content: space-between; padding: 5px 0; color: #4b5563; }
        .totals .row.grand { border-top: 2px solid #16a34a; margin-top: 6px; padding-top: 10px; font-weight: 700; color: #1f2937; font-size: 15px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 14px; display: flex; justify-content: space-between; font-size: 10px; color: #9ca3af; }
        .qr { margin-top: 20px; width: 120px; height: 120px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 10px; text-align: center; }
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
                <h2>Packing Slip</h2>
                <div class="order-no">{{ $order->order_number }}</div>
            </div>
        </div>

        <div class="ship-block">
            <h3>Ship To</h3>
            @php($ship = $order->shipping_address ?? [])
            <p>{{ data_get($ship, 'contact_name') ?? $order->customer_name ?? '—' }}</p>
            <p>{{ data_get($ship, 'address_line_1') }}</p>
            @if (data_get($ship, 'address_line_2'))<p>{{ data_get($ship, 'address_line_2') }}</p>@endif
            <p>{{ collect([data_get($ship, 'city'), data_get($ship, 'state'), data_get($ship, 'postal_code')])->filter()->implode(', ') }}</p>
            <p>{{ data_get($ship, 'country') }}</p>
            @if (data_get($ship, 'phone'))<p>{{ data_get($ship, 'phone') }}</p>@endif
        </div>

        <div class="meta">
            <div class="block">
                <h3>Order</h3>
                <p>{{ $order->order_number }} · {{ optional($order->placed_at)->format('d M Y') }}</p>
            </div>
            <div class="block">
                <h3>Payment</h3>
                <p>{{ $paymentMethodLabel }} · {{ ucfirst($paymentStatus) }}</p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->sku ?? '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row grand"><span>Total</span><span>{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</span></div>
        </div>

        <div class="qr">Order<br>{{ $order->order_number }}</div>

        <div class="footer">
            <span>Package contains the items listed above. Please inspect on delivery.</span>
            <span>Generated {{ now()->format('d M Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
