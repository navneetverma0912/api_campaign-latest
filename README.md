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
5. Run the project through your PHP server (for example, XAMPP Apache or a PHP web server).

## Running the API

The dashboard endpoints are PHP files that can be accessed directly via the browser or a server-side HTTP client.

Example:

```bash
http://localhost/api_campaign-latest/campaign-dashboard.php
```

This returns JSON data containing campaign metrics and engagement information.

## Weekly email reports

A separate utility exists in the `weekly_mail/` directory for sending campaign summaries by email.

See the README there for detailed setup:

```bash
weekly_mail/README.md
```

## Notes

- `config.php` stores database credentials and should not be committed to public repositories if it contains real secrets.
- This project is designed for a specific CRM/data structure and may require adjustments to table names or field mappings depending on your database schema.
- The API responses are intended for dashboard and reporting use and may be consumed by frontend applications or cron-driven automation.

## License

No explicit license file is included in this repository. Please confirm with the project owner before reusing or redistributing the code in a production or public environment.
