# Campaign report emailer

Pulls campaign stats from your existing API (`index.php`) and emails a styled
HTML report per campaign over SMTP, using PHPMailer.

## Setup

1. Upload this folder to your server (anywhere PHP + `curl` can run — a cron box is fine, it doesn't need to be public).
2. Install PHPMailer:
   ```bash
   composer install
   ```
3. Copy the config template and fill in your real values:
   ```bash
   cp config.example.php config.php
   ```
   Edit `config.php`:
   - `api_url` — full URL to your campaign API endpoint (the file you shared).
   - `smtp_host`, `smtp_port`, `smtp_secure`, `smtp_user`, `smtp_pass` — your mailbox/SMTP relay credentials.
   - `from_email` / `from_name` — sender identity.
   - `recipients` — who gets the report emails.
   - `logo_path` — optional. Drop a PNG/JPG in `assets/` and point to it, or leave the file missing and the logo block is skipped automatically.
4. `config.php` holds credentials — keep it out of version control / public web roots.

## Sending reports

```bash
# Email a report for every campaign the API returns
php send-campaign-reports.php

# Just one campaign
php send-campaign-reports.php --campaign=123

# Override recipients for this run only
php send-campaign-reports.php --to=someone@else.com,another@else.com
```

Wire it into cron for a recurring digest, e.g. every Monday at 8am:

```
0 8 * * 1 /usr/bin/php /path/to/send-campaign-reports.php >> /path/to/report.log 2>&1
```

## What's in the email vs. what isn't

Your API returns, per campaign: sent / opened / clicked / opted-out counts,
the four derived rates (open, click, CTOR, opt-out), and the list of contacts
who clicked. The report template uses exactly that.

Two sections from your original mockup **aren't in this build** because the
API has no data for them yet:
- the "Follow-up" funnel (second send to openers)
- the "Click breakdown by channel" table (LinkedIn / Calendar / Flyer)

If you add queries for those to the API later (e.g. a `campaign_id` for the
follow-up send, and a channel/label column on `campaign_tracking_url`), it's
a quick add — say the word and I'll wire them in.

One thing worth knowing: in the API, `end_date` is currently hard-replaced by
`start_date` (`"end"=>$campaign['start_date']`), so every report will show
the same start and end date until that's changed.

## Files

- `send-campaign-reports.php` — main script (fetch → render → send)
- `config.example.php` — copy to `config.php` and fill in
- `templates/report-template.html` — the email layout, with `{{PLACEHOLDERS}}`
- `templates/contact-row.html` — one contact card, repeated per engaged contact
- `composer.json` — PHPMailer dependency
