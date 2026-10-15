# NHRE — National Healthcare Record Exchange

NHRE is a PHP and MySQL prototype for healthcare record exchange. It includes role-based workspaces for patients, doctors, pharmacists, laboratory technicians, hospital administrators, and system administrators, along with appointments, records, consent, laboratory and vaccination workflows, pharmacy, and notifications.

> This is a local demo and prototype. Do not use it with real patient data or treat it as a production medical system without appropriate security, privacy, and regulatory review.

## Requirements

- PHP 8.1 or later with PDO MySQL, Fileinfo, and standard session/file upload support
- MySQL 8 or MariaDB
- Apache (XAMPP is supported) or PHP's built-in development server

## Quick start with XAMPP

1. Put the repository in the web root, for example `C:\xampp\htdocs\national-healthcare-record-exchange`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Create/import the `nhre` database using the included schema. From a terminal with the MySQL client available:

   ```powershell
   mysql -u root < database/nhre.sql
   ```

   You can also import `database/nhre.sql` through phpMyAdmin.
4. Review `config/database.php`. The local defaults are `127.0.0.1:3306`, database `nhre`, user `root`, and an empty password. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables to override them.
5. Open `http://localhost/national-healthcare-record-exchange/`.

To use PHP's built-in server from the repository root instead, run:

```powershell
php -S 127.0.0.1:8000
```

Then open `http://127.0.0.1:8000/`.

## Configuration

Database credentials can be supplied as environment variables; see `.env.example` for the names used by hosted deployments. PHP does not load `.env` files automatically, so configure these in your shell or hosting provider.

For a serverless/container environment with a shared MySQL database, set `NHRE_SESSION_STORAGE=database` to persist sessions across instances and `NHRE_FILE_STORAGE=database` to persist uploaded files in MySQL. The app creates the required `nhre_sessions` and `nhre_file_storage` support tables when the corresponding mode is enabled. The database user therefore needs permission to create those tables. Local filesystem uploads are stored under `uploads/` by default.

## Demo accounts

The login page provisions and displays demo accounts. Seeded credentials include:

| Role | Email | Password |
| --- | --- | --- |
| Patient | `patient@nhre.gov` | `Patient123!` |
| Doctor | `doctor001@nhre.dev` | `Doctor123!` |
| Pharmacist | `pharmacist@nhre.gov` | `Pharmacist123!` |
| Lab Technician | `lab@nhre.gov` | `Lab123!` |
| Hospital Admin | `admin@nhre.gov` | `Admin123!` |
| System Admin | `sysadmin@nhre.gov` | `SysAdmin123!` |

These are public demo credentials. Do not expose a deployment with demo accounts or default credentials to real users or data.

## Project layout

- `auth/` — request handlers, authentication checks, downloads, and uploads
- `config/` — database connection, storage, and session configuration
- `database/nhre.sql` — initial schema and demo seed data
- `includes/` — shared page and workflow helpers
- `assets/` — stylesheets, JavaScript, and images
- `uploads/` — local uploaded files
- `api/index.php` and `vercel.json` — PHP request dispatcher and Vercel routing

## Vercel deployment

The repository includes a PHP runtime configuration and dispatcher. A hosted deployment also needs a managed MySQL database reachable from the runtime. Import `database/nhre.sql` into that database (omit or adjust its `CREATE DATABASE` / `USE nhre` statements if the provider does not allow database creation), then configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in Vercel. Set both `NHRE_SESSION_STORAGE` and `NHRE_FILE_STORAGE` to `database` so sessions and uploads survive between serverless invocations. Redeploy after configuring the variables.

## Security notes

- This is prototype software, not a certified medical records system.
- Use HTTPS and unique secrets for any hosted instance; never use demo accounts with real data.
- Review authorization, audit logging, retention, backups, and applicable privacy requirements before any broader use.
- Password-reset flows are for demonstration and do not replace a verified email delivery process.

## License

MIT. See [LICENSE](LICENSE).
