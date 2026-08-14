<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Ticket Created</title>
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
            background: #10b981;
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #059669;
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
            background-color: #f0fdf4;
            border-radius: 4px;
            padding: 28px;
            margin: 30px 0;
            border: 1px solid #bbf7d0;
            border-left: 3px solid #10b981;
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
            border-bottom: 1px solid #dcfce7;
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
            background: #10b981;
            color: #ffffff !important;
            border: none;
        }

        .btn-primary:hover {
            background: #059669;
            color: #ffffff !important;
        }

        .next-steps {
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 24px;
            margin: 30px 0;
            border: 1px solid #e2e8f0;
        }

        .next-steps h4 {
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .step {
            margin-bottom: 12px;
            padding-left: 20px;
            position: relative;
            color: #4a5568;
            font-size: 15px;
        }

        .step:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
            font-size: 20px;
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
                ✓
            </div>
            <h1>Ticket Created</h1>
            <p>We have received your support request.</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $ticket->submitter_name ?? 'there' }},
            </div>

            <div class="message">
                <p>
                    Your support ticket has been created. Our team will review your request and respond as soon as possible.
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
                    <tr>
                        <td class="detail-label">Created:</td>
                        <td class="detail-value">{{ $ticket->created_at->format('F j, Y g:i A') }}</td>
                    </tr>
                </table>

                @if($ticket->description)
                <div class="description-box">
                    <strong style="color: #1e293b;">Description:</strong><br>
                    {!! nl2br(e(Str::limit(strip_tags($ticket->description), 300))) !!}
                </div>
                @endif
            </div>

            <!-- Action Button -->
            @if($ticketUrl)
            <div class="action-buttons">
                <a href="{{ $ticketUrl }}" class="btn btn-primary">View Ticket</a>
            </div>
            @endif

            <!-- Next Steps -->
            <div class="next-steps">
                <h4>Next Steps</h4>
                <div class="step">Our support team has been notified.</div>
                <div class="step">You will receive updates by email.</div>
                @if($ticketUrl)
                <div class="step">You can reply to this ticket at any time from your portal.</div>
                @else
                <div class="step">Keep the ticket ID for reference when communicating with support.</div>
                @endif
            </div>

            <div class="message">
                <p style="margin-top: 20px;">
                    Thank you for contacting us. Our team will review your request and respond as soon as possible.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ config('app.name') }} Team</strong><br>
            <a href="https://www.volvicon.com">www.volvicon.com</a></p>
            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                This email was sent because a new support ticket was submitted.<br>
                If you need further assistance, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
