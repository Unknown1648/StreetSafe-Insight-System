# Quick Start

1. Start PHP:
```bash
php -S localhost:8000
```

2. Create the database tables:
- Open `http://localhost:8000/setup.php`

3. Provision police accounts if needed:
- Open `http://localhost:8000/api/init-officers.php`

4. Register a community account:
- Open `http://localhost:8000/register.html`

5. Log in:
- Open `http://localhost:8000/login.html`

## Pages

- Public dashboard: `index.html`
- Community dashboard: `community-dashboard.html`
- Police dashboard: `police.html`

## API Endpoints

- `api/auth.php`
- `api/profile.php`
- `api/reports.php`
- `api/notifications.php`
- `api/streets.php`
- `api/dashboard.php`

## Notes

- There are no bundled demo users.
- Community users register online.
- Police users are created by admin office staff.
