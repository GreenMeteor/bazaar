# User Manual — HumHub Bazaar

This manual covers everything a HumHub Admin needs to know to browse, purchase, install, and manage modules through the Bazaar.

---

## Table of Contents

1. [Accessing the Bazaar](#1-accessing-the-bazaar)
2. [Browsing Modules](#2-browsing-modules)
3. [Viewing Module Details](#3-viewing-module-details)
4. [Installing Free Modules](#4-installing-free-modules)
5. [Purchasing Paid Modules](#5-purchasing-paid-modules)
6. [After Purchase — Installing Your Module](#6-after-purchase--installing-your-module)
7. [Manually Credited Modules](#7-manually-credited-modules)
8. [Managing the Cache](#8-managing-the-cache)
9. [Configuration Settings](#9-configuration-settings)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Accessing the Bazaar

1. Log in to HumHub as a **Admin**.
2. Go to **Administration** (top navigation or sidebar).
3. Click **Module Bazaar** in the administration menu.

You will see the module catalogue grid. Each card represents one available module.

---

## 2. Browsing Modules

The catalogue page provides three controls to help you find modules:

### Search
Type any keyword into the **Search modules…** field and press Enter or click the magnifier icon. The search checks both the module name and description.

### Category Filter
Use the **All Categories** dropdown to filter by:

| Category | Examples |
|---|---|
| Communication | Messaging, notifications, mail |
| Content | Wikis, documents, articles |
| Integration | Stripe, webhooks, external APIs |
| Productivity | Calendars, tasks, scheduling |
| Social | Polls, reactions, voting |
| Other | Everything else |

### Sort Order
Use the sort dropdown to reorder results by **Name**, **Price**, or **Category**. The default order reflects the order returned by the Green Meteor API.

### Module Card Status Badges

Each card shows one of the following status indicators:

| Badge | Meaning |
|---|---|
| **Free** (blue) | No cost — Install button shown |
| **$X.XX USD** (blue, top-right corner) | Paid, not yet purchased |
| **Purchased** (green, top-right corner) | You own this module — Install button shown |
| **Coming Soon** (yellow) | Not yet available |

---

## 3. Viewing Module Details

Click the **Details** button on any module card to open its detail page.

The detail page shows:

- **Screenshot carousel** — click the arrows to browse screenshots (if more than one is available)
- **Name, author, version**
- **Category badge**
- **Status badge** (Free / Purchased / Paid / Coming Soon)
- **Price** (for paid, unpurchased modules)
- **Action buttons** — Install, Download Only, or Purchase depending on ownership state
- **Installation instructions** — shown when the module is ready to install
- **Description** — full module description
- **Features list** — key capabilities
- **Requirements** — any dependencies or HumHub version requirements

---

## 4. Installing Free Modules

Free modules show a green **Install** button on both the catalogue card and the detail page.

**To install:**

1. Click **Install** on the module card or detail page.
2. A confirmation dialog will appear — click **OK** to proceed.
3. The module ZIP is downloaded from Green Meteor and automatically extracted to your HumHub `protected/modules/` directory.
4. Once complete you will see a success message:
   > *"[Module Name] has been installed. Enable it under Administration → Modules."*
5. Go to **Administration → Modules**, find the newly installed module, and click **Enable**.

> **If Install fails:** Use **Download Only** instead. Save the ZIP, extract it to `protected/modules/[module-id]/`, then enable it from Administration → Modules.

---

## 5. Purchasing Paid Modules

Paid modules that you have not yet purchased show a blue **Buy** button.

**To purchase:**

1. Click **Buy** on the module card, or **Purchase** on the detail page.
2. You will be taken to a purchase confirmation page inside HumHub.
3. Click **Confirm Purchase**.
4. You will be redirected to a **Stripe-hosted checkout page**.
5. Enter your payment details and complete the payment.
6. Stripe will redirect you back to HumHub automatically.

**What happens after payment:**

- HumHub verifies the payment with Green Meteor.
- Your purchase is recorded against your email address on greenmeteor.net.
- The module detail page updates to show the **Install** and **Download Only** buttons.
- The **Buy** button is replaced with **Install** on the catalogue card.

> **Important:** The email address of your HumHub admin account must match your registered email on greenmeteor.net for purchases to be recognised automatically. If they differ, contact Green Meteor support.

---

## 6. After Purchase — Installing Your Module

Once a module is marked as **Purchased**, the Install button becomes available on both the catalogue card and the detail page.

**To install after purchase:**

1. Navigate to the module's detail page (click **Details**).
2. Click the **Install** button.
3. Confirm the dialog prompt.
4. The module is downloaded and extracted automatically.
5. Go to **Administration → Modules** and enable the module.

**Alternatively — Download Only:**

If you prefer to install manually or your server does not have write access to the modules directory:

1. Click **Download Only** on the detail page.
2. A ZIP file will download to your computer.
3. Extract the ZIP and upload the folder to `protected/modules/` on your server via FTP/SFTP.
4. Ensure the folder name matches the module ID exactly.
5. Enable from **Administration → Modules**.

---

## 7. Manually Credited Modules

Green Meteor may credit a module directly to your account without a Stripe transaction — for example as part of a support arrangement or partnership.

When this happens:

1. Your module is added to your account on greenmeteor.net by the Green Meteor team.
2. **You must clear the Bazaar cache** for HumHub to pick up the change.
3. Go to **Administration → Module Bazaar**.
4. Click the **Clear Cache** button (top right of the panel).
5. The page will reload. The module's **Buy** button will now show as **Install**.

> **Why is this necessary?** HumHub caches the module catalogue to avoid hitting the API on every page load. Clearing the cache forces an immediate fresh fetch that picks up your newly credited module.

---

## 8. Managing the Cache

The Bazaar caches the module catalogue to improve performance. The cache is keyed to your admin email address so each user's purchase state is stored independently.

### Automatic Cache Busting

The cache is automatically invalidated in these situations:

- You complete a Stripe purchase (cache cleared for your account immediately)
- You visit the detail page of a paid-but-unpurchased module (live check performed)
- The cache TTL expires (default: 1 hour)

### Manual Cache Clear

Click **Clear Cache** on the Bazaar index page to flush the entire application cache immediately. Use this when:

- Green Meteor has manually credited a module to your account
- A new module has been added to the catalogue and is not appearing
- A price or module detail has changed and you want to see the latest data
- You have changed your API key in the Configure settings

> **Note:** Clear Cache flushes the full HumHub application cache, not just the Bazaar cache. This is intentional — it ensures all users see fresh data on their next page load.

---

## 9. Configuration Settings

Go to **Administration → Module Bazaar → Configure** to manage these settings.

| Setting | Description |
|---|---|
| **API Key** | Your Green Meteor API key. Required for the module to communicate with greenmeteor.net. Find this in your account on greenmeteor.net. |
| **Cache Timeout** | How many seconds to cache the module catalogue. Default is `3600` (1 hour). Lower this if you want more frequent automatic refreshes at the cost of more API calls. |
| **Enable Purchasing** | When enabled, Buy buttons and the Stripe checkout flow are active. Disable this if you want to prevent purchases from within HumHub (e.g. in a staging environment). |

### Test Connection

Click **Test Connection** on the Configure page to send a live request to the Green Meteor API and confirm everything is working. A successful result shows:

```
Connection successful. X modules found.
```

If the test fails, double-check your API key and confirm your server can reach `greenmeteor.net` on port 443.

---

## 10. Troubleshooting

### Buy button is not changing to Install after I purchased a module

1. Click **Clear Cache** on the Bazaar index page.
2. Confirm the email on your HumHub admin account matches your greenmeteor.net account email exactly (including capitalisation).
3. If still showing Buy, navigate to the module's **Details** page — this triggers a live purchase check independently of the catalogue cache.

### Buy button is not changing to Install after Green Meteor credited my account

Green Meteor manual credits are not pushed to HumHub — you must pull the update by clearing the cache:

1. Click **Clear Cache** on the Bazaar index page.
2. The Install button will appear on the next page load.

### Install fails with "Modules directory is not writable"

Your web server user does not have write permission to `protected/modules/`. Either:

- Ask your server administrator to run `chown -R www-data:www-data /path/to/humhub/protected/modules/`
- Use **Download Only** and install the module manually via FTP/SFTP

### "Could not reach the Green Meteor API" error on the catalogue page

- Check the API key in Configure is correct.
- Confirm your server can make outbound HTTPS requests: run `curl -I https://greenmeteor.net` from the server.
- Check whether a firewall or proxy is blocking outbound connections on port 443.

### Module installs but does not appear under Administration → Modules

- Confirm the extracted folder name exactly matches the module's ID (e.g. `calendar`, not `humhub-calendar`).
- Check file permissions on the extracted folder (`chmod -R 755`).
- Run **Administration → Flush Caches** in HumHub to clear the module discovery cache.

### "Test Connection" shows 0 modules

- Your API key may be incorrect or the greenmeteor.net server may be temporarily unavailable.
- Try again after a few minutes.
- If the problem persists, contact [support@greenmeteor.net](mailto:support@greenmeteor.net).

---

## Getting Help

For account and billing questions, email [pricing@greenmeteor.net](mailto:pricing@greenmeteor.net) or visit [greenmeteor.net](https://greenmeteor.net).
