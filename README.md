# NHRE — National Healthcare Record Exchange

NHRE is a PHP and MySQL healthcare portal prototype. It provides a public landing page and an authenticated workspace for patients and healthcare roles, with account management, appointments, medical-test bookings, notifications, vaccination/report viewing, pharmacy information, and blood donation workflows.

> This repository is a demonstration project, not a production-ready electronic health record system. The claims and statistics on the landing page are presentation content, and the application should not be used for real patient data without a full security, privacy, and regulatory review.

## Features

- Registration for Patient, Doctor, Pharmacist, and Lab Technician roles (Hospital Admin and System Admin are provisioned by administrators and cannot be self-registered)
- Login sessions, CSRF protection, password hashing, failed-login throttling, and optional remember-me tokens
- Profile management with optional profile-photo uploads (maximum 2 MB) and a choice of saved cartoon avatars
- In-app notifications with unread badges, high-contrast light/dark hover states, and secure click-through routing to the relevant authorized workspace
- Shared responsive, collapsible sidebar navigation with role-specific links and unread-notification access
- Patient-controlled data access: grant/revoke record access to doctors and hospitals, pending access requests, and an access history audit log
- Doctor patient search (by name, NID, phone, email) with consent-gated access to authorized records
- Appointment scheduling: patients can find doctors and book or cancel appointments; doctors can manage assigned appointments; Hospital Admins can oversee them
- Doctor ratings & reviews: patients rate and review doctors (one review per patient, updatable), with star-based display and rating-aware featured-doctor/filter logic
- Patient test-booking marketplace with Division → City/District → Hospital filtering, bookings, and result viewing
- Vaccination cards with patient booking forms, Division → City/District → Hospital selection, and booking-status tracking
- Lab Technician request management: separate Test Requests, Laboratory Reports, Test History, and Vaccination Bookings workspaces with hospital/section-scoped status updates
- Pharmacy medicine catalogue and pharmacy-support request workflow
- Blood donor registration, blood requests, donor directory, and donor eligibility tracking
- Password reset links generated in the application for local development
- Public landing-page destinations for NHRE mission, leadership, newsroom, services, resources, and contact information
- Standalone Privacy Policy and Terms & Conditions pages, shown only when their respective links are selected
- Responsive Bootstrap interface with custom CSS and JavaScript

## Technology

- PHP 8.1+ (PDO and `mbstring`)
- MySQL 5.7+ or MariaDB 10.4+
- HTML5, CSS, and vanilla JavaScript
- Bootstrap 5.3.3, Font Awesome 6.5.1, and Google Fonts loaded from CDNs
- Apache/XAMPP (recommended for the supplied setup), or PHP's built-in development server

No Composer or npm installation is required.

## Project structure

```text
NHRE/
├── assets/
│   ├── css/styles.css            # Shared application styles
│   └── js/app.js                 # UI behavior and notification polling
├── auth/                         # Form handlers and authenticated JSON endpoint
├── config/
│   └── database.php              # Database, session, and login settings
├── includes/
│   └── sidebar.php               # Shared authenticated role-based navigation
├── database/
│   └── nhre.sql                  # Database schema and optional demo user
├── uploads/profile_pics/         # Created automatically after a photo upload
├── uploads/test_results/         # Lab-result files; created automatically when needed
├── index.php                     # Public landing page
├── site_page.php                 # Public About, Services, Resources, and Contact pages
├── privacy_policy.php             # Public Privacy Policy page
├── terms_conditions.php           # Public Terms & Conditions page
├── dashboard.php                 # Authenticated home
├── profile.php                   # Profile view, editor, and cartoon-avatar picker
├── notifications.php             # Notification list
├── appointments.php              # Doctor discovery and appointment management
├── medical_tests.php             # Medical-test catalogue and bookings
├── vaccination.php               # Vaccination/doctor reports
├── pharmacy.php                  # Pharmacy module
├── data_access.php               # Patient-controlled record access and history
├── patient_search.php            # Doctor/Lab/Pharmacy patient lookup
├── authorized_records.php        # Consent-gated patient record view
├── access_requests.php           # Doctor access-request tracking
├── blood_donation.php            # Donor and blood-request workflows
├── admin_credentials.php         # Hospital Admin account credential view
├── coming_soon.php               # Authorized placeholders for planned workspaces
├── help_support.php               # Authenticated help and privacy guidance
└── test.php                      # Local setup diagnostic
```

## Local setup with XAMPP

1. Start **Apache** and **MySQL** in XAMPP.
2. Place the project in XAMPP's document root. The expected macOS location is:

   ```text
   /Applications/XAMPP/xamppfiles/htdocs/NHRE
   ```

