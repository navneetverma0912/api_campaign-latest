# Campaign API

This project is a PHP-based campaign analytics and automation toolkit for a CRM / marketing workflow. It connects to a MySQL database, reads campaign activity, surfaces campaign metrics through JSON endpoints, and integrates with Google Analytics and LinkedIn-related automation.

## Project purpose

The codebase supports:

- Campaign performance reporting
- Tracking campaign activity such as sent, opened, clicked, and opt-out events
- Dashboard-style JSON responses for campaign data
- Google Analytics data retrieval for campaign reporting
- LinkedIn token and post workflows
- Weekly campaign report email generation via the `weekly_mail` subproject

## Main files

- `campaign-dashboard.php` — main campaign dashboard API endpoint returning campaign metrics as JSON
- `campaign-dashboard-trivium.php` — variant for a separate Trivium dataset or environment
- `ga4-eurekii.php` and `ga4-trivium.php` — Google Analytics 4 integrations
- `linkedin.php`, `linkedin_cre.php`, `linkedin_post.php`, `linkedin_savetoken.php` — LinkedIn integration scripts
- `db.php` — PDO database connection setup
- `config.php` — database credentials and environment configuration
- `weekly_mail/` — standalone email report utility that consumes the campaign API

## Requirements

- PHP 8.1+
- MySQL / MariaDB
- Composer (for vendor dependencies)
- Apache or XAMPP / local PHP hosting environment

## Setup

1. Clone or copy the project into your local PHP environment, such as `C:/xampp/htdocs/api_campaign-latest`.
2. Install Composer dependencies:

   ```bash
   composer install
   ```

3. Update the database connection settings in `config.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

4. Ensure your MySQL database contains the campaign tables used by the scripts, such as `campaign`, `campaign_log_record`, and related tracking tables.
5. Configure the administrator login in the project-root `.env` file. The file is excluded from Git and denied by Apache. The current local `.env` is configured with username `Admin` and the password you specified, stored as a password hash. This password is weak; change it before making the dashboard accessible outside a trusted local network.

   To choose a different password and API token, generate their values locally:

   ```bash
   php -r "echo password_hash('replace-with-your-password', PASSWORD_DEFAULT), PHP_EOL;"
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   Set the generated values in `.env`:

   ```dotenv
   DASHBOARD_ADMIN_USERNAME=your-admin-username
   DASHBOARD_ADMIN_PASSWORD_HASH=paste-the-generated-hash-here
   DASHBOARD_API_TOKEN=paste-the-generated-api-token-here
   ```

   Environment variables set directly by Apache take precedence over `.env`. Restart Apache after changing its configuration.
6. Run the project through XAMPP Apache. Ensure Apache has `mod_rewrite` enabled and allows `.htaccess` overrides so dashboard HTML routes open through the authenticated page.

## Running the API

Open the project root or `login.php` in a browser and sign in with the configured administrator account. The dashboard and its campaign and GA4 data endpoints require an authenticated administrator session.

Automated report scripts use the same API endpoints without a browser session. They read `DASHBOARD_API_TOKEN` from the project `.env` unless it is set directly in the runner's environment. Requests without a valid administrator session or API token receive HTTP 401.

```bash
http://localhost/api_campaign-latest/
```

The dashboard displays campaign metrics and engagement information after login.

## Weekly email reports

A separate utility exists in the `weekly_mail/` directory for sending campaign summaries by email.

See the README there for detailed setup:

```bash
weekly_mail/README.md
```

## Notes

- `config.php` stores database credentials and should not be committed to public repositories if it contains real secrets.
- `.env` contains the administrator password hash and report API token; it is Git-ignored and denied by Apache. Never commit or share this file.
- This project is designed for a specific CRM/data structure and may require adjustments to table names or field mappings depending on your database schema.
- The API responses are intended for dashboard and reporting use and may be consumed by frontend applications or cron-driven automation.

## License

No explicit license file is included in this repository. Please confirm with the project owner before reusing or redistributing the code in a production or public environment.
