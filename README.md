# structiQX

## Run with XAMPP

1. Ensure Apache and MySQL are running.
2. Open `http://localhost/structiqx/public/install.php`.
3. Open `http://localhost/structiqx/public/`.
4. Sign in with `admin@structiqx.test` / `Password123!`.

The installer creates the `structiqx` database, schema, and realistic demo data. Database settings are in `app/config/database.php` and can be overridden with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

Self-registered organizations enter a pending state and can access the customer workspace only after approval in the platform admin.

## Platform administration

Open `http://localhost/structiqx/admin/`. Admin accounts are independent of customer accounts and use a separate session cookie. Apply the non-destructive admin migration through the installer at `http://localhost/structiqx/public/install.php` before first use.

Create the first Super Admin from PowerShell with environment variables (choose a unique, strong password; do not use the customer demo credentials):

```powershell
$env:STRUCTIQX_ADMIN_NAME = 'Platform Administrator'
$env:STRUCTIQX_ADMIN_EMAIL = 'admin@example.com'
$env:STRUCTIQX_ADMIN_PASSWORD = 'replace-with-a-unique-12-character-or-longer-password'
php scripts\create-admin.php
Remove-Item Env:STRUCTIQX_ADMIN_NAME, Env:STRUCTIQX_ADMIN_EMAIL, Env:STRUCTIQX_ADMIN_PASSWORD
```

The CLI provisioning command refuses duplicate email addresses and never prints or stores the plaintext password. Optional `STRUCTIQX_ADMIN_ROLE` values are `super_admin`, `content_manager`, and `support_manager`. Admin accounts should be provisioned only on the server/CLI.

## Security

- PDO prepared statements
- Password hashing and session regeneration
- CSRF tokens on POST forms
- Server-side organization and role checks
- 10 MB allowlisted document uploads
- Activity logging

Engineering calculations are explicitly draft tools and require qualified professional review; they are not approval records by themselves.

## Included modules

- Organization settings, profiles, invitations, password reset, role-based access
- Client CRUD and inline client creation from project create/edit screens
- Projects, comments, tasks, milestones, task dependencies, Gantt view
- Budgets, expenses, BOQ items, estimation, CSV exports
- RCC beam/slab draft calculations with professional review status
- Daily site reports, document uploads/downloads and version fields
- Suppliers, purchase requests, purchase orders and approvals
- Client portal, notifications and activity logging

## Phase completion

1. Foundation and database: complete.
2. Dashboard and project/task workflows: complete.
3. Gantt scheduling, team roles and project permissions: complete.
4. Budget revisions, expenses, BOQ items and rate analysis: complete.
5. Engineering calculation studio with review workflow: complete.
6. Site reports, photos, documents and document downloads: complete.
7. Suppliers, purchase requests, orders, deliveries, approvals and client portal: complete.
8. CSV, Excel-compatible and PDF exports, security headers, security checks and database indexes: complete.

## Validation

Run `php scripts/security-check.php` from the project root. It verifies core database tables and the built-in security configuration.

## Public website and authentication

Public routes:

- `/public/`
- `/public/features`
- `/public/solutions`
- `/public/pricing`
- `/public/about`
- `/public/contact`
- `/public/login`
- `/public/register`
- `/public/forgot-password`
- `/public/reset-password/{token}`

Organization registration creates the organization and its administrator membership in one transaction. New organizations must be approved by a platform administrator before customer workspace login is permitted. Legacy organizations are migrated as approved to preserve existing access.

Run the backward-compatible public-auth migration with the installer or directly:

```sql
SOURCE database/migration_public_auth.sql;
```

The contact form saves submissions to `contact_messages`. Email delivery is not configured; password-reset and email-verification links require SMTP integration before production email delivery.

The admin CMS supports published pages and structured sections, verified image uploads, editable plans, manual subscription records, organization approval, contact-message notes, customer-user access moderation, platform settings, and administrator activity history. A subscription marked active does not imply payment; payment verification is tracked separately and requires a reference.
