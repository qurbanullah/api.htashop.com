<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>New Support Ticket</title>
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
            background: #7c2d12;
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #ea580c;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin: 0 auto 20px;
            text-align: center;
            line-height: 56px;
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

        .ticket-details {
            background-color: #fff7ed;
            border-radius: 4px;
            padding: 28px;
            margin: 30px 0;
            border: 1px solid #fed7aa;
            border-left: 3px solid #ea580c;
        }

        .ticket-details h3 {
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table td {
            padding: 8px 0;
            border-bottom: 1px solid #fed7aa;
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
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            background-color: #dbeafe;
            color: #1e40af;
        }

        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .priority-low {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .priority-medium {
            background-color: #fef3c7;
            color: #92400e;
        }

        .priority-high {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            text-align: center;
            margin: 30px 0;
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
            background: #ea580c;
            color: #ffffff !important;
            border: none;
        }

        .btn-primary:hover {
            background: #c2410c;
            color: #ffffff !important;
        }

        .alert-box {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-left: 3px solid #f59e0b;
            border-radius: 4px;
            padding: 20px;
            margin: 30px 0;
        }

        .alert-box strong {
            color: #92400e;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        .alert-box p {
            color: #78350f;
            font-size: 14px;
            margin: 0;
        }

        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .footer strong {
            color: #334155;
            font-weight: 600;
        }

        .footer a {
            color: #ea580c;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .description-box {
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 16px;
            margin: 15px 0;
            border-left: 3px solid #94a3b8;
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px 0;
            }

            .email-container {
                border-radius: 0;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .content {
                padding: 30px 20px;
            }

            .detail-table td {
                display: block;
                width: 100% !important;
                padding: 4px 0;
            }

            .detail-label {
                padding-right: 0;
                margin-bottom: 4px;
            }

            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                ⚠
            </div>
            <h1>New Support Ticket</h1>
            <p>Requires your attention</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello Admin,
            </div>

            <div class="message">
                <p>
                    A new support ticket has been created and requires attention.
                </p>
            </div>

            <!-- Ticket Details -->
            <div class="ticket-details">
                <h3>Ticket Details</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Ticket ID:</td>
                        <td class="detail-value">#{{ $ticket->uuid }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Title:</td>
                        <td class="detail-value">{{ $ticket->title }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Reporter:</td>
                        <td class="detail-value">{{ $ticket->submitter_name ?? 'Unknown' }} ({{ $ticket->submitter_email ?? 'N/A' }})</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Priority:</td>
                        <td class="detail-value">
                            <span class="priority-badge priority-{{ $ticket->priority->value }}">
                                {{ ucfirst($ticket->priority->value) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="detail-label">Status:</td>
                        <td class="detail-value">
                            <span class="status-badge">{{ ucfirst($ticket->status->value) }}</span>
                        </td>
                    </tr>
                    @if($ticket->stype)
                    <tr>
                        <td class="detail-label">Category:</td>
                        <td class="detail-value">{{ ucfirst($ticket->stype->value) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Created:</td>
                        <td class="detail-value">{{ $ticket->created_at->format('F j, Y g:i A') }}</td>
                    </tr>
                </table>

                @if($ticket->description)
                <div class="description-box">
                    <strong style="color: #1e293b;">Description:</strong><br>
                    {!! nl2br(e(Str::limit(strip_tags($ticket->description), 400))) !!}
                </div>
                @endif
            </div>

            <!-- Action Button -->
            <div class="action-buttons">
                <a href="{{ $ticketUrl }}" class="btn btn-primary">View & Assign Ticket</a>
            </div>

            <!-- Alert Box -->
            <div class="alert-box">
                <strong>Action Required:</strong>
                <p>Please review and assign this ticket to the appropriate team member.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                <strong>{{ config('app.name') }} System</strong><br>
                This is an automated message. Please do not reply directly to this email.
            </p>
            <p style="margin-top: 15px;">
                <a href="{{ config('app.admin_url') }}">Admin Portal</a>
            </p>
        </div>
    </div>
</body>
</html>
