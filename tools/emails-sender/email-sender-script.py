"""
Volvicon Bulk Email Sender
--------------------------
Production-ready script for sending personalised HTML emails via mailcow SMTP.

Usage:
    python email-sender-script.py --list-categories
    python email-sender-script.py --category old-users
    python email-sender-script.py --category licensed-users --category registered-users
    python email-sender-script.py --category old-users --dry-run
    python email-sender-script.py --category old-users --limit 5

Credentials are read from environment variables (never hard-coded):
    VOLVICON_SMTP_PASSWORD   – SMTP account password  (required)

Or from a local .env file in the same directory (see .env.example below).

.env.example:
    VOLVICON_SMTP_PASSWORD=your_password_here
"""

import argparse
import csv
from datetime import datetime
import logging
import os
import random
import re
import smtplib
import sys
import time
from email import charset as Charset
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formataddr, formatdate, make_msgid, parseaddr
from pathlib import Path

_QP = Charset.Charset("utf-8")
_QP.body_encoding = Charset.QP  # prefer quoted-printable over base64 for HTML

DOTENV_FILE = Path(__file__).parent / ".env"
RECIPIENTS_DIR = Path(__file__).parent / "recipients"
FAILED_RECIPIENTS_DIR = Path(__file__).parent / "failed-recipients"
EMAIL_ADDRESS_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")


class ConfigError(ValueError):
    """Raised when the mailer configuration is invalid."""


def load_secret(key: str, dotenv_path: Path) -> tuple[str, str]:
    """Load a secret from .env first, then the current process environment."""
    if dotenv_path.exists():
        for raw_line in dotenv_path.read_text(encoding="utf-8").splitlines():
            line = raw_line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            name, value = line.split("=", 1)
            if name.strip() == key:
                return value.strip().strip('"').strip("'"), ".env"

    env_value = os.environ.get(key, "")
    if env_value:
        return env_value.strip().strip('"').strip("'"), "environment"

    return "", "missing"


def get_env_secret(key: str) -> str:
    """Return the raw current-process environment value for a key."""
    return os.environ.get(key, "").strip().strip('"').strip("'")


def validate_header_value(value: str, field_name: str) -> str:
    """Reject newline characters that could break email headers."""
    if "\r" in value or "\n" in value:
        raise ConfigError(f"{field_name} contains an invalid newline character.")
    return value


def validate_email_address(value: str, field_name: str) -> str:
    """Return a normalized email address or raise on invalid input."""
    candidate = validate_header_value(value.strip(), field_name)
    _, parsed_email = parseaddr(candidate)
    if not parsed_email or parsed_email != candidate or not EMAIL_ADDRESS_RE.match(parsed_email):
        raise ConfigError(f"{field_name} is not a valid email address: {value!r}")
    return parsed_email


def validate_display_name(value: str, field_name: str) -> str:
    """Return a safe display name for email headers."""
    return validate_header_value(value.strip(), field_name)


def normalise_category_name(value: str) -> str:
    """Convert a user-provided category name to the CSV file slug."""
    category = value.strip().lower()
    category = re.sub(r"[^a-z0-9]+", "-", category).strip("-")
    if not category:
        raise ConfigError("Category name cannot be empty.")
    return category


def display_category_name(category: str) -> str:
    """Return a human-readable category label."""
    return category.replace("-", " ").title()


def get_category_file_path(category: str) -> Path:
    """Return the CSV file path for a category slug."""
    return RECIPIENTS_DIR / f"{category}.csv"


def list_available_categories() -> list[str]:
    """Return all available recipient category slugs."""
    if not RECIPIENTS_DIR.exists():
        return []
    return sorted(path.stem for path in RECIPIENTS_DIR.glob("*.csv") if path.is_file())


def parse_requested_categories(raw_values: list[str]) -> list[str]:
    """Parse repeated or comma-separated category arguments."""
    categories: list[str] = []
    seen: set[str] = set()
    for raw_value in raw_values:
        for token in raw_value.split(","):
            category = normalise_category_name(token)
            if category not in seen:
                categories.append(category)
                seen.add(category)
    return categories


