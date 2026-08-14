# Volvicon Email Sender Script

## Overview

This document describes the current email-related changes in this folder:

1. `email-sender-script.py` sends the campaign email through Volvicon SMTP.
2. `email-preview.html` is the HTML email template used as the message body.
3. `recipients/*.csv` defines the recipient groups used by the sender script.

The current implementation supports category-based recipient selection, dry runs, SMTP authentication testing, recipient deduplication across categories, and a production-style multipart message with both plain-text and HTML bodies.

It also supports paced sending using:

1. configurable batch size
2. configurable delay between messages
3. configurable random jitter between messages
4. configurable cooldown pause between batches
5. automatic CSV export of non-delivered recipients after a live send

## Files Involved

1. `email-sender-script.py`
2. `email-preview.html`
3. `recipients/licensed-users.csv`
4. `recipients/registered-users.csv`
5. `recipients/old-users.csv`

## Requirements

### Runtime Requirements

1. Windows PowerShell or another shell that can run Python commands.
2. Python 3.14 installed and available from the terminal.
3. Access to the Volvicon SMTP account password.
4. A valid `email-preview.html` template in the same folder as the script.
5. At least one populated CSV file under `recipients/` if you want to send or preview a category.

### Python Requirement Note

The script has been validated in this environment with Python 3.14. Use Python 3.14 if you want the same execution path and behavior.

### No Third-Party Dependencies

The script uses only Python standard library modules:

1. `argparse`
2. `csv`
3. `logging`
4. `pathlib`
5. `smtplib`
6. `email`

You do not need `pip install` for this script.

## What The Script Does

When you run the script, it does the following:

1. Reads the SMTP password from `.env` or from the process environment.
2. Reads the HTML email template from `email-preview.html`.
3. Reads recipients from one or more category CSV files in `recipients/`.
4. Validates email addresses and header values.
5. Deduplicates recipients if the same email exists in multiple categories.
6. Builds a multipart email with plain-text and HTML versions.
7. Connects to `mail.volvicon.com` over implicit SSL on port `465`.
8. Sends email in paced batches with configurable delays and cooldowns.
9. Sends the email or performs a dry run, depending on the flags you pass.

## Default Pacing Strategy

The script now uses a conservative pacing profile by default:

1. Batch size: `8`
2. Between-message delay: `10` seconds
3. Between-message jitter: `+/- 2` seconds
4. Between-batch pause: `180` seconds

This corresponds to an effective pattern close to:

1. `8` messages per batch
2. `8-12` seconds between messages
3. `3` minutes between batches

This is intended to be a safer default for a new or lightly used Mailcow IP than sending all messages back-to-back.

## Failed Recipient Export

If a live send finishes with one or more non-delivered recipients, the script now writes two CSV files under `failed-recipients/`:

1. A timestamped snapshot such as `failed-recipients-20260320-153000.csv`
2. A rolling latest copy named `latest-failed-recipients.csv`

CSV columns:

1. `email`
2. `name`
3. `category`
4. `error_type`
5. `error`

This includes both:

1. explicit SMTP recipient refusals
2. SMTP or unexpected failures that prevented delivery

This is intended for operational recovery after transient SMTP deferrals like `451 4.7.1`.

The `failed-recipients/` folder is ignored by git so operational exports do not pollute source control.

## Delivery Outcome Summary

The live-send summary now reports:

1. `sent`: accepted by the SMTP server
2. `refused`: explicitly rejected by the SMTP server for that recipient
3. `failed`: SMTP/session/script errors that prevented a normal send attempt from completing
4. `total`: recipients processed for the run

For safety, the script exits with a non-zero code if any recipients are `refused` or `failed`.

## Current Campaign Template

The current email template in `email-preview.html` is a relaunch announcement for Volvicon. It includes:

1. Relaunch messaging.
2. Product capability highlights.
3. Licensing and onboarding steps.
4. Learning-center links.
5. A CTA button to `https://volvicon.com`.

## Recipient Categories

Recipient categories are stored as separate CSV files in `recipients/`.

Current categories:

1. `licensed-users`
2. `registered-users`
3. `old-users`

The category name is the CSV filename without `.csv`.

Examples:

1. `recipients/old-users.csv` becomes `old-users`
2. `recipients/registered-users.csv` becomes `registered-users`

## CSV Format

Each CSV file must contain an `email` column.

Recommended format:

```csv
email,name
person@example.com,Jane Doe
another@example.com,
```

