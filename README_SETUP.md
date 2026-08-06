# StreetSafe Insight Setup

## Stack

- Frontend: Vanilla JavaScript + HTML
- Backend: PHP
- Database: MySQL

## What the system supports

- Public dashboard with street profiles and report trends
- Community registration and login
- Community dashboards with personal profile, reports, and notifications
- Police dashboards with incident logs, responses, and notifications
- Database-backed storage for users, streets, incidents, and notifications

## Setup

1. Configure MySQL credentials in `config.php`.
2. Open `setup.php` to create the database tables.
3. Add police accounts through `api/init-officers.php` from an admin office.
4. Register a community account from `register.html`.
5. Log in from `login.html`.

## Important files

- `db_schema.sql`
- `setup.php`
- `api/auth.php`
- `api/profile.php`
- `api/reports.php`
- `api/notifications.php`
- `api/streets.php`
- `api/dashboard.php`

## Notes

- No seeded users are included.
- No sample incidents or demo login details are bundled.
- Streets and reports are added through the application or database tools.
