# StreetSafe Insight System 🛡️

A comprehensive community safety and incident reporting system designed to enhance public safety through real-time data and community engagement. This system provides a public dashboard for viewing street safety, allows community members to report incidents, and offers police dashboards for managing and responding to these incidents.

## 🚀 Features

*   **Public Safety Dashboard:** Real-time view of street safety scores, incident trends, and a live crime map 🗺️.
*   **Community Reporting:** Easy incident reporting with details on type, severity, location, and description 🚨.
*   **User Authentication:** Secure registration and login for community members and dedicated access for police personnel 🔐.
*   **Personalized Dashboards:** Community members can view their reports, profile, and notifications 🔔.
*   **Police Operations Portal:** Police officers can manage incidents, view logs, track responses, and manage street profiles 🚓.
*   **Dynamic Scoring System:** Streets are scored based on incident history, severity, response times, and environmental factors 📊.
*   **Notifications:** Real-time alerts for new reports, incident updates, and relevant community safety information 📣.
*   **Street Profile Management:** Police can add, update, and manage detailed profiles for streets, including lighting and CCTV status 🛣️.

## 🛠️ Tech Stack

*   **Frontend:** HTML, Vanilla JavaScript, CSS, Leaflet.js
*   **Backend:** PHP
*   **Database:** MySQL
*   **Frameworks/Libraries:** Bootstrap (implied by styling, though not explicitly imported), Tailwind CSS (used in some admin/police views)

## ⚙️ Installation

1.  **Database Setup:**
    *   Configure your MySQL credentials in `config.php`.
    *   Run `setup.php` via your web server (e.g., `http://localhost/StreetSafe-Insight-System/setup.php`) to create the necessary database tables.

2.  **Initial Police Accounts:**
    *   Police accounts cannot be created via the frontend. Use the `api/init-officers.php` script or the `create_police.php` CLI script to provision initial police accounts. This requires direct access to the server or terminal.
    *   Example using CLI:
        ```bash
        php create_police.php "Officer Name" "officer@example.com" "BADGE123" "TemporaryPass123" "Station Name"
        ```

3.  **Accessing the System:**
    *   **Public Dashboard:** Access `index.html`.
    *   **Community Registration:** Visit `register.html`.
    *   **Community Login:** Use `login.html` to access the community dashboard (`community-dashboard.html`).
    *   **Police Login:** Use `login.html` to access the police portal (`police.html`).

## 💡 Usage

### Community Member

1.  **Register:** Create an account via `register.html`.
2.  **Login:** Access your dashboard via `login.html`.
3.  **Report Incidents:** Navigate to the 'Report Incident' section to submit details about safety concerns.
4.  **Monitor:** View your submitted reports, safety scores, and notifications on your personalized dashboard.

### Police Officer

1.  **Login:** Use your badge number or email and password via `login.html`.
2.  **Dashboard Overview:** Get a summary of active incidents, pending responses, resolved cases, and overall alert levels.
3.  **View Incidents:** Access the 'Incidents' page (`police-incidents.html`) to see a list of all reported incidents.
4.  **Log Incidents:** Use the 'Log Incident' form (`police-report.html`) to manually add incidents.
5.  **Manage Responses:** Utilize the 'Response Center' (`police-response.html`) to update incident statuses, add replies, and record police actions.
6.  **Map View:** Monitor live incident locations and street safety scores on the 'Live Map' (`police-map.html`).
7.  **Street Management:** Add or edit street profiles to maintain accurate safety data and scores.

## 📁 Project Structure

```
StreetSafe-Insight-System/
├── api/
│   ├── analytics.php
│   ├── auth.php
│   ├── dashboard.php
│   ├── dashboard-stats.php
│   ├── helpers.php
│   ├── incidents.php
│   ├── init-officers.php
│   ├── logs/ (directory for error logs)
│   ├── notifications.php
│   ├── police-kpis.php
│   ├── profile.php
│   ├── reports.php
│   ├── respond-notification.php
│   ├── seed_demo_data.php
│   └── streets.php
├── js/
│   ├── core/
│   │   ├── auth.js
│   │   ├── event-bus.js
│   │   ├── storage.js
│   │   └── utils.js
│   ├── services/
│   │   ├── analytics-service.js
│   │   ├── incident-service.js
│   │   └── scoring-engine.js
│   └── ui/
│       ├── community.js
│       ├── dashboard.js
│       ├── map.js
│       └── police.js
├── css/
├── fonts/
├── images/
├── config.php
├── create_police.php
├── db_schema.sql
├── index.html
├── login.html
├── public_dashboard.html
├── README.md
├── README_SETUP.md
├── register.html
├── setup.php
├── community-dashboard.html
├── police-incidents.html
├── police-map.html
├── police-report.html
├── police-reports.html
├── police-response.html
└── police.html
```

## 📚 API Reference

The system exposes several API endpoints under the `api/` directory for frontend interactions:

*   **`api/auth.php`**: Handles user registration, login, logout, and authentication checks.
*   **`api/dashboard.php`**: Provides data for the public dashboard, including street and incident summaries.
*   **`api/dashboard-stats.php`**: Fetches general statistics like total incidents, streets, and safety scores.
*   **`api/reports.php`**: Manages incident reporting, retrieval (public, mine, all), and creation.
*   **`api/notifications.php`**: Handles fetching, marking as read, assigning, and responding to notifications.
*   **`api/streets.php`**: Provides endpoints for retrieving and managing street profiles.
*   **`api/analytics.php`**: Offers detailed analytics, including street-specific data and report groupings.
*   **`api/police-kpis.php`**: Fetches key performance indicators relevant to police operations.
*   **`api/profile.php`**: Manages user profile retrieval and updates.
*   **`api/seed_demo_data.php`**: (For development) Populates the database with sample data.
*   **`api/init-officers.php`**: Web-based script for initializing police accounts.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request or open an issue. For major changes, please open an issue first to discuss what you would like to change.

Please read our [Contributing Guidelines](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## 🔗 Important Links

*   [Live Demo](https://unknown1648.github.io/StreetSafe-Insight-System/) (Note: Live demo may not have backend functionality).
*   [Repository](https://github.com/Unknown1648/StreetSafe-Insight-System)

## ✨ Footer

© 2024 [StreetSafe Insight System](https://github.com/Unknown1648/StreetSafe-Insight-System)

**Author:** Unknown1648

**Contact:** [Provide Contact Info if available]

Feel free to fork this repository, star it ⭐, and report any issues!


---
**<p align="center">Generated by [ReadmeCodeGen](https://www.readmecodegen.com/)</p>**