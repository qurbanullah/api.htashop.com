LICENSE APPROVED - {{ $software->name }}

Hello {{ $user->name }},

Your license request for {{ $software->name }} has been approved.

LICENSE DETAILS:
================
Software: {{ $software->name }}
Version: {{ $version->name }}
Package: {{ $package->name }}
License Type: {{ $licenseType->name }}
Status: Approved
@if($license->expires_at)
Expires: {{ $license->expires_at->format('F j, Y') }}
@else
Duration: Perpetual License
@endif

DOWNLOAD YOUR LICENSE:
======================
Visit: {{ $downloadUrl }}

VIEW LICENSE DETAILS:
=====================
Visit: {{ $licenseDetailUrl }}

HOW TO ACTIVATE YOUR LICENSE:
=============================
1. Download the license file: Download the attached license file (.lic) or download it from your portal account by clicking the "View License Detail" link.

2. Launch Volvicon: Start Volvicon again. The License Manager dialog should appear automatically.

3. Import your license file: Select Existing license, click Browse, and choose the .lic file. Verify that the displayed license information is correct.

4. Complete activation: Click Go Back, close the dialog, and start using Volvicon.

WRITTEN GUIDE:
==============
https://help.volvicon.com/docs/getting-started/license-guide

VIDEO GUIDE:
============
https://volvicon.com/learning-center/volvicon-licensing-and-installation-complete-stepbystep-guide

IMPORTANT SECURITY NOTICE:
==========================
Keep your license files secure and do not share them with unauthorized users. Your license is tied to your account and should only be used in accordance with your license agreement.

NEED HELP?
==========
If you encounter any issues during activation or have questions about using {{ $software->name }}, our support team is here to help.

Create a support ticket: {{ $supportUrl }}
Email: support@volvicon.com
Website: https://volvicon.com

For assistance with activation or download, contact support.

---
Volvicon Team
This email was sent because your license status was updated.
If you have any questions, please contact our support team.
