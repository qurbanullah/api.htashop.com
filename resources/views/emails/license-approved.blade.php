<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Approved - {{ $software?->name ?? 'Unknown Software' }}</title>
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
            background: #1e3a8a;
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #10b981;
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

        .success-icon {
            width: 56px;
            height: 56px;
            background: #10b981;
            border-radius: 8px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
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
            border-left: 3px solid #3b82f6;
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

        .status-badge {
            background-color: #10b981;
            color: white;
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-buttons {
            text-align: center;
            margin: 32px 0;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            transition: all 0.2s ease;
            margin: 0 8px 10px 0;
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

        .instructions {
            background-color: #eff6ff;
            border-radius: 4px;
            padding: 24px;
            margin: 28px 0;
            border: 1px solid #dbeafe;
            border-left: 3px solid #3b82f6;
        }

        .instructions h4 {
            color: #1e3a8a;
            font-size: 14px;
            margin-bottom: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .instruction-step {
            margin-bottom: 12px;
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
        }

        .instruction-step strong {
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

        .warning-box {
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 20px;
            margin: 24px 0;
            border-radius: 4px;
            border: 1px solid #fde68a;
        }

        .warning-box h4 {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .warning-box p {
            color: #78350f;
            font-size: 14px;
            line-height: 1.6;
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
                    License Approved - Admin Alert
                @elseif($recipientType === 'sales_manager')
                    License Approved - Sales Review
                @else
                    License Approved
                @endif
            </h1>
            <p>{{ $software?->name ?? 'Software' }} license is ready for activation</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $user?->name ?? 'User' }},
            </div>

            <div class="message">
                <p>Your license request for <strong>{{ $software?->name ?? 'the software' }}</strong> has been approved.</p>
                <p>Your license files are available for download from your account.</p>
            </div>

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
                        <td class="detail-value"><span class="status-badge">Approved</span></td>
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

            <!-- Action Buttons -->
            <div class="action-buttons" style="text-align: center; margin: 32px 0; padding: 32px 0;">
                <a href="{{ $licenseDetailUrl }}" class="btn btn-secondary" style="display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; background-color: #f1f5f9; color: #334155 !important; border: 1px solid #cbd5e1;">
                    View License Details
                </a>
            </div>

            <!-- Activation Instructions -->
            <div class="instructions">
                <h4>How to Activate Your License</h4>
                <div class="instruction-step">
                    <strong>1. Download the license file:</strong> Download the attached license file (.lic) or download it from your portal account by clicking the "View License Detail" link.
                </div>
                <div class="instruction-step">
                    <strong>2. Launch Volvicon:</strong> Start Volvicon again. The License Manager dialog should appear automatically.
                </div>
                <div class="instruction-step">
                    <strong>3. Import your license file:</strong> Select <strong>Existing license</strong>, click <strong>Browse</strong>, and choose the <code>.lic</code> file. Verify that the displayed license information is correct.
                </div>
                <div class="instruction-step">
                    <strong>4. Complete activation:</strong> Click <strong>Go Back</strong>, close the dialog, and start using Volvicon.
                </div>
                <div class="instruction-step" style="margin-top: 18px; padding-top: 18px; border-top: 1px solid #dbeafe;">
                    <strong>Written guide:</strong> <a href="https://help.volvicon.com/docs/getting-started/license-guide">Volvicon License Registration Guide</a>
                </div>
                <div class="instruction-step">
                    <strong>Video guide:</strong> <a href="https://volvicon.com/learning-center/volvicon-licensing-and-installation-complete-stepbystep-guide">Volvicon Licensing and Installation: Complete Step‑by‑Step Guide</a>
                </div>
            </div>

            <!-- Security Warning -->
            <div class="warning-box">
                <h4>Important Security Notice</h4>
                <p>Keep your license files secure and do not share them with unauthorized users. Your license is tied to your account and should only be used in accordance with your license agreement.</p>
            </div>

            <div class="divider"></div>

            <div class="message">
                <p>If you encounter any issues during activation or have questions about using {{ $software?->name ?? 'the software' }}, our support team is here to help.</p>
            </div>

            <div style="text-align: center; padding-top: 20px; margin-top: 20px;">
                <a href="{{ $supportUrl }}" class="btn btn-secondary" style="color: #334155 !important; display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; background-color: #f1f5f9; border: 1px solid #cbd5e1;">
                    Contact Support
                </a>
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
