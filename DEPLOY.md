# Deploying SafeSpeak on Render + Aiven MySQL

1. **Aiven**: create a free MySQL service. Copy Host, Port, User, Password, Database name
   (usually `defaultdb`) and download the CA certificate (`ca.pem`).
2. **Import schema**: connect (MySQL Workbench / `mysql` CLI with `--ssl-ca=ca.pem`) and run
   `database/database_cloud.sql` against the Aiven database.
3. **GitHub**: push this project to a GitHub repository (do not commit real passwords).
4. **Render**: New > Web Service (or Blueprint using `render.yaml`) > select the repo, runtime Docker.
5. **Environment variables** (Render dashboard):
   - `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` - from Aiven
   - `DB_SSL` = `true`
   - `DB_SSL_CA_PEM` - paste the contents of `ca.pem` (newlines may be pasted as `\n`)
   - `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM` - your Gmail address and a new app password
6. Open the Render URL; it redirects to `/frontend/`. Admin panel: `/frontend/admin/login.php`.

Notes: uploaded files (evidence, IDs, term PDFs) live on Render's temporary disk and are
lost on redeploy/restart on the free plan. The free service sleeps when idle.
Local XAMPP use is unchanged (settings fall back to the old defaults).