def load_recipients_from_category(category: str) -> list[dict[str, str]]:
    """Load normalized recipients from a category CSV file."""
    csv_path = get_category_file_path(category)
    if not csv_path.exists():
        raise ConfigError(f"Recipient category file not found: {csv_path}")

    with csv_path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise ConfigError(f"Recipient category file is empty: {csv_path}")

        field_map = {
            field_name.strip().lower(): field_name
            for field_name in reader.fieldnames
            if field_name and field_name.strip()
        }
        email_field = field_map.get("email")
        name_field = field_map.get("name")

        if not email_field:
            raise ConfigError(f"Recipient category file must contain an 'email' column: {csv_path}")

        recipients: list[dict[str, str]] = []
        for row_number, row in enumerate(reader, start=2):
            row_values = [(value or "").strip() for value in row.values()]
            if not any(row_values):
                continue

            raw_email = (row.get(email_field) or "").strip()
            raw_name = (row.get(name_field) or "").strip() if name_field else ""
            email = validate_email_address(raw_email, f"Recipient email in {csv_path.name}:{row_number}")
            name = validate_display_name(raw_name, f"Recipient name in {csv_path.name}:{row_number}")
            recipients.append({
                "email": email,
                "name": name,
                "category": category,
            })

    return recipients


def load_recipients(categories: list[str]) -> tuple[list[dict[str, str]], int]:
    """Load and deduplicate recipients across the selected categories."""
    recipients: list[dict[str, str]] = []
    seen_emails: set[str] = set()
    duplicate_count = 0

    for category in categories:
        for recipient in load_recipients_from_category(category):
            email_key = recipient["email"].lower()
            if email_key in seen_emails:
                duplicate_count += 1
                continue
            seen_emails.add(email_key)
            recipients.append(recipient)

    return recipients, duplicate_count


SMTP_HOST = "mail.volvicon.com"
SMTP_PORT = 465  # 465 = implicit SSL (SMTP_SSL) — confirmed working
                 # 587 = STARTTLS (not available on this server)
SMTP_USERNAME = "team@volvicon.com"
SMTP_PASSWORD, SMTP_PASSWORD_SOURCE = load_secret("VOLVICON_SMTP_PASSWORD", DOTENV_FILE)

FROM_NAME = "Volvicon Team"
FROM_EMAIL = "team@volvicon.com"
REPLY_TO = "team@volvicon.com"
SUBJECT = "Volvicon Has Relaunched - Explore the New Platform"

HTML_FILE = Path(__file__).parent / "email-preview.html"
DEFAULT_BATCH_SIZE = 8
DEFAULT_SEND_DELAY_SECONDS = 10.0
DEFAULT_SEND_DELAY_JITTER_SECONDS = 2.0
DEFAULT_BATCH_PAUSE_SECONDS = 180.0
UNSUBSCRIBE_EMAIL = "unsubscribe@volvicon.com"


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s  %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("volvicon_mailer")


def build_message(to_email: str, to_name: str, html_body: str) -> MIMEMultipart:
    """Construct a standards-compliant MIME email message."""
    msg = MIMEMultipart("alternative")

    msg["Subject"] = SUBJECT
    msg["From"] = formataddr((FROM_NAME, FROM_EMAIL))
    msg["To"] = formataddr((to_name, to_email)) if to_name else to_email
    msg["Reply-To"] = REPLY_TO
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid(domain=FROM_EMAIL.split("@")[1])
    msg["List-Unsubscribe"] = f"<mailto:{UNSUBSCRIBE_EMAIL}?subject=unsubscribe>"

    plain_text = (
        f"Dear{' ' + to_name if to_name else ' Volvicon User'},\n\n"
        "Volvicon has relaunched with significantly enhanced tools for medical "
        "and industrial 3D imaging.\n\n"
        "Visit https://volvicon.com to explore the new platform.\n\n"
        "To get your license:\n"
        "  1. Register at https://manage.volvicon.com/register\n"
        "  2. Download Volvicon from https://manage.volvicon.com/downloads\n"
        "  3. Generate your hardware key (.dat) inside the app\n"
        "  4. Submit a license request at https://manage.volvicon.com/licenses/create\n"
        "     (Include USER1YEAR in the message for a free 1-year evaluation)\n"
        "  5. Apply the issued license file to activate Volvicon\n\n"
        "- The Volvicon Team\n"
        "https://volvicon.com\n\n"
        f"To unsubscribe, email {UNSUBSCRIBE_EMAIL} with subject: unsubscribe"
    )

    plain_part = MIMEText(plain_text, "plain", "utf-8")
    del plain_part["MIME-Version"]

    html_part = MIMEText(html_body, "html", _charset=_QP)
    del html_part["MIME-Version"]

    msg.attach(plain_part)
    msg.attach(html_part)
    return msg