Rules:

1. `email` is required.
2. `name` is optional.
3. Blank rows are ignored.
4. Duplicate email addresses across multiple selected categories are sent only once.

## How To Enter The SMTP Password

You have two supported ways to provide the password.

### Option 1: Put The Password In `.env`

Create or edit a file named `.env` in the same folder as `email-sender-script.py`.

Content:

```env
VOLVICON_SMTP_PASSWORD=your_real_password_here
```

Steps:

1. Open the folder `C:\dev\real3d\websites\api.volvicon.com\tools\emails-sender`.
2. Create or edit `.env`.
3. Add `VOLVICON_SMTP_PASSWORD=...`.
4. Save the file.
5. Run the script.

### Option 2: Set The Password In PowerShell For The Current Terminal Session

Use this command in PowerShell:

```powershell
$env:VOLVICON_SMTP_PASSWORD = "your_real_password_here"
```

Then run the script in the same terminal session.

Important behavior:

1. If both `.env` and the terminal environment contain a password, the script gives precedence to `.env`.
2. If no password is provided, the script exits with an error before sending.

## Step-By-Step Usage On Windows

### Step 1: Open PowerShell In The Project Folder

```powershell
Set-Location C:\dev\real3d\websites\api.volvicon.com\tools\emails-sender
```

### Step 2: Confirm Python 3.14 Is Available

```powershell
python --version
```

Expected result should show Python 3.14.x.

### Step 3: Enter The SMTP Password

Use one of these two methods:

1. Add it to `.env`.
2. Set `$env:VOLVICON_SMTP_PASSWORD` in the terminal.

### Step 4: Inspect Available Categories

```powershell
python email-sender-script.py --list-categories
```

### Step 5: Run A Dry Run First

```powershell
python email-sender-script.py --category old-users --dry-run
```

This builds the messages and prints the recipients, but does not send email.

### Step 6: Send The Real Campaign

```powershell
python email-sender-script.py --category old-users
```

### Step 7: Use A Conservative First Live Batch

For a cautious first campaign, you can keep the defaults or make them explicit:

```powershell
python email-sender-script.py --category old-users --batch-size 8 --send-delay-seconds 10 --send-delay-jitter-seconds 2 --batch-pause-seconds 180
```

## Command Reference

### List Categories

```powershell
python email-sender-script.py --list-categories
```

Use this to inspect which recipient category files are currently available.

### Dry Run One Category

```powershell
python email-sender-script.py --category old-users --dry-run
```

Use this before every real send.

### Send One Category

```powershell
python email-sender-script.py --category old-users
```

### Send Multiple Categories

```powershell
python email-sender-script.py --category licensed-users --category registered-users
```

### Send All Categories

```powershell
python email-sender-script.py --all-categories
```

### Limit The Number Of Recipients

```powershell
python email-sender-script.py --category old-users --limit 5
```

Use this for a controlled partial send.

### Send With Explicit Conservative Pacing

```powershell
python email-sender-script.py --category old-users --batch-size 8 --send-delay-seconds 10 --send-delay-jitter-seconds 2 --batch-pause-seconds 180
```

Use this if you want the pacing behavior to be explicit on the command line instead of relying on defaults.

### Adjust The Batch Pause

```powershell
python email-sender-script.py --category old-users --batch-pause-seconds 240
```

Use this if you want a slower cooldown between batches.

### Adjust The Within-Batch Delay

```powershell
python email-sender-script.py --category old-users --send-delay-seconds 12 --send-delay-jitter-seconds 1
```

Use this if you want slower, tighter pacing between individual messages.

### Test SMTP Authentication Only

```powershell
python email-sender-script.py --test-auth
```

This checks login only and does not send email.

Do not combine `--test-auth` with `--dry-run`. They serve different purposes and the script treats them as mutually exclusive.

## Recommended Workflow

Use this order when preparing a send:

1. Update the recipient CSV file or files.
2. Check the HTML template in `email-preview.html`.
3. Set the SMTP password.
4. Run `--list-categories`.
5. Run a `--dry-run` for the intended audience.
6. Optionally run with `--limit 1` or `--limit 5` for a controlled test.
7. Decide whether to keep the default pacing or override it.
8. Run the real send.

## Practical Use Cases

### Use Case 1: Preview The Email For Old Users

```powershell
python email-sender-script.py --category old-users --dry-run
```

