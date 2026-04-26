# F1 Racing Management System

A multi-role web application for managing Formula 1 race data, built with PHP 8.1 and MySQL 8.0 as a university Database & Applications course project.

## Requirements

- Apache 2.4+ with `mod_rewrite` enabled
- PHP 8.1+
- MySQL 8.0+

## Setup

### 1. Place files

Copy the project folder to your web server root:

```
/var/www/html/f1app/     # Linux/Apache
C:\xampp\htdocs\f1app\   # XAMPP on Windows
```

### 2. Create the database

Run the DDL script to create the database, tables, and seed data:

```bash
# Linux
sudo mysql -u root -p < /var/www/html/f1app/f1app_ddl.sql

# XAMPP (Windows) — open XAMPP Shell
mysql -u root < C:\xampp\htdocs\f1app\f1app_ddl.sql
```

### 3. Configure the database connection

Edit `config/database.php` and update the credentials if yours differ from the defaults:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'f1app');
define('DB_USER', 'root');
define('DB_PASS', 'root');
```

### 4. Configure Apache (Linux only)

Enable the Apache PHP module and restart:

```bash
sudo a2enmod php8.1
sudo systemctl restart apache2
```

The application does not use `.htaccess` rewrites — all navigation uses direct `?param=` query strings.

### 5. Open in browser

Navigate to:

```
http://localhost/f1app/
```

---

## Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@f1app.com | Admin@F1#2025 |
| Race Director | director@f1app.com | Admin@F1#2025 |
| Team Manager | manager@f1app.com | Demo@F1#2025 |
| Engineer | engineer@f1app.com | Demo@F1#2025 |
| Driver | driver@f1app.com | Demo@F1#2025 |
| Media | media@f1app.com | Demo@F1#2025 |
| Fan | fan@f1app.com | Demo@F1#2025 |

---

## Application Structure

```
f1app/
├── admin/          — User and system management (admin role only)
├── race_director/  — Race entries, qualifying, results, penalties
├── team_manager/   — Driver/engineer rosters, CSV export
├── engineer/       — Lap telemetry and pit stop entry
├── driver/         — Personal race history and telemetry view
├── media/          — Championship standings and race results
├── public/         — Public standings and circuit info (no login required)
├── shared/         — Change password (all roles)
├── auth/           — Login, logout, password reset
├── config/         — Database connection and application constants
├── includes/       — Shared layout (header, footer, helper functions)
├── middleware/      — Session management and role enforcement
└── f1app_ddl.sql   — Full DDL with seed data
```

## Security Notes

- Passwords are hashed with bcrypt (cost 12) via `password_hash()`
- All database queries use PDO prepared statements
- Every POST form is protected with a CSRF token
- All output is escaped with `htmlspecialchars()`
- Sessions regenerate on login (`session_regenerate_id(true)`)
- Single active session per restricted-role user (race_director, team_manager, engineer, driver)
- 2-hour session timeout with last-activity tracking
