# Quick Start: Accounts and Access

## What Changed

- Community members can register online.
- Police accounts are created by admin office staff, not self-registered.
- No demo users or fixed credentials are bundled.

## Setup

1. Start the server:
```bash
php -S localhost:8000
```

2. Initialize the database:
- Open `http://localhost:8000/setup.php`

3. Create streets and accounts:
- Use the police dashboard after login to add location profiles and manage incidents.
- Use the admin-office provisioning page at `api/init-officers.php` to create police accounts when needed.

## Main Pages

- Public dashboard: `index.html`
- Community login: `login.html`
- Community registration: `register.html`
- Community dashboard: `community-dashboard.html`
- Police login / dashboard: `police.html`

## Login Flow

### Community members
1. Register at `register.html`
2. Sign in at `login.html`
3. You will be redirected to `community-dashboard.html`

### Police officers
1. Get an account from the nearest admin office
2. Sign in at `login.html`
3. You will be redirected to `police.html`

## API Endpoints

- `api/auth.php` - register, login, logout, session check
- `api/profile.php` - load and update profile
- `api/reports.php` - submit and list reports/incidents
- `api/notifications.php` - notifications and police responses
- `api/streets.php` - location profiles
- `api/dashboard.php` - public dashboard data

## Notes

- Reports can be submitted as named or anonymous sources.
- Community users can keep personal details private in their profile.
- Police can respond directly to a report from the dashboard.
- No localStorage seed data is used for users or reports.
