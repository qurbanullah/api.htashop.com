<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Reopened - {{ $ticket->title }}</title>
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
            background: #ca8a04;
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #eab308;
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

        .icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin: 0 auto 20px;
            text-align: center;
            line-height: 56px;
            font-size: 28px;
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

        .ticket-details {
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 28px;
            margin: 30px 0;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #eab308;
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
        }

        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-open {
            background-color: #3b82f6;
            color: white;
        }

        .status-in_progress {
            background-color: #8b5cf6;
            color: white;
        }

        .status-resolved {
            background-color: #10b981;
            color: white;
        }

        .status-closed {
            background-color: #64748b;
            color: white;
        }

        .priority-low {
            background-color: #10b981;
            color: white;
        }

        .priority-medium {
            background-color: #f59e0b;
            color: white;
        }

        .priority-high {
            background-color: #f97316;
            color: white;
        }

        .priority-urgent {
            background-color: #ef4444;
            color: white;
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
            background: #ca8a04;
            color: #ffffff !important;
            border: none;
        }

        .btn-primary:hover {
            background: #a16207;
            color: #ffffff !important;
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
        <div class="header">
            <div class="icon">↻</div>
            <h1>Ticket Reopened</h1>
            <p>Support ticket requires additional attention</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello
                @if($recipientType === 'creator')
                    {{ $ticket->user?->name ?? 'User' }},
                @elseif($recipientType === 'assignee')
                    {{ $ticket->assignee?->name ?? 'Team Member' }},
                @elseif($recipientType === 'support')
                    Support Team,
                @else
                    Team,
                @endif
            </div>

            <div class="message">
                @if($recipientType === 'creator')
                    <p>Your support ticket <strong>#{{ $ticket->uuid }}</strong> has been reopened and our team will look into it again.</p>
                    <p>We are committed to ensuring your issue is fully resolved.</p>
                @elseif($recipientType === 'assignee')
                    <p>Ticket <strong>#{{ $ticket->uuid }}</strong> assigned to you has been reopened and requires your attention.</p>
                @elseif($recipientType === 'support')
                    <p>Ticket <strong>#{{ $ticket->uuid }}</strong> has been reopened and requires review by the support team.</p>
                @else
                    <p>Ticket <strong>#{{ $ticket->uuid }}</strong> has been reopened.</p>
                @endif
            </div>

            <!-- Ticket Details -->
            <div class="ticket-details">
                <h3>Ticket Details</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">UUID:</td>
                        <td class="detail-value">#{{ $ticket->uuid }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Title:</td>
                        <td class="detail-value">{{ $ticket->title }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Priority:</td>
                        <td class="detail-value">
                            <span class="status-badge priority-{{ strtolower($ticket->priority->value) }}">
                                {{ ucfirst($ticket->priority->value) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="detail-label">Status:</td>
                        <td class="detail-value">
                            <span class="status-badge status-{{ strtolower($ticket->status->value) }}">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status->value)) }}
                            </span>
                        </td>
                    </tr>
                    @if($ticket->assignee)
                    <tr>
                        <td class="detail-label">Assigned To:</td>
                        <td class="detail-value">{{ $ticket->assignee->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Created:</td>
                        <td class="detail-value">{{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Reopened:</td>
                        <td class="detail-value">{{ $ticket->updated_at->format('M j, Y \a\t g:i A') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Action Buttons -->
            @if($ticketUrl)
            <div class="action-buttons">
                <a href="{{ $ticketUrl }}" class="btn btn-primary">View Ticket</a>
            </div>
            @endif

            <div class="divider"></div>

            <div class="message">
                @if($recipientType === 'creator')
                    <p>Thank you for providing additional information. We'll review your ticket and respond as soon as possible.</p>
                @elseif($recipientType === 'assignee' || $recipientType === 'support')
                    <p>Please review the latest updates and address the ticket at your earliest convenience.</p>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ config('app.name') }} Team</strong><br>
            <a href="https://www.volvicon.com">www.volvicon.com</a></p>
            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                This email was sent because a ticket status was updated.<br>
                If you need further assistance, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