def read_html_template(path: Path) -> str:
    """Read the HTML template and strip a UTF-8 BOM if present."""
    return path.read_text(encoding="utf-8-sig")


def get_inter_send_delay(base_delay_seconds: float, jitter_seconds: float) -> float:
    """Return the next between-message delay using symmetric jitter."""
    if base_delay_seconds <= 0:
        return 0.0
    if jitter_seconds <= 0:
        return base_delay_seconds

    minimum_delay = max(0.0, base_delay_seconds - jitter_seconds)
    maximum_delay = base_delay_seconds + jitter_seconds
    return random.uniform(minimum_delay, maximum_delay)


def export_failed_recipients(failed_recipients: list[dict[str, str]]) -> tuple[Path, Path] | None:
    """Persist non-delivered recipients to timestamped and latest CSV files."""
    if not failed_recipients:
        return None

    FAILED_RECIPIENTS_DIR.mkdir(exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    export_path = FAILED_RECIPIENTS_DIR / f"failed-recipients-{timestamp}.csv"
    latest_path = FAILED_RECIPIENTS_DIR / "latest-failed-recipients.csv"
    fieldnames = ["email", "name", "category", "error_type", "error"]

    for output_path in (export_path, latest_path):
        with output_path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(handle, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(failed_recipients)

    return export_path, latest_path


def _ensure_server(server: smtplib.SMTP) -> smtplib.SMTP:
    """Return the server if still alive, otherwise reconnect transparently."""
    try:
        server.noop()
        return server
    except (smtplib.SMTPException, OSError):
        log.warning("SMTP connection lost — reconnecting to %s:%d...", SMTP_HOST, SMTP_PORT)
        new_server = smtplib.SMTP_SSL(SMTP_HOST, SMTP_PORT, timeout=30)
        new_server.ehlo()
        new_server.login(SMTP_USERNAME, SMTP_PASSWORD)
        log.info("Reconnected successfully.")
        return new_server


def validate_config(*, require_template: bool, require_recipient_dir: bool, require_password: bool = True) -> bool:
    """Check required config before attempting any sends."""
    ok = True

    if require_password and not SMTP_PASSWORD:
        if DOTENV_FILE.exists():
            log.error(
                "SMTP password not set. '%s' exists, but VOLVICON_SMTP_PASSWORD was not found in it and is not set in the environment.",
                DOTENV_FILE,
            )
        else:
            log.error(
                "SMTP password not set. Export VOLVICON_SMTP_PASSWORD as an environment variable or add it to %s.",
                DOTENV_FILE,
            )
        ok = False

    if require_template and not HTML_FILE.exists():
        log.error("HTML template not found: %s", HTML_FILE)
        ok = False

    if require_recipient_dir and not RECIPIENTS_DIR.exists():
        log.error("Recipient category directory not found: %s", RECIPIENTS_DIR)
        ok = False

    try:
        validate_display_name(FROM_NAME, "FROM_NAME")
        validate_email_address(FROM_EMAIL, "FROM_EMAIL")
        validate_email_address(REPLY_TO, "REPLY_TO")
        validate_email_address(SMTP_USERNAME, "SMTP_USERNAME")
        validate_email_address(UNSUBSCRIBE_EMAIL, "UNSUBSCRIBE_EMAIL")
    except ConfigError as exc:
        log.error("%s", exc)
        ok = False

    return ok


def main() -> None:
    parser = argparse.ArgumentParser(description="Volvicon bulk email sender")
    parser.add_argument(
        "--category",
        action="append",
        default=[],
        metavar="CATEGORY",
        help="Recipient category slug to send to. May be repeated or comma-separated.",
    )
    parser.add_argument(
        "--all-categories",
        action="store_true",
        help="Send to all category CSV files found in the recipients directory.",
    )
    parser.add_argument(
        "--list-categories",
        action="store_true",
        help="List available recipient categories and exit.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Build and log each message but do not actually send.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=0,
        metavar="N",
        help="Send to the first N recipients only (0 = all).",
    )
    parser.add_argument(
        "--test-auth",
        action="store_true",
        help="Only verify SMTP login and exit without sending any email.",
    )
    parser.add_argument(
        "--batch-size",
        type=int,
        default=DEFAULT_BATCH_SIZE,
        metavar="N",
        help="Recipients to send per batch before a cooldown pause. Default: 8.",
    )
    parser.add_argument(
        "--send-delay-seconds",
        type=float,
        default=DEFAULT_SEND_DELAY_SECONDS,
        metavar="SECONDS",
        help="Base delay between messages inside a batch. Default: 10 seconds.",
    )
    parser.add_argument(
        "--send-delay-jitter-seconds",
        type=float,
        default=DEFAULT_SEND_DELAY_JITTER_SECONDS,
        metavar="SECONDS",
        help="Random jitter applied around the base between-message delay. Default: 2 seconds.",
    )
    parser.add_argument(
        "--batch-pause-seconds",
        type=float,
        default=DEFAULT_BATCH_PAUSE_SECONDS,
        metavar="SECONDS",
        help="Cooldown pause between batches. Default: 180 seconds.",
    )
    args = parser.parse_args()

    if args.limit < 0:
        parser.error("--limit must be 0 or a positive integer.")
    if args.all_categories and args.category:
        parser.error("Use either --category or --all-categories, not both.")
    if args.dry_run and args.test_auth:
        parser.error("Use either --dry-run or --test-auth, not both.")
    if args.batch_size <= 0:
        parser.error("--batch-size must be a positive integer.")
    if args.send_delay_seconds < 0:
        parser.error("--send-delay-seconds cannot be negative.")
    if args.send_delay_jitter_seconds < 0:
        parser.error("--send-delay-jitter-seconds cannot be negative.")
    if args.batch_pause_seconds < 0:
        parser.error("--batch-pause-seconds cannot be negative.")

    try:
        requested_categories = parse_requested_categories(args.category)
    except ConfigError as exc:
        parser.error(str(exc))

    available_categories = list_available_categories()
    if args.list_categories:
        if not RECIPIENTS_DIR.exists():
            parser.error(f"Recipient category directory not found: {RECIPIENTS_DIR}")
        if not available_categories:
            log.info("No recipient categories found in %s", RECIPIENTS_DIR)
            return
        log.info("Available recipient categories:")
        for category in available_categories:
            log.info("  %s (%s)", display_category_name(category), category)
        return

    _log_fh = logging.FileHandler(Path(__file__).parent / "send.log", encoding="utf-8")
    _log_fh.setFormatter(logging.Formatter("%(asctime)s  %(levelname)-8s  %(message)s", datefmt="%Y-%m-%d %H:%M:%S"))
    logging.getLogger().addHandler(_log_fh)

    selected_categories = available_categories if args.all_categories else requested_categories
    if not args.test_auth and not selected_categories:
        parser.error("Specify --category CATEGORY or use --all-categories.")

    unknown_categories = [category for category in selected_categories if category not in available_categories]
    if unknown_categories:
        parser.error(
            "Unknown categories: "
            + ", ".join(unknown_categories)
            + ". Use --list-categories to inspect available options."
        )

    if not validate_config(
        require_template=not args.test_auth,
        require_recipient_dir=bool(selected_categories),
        require_password=not args.dry_run,
    ):
        sys.exit(1)

    html_body = read_html_template(HTML_FILE) if not args.test_auth else ""

    recipients: list[dict[str, str]] = []
    duplicate_count = 0
    if selected_categories:
        try:
            recipients, duplicate_count = load_recipients(selected_categories)
        except (ConfigError, OSError, csv.Error) as exc:
            log.error("Failed to load recipient categories: %s", exc)
            sys.exit(1)
        if not recipients:
            log.error("No recipients found for the selected categories: %s", ", ".join(selected_categories))
            sys.exit(1)

    if args.limit > 0:
        recipients = recipients[: args.limit]

    total = len(recipients)
    sent = refused = failed = 0
    failed_recipients: list[dict[str, str]] = []

    log.info("═" * 52)
    log.info("Volvicon Mailer — %s", "DRY RUN" if args.dry_run else "LIVE SEND")
    log.info("SMTP      : %s:%s", SMTP_HOST, SMTP_PORT)
    log.info("From      : %s <%s>", FROM_NAME, FROM_EMAIL)
    if selected_categories:
        log.info("Categories: %s", ", ".join(selected_categories))
        log.info("Recipients: %d", total)
    if duplicate_count:
        log.info("Deduped   : %d duplicate recipient(s) skipped", duplicate_count)
    log.info(
        "Pacing    : batch=%d | inter-send=%.1fs +/- %.1fs | batch-pause=%.1fs",
        args.batch_size,
        args.send_delay_seconds,
        args.send_delay_jitter_seconds,
        args.batch_pause_seconds,
    )
    log.info("Auth src  : %s", SMTP_PASSWORD_SOURCE)
    env_password = get_env_secret("VOLVICON_SMTP_PASSWORD")
    if SMTP_PASSWORD_SOURCE == ".env" and env_password:
        log.info("Auth note : .env is taking precedence over terminal environment.")
    log.info("═" * 52)

    if args.dry_run:
        for recipient in recipients:
            msg = build_message(recipient["email"], recipient["name"], html_body)
            log.info("[DRY-RUN] Would send to: %s [%s]", msg["To"], recipient["category"])
        log.info("Dry-run complete — no emails were sent.")
        return

    try:
        server = smtplib.SMTP_SSL(SMTP_HOST, SMTP_PORT, timeout=30)
        server.ehlo()
        server.login(SMTP_USERNAME, SMTP_PASSWORD)
        log.info("SMTP connection established (SSL port %d).", SMTP_PORT)
    except smtplib.SMTPAuthenticationError:
        log.critical(
            "Authentication failed. Check VOLVICON_SMTP_PASSWORD and that '%s' is allowed to send on the mailcow server.",
            SMTP_USERNAME,
        )
        sys.exit(1)
    except smtplib.SMTPException as exc:
        log.critical("Failed to connect to SMTP server: %s", exc)
        sys.exit(1)

    if args.test_auth:
        try:
            server.quit()
        except Exception:
            pass
        log.info("SMTP authentication succeeded. No email was sent.")
        return

    try:
        for index, recipient in enumerate(recipients, start=1):
            email = recipient["email"]
            name = recipient["name"]
            label = f"[{index}/{total}]"

            try:
                server = _ensure_server(server)
                msg = build_message(email, name, html_body)
                server.sendmail(FROM_EMAIL, [email], msg.as_bytes())
                sent += 1
                log.info("%s Sent     → %s [%s]", label, email, recipient["category"])
            except smtplib.SMTPRecipientsRefused as exc:
                refused += 1
                code, detail = exc.recipients.get(email, (0, b""))
                failed_recipients.append({
                    "email": email,
                    "name": name,
                    "category": recipient["category"],
                    "error_type": "refused",
                    "error": f"{code} {detail.decode(errors='replace')}",
                })
                log.warning(
                    "%s REFUSED  → %s [%s] (%d %s)",
                    label,
                    email,
                    recipient["category"],
                    code,
                    detail.decode(errors="replace"),
                )
            except smtplib.SMTPException as exc:
                failed += 1
                failed_recipients.append({
                    "email": email,
                    "name": name,
                    "category": recipient["category"],
                    "error_type": "smtp",
                    "error": str(exc),
                })
                log.error("%s FAILED   → %s [%s]  (%s)", label, email, recipient["category"], exc)
            except Exception as exc:  # noqa: BLE001
                failed += 1
                failed_recipients.append({
                    "email": email,
                    "name": name,
                    "category": recipient["category"],
                    "error_type": "unexpected",
                    "error": str(exc),
                })
                log.error("%s ERROR    → %s [%s]  (%s)", label, email, recipient["category"], exc)

            if index < total:
                if index % args.batch_size == 0:
                    if args.batch_pause_seconds > 0:
                        log.info("%s Cooldown → %.1fs before next batch", label, args.batch_pause_seconds)
                        time.sleep(args.batch_pause_seconds)
                else:
                    next_delay = get_inter_send_delay(
                        args.send_delay_seconds,
                        args.send_delay_jitter_seconds,
                    )
                    if next_delay > 0:
                        log.info("%s Delay    → %.1fs before next message", label, next_delay)
                        time.sleep(next_delay)
    finally:
        try:
            server.quit()
        except Exception:
            pass

    log.info("═" * 52)
    log.info(
        "Done — sent: %d  |  refused: %d  |  failed: %d  |  total: %d",
        sent,
        refused,
        failed,
        total,
    )
    log.info("Full log saved to: %s", Path(__file__).parent / "send.log")
    if failed_recipients:
        try:
            export_path, latest_path = export_failed_recipients(failed_recipients)
        except OSError as exc:
            log.error("Failed to write failed-recipient export: %s", exc)
        else:
            log.info("Failed list : %s", export_path)
            log.info("Latest fail : %s", latest_path)
    log.info("═" * 52)

    if refused or failed:
        sys.exit(1)


if __name__ == "__main__":
    main()
