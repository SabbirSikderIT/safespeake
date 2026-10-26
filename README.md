# SafeSpeak

An AI-enhanced anonymous reporting platform for universities. Students can report bullying, harassment and misconduct, either anonymously or from their account, and administrators review, investigate and resolve each case from a dedicated panel. Built as a Software Engineering Lab project.

## Features

**Students**
- Anonymous reporting with no account required, verified by an email code
- Student registration and login, dashboard, profile and "My Reports"
- Track a report with its tracking ID, with no login needed
- Anonymous follow-up messages with the investigators
- Evidence upload, and ID verification when a report is flagged
- Post-resolution satisfaction survey

**Administrators**
- Dashboard with live report statistics
- Report management: status, priority, messaging the reporter
- Case room with internal notes, tasks and a timeline
- Evidence viewer with annotations
- ID verification review
- Analytics with CSV export
- Student directory and CSV import
- University terms and policy documents
- Admin accounts and settings

**Platform**
- AI-assisted analysis: urgency keyword detection, priority suggestion, spam signals
- Email notifications (confirmation, status updates, verification codes)
- Dark and light themes with a toggle in the top-right corner

## Tech stack

PHP 8, MySQL, PDO, PHPMailer, plain HTML/CSS/JavaScript, Chart.js.

## Project structure

```
safe/
├── frontend/   user-facing pages (student + admin UI), theme.css / theme.js
├── backend/    config and helpers, mailer, uploads, and scripts with no UI
├── database/   database.sql, database_cloud.sql, studentdata.csv
├── Dockerfile  container for deployment (Render)
├── render.yaml Render service definition
└── DEPLOY.md   deployment guide
```

## Run locally (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this project into `C:\xampp\htdocs\safe`.
3. Import `database/database.sql` in phpMyAdmin. It creates the `safespeak_bd` database.
4. Check the database and email settings at the top of `backend/includes/config.php`.
5. Open <http://localhost/safe/frontend/>.

The default admin account created by `database.sql` has the username `admin`. Sign in at `/frontend/admin/login.php`, then **change the password immediately**.

## Configuration

Every setting can be given as an environment variable. If it is not set, the local default in `backend/includes/config.php` is used.

| Variable | Purpose |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` | Database connection |
| `DB_SSL`, `DB_SSL_CA_PEM` | Encrypted connection for managed databases such as Aiven |
| `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM` | Email sending (Gmail app password) |
| `APP_URL` / `SITE_URL`, `BACKEND_URL` | Public URLs of the site |

**Never commit real passwords.** Use your own Gmail app password and keep it in the environment.

## Deploy

The project is ready for Render with an external MySQL database (Aiven). See [DEPLOY.md](DEPLOY.md) for the steps.

On Render's free plan, uploaded files are lost on each redeploy or restart, and the service sleeps when idle.

## License

Educational project. Add a license of your choice before reusing it.
