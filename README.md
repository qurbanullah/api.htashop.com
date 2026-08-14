# Email Preview Setup And Generation

## Community Forum

This API also exposes the Volvicon community/forum backend used by the admin portal, the manage portal, and the public frontend.

Current forum responsibilities in this repository:

1. Topic management for admins.
2. Public post listing and post detail retrieval.
3. Authenticated post creation, editing, deletion, likes, comments, and reporting.
4. Admin moderation for posts, comments, reports, and forum statistics.

Important route groups:

1. Public: `/api/v1/forum/topics`, `/api/v1/forum/posts`, `/api/v1/forum/posts/{slug}`, `/api/v1/forum/posts/{slug}/comments`
2. Authenticated user: `/api/v1/forum/my-posts`, `/api/v1/forum/posts`, `/api/v1/forum/comments/*`, `/api/v1/forum/report`
3. Admin: `/api/v1/admin/forum/topics`, `/api/v1/admin/forum/posts`, `/api/v1/admin/forum/comments`, `/api/v1/admin/forum/reports`, `/api/v1/admin/forum/stats`

Important implementation areas:

1. Models: `ForumTopic`, `ForumPost`, `ForumComment`, `ForumLike`, `ForumReport`
2. Services: `app/Services/Forum/`
3. Controllers: `app/Http/Controllers/V1/Forum/`
4. Requests and resources: `app/Http/Requests/V1/Forum/` and `app/Http/Resources/V1/Forum/`
5. Tests: `tests/Feature/Forum/`

This repository now includes a PowerShell helper that regenerates the email preview HTML files under public/previews using stable local fixture data.

## What Was Done Locally

The local setup that was used to validate and generate previews was:

1. Install PHP 8.4 on Windows and make sure php.exe is available.
2. Install Composer so Laravel dependencies can be installed.
3. Create .env from .env.example and configure the local database.
4. Install PHP dependencies with Composer.
5. Generate the Laravel application key.
6. Run the database migrations.
7. Seed the languages table with LanguageSeeder.
8. Generate the preview HTML files into public/previews.
9. Optionally serve the public folder locally and open the preview pages in a browser.

## Prerequisites

Before generating previews, make sure these are already in place:

1. PHP 8.4 is installed.
2. Composer dependencies are installed, so vendor/autoload.php exists.
3. .env exists in the repository root.
4. The application key has been generated.
5. Migrations have been run.
6. LanguageSeeder has been executed.

If the application is not set up yet, complete the normal Laravel bootstrap first. The preview script does not rewrite your environment configuration.

## Generate All Preview HTML Files

Run the PowerShell script below from the repository root or by using its full path:

cd path\to\api.volvicon.com\scripts
.\generate-email-previews.ps1

What the script does:

1. Locates php.exe automatically, or uses the path you pass in with -PhpPath.
2. Verifies that .env, artisan, and vendor/autoload.php already exist.
3. Boots the Laravel application.
4. Creates or updates stable preview fixture records for users, feedback, comments, tickets, assignments, software, versions, packages, license types, and licenses.
5. Renders the current mailables into static HTML files.
6. Writes the HTML files into public/previews.

The script generates these files:

1. public/previews/feedback-submitted.html
2. public/previews/feedback-reply.html
3. public/previews/feedback-comment.html
4. public/previews/license-request-user.html
5. public/previews/license-request-admin.html
6. public/previews/license-approved-user.html
7. public/previews/license-approved-admin.html
8. public/previews/license-activated-user.html
9. public/previews/license-activated-admin.html
10. public/previews/license-deactivated-user.html
11. public/previews/ticket-created.html
12. public/previews/ticket-resolved-creator.html
13. public/previews/ticket-resolved-support.html
14. public/previews/ticket-unassigned-creator.html
15. public/previews/ticket-unassigned-previous-assignee.html

## Optional Script Parameters

The script accepts these optional parameters:

1. -PhpPath
Use this if php.exe is not on PATH.

2. -StartPreviewServer
After generating the HTML files, start PHP's built-in static server for the public folder.

3. -PreviewPort
Choose a different local port when using -StartPreviewServer. The default is 18082.

## Open The Preview Pages

After generation, open any file directly from public/previews, or use the script with -StartPreviewServer and browse to URLs such as:

1. <http://localhost:18082/previews/feedback-submitted.html>
2. <http://localhost:18082/previews/license-approved-user.html>
3. <http://localhost:18082/previews/ticket-created.html>

## Notes

1. The preview content still reflects your local .env values for settings such as app name, frontend URLs, and mail sender values.
2. The script is designed to be repeatable. Running it again refreshes the same preview fixture records and overwrites the generated HTML files.
3. The script only generates preview HTML files. It does not stage files, commit changes, or modify deployment configuration.
