<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>License Request Created</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: #1e3a8a;
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #3b82f6;
        }

        .header.admin {
            background: #7c2d12;
            border-bottom-color: #ea580c;
        }

        .header.sales_manager {
            background: #0c4a6e;
            border-bottom-color: #0284c7;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .header h1 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 15px;
            opacity: 0.9;
            font-weight: 400;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .message {
            margin-bottom: 30px;
        }

        .message p {
            margin-bottom: 15px;
            font-size: 16px;
            line-height: 1.6;
            color: #4a5568;
        }

        .request-details {
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 28px;
            margin: 30px 0;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #3b82f6;
        }

        .request-details.admin {
            border-left-color: #ea580c;
            background-color: #fff7ed;
        }

        .request-details.sales_manager {
            border-left-color: #0284c7;
            background-color: #f0f9ff;
        }

        .request-details h3 {
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-row {
            width: 100%;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table td {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .detail-table tr:last-child td {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
            width: 35%;
            padding-right: 20px;
        }

        .detail-value {
            color: #2d3748;
            font-size: 14px;
            font-weight: 500;
            width: 65%;
            word-break: break-word;
        }

        .contact-name,
        .contact-email {
            display: block;
        }

        .contact-email {
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .status-badge {
            background-color: #fef3c7;
            color: #92400e;
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #fde68a;
        }

        .action-buttons {
            text-align: center;
            margin: 32px 0;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin: 8px 10px;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: #1e3a8a;
            color: #ffffff !important;
            border: none;
        }

        .btn-primary:hover {
            background: #1e40af;
            color: #ffffff !important;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155 !important;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
            color: #1e293b !important;
        }

        .admin-notice {
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 20px;
            margin: 24px 0;
            border-radius: 4px;
            border: 1px solid #fde68a;
        }

        .admin-notice h4 {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-notice p {
            color: #78350f;
            font-size: 14px;
            line-height: 1.6;
        }

        .next-steps {
            background-color: #eff6ff;
            border-radius: 4px;
            padding: 24px;
            margin: 28px 0;
            border: 1px solid #dbeafe;
            border-left: 3px solid #3b82f6;
        }

        .next-steps h4 {
            color: #1e3a8a;
            font-size: 14px;
            margin-bottom: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .step {
            margin-bottom: 12px;
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
        }

        .step strong {
            color: #1e3a8a;
            font-weight: 600;
        }

        .footer {
            background-color: #1e293b;
            color: #94a3b8;
            padding: 32px 30px;
            text-align: center;
            border-top: 1px solid #334155;
        }

        .footer p {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .footer strong {
            color: #e2e8f0;
            font-weight: 600;
        }

        .footer a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            color: #93c5fd;
        }

        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 30px 0;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }

            .header, .content, .footer {
                padding: 20px;
            }

            .btn {
                display: block;
                margin: 10px 0;
            }

            .detail-table,
            .detail-table tbody,
            .detail-table tr,
            .detail-table td {
                display: block;
                width: 100%;
            }

            .detail-table tr {
                margin-bottom: 15px;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 10px;
            }

            .detail-table tr:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }

            .detail-table td {
                border-bottom: none;
                padding: 2px 0;
            }

            .detail-label {
                width: 100%;
                padding-right: 0;
                margin-bottom: 4px;
            }

            .detail-value {
                width: 100%;
                padding-left: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header {{ $recipientType }}">
            <h1>
                @if($recipientType === 'admin')
                    New License Request - Admin Alert
                @elseif($recipientType === 'sales_manager')
                    New License Request - Sales Review
                @else
                    License Request Submitted
                @endif
            </h1>
            <p>{{ $software->name }} License Request</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                @if($recipientType === 'admin')
                    Hello Admin,
                @elseif($recipientType === 'sales_manager')
                    Hello Sales Manager,
                @else
                    Hello {{ $user->name }},
                @endif
            </div>

            <div class="message">
                @if($recipientType === 'admin')
                    <p>A new license request has been submitted and requires administrative review.</p>
                    <p>Please review the request details below and take appropriate action through the admin dashboard.</p>
                @elseif($recipientType === 'sales_manager')
                    <p>A new license request has been submitted for <strong>{{ $software->name }}</strong> and is ready for sales review.</p>
                    <p>Please evaluate the request and coordinate with the admin team for processing.</p>
                @else
                    <p>Thank you for submitting your license request for <strong>{{ $software->name }}</strong>.</p>
                    <p>Your request has been received and is currently under review. You will receive an email when your license is ready.</p>
                @endif
            </div>

            <!-- Request Details -->
            <div class="request-details {{ $recipientType }}">
                <h3>Request Details</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Requested By:</td>
                        <td class="detail-value">
                            <span class="contact-name">{{ $user->name ?? '—' }}</span>
                            <span class="contact-email">{{ $user->email ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="detail-label">Software:</td>
                        <td class="detail-value">{{ $software->name }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Version:</td>
                        <td class="detail-value">{{ $version?->version_number ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">License Tier:</td>
                        <td class="detail-value">{{ $package->name ?? 'N/A'  }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">License Type:</td>
                        <td class="detail-value">{{ $licenseType->name ?? 'N/A'  }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Status:</td>
                        <td class="detail-value"><span class="status-badge">{{ ucfirst($license?->status?->value) }}</span></td>
                    </tr>
                    <tr>
                        <td class="detail-label">Request Date:</td>
                        <td class="detail-value">{{ $license?->created_at?->format('F j, Y \a\t g:i A') ?? 'N/A' }}</td>
                    </tr>
                    @if($license->user_message)
                    <tr>
                        <td class="detail-label">Message:</td>
                        <td class="detail-value">{{ $license->user_message }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="{{ $licenseDetailUrl }}" class="btn btn-primary">
                    View Request Details
                </a>
                <a href="{{ $dashboardUrl }}" class="btn btn-secondary">
                    Go to Dashboard
                </a>
            </div>

            @if($recipientType === 'admin')
            <!-- Admin Notice -->
            <div class="admin-notice">
                <h4>Administrative Action Required</h4>
                <p>This request requires administrative review and approval. Please log into the admin dashboard to process this license request.</p>
            </div>
            @elseif($recipientType === 'sales_manager')
            <!-- Sales Manager Next Steps -->
            <div class="next-steps">
                <h4>Sales Review Process</h4>
                <div class="step">
                    <strong>1. Review Request:</strong> Evaluate the license request details and customer requirements.
                </div>
                <div class="step">
                    <strong>2. Customer Contact:</strong> Reach out to the customer if additional information is needed.
                </div>
                <div class="step">
                    <strong>3. Coordination:</strong> Work with the admin team to ensure smooth license processing.
                </div>
                <div class="step">
                    <strong>4. Follow-up:</strong> Monitor the request status and provide customer updates as needed.
                </div>
            </div>
            @else
            <!-- User Next Steps -->
            <div class="next-steps">
                <h4>Next Steps</h4>
                <div class="step">
                    <strong>1. Review Process:</strong> Our team will review your license request.
                </div>
                <div class="step">
                    <strong>2. Email Notification:</strong> You will receive an email when your license is approved and available for download.
                </div>
                <div class="step">
                    <strong>3. License Activation:</strong> Follow the instructions in the approval email to activate your license.
                </div>
                <div class="step">
                    <strong>4. Support:</strong> Contact our support team if you have any questions during the process.
                </div>
            </div>
            @endif

            <div class="divider"></div>

            <div class="message">
                @if($recipientType === 'user')
                    <p>Your request has been recorded. Contact support if you need additional assistance while it is under review.</p>
                @else
                    <p>Please take appropriate action on this license request to ensure timely customer service.</p>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Volvicon Team</strong></p>
            <p><a href="mailto:support@volvicon.com">support@volvicon.com</a></p>
            <p><a href="https://volvicon.com">www.volvicon.com</a></p>
            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                This email was sent because a new license request was submitted.<br>
                If you have any questions, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
