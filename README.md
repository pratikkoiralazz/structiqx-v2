# structiQX

## Run with XAMPP

1. Ensure Apache and MySQL are running.
2. Open `http://localhost/structiqx/public/install.php`.
3. Open `http://localhost/structiqx/public/`.
4. Sign in with `admin@structiqx.test` / `Password123!`.

The installer creates the `structiqx` database, schema, and realistic demo data. Database settings are in `app/config/database.php` and can be overridden with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

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
- Projects, comments, tasks, milestones, task dependencies, Gantt view
- Budgets, expenses, BOQ items, estimation, CSV exports
- RCC beam/slab draft calculations with professional review status
- Daily site reports, document uploads/downloads and version fields
- Suppliers, purchase requests, purchase orders and approvals
- Client portal, notifications and activity logging
