<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f7fa;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">

                    <!-- Header with Logo -->
                    {{-- <tr>
                        <td style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 40px 40px 35px 40px; border-radius: 8px 8px 0 0; text-align: center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <div style="display: inline-block; background-color: #ffffff; width: 60px; height: 60px; border-radius: 50%; padding: 15px; margin-bottom: 20px;">
                                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 15V17M6 21H18C19.1046 21 20 20.1046 20 19V13C20 11.8954 19.1046 11 18 11H6C4.89543 11 4 11.8954 4 13V19C4 20.1046 4.89543 21 6 21ZM16 11V7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7V11H16Z" stroke="#2563eb" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">
                                            {{ config('app.name') }}
                                        </h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> --}}

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 45px 40px 35px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; font-weight: 600; line-height: 1.3;">
                                            Reset Your Password
                                        </h2>
                                        <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                            Hello <strong style="color: #1f2937;">{{ $userName }}</strong>,
                                        </p>
                                        <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">
                                            We received a request to reset the password for your account associated with <strong style="color: #1f2937;">{{ $email }}</strong>. To proceed with resetting your password, please click the button below:
                                        </p>
                                    </td>
                                </tr>

                                <!-- CTA Button -->
                                <tr>
                                    <td style="padding: 30px 0;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="{{ $resetUrl }}"
                                                       style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 6px; font-weight: 600; font-size: 16px; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                                                        Reset Password
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Security Information -->
                                <tr>
                                    <td>
                                        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 18px 20px; border-radius: 4px; margin: 25px 0;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="vertical-align: top; padding-right: 12px; width: 24px;">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M12 9V11M12 15H12.01M5.07183 19H18.9282C20.4678 19 21.4301 17.3333 20.6603 16L13.7321 4C12.9623 2.66667 11.0377 2.66667 10.2679 4L3.33978 16C2.56998 17.3333 3.53223 19 5.07183 19Z" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </td>
                                                    <td>
                                                        <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.5;">
                                                            <strong style="display: block; margin-bottom: 4px;">Security Notice:</strong>
                                                            This password reset link will expire in <strong>60 minutes</strong> for security reasons.
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <p style="margin: 20px 0 15px 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                            If you didn't request a password reset, please ignore this email or contact our support team if you have concerns. Your password will remain unchanged.
                                        </p>
                                    </td>
                                </tr>

                                <!-- Alternative Link -->
                                <tr>
                                    <td style="padding-top: 30px; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
                                            If the button above doesn't work, copy and paste this link into your browser:
                                        </p>
                                        <p style="margin: 0; word-break: break-all;">
                                            <a href="{{ $resetUrl }}"
                                               style="color: #2563eb; text-decoration: none; font-size: 13px; line-height: 1.6;">
                                                {{ $resetUrl }}
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    {{-- <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
                                            <strong style="color: #374151;">{{ config('app.name') }}</strong> - Journal Management System
                                        </p>
                                        <p style="margin: 0 0 15px 0; color: #9ca3af; font-size: 12px; line-height: 1.5;">
                                            This is an automated message. Please do not reply to this email.
                                        </p>
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> --}}
                </table>

                <!-- Email Client Compatibility Spacer -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 20px auto 0 auto;">
                    <tr>
                        <td style="text-align: center; padding: 0 20px;">
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; line-height: 1.5;">
                                If you have any questions or need assistance, please contact our support team.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
