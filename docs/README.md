# HumHub Bazaar

A HumHub administration module that connects your HumHub instance to the [Green Meteor](https://greenmeteor.net) module marketplace. Browse, purchase, and install premium HumHub modules directly from your administration panel.

---

## Features

- Browse the full Green Meteor module catalogue from within HumHub
- Purchase paid modules via Stripe — no leaving your admin panel
- One-click install for free and purchased modules
- Per-user purchase state — Buy button automatically becomes Install after payment or manual credit
- Category filtering, keyword search, and sort controls
- Bootstrap 5 responsive card layout with screenshot carousel on detail pages
- Server-side response caching with automatic cache busting on purchase
- Full i18n / translation support
- Admin configuration panel with live API connection test

---

## Requirements

| Requirement | Minimum |
|---|---|
| HumHub | 1.18.0 |
| PHP | 8.2 |
| PHP extensions | curl, zip, json |
| Internet access | Required (API + Stripe) |
| Green Meteor account | [greenmeteor.net](https://greenmeteor.net) |

---

## Configuration

All settings are managed from the **Configure** page inside the module. The key settings are:

| Setting | Description | Default |
|---|---|---|
| API Key | Your Green Meteor account API key | — |
| Cache Timeout | How long API responses are cached (seconds) | `3600` |
| Enable Purchasing | Allow Stripe checkout from within HumHub | `true` |

The API key is tied to your registered email address on greenmeteor.net. The email address of the HumHub admin performing the action is sent with every API request so purchased and credited modules are resolved correctly per-user.

---

## How Purchase State Works

The module resolves whether a user has purchased a given module by sending the logged-in HumHub admin's email address to the Green Meteor API on every catalogue fetch. This means:

- **Stripe purchase** — after completing checkout the purchase is recorded automatically against your email and reflected on next page load.
- **Manual credit** — if Green Meteor credits a module to your account, clear the Bazaar cache (**Configure → Clear Cache**) to force an immediate refresh.
- **Free modules** — always show an Install button with no account required.

---

## API Endpoints Used

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/modules.php?action=list` | Full module catalogue |
| `GET` | `/api/modules.php?action=get` | Single module detail |
| `POST` | `/api/modules.php` (`action=purchase`) | Create Stripe Checkout session |
| `GET` | `/api/verify-purchase.php` | Verify Stripe payment after redirect |

---
