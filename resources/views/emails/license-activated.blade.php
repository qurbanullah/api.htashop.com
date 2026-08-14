<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License {{ $isActivated ? 'Activated' : 'Deactivated' }} - {{ $software?->name ?? 'Unknown Software' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8fafc;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: {{ $isActivated ? '#1e3a8a' : '#1e293b' }};
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid {{ $isActivated ? '#3b82f6' : '#64748b' }};
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

        .status-icon {
            width: 56px;
            height: 56px;
            background: {{ $isActivated ? '#3b82f6' : '#64748b' }};
            border-radius: 8px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 2px 8px {{ $isActivated ? 'rgba(59, 130, 246, 0.3)' : 'rgba(100, 116, 139, 0.3)' }};
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .message {
            font-size: 16px;
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .license-details {
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 28px;
            margin: 30px 0;
            border: 1px solid #e2e8f0;
            border-left: 3px solid {{ $isActivated ? '#3b82f6' : '#64748b' }};
        }

        .license-details h3 {
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

        .btn {
            display: inline-block;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 10px 5px;
            transition: all 0.3s ease;
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

        .alert-box {
            background-color: {{ $isActivated ? '#eff6ff' : '#f8fafc' }};
            border: 1px solid {{ $isActivated ? '#bfdbfe' : '#cbd5e1' }};
            color: {{ $isActivated ? '#1e3a8a' : '#475569' }};
            padding: 18px;
            border-radius: 4px;
            margin: 24px 0;
            border-left: 3px solid {{ $isActivated ? '#3b82f6' : '#64748b' }};
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
        <div class="header">
            <h1>
                @if($recipientType === 'admin')
                    License {{ $isActivated ? 'Activated' : 'Deactivated' }} - Admin Alert
                @elseif($recipientType === 'sales_manager')
                    License {{ $isActivated ? 'Activated' : 'Deactivated' }} - Sales Review
                @else
                    License {{ $isActivated ? 'Activated' : 'Deactivated' }}
                @endif
            </h1>
            <p>{{ $software?->name ?? 'Unknown Software' }}</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello {{ $user?->name ?? 'User' }},</p>

            <div class="alert-box">
                @if($isActivated)
                    <strong>License activated.</strong> Your license is now available for use.
                @else
                    <strong>License deactivated.</strong> This license is no longer active.
                @endif
            </div>

            <p class="message">
                @if($isActivated)
                    Your {{ $software?->name ?? 'software' }} license has been activated. You can now access the features included in your license tier.
                @else
                    Your {{ $software?->name ?? 'software' }} license has been deactivated. If you believe this change is incorrect or need assistance, contact support.
                @endif
            </p>

            <!-- License Details -->
            <div class="license-details">
                <h3>License Details</h3>
                <table class="detail-table">
                    @if(!empty($is_admin) && $is_admin)
                    <tr>
                        <td class="detail-label">Approved By:</td>
                        <td class="detail-value">
                            <span class="contact-name">{{ $approved_by?->name ?? '—' }}</span>
                            <span class="contact-email">{{ $approved_by?->email ?? '—' }}</span>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Requested By:</td>
                        <td class="detail-value">
                            <span class="contact-name">{{ $requested_by?->name ?? '—' }}</span>
                            <span class="contact-email">{{ $requested_by?->email ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="detail-label">Software:</td>
                        <td class="detail-value">{{ $software?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Version:</td>
                        <td class="detail-value">{{ $version?->version_number ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">License Tier:</td>
                        <td class="detail-value">{{ $package?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">License Type:</td>
                        <td class="detail-value">{{ $licenseType?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Status:</td>
                        <td class="detail-value">
                            <strong style="color: {{ $isActivated ? '#28a745' : '#dc3545' }};">
                                {{ $isActivated ? 'ACTIVE' : 'INACTIVE' }}
                            </strong>
                        </td>
                    </tr>
                    @if($license->expires_at)
                    <tr>
                        <td class="detail-label">Expires:</td>
                        <td class="detail-value">{{ $license->expires_at->format('F j, Y') }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="detail-label">Duration:</td>
                        <td class="detail-value">Perpetual License</td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($isActivated)
            <p class="message">
                You can view your license details and download your license files from your account dashboard.
            </p>
            @else
            <p class="message">
                If you need assistance or have questions about this deactivation, contact support.
            </p>
            @endif

            <!-- Action Buttons -->
            <div style="text-align: center; margin: 20px 0; padding: 20px 0;">
                <a href="{{ $licenseDetailUrl }}" class="btn btn-primary" style="display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; background: #1e3a8a; color: #ffffff !important; margin: 10px 5px;">View License Details</a>
                @if(!$isActivated)
                <a href="{{ $supportUrl }}" class="btn btn-secondary" style="display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; background-color: #f1f5f9; color: #334155 !important; border: 1px solid #cbd5e1; margin: 10px 5px;">Contact Support</a>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Volvicon Team</strong></p>
            <p><a href="mailto:support@volvicon.com">support@volvicon.com</a></p>
            <p><a href="https://volvicon.com">www.volvicon.com</a></p>
            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                This email was sent because your license status was updated.<br>
                If you have any questions, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
