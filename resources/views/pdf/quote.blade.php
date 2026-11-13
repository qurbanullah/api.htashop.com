<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quoteRequest->quote_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.5; }
        .page { padding: 36px 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2563eb; padding-bottom: 16px; }
        .brand h1 { font-size: 20px; font-weight: 800; color: #2563eb; letter-spacing: 0.3px; }
        .brand p { color: #6b7280; font-size: 10px; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 24px; font-weight: 700; color: #1f2937; text-transform: uppercase; }
        .doc-title .quote-no { color: #2563eb; font-weight: 600; margin-top: 4px; }
        .doc-title .revision { color: #6b7280; font-size: 11px; }
        .meta { display: flex; justify-content: space-between; gap: 32px; margin-top: 24px; }
        .meta .block { flex: 1; }
        .meta h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .meta p { font-size: 12px; color: #1f2937; }
        .meta .muted { color: #6b7280; }
        .message { margin-top: 24px; padding: 14px 16px; background: #f8fafc; border-left: 3px solid #2563eb; border-radius: 4px; white-space: pre-line; color: #374151; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.items th { background: #eff6ff; color: #2563eb; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; padding: 9px 8px; border-bottom: 2px solid #bfdbfe; }
        table.items td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items .num { text-align: right; }
        table.items .sku { color: #9ca3af; font-size: 10px; }
        .totals { margin-top: 18px; margin-left: auto; width: 290px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; color: #4b5563; }
        .totals .row.grand { border-top: 2px solid #2563eb; margin-top: 6px; padding-top: 10px; font-weight: 700; color: #1f2937; font-size: 15px; }
        .terms { margin-top: 28px; padding-top: 14px; border-top: 1px solid #e5e7eb; white-space: pre-line; color: #4b5563; font-size: 11px; }
        .terms h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .terms-grid { display: flex; gap: 32px; margin-top: 26px; }
        .terms-grid > div { flex: 1; }
        .terms-grid h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .terms-grid p { font-size: 11px; color: #374151; margin-bottom: 3px; }
        .terms-grid .muted { color: #6b7280; }
        .footer { margin-top: 34px; border-top: 1px solid #e5e7eb; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #9ca3af; }
        .validity { margin-top: 10px; font-size: 11px; color: #b45309; font-weight: 600; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                <h1>{{ $company['name'] }}</h1>
                <p>{{ $company['address'] }}</p>
                <p>{{ $company['phone'] }} &nbsp;·&nbsp; {{ $company['email'] }} &nbsp;·&nbsp; {{ $company['website'] }}</p>
            </div>
            <div class="doc-title">
                <h2>Quotation</h2>
                <div class="quote-no">{{ $quoteRequest->quote_number ?? '—' }}</div>
                <div class="revision">Revision {{ $quoteResponse->revision }}</div>
            </div>
        </div>

        <div class="meta">
            <div class="block">
                <h3>Prepared for</h3>
                <p>{{ $quoteRequest->first_name }} {{ $quoteRequest->last_name }}</p>
                @if ($quoteRequest->organization)<p class="muted">{{ $quoteRequest->organization }}</p>@endif
                @if ($quoteRequest->job_title)<p class="muted">{{ $quoteRequest->job_title }}</p>@endif
                <p>{{ $quoteRequest->email }}</p>
                @if ($quoteRequest->phone)<p class="muted">{{ $quoteRequest->phone }}</p>@endif
                <p class="muted">
                    {{ collect([$quoteRequest->city, $quoteRequest->state, $quoteRequest->country])->filter()->implode(', ') }}
                </p>
            </div>
            <div class="block">
                <h3>Details</h3>
                <p>Issued: <span class="muted">{{ optional($quoteResponse->sent_at ?? $quoteResponse->created_at)->format('d M Y') }}</span></p>
                <p>Valid until: <span class="muted">{{ optional($quoteResponse->expires_at)->format('d M Y') ?? '—' }}</span></p>
                <p>Currency: <span class="muted">{{ $quoteResponse->currency }}</span></p>
                @if ($quoteResponse->sender)
                    <p>Prepared by: <span class="muted">{{ $quoteResponse->sender->name }}</span></p>
                @endif
            </div>
        </div>

        @if ($quoteResponse->subject)
            <div class="message"><strong>{{ $quoteResponse->subject }}</strong>
{{ $quoteResponse->message }}</div>
        @else
            <div class="message">{{ $quoteResponse->message }}</div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Discount</th>
                    <th class="num">Tax</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quoteResponse->items as $item)
                    <tr>
                        <td>
                            {{ $item->name }}
                            @if ($item->description)
                                <div class="sku">{{ $item->description }}</div>
                            @endif
                            @if ($item->lead_time_days)
                                <div class="sku">Lead time: {{ $item->lead_time_days }} days</div>
                            @endif
                        </td>
                        <td class="sku">{{ $item->sku ?? '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">{{ number_format((float) $item->discount_amount, 2) }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2), '0'), '.') }}%</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>{{ $quoteResponse->currency }} {{ number_format((float) $quoteResponse->subtotal, 2) }}</span></div>
            <div class="row"><span>Discount</span><span>− {{ $quoteResponse->currency }} {{ number_format((float) $quoteResponse->discount_amount, 2) }}</span></div>
            <div class="row"><span>Tax</span><span>{{ $quoteResponse->currency }} {{ number_format((float) $quoteResponse->tax_amount, 2) }}</span></div>
            <div class="row"><span>Shipping</span><span>{{ $quoteResponse->currency }} {{ number_format((float) $quoteResponse->shipping_amount, 2) }}</span></div>
            <div class="row grand"><span>Total</span><span>{{ $quoteResponse->currency }} {{ number_format((float) $quoteResponse->total_amount, 2) }}</span></div>
        </div>

        @if ($quoteResponse->expires_at)
            <p class="validity">This offer is valid until {{ $quoteResponse->expires_at->format('d M Y') }}.</p>
        @endif

        <div class="terms-grid">
            <div>
                <h3>Commercial terms</h3>
                @if ($quoteResponse->incoterm)
                    <p><strong>Incoterms 2020:</strong> {{ $quoteResponse->incoterm }}@if ($quoteResponse->incoterm_location) — {{ $quoteResponse->incoterm_location }}@endif</p>
                @endif
                @if ($quoteResponse->payment_terms)
                    <p><strong>Payment:</strong> {{ \App\Enums\PaymentTerms::labelFor($quoteResponse->payment_terms) }}</p>
                @endif
                @if ($quoteResponse->lead_time_days !== null)
                    <p><strong>Lead time:</strong> {{ $quoteResponse->lead_time_days }} days</p>
                @endif
                @if ($quoteResponse->estimated_ship_date)
                    <p><strong>Estimated dispatch:</strong> {{ $quoteResponse->estimated_ship_date->format('d M Y') }}</p>
                @endif
                @if ($quoteResponse->warranty_months)
                    <p><strong>Warranty:</strong> {{ $quoteResponse->warranty_months }} months</p>
                @endif
                @if ($quoteResponse->partial_shipment_allowed === false)
                    <p class="muted">Partial shipments not allowed.</p>
                @endif
            </div>
            <div>
                <h3>Logistics &amp; packing</h3>
                @if ($quoteResponse->shipping_method)
                    <p><strong>Method:</strong> {{ \App\Enums\ShippingMethod::labelFor($quoteResponse->shipping_method) }}</p>
                @endif
                @if ($quoteResponse->country_of_origin)
                    <p><strong>Origin:</strong> {{ $quoteResponse->country_of_origin }}</p>
                @endif
                @if ($quoteResponse->hs_code)
                    <p><strong>HS code:</strong> {{ $quoteResponse->hs_code }}</p>
                @endif
                @if ($quoteResponse->port_of_loading)
                    <p><strong>Port of loading:</strong> {{ $quoteResponse->port_of_loading }}</p>
                @endif
                @if ($quoteResponse->port_of_discharge)
                    <p><strong>Port of discharge:</strong> {{ $quoteResponse->port_of_discharge }}</p>
                @endif
                @if ($quoteResponse->package_count)
                    <p><strong>Packages:</strong> {{ $quoteResponse->package_count }}</p>
                @endif
                @php
                    $weights = [];
                    if ($quoteResponse->gross_weight_kg !== null) {
                        $weights[] = number_format((float) $quoteResponse->gross_weight_kg, 2).' kg gross';
                    }
                    if ($quoteResponse->net_weight_kg !== null) {
                        $weights[] = number_format((float) $quoteResponse->net_weight_kg, 2).' kg net';
                    }
                @endphp
                @if ($weights !== [])
                    <p><strong>Weight:</strong> {{ implode(' · ', $weights) }}</p>
                @endif
                @if ($quoteResponse->package_dimensions)
                    <p><strong>Dimensions:</strong> {{ $quoteResponse->package_dimensions }}</p>
                @endif
            </div>
        </div>

        @if ($quoteResponse->shipping_notes || $quoteResponse->packaging_notes)
            <div class="terms">
                @if ($quoteResponse->shipping_notes)
                    <h3>Shipping notes</h3>
                    {{ $quoteResponse->shipping_notes }}
                @endif
                @if ($quoteResponse->packaging_notes)
                    <h3 style="margin-top: 8px;">Packaging notes</h3>
                    {{ $quoteResponse->packaging_notes }}
                @endif
            </div>
        @endif

        @if ($quoteResponse->terms)
            <div class="terms">
                <h3>Terms &amp; conditions</h3>
                {{ $quoteResponse->terms }}
            </div>
        @endif

        <div class="footer">
            <span>{{ $company['name'] }} · {{ $company['website'] }}</span>
            <span>Generated {{ now()->format('d M Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