3. Import [`database/nhre.sql`](database/nhre.sql) using phpMyAdmin, or from a terminal:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/nhre.sql
   ```

   The application can also create the appointment and medical-test tables at runtime when those modules are first opened. Importing the schema remains the recommended setup path because it creates the complete database structure up front.

4. Confirm the connection values in [`config/database.php`](config/database.php). The included defaults are:

   ```php
   DB_HOST = localhost
   DB_NAME = nhre
   DB_USER = root
   DB_PASS = (empty)
   ```

5. Open [http://localhost/NHRE/](http://localhost/NHRE/) in a browser.
6. Optionally visit [http://localhost/NHRE/test.php](http://localhost/NHRE/test.php) to check the PHP version, PDO MySQL connection, database tables, writable session directory, and configured roles. Remove or restrict this diagnostic page before deployment.

### PHP built-in server

If MySQL is already running and configured, the application can also be served from the project directory:

```bash
php -S localhost:8000
```

Then visit [http://localhost:8000](http://localhost:8000).

## Accounts and demo access

The normal route is to create an account at `register.php`. Passwords must contain at least eight characters, including uppercase, lowercase, numeric, and symbol characters.

Use the **Demo accounts** table on `login.php` to fill the sign-in form quickly. The runtime demo setup prepares the Demo Patient, Lab Technician, and administrative accounts. It also seeds pending, completed, and cancelled test requests plus vaccination bookings for the Demo Patient, so each Lab Technician queue can be tested.

| Role | Email | Password |
| --- | --- | --- |
| Patient | `patient@nhre.gov` | `Patient123!` |
| Doctor | `doctor001@nhre.dev` | `Doctor123!` |
| Pharmacist | `pharmacist@nhre.gov` | `Pharmacist123!` |
| Lab Technician | `lab@nhre.gov` | `Lab123!` |
| Hospital Admin | `admin@nhre.gov` | `Admin123!` |
| System Admin | `sysadmin@nhre.gov` | `SysAdmin123!` |

### Specific demo doctor login

Every seeded doctor uses the same demo password. Replace the number with any value from `001` to `100`:

`doctor001@nhre.dev` / `Doctor123!`

| Demo doctor emails | Password |
| --- | --- |
| `doctor001@nhre.dev` through `doctor100@nhre.dev` | `Doctor123!` |

Open the Appointments page after logging in as a doctor to approve, reject, complete, and add notes to appointments assigned to that doctor. The initial seeded appointment set includes requests assigned across the first six demo doctors.

### Demo patients and medical records

The login page now seeds 25 demo patients. Patient A is `patient@nhre.gov` / `Patient123!`; the other accounts are `patient002@nhre.demo` through `patient025@nhre.demo`, all with password `Patient123!`. Each has a permanent `NHRE-P-######` identifier and a demo allergy/medical-record entry. Log in as any demo patient and open **Medical Records** or **Allergies** in the sidebar.

| Demo patient | Email | Password |
| --- | --- | --- |
| Patient A | `patient@nhre.gov` | `Patient123!` |
| Patient 002 | `patient002@nhre.demo` | `Patient123!` |
| Patient 003 | `patient003@nhre.demo` | `Patient123!` |
| Patient 004 | `patient004@nhre.demo` | `Patient123!` |
| Patient 005 | `patient005@nhre.demo` | `Patient123!` |
| Patient 006 | `patient006@nhre.demo` | `Patient123!` |
| Patient 007 | `patient007@nhre.demo` | `Patient123!` |
| Patient 008 | `patient008@nhre.demo` | `Patient123!` |
| Patient 009 | `patient009@nhre.demo` | `Patient123!` |
| Patient 010 | `patient010@nhre.demo` | `Patient123!` |
| Patient 011 | `patient011@nhre.demo` | `Patient123!` |
| Patient 012 | `patient012@nhre.demo` | `Patient123!` |
| Patient 013 | `patient013@nhre.demo` | `Patient123!` |
| Patient 014 | `patient014@nhre.demo` | `Patient123!` |
| Patient 015 | `patient015@nhre.demo` | `Patient123!` |
| Patient 016 | `patient016@nhre.demo` | `Patient123!` |
| Patient 017 | `patient017@nhre.demo` | `Patient123!` |
| Patient 018 | `patient018@nhre.demo` | `Patient123!` |
| Patient 019 | `patient019@nhre.demo` | `Patient123!` |
| Patient 020 | `patient020@nhre.demo` | `Patient123!` |
| Patient 021 | `patient021@nhre.demo` | `Patient123!` |
| Patient 022 | `patient022@nhre.demo` | `Patient123!` |
| Patient 023 | `patient023@nhre.demo` | `Patient123!` |
| Patient 024 | `patient024@nhre.demo` | `Patient123!` |
| Patient 025 | `patient025@nhre.demo` | `Patient123!` |

The login-page cards show the saved profile picture for each demo account. If an account has not uploaded a photo, NHRE displays a stable cartoon avatar instead.

Do not retain demo credentials in any deployed environment.

## Demo seed and clinical additions

