# Installation Guide — HumHub Bazaar

---

## Requirements

Before installing, confirm your environment meets the following:

| Requirement | Minimum | Notes |
|---|---|---|
| HumHub | 1.18.0 | Check under Administration → About |
| PHP | 8.2 | `php -v` |
| PHP extensions | `curl`, `zip`, `json` | Required for API calls and module installs |
| Web server write access | `/protected/modules/` | Required for one-click installs |
| Internet access | Outbound HTTPS on port 443 | Required for API and Stripe |
| Green Meteor account | — | Register free at [greenmeteor.net](https://greenmeteor.net) |

---

## Step 1 — Download the Module

**Option A — Git clone (recommended)**

```bash
cd /path/to/humhub/protected/modules
git clone https://github.com/greenmeteor/bazaar.git bazaar
```

**Option B — Manual ZIP install**

1. Download the latest release ZIP from [greenmeteor.net](https://greenmeteor.net) or GitHub.
2. Extract the ZIP so the module folder is named exactly `bazaar`:
   ```
   /path/to/humhub/protected/modules/bazaar/
   ```

---

## Step 2 — Set Permissions

```bash
chmod -R 755 /path/to/humhub/protected/modules/bazaar/
chown -R www-data:www-data /path/to/humhub/protected/modules/bazaar/
```

> **Note:** Replace `www-data` with your actual web server user (e.g. `nginx`, `apache`, `httpd`). Run `ps aux | grep -E 'apache|nginx|php-fpm' | head -1` if unsure.

If you want one-click installs to work (downloading and extracting module ZIPs automatically), the entire `modules/` directory must also be writable:

```bash
chmod -R 755 /path/to/humhub/protected/modules/
chown -R www-data:www-data /path/to/humhub/protected/modules/
```

---

## Step 3 — Enable the Module in HumHub

1. Log in to HumHub as a **Admin**.
2. Go to **Administration → Modules**.
3. Find **"Module Bazaar"** in the list.
4. Click **Enable**.

---

## Step 4 — Configure the Module

1. Go to **Administration → Module Bazaar**.
2. Click the **Configure** button (top right of the panel).
3. Fill in the following settings:

| Field | Value | Where to find it |
|---|---|---|
| **API Key** | Your Green Meteor API key | [greenmeteor.net](https://greenmeteor.net/login) → Developer Portal → API Keys |
| **Cache Timeout** | `3600` (default) | Seconds to cache the module catalogue |
| **Enable Purchasing** | Checked | Allows Stripe checkout from within HumHub |

4. Click **Save**.
5. Click **Test Connection** to confirm the API is reachable. You should see:
   ```
   Connection successful. X modules found.
   ```

---

## Step 5 — Verify Your Email Matches greenmeteor.net

The module identifies your account by the email address of the logged-in HumHub admin. For purchased and credited modules to appear correctly, **this email must match your registered email on greenmeteor.net**.

To check:
- HumHub email: **Administration → Users → your account**
- Green Meteor email: **greenmeteor.net → Account Settings**

If they differ, either update your HumHub profile email or your Green Meteor account email so they match.

---

## Updating the Module

**Via Git:**
```bash
cd /path/to/humhub/protected/modules/bazaar
git pull origin main
```

Then clear the Bazaar cache: **Administration → Module Bazaar → Clear Cache**.

**Via ZIP:**

1. Back up your current `bazaar/` folder.
2. Delete `bazaar/` and extract the new ZIP in its place.
3. Re-apply permissions (Step 2).
4. Clear cache.

---

## Uninstalling

1. Go to **Administration → Modules**.
2. Find **Module Bazaar** and click **Disable**, then **Uninstall**.
3. Remove the module folder:
   ```bash
   rm -rf /path/to/humhub/protected/modules/bazaar
   ```

---

## Troubleshooting

**"Could not reach the Green Meteor API" error**

- Confirm outbound HTTPS (port 443) is not blocked by a firewall.
- Run `curl -I https://greenmeteor.net/api/modules.php` from your server to test.
- Check your API key is correct in the Configure page.

**Buy button not changing to Install after purchase or credit**

- Click **Clear Cache** on the Bazaar index page.
- Confirm your HumHub admin email matches your greenmeteor.net account email (see Step 5).

**One-click install fails with "Modules directory is not writable"**

- Re-run the `chmod`/`chown` commands from Step 2 on the parent `modules/` directory.
- Alternatively, use **Download Only** and extract the ZIP manually.

**"Test Connection" returns 0 modules**

- Your API key may be incorrect or expired.
- Try clearing the Bazaar cache and testing again.