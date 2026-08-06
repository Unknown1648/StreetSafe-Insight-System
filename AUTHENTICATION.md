# Authentication Guide

## Roles

- **Community members** can register online and manage their own profile, reports, and notifications.
- **Police officers** must receive an account from the nearest admin office.

## Pages

- `login.html` - shared login page for community and police
- `register.html` - community registration
- `community-dashboard.html` - community dashboard
- `police.html` - police dashboard

## API

- `api/auth.php` - register, login, logout, session check
- `api/profile.php` - view and update profile
- `api/reports.php` - submit and list reports
- `api/notifications.php` - notifications and police responses

## Login Flow

1. Community users register in `register.html`.
2. Community users log in in `login.html`.
3. Police users receive credentials from an admin office and log in in `login.html`.
4. The dashboard is loaded only after the session check succeeds.

## Notes

- No demo credentials are bundled.
- No localStorage is used for authentication.
- Sessions are handled by PHP.