Use this when you want to confirm the selected category and recipient loading without sending anything.

### Use Case 2: Send To Registered Users Only

```powershell
python email-sender-script.py --category registered-users
```

Use this when the campaign should target only users who created an account.

### Use Case 3: Send To Two Business Groups Together

```powershell
python email-sender-script.py --category licensed-users --category registered-users
```

Use this when the same campaign applies to both groups. If the same address appears in both lists, the script sends only one email to that address.

### Use Case 4: Test A Small Batch Before The Full Send

```powershell
python email-sender-script.py --category old-users --limit 2 --dry-run
python email-sender-script.py --category old-users --limit 2
```

Use this to validate a small batch before sending to the entire category.

### Use Case 5: Send A Conservative First Campaign

```powershell
python email-sender-script.py --category old-users --batch-size 8 --send-delay-seconds 10 --send-delay-jitter-seconds 2 --batch-pause-seconds 180
```

Use this when you want a conservative delivery profile for a new or lightly used sender.

### Use Case 6: Verify SMTP Credentials Before Campaign Day

```powershell
python email-sender-script.py --test-auth
```

Use this to confirm that the account can authenticate without sending any message.

### Use Case 7: Retry Only Non-Delivered Recipients

After a live send with transient nondeliveries, use the generated CSV in `failed-recipients/` to build a retry-only category file under `recipients/`.

For the current deferred `old-users` send, a retry-ready category file has already been created:

1. `recipients/old-users-retry-2026-03-20.csv`

You can resend only those addresses with:

```powershell
python email-sender-script.py --category old-users-retry-2026-03-20
```

If you want to be extra conservative on the retry run, use:

```powershell
python email-sender-script.py --category old-users-retry-2026-03-20 --batch-size 5 --send-delay-seconds 15 --send-delay-jitter-seconds 2 --batch-pause-seconds 240
```

## Safety Notes

1. Always run a dry run before a live send.
2. Use `--limit` when testing a campaign.
3. Keep recipient CSV files clean and category-specific.
4. Do not commit real passwords into source control.
5. Remember that `.env` takes precedence over terminal environment variables.
6. For a new or lightly used sender, prefer the default conservative pacing or slower.

## Troubleshooting

### Error: SMTP password not set

Cause:

1. `.env` is missing or does not contain `VOLVICON_SMTP_PASSWORD`.
2. The terminal environment variable is not set.

Fix:

1. Add the password to `.env`, or
2. Set `$env:VOLVICON_SMTP_PASSWORD` in PowerShell.

### Error: Recipient category directory not found

Cause:

1. The `recipients/` folder is missing.

Fix:

1. Recreate the `recipients/` folder.
2. Add the required CSV files.

### Error: Unknown categories

Cause:

1. The category name passed on the command line does not match any CSV filename.

Fix:

1. Run `python email-sender-script.py --list-categories`.
2. Use one of the category names shown there.

### Error: No recipients found for the selected categories

Cause:

1. The CSV file exists, but it is empty except for the header.
2. The rows are blank or invalid.

Fix:

1. Open the CSV file.
2. Confirm that it contains valid email addresses.

### Error: Authentication failed

Cause:

1. Wrong password.
2. Wrong SMTP account permissions.

Fix:

1. Re-enter the password.
2. Run `--test-auth`.
3. Verify the `team@volvicon.com` SMTP account is allowed to send.

### Some recipients failed with a transient SMTP error

Cause:

1. The SMTP server or downstream receiver temporarily deferred delivery.
2. The sender session or IP likely hit a temporary throttle window.

Fix:

1. Check the exported CSV in `failed-recipients/`.
2. Create or use a retry-only category file under `recipients/`.
3. Retry later with slower pacing.

### Some recipients were refused by the SMTP server

Cause:

1. The SMTP server explicitly rejected one or more recipient addresses during the send.
2. The rejection may be temporary (`4xx`) or permanent (`5xx`).

Fix:

1. Review the exported rows in `failed-recipients/`.
2. Retry only temporary refusals later.
3. Do not blindly retry permanent refusals without inspecting the address or policy reason.

## Summary

The email sender now supports a more operational workflow:

1. HTML email content is stored in `email-preview.html`.
2. Recipient groups are separated into CSV categories.
3. The sender script can preview, authenticate, limit, deduplicate, export nondeliveries, and send based on category selection.
4. Python 3.14 on Windows PowerShell is the recommended runtime path for this setup.
