LICENSE REQUEST @if($recipientType === 'admin') - ADMIN ALERT @elseif($recipientType === 'sales_manager') - SALES REVIEW @else SUBMITTED @endif

@if($recipientType === 'admin')
Hello Admin,
@elseif($recipientType === 'sales_manager')
Hello Sales Manager,
@else
Hello {{ $user->name }},
@endif

@if($recipientType === 'admin')
A new license request has been submitted and requires administrative review.
Please review the request details below and take appropriate action through the admin dashboard.
@elseif($recipientType === 'sales_manager')
A new license request has been submitted for {{ $software->name }} and is ready for sales review.
Please evaluate the request and coordinate with the admin team for processing.
@else
Thank you for submitting your license request for {{ $software->name }}.
Your request has been received and is currently under review. You will receive an email when your license is ready.
@endif

REQUEST DETAILS:
================
Requested By: {{ $user->name }} ({{ $user->email }})
Software: {{ $software->name }}
Version: {{ $version->name }}
Package: {{ $package->name }}
License Type: {{ $licenseType->name }}
Status: {{ ucfirst($license->status->value) }}
Request Date: {{ $license->created_at->format('F j, Y \a\t g:i A') }}
@if($license->user_message)
Message: {{ $license->user_message }}
@endif

ACTIONS:
========
View Request Details: {{ $licenseDetailUrl }}
Go to Dashboard: {{ $dashboardUrl }}

@if($recipientType === 'admin')
ADMINISTRATIVE ACTION REQUIRED:
===============================
This request requires administrative review and approval. Please log into the admin dashboard to process this license request.
@elseif($recipientType === 'sales_manager')
SALES REVIEW PROCESS:
=====================
1. Review Request: Evaluate the license request details and customer requirements.
2. Customer Contact: Reach out to the customer if additional information is needed.
3. Coordination: Work with the admin team to ensure smooth license processing.
4. Follow-up: Monitor the request status and provide customer updates as needed.
@else
WHAT HAPPENS NEXT:
==================
1. Review Process: Our team will review your license request.
2. Email Notification: You will receive an email when your license is approved and ready for download.
3. License Activation: Follow the instructions in the approval email to activate your license.
4. Support: Contact our support team if you have any questions during the process.
@endif

@if($recipientType === 'user')
Your request has been recorded. Contact support if you need additional assistance while it is under review.
@else
Please take appropriate action on this license request to ensure timely customer service.
@endif

---
Volvicon Team
Email: support@volvicon.com
Website: https://volvicon.com

This email was sent because a new license request was submitted. If you have any questions, please contact our support team.
