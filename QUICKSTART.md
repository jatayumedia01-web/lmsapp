# Quickstart — push to GitHub + install on Hostinger

Mee setup-specific steps based on the screenshots:
- GitHub repo: `https://github.com/jatayumedia01-web/lmsapp`
- Hostinger site: `apptesting.in`
- DB created: `u169457691_appbackend` (rotate the password first — see SECURITY note below).

---

## ⚠️ Step 0 — rotate the DB password (critical)

The password shared in the screenshot (`Madhu2033@`) is now leaked in chat history.

- hPanel → **Databases** → **Management** → row for `u169457691_appbackend` → **Change password**
- Generate a strong one (Hostinger has a button). Save it in your password manager — **don't paste it anywhere else**.

---

## Step 1 — push the backend to GitHub

From your local machine:

```bash
cd ~/devithor-backend

git init
git add .
git commit -m "Initial Devithor LMS backend + admin dashboard"
git branch -M main
git remote add origin https://github.com/jatayumedia01-web/lmsapp.git
git push -u origin main
```

GitHub will ask for credentials. If it rejects your account password, create a [fine-grained personal access token](https://github.com/settings/personal-access-tokens) with **repository:contents:write** scope and use that as the password.

---

## Step 2 — get the code onto Hostinger

Two paths — pick whichever is easier:

### Option A: SSH + git clone (recommended)

1. hPanel → **Advanced** → **SSH Access** → **Enable**.
2. Note the SSH command shown (e.g. `ssh -p 65002 u169457691@123.45.67.89`).
3. From your local terminal:
   ```bash
   ssh -p 65002 u169457691@123.45.67.89
   cd ~/domains/apptesting.in/public_html
   git clone https://github.com/jatayumedia01-web/lmsapp.git devithor-backend
   ```

### Option B: hPanel File Manager (no SSH needed)

1. From your local terminal:
   ```bash
   cd ~/devithor-backend
   zip -r devithor-backend.zip . -x ".git/*"
   ```
2. hPanel → **Files** → **File Manager** → navigate to `domains/apptesting.in/public_html/`.
3. Upload `devithor-backend.zip` → right-click → **Extract** → into a `devithor-backend/` folder.

---

## Step 3 — point the domain at `/public`

hPanel → **Domains** → **Manage** for `apptesting.in` → **Document Root** → set to:

```
/public_html/devithor-backend/public
```

If that field is read-only on your plan, the root `.htaccess` we ship will redirect through `/public` automatically — so it'll work either way.

---

## Step 4 — run the installer

Visit:

```
https://apptesting.in/install.php
```

The wizard will:

1. ✅ Check requirements (PHP 8.2+, PDO, etc.)
2. 🔐 Ask for your DB host / name / user / **new** password → tests connection → writes `.env`
3. 🛠 Create all tables (users, courses, lessons, billing, Q&A, bookmarks)
4. 👤 Create your first admin user (email + name + password — min 12 chars)
5. 🔒 Drop a `.installed` lock file so the installer can't run again

---

## Step 5 — clean up

After the success screen:

1. **Delete** `public/install.php` from the server (File Manager or SSH `rm`).
2. (If you have SSH) `chmod 600 .env` so only your user can read it.
3. Smoke-test: open `https://apptesting.in/api/v1/health` → should return `{"status":"ok","time":"..."}`.
4. Sign in: `https://apptesting.in/admin/login`.

---

## Step 6 — point the Android app at the live API

In `~/LMS-App/app/build.gradle.kts`, change the release `API_BASE_URL`:

```kotlin
release {
    // ...existing config...
    buildConfigField("String", "API_BASE_URL", "\"https://apptesting.in/api/v1/\"")
}
```

Build a release APK → install on a device → the catalog now reads from your Hostinger backend. Edit a course title in the dashboard → next app launch shows the new title.

---

## Step 7 (optional) — wire GitHub Actions auto-deploy

So every `git push` deploys automatically. Follow [`deploy/DEPLOY.md`](deploy/DEPLOY.md) section 8 — you'll add 5 secrets to GitHub and from then on `git push origin main` triggers a deploy.

---

## Troubleshooting

**"Could not connect to the database"** in the installer → re-check the credentials match exactly what hPanel shows. The host is usually `localhost`. The username + database both have your account prefix (e.g. `u169457691_`).

**`/admin/login` shows a blank page or 500** → check `~/domains/apptesting.in/public_html/devithor-backend/error_log` (or via File Manager). 99% of the time: PHP version is older than 8.2 (fix in hPanel → Websites → PHP Configuration).

**Installer says "Already installed"** but you want to re-run → SSH in, `rm .installed .env`, refresh the page. (Production: don't do this — it wipes your admin login config.)
