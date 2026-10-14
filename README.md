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
DB_HOST = '127.0.0.1';
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

## Deploying to Vercel

The repository includes `vercel.json` and a PHP dispatcher for the existing
pages. Vercel runs PHP requests as serverless functions; this is not a static
PHP host. A persistent managed database is required for accounts, sessions,
and uploaded files.

1. Push the project to GitHub, GitLab, or Bitbucket and import the repository
   into Vercel. Set the **Root Directory** to the repository root. There is no
   frontend build step; leave the build command empty. Keep the included
   `vercel.json` so Vercel uses the PHP runtime and routes page requests through
   `api/index.php`.
2. Create a MySQL database with a provider that allows connections from Vercel.
   Create the database and user in the provider's dashboard, then import
   `database/nhre.sql` into that database. The SQL file contains `CREATE
   DATABASE` and `USE nhre` for local setup; for a hosted provider, select the
   database you created and remove or adjust those two statements if the
   provider does not allow database creation. Confirm the database user can
   create tables because the app initializes its session and file storage
   tables when it connects.
3. In **Vercel Project Settings > Environment Variables**, add these values for
   every environment you plan to use (Preview and Production):

   | Variable | Value |
   | --- | --- |
   | `DB_HOST` | Hostname from your database provider |
   | `DB_PORT` | Provider's port, usually `3306` |
   | `DB_NAME` | Name of the database you created |
   | `DB_USER` | Database user |
   | `DB_PASS` | Database password |
   | `NHRE_SESSION_STORAGE` | `database` |
   | `NHRE_FILE_STORAGE` | `database` |

   `.env.example` lists the same variable names. Do not commit actual database
   credentials. Database storage is required because Vercel's function
   filesystem is temporary; it keeps logins and uploaded profile photos, lab
   results, and private documents available across invocations.
4. Redeploy after adding the variables. Open `/` and `/login.php`; then try a
   login and an upload to verify the database connection and persistent
   storage. If the deployment returns a server error, inspect the function
   logs in Vercel and confirm the database accepts connections from the
   deployment region.
### Vercel runtime limits

- The PHP runtime is serverless. Enable the database storage environment
  variables above for sessions and uploads; the local XAMPP filesystem mode is
  still the default for development.
- Vercel does not provide MySQL. A reachable managed database is required;
  the local `localhost` defaults are only for XAMPP development.
- Treat the included demo accounts and database seed data as public demo data.
  Do not use real patient data without a full security and privacy review.

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

This project is licensed under the MIT License. See the [MIT License](LICENSE).