Opening the Appointments workspace idempotently prepares a Bangladesh-oriented directory of **at least 100 DEMO doctor accounts** (`doctor001@nhre.dev` through `doctor100@nhre.dev`, password `Doctor123!`). These accounts are fictional demonstration data only. Patient self-registration receives one persistent NHRE account number (`NHRE-########`) at creation.

The clinical workspaces are available at `medical_records.php`, `allergies.php`, and `medical_documents.php`. Medical-document uploads accept server-verified PDF, JPEG, or PNG files up to 5 MB, use randomized filenames in `uploads/private_documents`, and can only be downloaded through the authorization-checked endpoint. A doctor must have active patient consent before viewing or verifying a patient document.

After importing an earlier schema, open any clinical or appointment workspace once to apply the non-destructive runtime upgrades. For new installations, `database/nhre.sql` includes the added tables and notification/appointment fields.

## Configuration

Runtime constants are defined in [`config/database.php`](config/database.php):

| Setting | Default | Purpose |
| --- | --- | --- |
| `SESSION_NAME` | `nhre_session` | Session cookie name |
| `SESSION_LIFETIME` | `7200` | Session cookie lifetime in seconds |
| `COOKIE_REMEMBER_DAYS` | `30` | Remember-me token lifetime |
| `LOGIN_ATTEMPT_LIMIT` | `5` | Failed attempts allowed per email/IP window |
| `LOGIN_ATTEMPT_WINDOW_MINUTES` | `15` | Login throttling window |

For shared or production-like environments, use a dedicated database account and keep credentials outside the web root rather than committing them to source control.

## Password reset behavior

This prototype does not send email. After a valid email is submitted at `forgot_password.php`, the generated one-hour reset URL is displayed directly in the browser. Connect the handler to a trusted mail provider and stop exposing the URL in the response before production use.

## Data and role notes

- The schema creates user, authentication, doctor-directory, blood-donation, notification, doctor-report, appointment, medical-test, vaccination-center, and booking tables. Technician-scoped workflows use `lab_technician_assignments`; vaccination requests use `vaccination_bookings`; notifications store a role-validated `target_path` for click-through navigation.
- The application recognizes six roles: Patient, Doctor, Pharmacist, Lab Technician, Hospital Admin, and System Admin. Self-registration supports Patient, Doctor, Pharmacist, and Lab Technician only; Hospital Admin and System Admin demo accounts are provisioned automatically for local demonstration.
- Profile pictures can be uploaded or selected from the Account Summary cartoon-avatar picker. Cartoon avatars are delivered by the DiceBear avatar service; a network connection is required for those SVGs to load.
- The public footer links to dedicated About, Services, Resources, Contact, Privacy Policy, and Terms & Conditions pages. These informational pages are not shown in the authenticated navigation.
- The authenticated sidebar is role-aware. Lab Technicians have distinct Test Requests, Laboratory Reports, Test History, Patient Search, and Vaccination Bookings pages; their test and vaccination queues are limited server-side to assigned hospitals and laboratory sections.
- Patient data access: patients grant or revoke record access (Medical History, Lab Reports, Prescriptions, Vaccinations, Allergies, Medical Documents) to individual doctors or to hospitals. Doctors can request access, which appears as a pending request for the patient to approve or reject. `authorized_records.php` enforces an active, unexpired permission server-side and writes to the access log.
- Hospital-only donor controls and appointment administration are shown conditionally in the interface; server-side role authorization should be strengthened before deployment.
- The pharmacy catalogue is interface/demo data, but the pharmacy support-request form writes real rows to `pharmacy_requests`.
- Patient vaccination cards create `vaccination_bookings` with a selected hospital and status. Lab Technicians can manage only bookings for their authorized hospitals; the demo technician is assigned to the demo hospital catalogue for local testing.
- Medical-test catalogue entries and technician queue records are seeded with demo data. Lab result files are stored under `uploads/test_results/` and should be treated as untrusted uploads.

## Security checklist before deployment

- Serve the site only over HTTPS and use secure environment-specific database credentials.
- Add strict server-side authorization checks to every privileged action.
- Validate profile photos and laboratory-result files from file contents, store uploads outside the public web root, and disable script execution in upload directories.
- Replace the on-screen password-reset link with email delivery.
- Add audit logging, consent enforcement, data encryption, retention rules, backups, and regulatory review.
- Remove `test.php`, disable PHP error display, and add appropriate security headers.
- Review cleanup/expiry handling for login attempts, remember-me tokens, and reset tokens.

## Basic verification

Lint every PHP file:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

For a manual smoke test, import the schema, log in as the Demo Patient and submit a test or vaccination booking using the cascading hospital selector. Then log in as the Demo Lab Technician and verify that Test Requests, Laboratory Reports, Test History, and Vaccination Bookings show only authorized records and allow status management. Also verify a notification opens its relevant authorized module.

## License

No license file is currently included. Unless the project owner adds one, all rights are reserved by default.
