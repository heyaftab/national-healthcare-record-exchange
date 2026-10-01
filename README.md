# NHRE — National Healthcare Record Exchange

NHRE is a PHP + MySQL healthcare portal for local demo and prototype use. It includes a public landing page, role-based authentication, patient and clinician workspaces, appointment workflows, medical records access, lab and vaccination management, pharmacy support, and a hospital-style dashboard.

> This project is designed for local development and demo scenarios. It is not a production-ready medical system and should not be used for real patient data without security, privacy, and regulatory review.

## What is included

- Role-based access for Patient, Doctor, Pharmacist, Lab Technician, Hospital Admin, and System Admin
- Registration and login with password hashing, CSRF protection, session handling, and remember-me support
- Profile updates with duplicate-check validation and photo/avatar support
- Hospital and doctor directories, appointment booking, and management tools
- Patient record access and consent-driven authorization flows
- Medical-document uploads, allergy views, and test/vaccination history tracking
- Pharmacy request workflows, blood donation support, and notifications
- Role-aware settings pages and a responsive Bootstrap UI

## Recent fixes and improvements

- Fixed false duplicate-email warnings during profile edits by excluding the current user from validation and normalizing phone values
- Fixed avatar mismatch between profile updates and the login page by using the active user's stored profile photo
- Reduced login-page slowdown by removing unnecessary heavy demo-data seeding from the initial page load
- Added more role-specific settings sections so the settings page matches actual roles and responsibilities

## Tech stack

- PHP 8.x
- MySQL / MariaDB
- HTML, CSS, and JavaScript
- Bootstrap 5 and Font Awesome
- Apache + XAMPP for local hosting

## Project structure

```text
NHRE/
├── assets/
├── auth/
├── config/
├── database/
├── includes/
├── uploads/
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── profile.php
├── settings.php
├── appointments.php
├── medical_tests.php
├── vaccination.php
├── pharmacy.php
├── blood_donation.php
├── patient_search.php
├── medical_records.php
├── allergies.php
├── medical_documents.php
├── access_requests.php
├── data_access.php
├── notifications.php
├── admin_dashboard.php
├── admin_credentials.php
├── help_support.php
├── test.php
├── README.md
└── database/nhre.sql
```

## Local setup

1. Start Apache and MySQL in XAMPP.
2. Place the project under the web root, for example:

```text
/Applications/XAMPP/xamppfiles/htdocs/NHRE
```

3. Import the schema if needed:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/nhre.sql
```

4. Confirm database settings in [config/database.php](config/database.php):

```php
DB_HOST = 'localhost';
DB_NAME = 'nhre';
DB_USER = 'root';
DB_PASS = '';
```

5. Start the project:

```bash
php -S localhost:8000
```

6. Open:

- http://localhost:8000
- or http://localhost/NHRE/ when using XAMPP

## Demo accounts

Use the demo login cards on the login page or login directly with the seeded accounts below.

| Role | Email | Password |
| --- | --- | --- |
| Patient | patient@nhre.gov | Patient123! |
| Doctor | doctor001@nhre.dev | Doctor123! |
| Pharmacist | pharmacist@nhre.gov | Pharmacist123! |
| Lab Technician | lab@nhre.gov | Lab123! |
| Hospital Admin | admin@nhre.gov | Admin123! |
| System Admin | sysadmin@nhre.gov | SysAdmin123! |

Additional demo patient accounts are also generated for local testing, such as:

- patient002@nhre.demo
- patient003@nhre.demo
- ...
- patient025@nhre.demo

All use the same password: Patient123!

## Notes on initialization and performance

The app uses lazy database setup in several places so the project does not perform heavy demo seeding on every page load. This was adjusted specifically so the login page stays fast and responsive while still enabling required demo data when a feature needs it.

## Role-aware settings

The settings page is designed around the current user's role and can show different configuration blocks depending on whether the user is a Patient, Doctor, Pharmacist, Lab Technician, Hospital Admin, or System Admin.

This keeps the interface aligned to the permissions and workflows for that role rather than showing a generic one-size-fits-all page.

## Security and deployment notes

- This is a demo project, not a medical production system.
- Do not deploy with default credentials or demo data in a live environment.
- Use HTTPS and real secrets in production.
- Replace any in-browser password-reset flow with real email delivery.
- Store uploaded files safely and validate them on the server before use.
- Review access control rules before exposing the app beyond local testing.

## Verification

PHP syntax was checked across the project with:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

This project also responds correctly through the local PHP server and the login, register, and dashboard endpoints.

## License

No license file is currently included. Unless otherwise specified by the project owner, all rights are reserved by default.
