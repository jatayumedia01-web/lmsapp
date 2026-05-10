# Deploying Devithor LMS to Hostinger Cloud Hosting

End-to-end one-time setup. After step **8** every `git push origin main`
auto-deploys via GitHub Actions.

---

## 0. Before you start

You'll need:
- A Hostinger Cloud Hosting plan (Startup / Professional / Enterprise)
- A domain or subdomain pointed to that plan (e.g. `api.devithor.com`)
- A GitHub account + a fresh empty repo for this backend

> ⚠️ **Never paste credentials anywhere except the Hostinger hPanel and the
> GitHub Secrets UI.** Especially never in chat, commit messages, or `.env`
> committed to Git.

---

## 1. Create the MySQL database (hPanel)

1. Sign in to hPanel → **Databases** → **MySQL Databases**.
2. Click **Create new MySQL database**.
3. Note these — you'll paste them into `.env` in step 6:
   - **Database name** (looks like `u123456789_lms`)
   - **Username** (looks like `u123456789_admin`)
   - **Password** (Hostinger generates one; copy it)
   - **Host** (usually `localhost`; in some plans `mysql.<panel-id>.hstgrcp.com`)

---

## 2. Point the domain at `/public`

In hPanel → **Domains** → **Manage** for your domain → **Document root**:

- Set **Document Root** to `/public_html/devithor-backend/public`
  (or whatever subfolder you'll deploy into).
- Save.

If you can't change the document root for the domain you're using, the root
`.htaccess` we ship will redirect everything through `/public` as a fallback.

---

## 3. Enable SSH access (one-time)

In hPanel → **Advanced** → **SSH Access**:

- Click **Enable** for your hosting account.
- **Add an SSH key** — paste the public key of whichever GitHub Actions runner
  will deploy. Generate one locally if you don't have a deploy key yet:

  ```bash
  ssh-keygen -t ed25519 -C "github-actions-deploy" -f ./hostinger_deploy_key
  ```

  Add the contents of `hostinger_deploy_key.pub` in the SSH Access page.
  Keep `hostinger_deploy_key` (the private key) safe — you'll paste it into
  GitHub Secrets in step 7.

- Note the **SSH command shown** (e.g. `ssh -p 65002 u123456789@123.45.67.89`).
  You'll need the host, port, and username for both initial setup and CI.

---

## 4. First-time SSH in + clone the repo

```bash
ssh -p <PORT> <USERNAME>@<HOST>
cd ~/public_html

# Clone using a deploy key (or HTTPS + a personal access token)
git clone git@github.com:<your-username>/<your-repo>.git devithor-backend
cd devithor-backend
```

If `git@github.com` isn't allowed (firewall), use the HTTPS URL with a
[fine-grained PAT](https://github.com/settings/personal-access-tokens):
`git clone https://<USERNAME>:<TOKEN>@github.com/<USERNAME>/<REPO>.git`

---

## 5. Verify PHP version

```bash
php --version
```

Must be **8.2 or higher**. If hPanel default is older, set it via
**hPanel → Websites → Manage → PHP Configuration → PHP version → 8.2**.

---

## 6. Create `.env` on the server

```bash
cp .env.example .env
nano .env
```

Fill in:
- `APP_URL` — your domain (e.g. `https://api.devithor.com`)
- `APP_KEY` — generate one:
  ```bash
  openssl rand -base64 48
  ```
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from step 1
- `ADMIN_EMAIL`, `ADMIN_PASSWORD` — credentials for your *first* admin login

Save (`Ctrl+O`, `Enter`, `Ctrl+X`). Then:

```bash
chmod 600 .env  # Only your user can read it
```

---

## 7. Run migrations + seed

```bash
php migrations/migrate.php
php seeds/seed.php
```

Visit `https://your-domain/admin/login` and sign in with the admin email +
password from `.env`. Confirm the dashboard loads.

> Once you've signed in successfully, **edit `.env` and remove the
> `ADMIN_*` lines** so the credentials don't sit on disk in plaintext. The
> admin user already exists in the DB — you don't need them anymore.

---

## 8. Wire GitHub Actions auto-deploy

In your GitHub repo → **Settings** → **Secrets and variables** → **Actions**
→ **New repository secret**, add four secrets:

| Name              | Value                                                       |
|-------------------|-------------------------------------------------------------|
| `HOSTINGER_HOST`  | The IP / hostname from step 3 (`123.45.67.89`)              |
| `HOSTINGER_PORT`  | The SSH port from step 3 (usually `65002`)                  |
| `HOSTINGER_USER`  | The SSH username from step 3 (`u123456789`)                 |
| `HOSTINGER_SSH_KEY` | The **private** key from step 3 (`hostinger_deploy_key`) |
| `HOSTINGER_PATH`  | Absolute path to the deploy dir (`/home/u123.../public_html/devithor-backend`) |

The workflow at [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml)
will fire on every push to `main`:

1. Connects via SSH using the key
2. `cd $HOSTINGER_PATH`
3. `git pull --ff-only`
4. `php migrations/migrate.php` (only applies new migrations — safe)
5. Touches a `deployment_succeeded` marker file

Push a trivial change to verify:

```bash
git commit --allow-empty -m "deploy: smoke test"
git push origin main
```

Watch **Actions** tab — green tick means you're done.

---

## 9. (Optional) Set up a daily backup cron

In hPanel → **Advanced** → **Cron Jobs** → **Add new**:

```
0 3 * * *   mysqldump -u<DB_USERNAME> -p<DB_PASSWORD> <DB_DATABASE> | gzip > ~/backups/lms_$(date +\%Y\%m\%d).sql.gz
```

Hostinger keeps daily backups of the whole account too, but a per-DB dump is
faster to restore from.

---

## 10. Pointing the Android app at the live API

In `~/LMS-App/app/build.gradle.kts` set the `API_BASE_URL` BuildConfig field
to your API URL (see the Android section of this PR for the snippet). On
release builds it'll point at production; debug builds at staging or
`http://10.0.2.2:8000` for the local PHP dev server.

---

## Troubleshooting

**500 on every page** → Check `/public_html/devithor-backend/error_log`.
Most often: `.env` missing or PHP version too old.

**`/admin/login` shows 404** → Document root not pointing at `/public`.
Either fix it in hPanel (preferred) or confirm the root `.htaccess` is in
place and `mod_rewrite` is enabled (it is, by default, on Hostinger).

**Migrations fail with "Access denied"** → DB credentials wrong in `.env`.
Test directly: `mysql -u<USER> -p<DB>` from SSH.

**GitHub Actions fails on `git pull`** → The deploy key on Hostinger doesn't
have read access to the GitHub repo. Use a [GitHub deploy key](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys#deploy-keys) instead of a personal SSH key.
