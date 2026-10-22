#!/usr/bin/env bash
#
# Fail if punchout internals leak outside the punchout boundary.
#
# Run from the api/ directory (or via CI). This is intentionally a simple grep:
# it catches the common accident of a storefront class importing a Punchout
# action, service or model. The Pest architecture test covers relationship
# rules; this script covers the filesystem boundary.
#
# It searches production code only. Tests are allowed to reference the model
# freely; the architecture test covers class-level coupling.

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

pattern='App\\(Actions|Services|Support|Interfaces|Http\\Controllers\\V1|Http\\Requests\\V1|Http\\Resources\\V1|Console\\Commands)\\Punchout|PunchoutSession|punchout_session_id'

status=0
files=$(grep -RIlE --include='*.php' "$pattern" app routes 2>/dev/null || true)

while IFS= read -r file; do
    [ -z "$file" ] && continue

    case "$file" in
        app/Actions/Punchout/*|\
        app/Services/Punchout/*|\
        app/Support/Punchout/*|\
        app/Interfaces/Punchout/*|\
        app/Http/Controllers/V1/Punchout/*|\
        app/Http/Requests/V1/Punchout/*|\
        app/Http/Resources/V1/Punchout/*|\
        app/Console/Commands/Punchout/*|\
        app/Models/PunchoutSession.php|\
        app/Models/Tenant.php|\
        app/Models/Cart.php|\
        app/Http/Middleware/ResolvePunchoutSession.php|\
        app/Providers/AppServiceProvider.php|\
        routes/punchout.php|\
        bootstrap/app.php)
            ;;
        *)
            echo "punchout reference outside the allowed boundary: $file" >&2
            status=1
            ;;
    esac
done <<< "$files"

if [ "$status" -ne 0 ]; then
    echo "" >&2
    echo "Move the code under the matching Punchout namespace, or add the path to this allowlist with a reason." >&2
fi

exit "$status"
