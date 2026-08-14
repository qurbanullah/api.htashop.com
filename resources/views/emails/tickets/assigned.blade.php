<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Assigned</title>
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333333; background-color: #f8fafc; margin: 0; padding: 20px 0;}
        .email-container {max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);}
        .header {background: #3b82f6; color: white; padding: 40px 30px; text-align: center; border-bottom: 4px solid #2563eb;}
        .header-icon {width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin: 0 auto 20px;
            text-align: center;
            line-height: 56px;
            font-size: 28px;}
        .header h1 {font-size: 26px; font-weight: 600; margin-bottom: 8px; letter-spacing: -0.5px;}
        .header p {font-size: 15px; opacity: 0.9; font-weight: 400;}
        .content {padding: 40px 30px;}
        .greeting {font-size: 18px; font-weight: 600; color: #2d3748; margin-bottom: 20px;}
        .message p {margin-bottom: 15px; font-size: 16px; line-height: 1.6; color: #4a5568;}
        .ticket-details {background-color: #eff6ff; border-radius: 4px; padding: 28px; margin: 30px 0; border: 1px solid #bfdbfe; border-left: 3px solid #3b82f6;}
        .ticket-details h3 {color: #1e293b; font-size: 14px; margin-bottom: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        .detail-table {width: 100%; border-collapse: collapse;}
        .detail-table td {padding: 8px 0; border-bottom: 1px solid #dbeafe; vertical-align: top;}
        .detail-table tr:last-child td {border-bottom: none;}
        .detail-label {font-weight: 600; color: #4a5568; font-size: 14px; width: 35%; padding-right: 20px;}
        .detail-value {color: #2d3748; font-size: 14px; font-weight: 500; width: 65%;}
        .status-badge {display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; background-color: #dbeafe; color: #1e40af;}
        .priority-badge {display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;}
        .priority-low {background-color: #e0e7ff; color: #3730a3;}
        .priority-medium {background-color: #fef3c7; color: #92400e;}
        .priority-high {background-color: #fee2e2; color: #991b1b;}
        .action-buttons {text-align: center; margin: 30px 0;}
        .btn {display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center; margin: 10px 5px; transition: all 0.3s ease;}
        .btn-primary {background: #3b82f6; color: #ffffff !important; border: none;}
        .btn-primary:hover {background: #2563eb; color: #ffffff !important;}
        .alert-box {background-color: #eff6ff; border: 1px solid #bfdbfe; border-left: 3px solid #3b82f6; border-radius: 4px; padding: 20px; margin: 30px 0;}
        .footer {background-color: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;}
        .footer p {color: #64748b; font-size: 14px; margin-bottom: 10px;}
        .footer strong {color: #334155; font-weight: 600;}
        .footer a {color: #3b82f6; text-decoration: none; font-weight: 600;}
        @media (max-width: 600px) {
            body {padding: 10px 0;} .email-container {border-radius: 0;} .header {padding: 30px 20px;} .header h1 {font-size: 22px;} .content {padding: 30px 20px;}
            .detail-table td {display: block; width: 100% !important; padding: 4px 0;} .detail-label {padding-right: 0; margin-bottom: 4px;} .btn {display: block; margin: 10px 0;}
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="header-icon">👤</div>
            <h1>
                @if($recipientType === 'assignee')
                    You Have Been Assigned to a Ticket
                @elseif($recipientType === 'creator')
                    Your Ticket Has Been Assigned
                @else
                    Ticket Reassigned
                @endif
            </h1>
            <p>Ticket assignment update</p>
        </div>

        <div class="content">
            <div class="greeting">
                @if($recipientType === 'assignee')
                    Hello {{ $assignee->name }},
                @elseif($recipientType === 'creator')
                    Hello {{ $ticket->submitter_name ?? 'there' }},
                @else
                    Hello,
                @endif
            </div>

            <div class="message">
                <p>
                    @if($recipientType === 'assignee')
                        You have been assigned to support ticket #{{ $ticket->uuid }}.
                    @elseif($recipientType === 'creator')
                        Your support ticket has been assigned to <strong>{{ $assignee->name }}</strong> who will be assisting you.
                    @else
                        Ticket #{{ $ticket->uuid }} has been reassigned to <strong>{{ $assignee->name }}</strong>.
                    @endif
                </p>
            </div>

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
                        <td class="detail-value"><span class="status-badge">{{ ucfirst($ticket->status->value) }}</span></td>
                    </tr>
                    @if($recipientType !== 'creator')
                    <tr>
                        <td class="detail-label">Reporter:</td>
                        <td class="detail-value">{{ $ticket->submitter_name ?? 'Unknown' }}</td>
                    </tr>
                    @endif
                    @if($recipientType === 'assignee')
                    <tr>
                        <td class="detail-label">Assigned By:</td>
                        <td class="detail-value">{{ $ticket->currentAssignment()?->assignedBy->name ?? 'System' }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="detail-label">Assigned To:</td>
                        <td class="detail-value">{{ $assignee->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Assigned:</td>
                        <td class="detail-value">{{ now()->format('F j, Y g:i A') }}</td>
                    </tr>
                </table>
            </div>

            @if($ticketUrl)
            <div class="action-buttons">
                <a href="{{ $ticketUrl }}" class="btn btn-primary">View Ticket</a>
            </div>
            @endif

            @if($recipientType === 'assignee')
            <div class="alert-box">
                <p style="margin: 0; color: #1e40af; font-weight: 500;">
                    Please review this ticket at your earliest convenience and provide an initial response to the customer.
                </p>
            </div>
            @elseif($recipientType === 'creator')
            <div class="alert-box">
                <p style="margin: 0; color: #1e40af; font-weight: 500;">
                    Your assigned support representative will review your ticket and respond shortly.
                </p>
            </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>{{ config('app.name') }} Team</strong><br>
            <a href="https://www.volvicon.com">www.volvicon.com</a></p>
            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                This email was sent because a ticket was assigned.<br>
                If you need further assistance, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
