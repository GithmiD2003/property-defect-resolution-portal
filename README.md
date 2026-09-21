# Property Defect Resolution Portal

A web application for a construction or renovation company to report property defects, coordinate repairs, and verify completion.

## Live Website

[Open the Property Defect Resolution Portal](https://property-defect-portal.alwaysdata.net)

## Features

- Manager, contractor, and property-owner roles.

- Property and room management.

- Invitations and property-specific access.

- Defect reporting with private photos.

- Contractor assignment, priorities, and deadlines.

- Repair evidence, verification, and reopening.

- Comments and activity history.

- Search, filters, and dashboards.

- Printable property handover reports.

- User deactivation that preserves historical records.

- Email password reset and two-factor authentication.

## Repair workflow

Reported -> Assigned -> In progress -> Repaired -> Verified

Contractors submit repair notes and photos. Managers verify repairs or reopen them with an explanation. Owners can request review through comments.

## Technology

Laravel, PHP, Blade, Livewire, Tailwind CSS, and MySQL.

The repository also includes Docker deployment configuration and GitHub Actions checks with a temporary PostgreSQL database.

## Fresh local installation

These steps are for a new installation, not an existing configured application.

Requirements:

- PHP 8.4 with the required extensions, including pdo\_mysql.

- Composer 2.

- Node.js 24 and npm.

- MySQL 8.

- Git.

1\. Clone the repository and open its directory.

2\. Run: composer install

3\. Copy .env.example to .env.

4\. Create an empty MySQL database and an application database user.

5\. Configure DB\_HOST, DB\_PORT, DB\_DATABASE, DB\_USERNAME, and DB\_PASSWORD in .env.

6\. Set APP\_URL=http://127.0.0.1:8000 for local use.

7\. Set DEFECT\_PHOTOS\_DRIVER=local.

8\. Run: php artisan key:generate

9\. Run: php artisan migrate

10\. Run: npm ci

11\. Run: npm run build

12\. Run: php artisan app:create-manager

13\. Run: php artisan serve --host=127.0.0.1 --port=8000

On Windows PowerShell, use npm.cmd if npm is blocked by the execution policy.

The manager command prompts for a name, unique email address, password, and password confirmation. Its password requires at least 12 characters, including letters and numbers.

Do not use the default database seeder to create a production manager. It creates a test account.

Never regenerate APP\_KEY for an existing installation as a routine setup step.

## Email configuration

The log mailer writes messages to application logs and does not deliver email.

Configure a supported mail provider in .env to deliver invitations and password-reset emails. The current laptop demonstration uses Gmail SMTP with an app password.

Set APP\_URL to the address recipients can actually open.

After changing environment settings, run:

- php artisan config:clear

- php artisan queue:restart

Restart the Laravel development server and any queue workers as needed.

## Temporary public demonstration

Start Laravel:

php artisan serve --host=127.0.0.1 --port=8000

In a separate terminal, start:

cloudflared tunnel --url http://127.0.0.1:8000

Open the HTTPS address printed by cloudflared.

Keep both terminals running. The laptop must remain awake and connected to the internet.

When a new tunnel address is generated:

1\. Update APP\_URL in .env to the new HTTPS address.

2\. Clear the configuration cache and restart Laravel.

3\. Restart any queue workers.

4\. Request new invitation or password-reset emails.

Previously sent emails can still contain the old address.

This is a temporary demonstration setup, not permanent hosting.

## Automated checks

Use an isolated testing database, never a database containing client data.

Run:

- composer validate

- composer ci:check

- npm run build

GitHub Actions also builds the Docker image and checks application startup, migrations, the health endpoint, and the login page against a temporary PostgreSQL database.

## Backup and recovery

Back up:

- The database.

- storage/app/private/defect-photos when using local photo storage.

- The private .env configuration, including APP\_KEY.

Keep backups outside the repository and maintain a separate-device copy.

Restore into a separate test database before considering a backup usable. Check important records, photo integrity, and application behavior.

A backup import has been checked with matching record counts across six core tables. All six backed-up photo files matched their originals by relative path and SHA-256 hash.

Application-level testing against the restored database remains outstanding.

## Security

- Never commit .env, passwords, application keys, storage secrets, or database backups.

- Keep defect photos private.

- Use real email addresses for accounts that need email delivery.

- Disable debug output for public demonstrations and deployment.

- Deactivate users to preserve their historical records.

## Delivery status

Verified during development:

- Main repair workflow, including mobile use.

- Private photo viewing.

- Email password reset and login with the new password.

- Automated application checks.

- Docker startup with PostgreSQL.

- Basic backup import and photo integrity checks.

Still required before a client pilot:

- Permanent hosting with persistent storage.

- Verification of email and photo storage on the chosen host.

- Application-level restore testing.

- Workflow validation with a potential client.

## Project scope

See [Project scope](docs/project-scope.md) for business rules, acceptance criteria, and excluded features.
