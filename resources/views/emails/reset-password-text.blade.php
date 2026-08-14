Reset Your Password - {{ config('app.name') }}

Hello {{ $userName }},

We received a request to reset the password for your account associated with {{ $email }}.

To reset your password, please click the link below or copy and paste it into your browser:

{{ $resetUrl }}

SECURITY NOTICE:
This password reset link will expire in 60 minutes for security reasons.

If you didn't request a password reset, please ignore this email or contact our support team if you have concerns. Your password will remain unchanged.

---

{{ config('app.name') }} - Journal Management System
This is an automated message. Please do not reply to this email.

© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

If you have any questions or need assistance, please contact our support team.
