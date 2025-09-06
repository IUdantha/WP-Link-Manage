# Link Manage

Manage IELTS resource links for your students from the WordPress admin — and display only the relevant links to each logged-in user via a shortcode.

* **Admin → Link Manage**
  * **Students:** toggle each user’s access to **PRE-IELTS** and/or **Advanced IELTS** (stored as user meta).
  * **Links:** create, edit, delete resource links; filter by Type, Created date, Expire date, and global search.
  * **Settings:** placeholder (coming soon).
* **Frontend:**`[ielts-resource-links]` renders a Bootstrap-styled table with filters. Logged-in users see only links for the categories assigned to them. Expired links are hidden automatically.

---

## Contents

* [Requirements](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#requirements)
* [Installation](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#installation)
* [File structure](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#file-structure)
* [Database schema](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#database-schema)
* [How it works](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#how-it-works)
  * [Students tab](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#students-tab)
  * [Links tab](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#links-tab)
  * [Admin-side filters](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#admin-side-filters)
  * [Frontend shortcode](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#frontend-shortcode)
* [Permissions](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#permissions)
* [Bootstrapping & theme compatibility](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#bootstrapping--theme-compatibility)
* [Security](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#security)
* [Troubleshooting](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#troubleshooting)
* [Roadmap](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#roadmap)
* [Changelog](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#changelog)
* [License](https://chatgpt.com/g/g-p-678ffe8af80c8191b72409b4c569ed3b-sirimt/c/68bc2f6f-4e48-832e-a518-0971fef12647#license)

---

## Requirements

* WordPress **5.8+** (tested on 6.x)
* PHP **7.4+** (8.0+ recommended)
* MySQL/MariaDB with InnoDB
* A theme that either includes Bootstrap (optional) or has reasonable default table/form styles

---

## Installation

1. Copy the plugin folder to:
   ```
   wp-content/plugins/link-manage
   ```
2. Activate **Link Manage** in **WP Admin → Plugins**.

> On activation, the plugin creates (or upgrades) its table. It also auto-verifies the table exists on every load to avoid runtime errors.

---

## File structure

```
link-manage/
├─ link-manage.php
├─ includes/
│  ├─ class-lm-activator.php
│  ├─ class-lm-admin.php
│  ├─ class-lm-shortcode.php
│  └─ class-lm-ajax.php
└─ assets/
   ├─ admin.css
   └─ admin.js
```

---

## Database schema

Custom table: **`{$wpdb->prefix}lm_links`**


| Column      | Type               | Notes                           |
| ----------- | ------------------ | ------------------------------- |
| id          | BIGINT UNSIGNED PK | Auto-increment                  |
| title       | VARCHAR(255)       | Link title                      |
| link\_type  | VARCHAR(20)        | `PRE-IELTS`or`Advanced IELTS`   |
| url         | TEXT               | Target URL                      |
| description | LONGTEXT           | Rich text (TinyMCE/`wp_editor`) |
| created\_at | DATETIME           | Default`CURRENT_TIMESTAMP`      |
| expire\_at  | DATETIME NULL      | `NULL`means**no expiry**        |

> Legacy installs that previously used a `type` column are auto-migrated to `link_type`.

---

## How it works

### Students tab

* Shows all users with search/role filters.
* Two toggles per user:
  * **PRE-IELTS:** user meta key `lm_pre_ielts` (`'1'` or `'0'`)
  * **Advanced IELTS:** user meta key `lm_adv_ielts` (`'1'` or `'0'`)
* Saves instantly via AJAX (no page reload, no custom student table).

### Links tab

* **Add Link** form: Title, Type (PRE/Advanced), URL, Description (WordPress editor), Expiry (switch + date).
* **Created date** is stored automatically on insert.
* **Expire date** is optional; if the switch is off, expiry is not set (treated as “no expire”).
* After submit, entries list below with **Edit** and **Delete** actions.

### Admin-side filters

On **Link Manage → Links**, a filter bar lets you refine the table:

* **Global search** (`title`, `url`, `description`)
* **Type** (`PRE-IELTS` or `Advanced IELTS`)
* **Created date** range
* **Expire date** range (applies only to rows that have an expiry)

Filters use GET parameters and safe prepared SQL. You can bookmark filtered views.

### Frontend shortcode

Place this shortcode on any page:

```
[ielts-resource-links]
```

**Behavior**

* Detects the **current logged-in user**.
* Checks their meta to determine access (**PRE-IELTS**, **Advanced IELTS**, or both).
* Displays a table with columns: **No, Title, Type, Link, Description, Created Date, Expire Date**.
* **Expired links are hidden**: only records where `expire_at IS NULL OR expire_at >= CURRENT_DATE()` are shown.
* Built-in filters (GET-based):
  * `lm_q` — global search
  * `lm_type` — type filter
  * `lm_c_from`, `lm_c_to` — created date range
  * `lm_e_from`, `lm_e_to` — expiry date range

Example filtered URL:

```
/resources/?lm_type=PRE-IELTS&lm_q=listening&lm_c_from=2025-08-01
```

---

## Permissions

* All admin screens and CRUD actions require the `manage_options` capability (i.e., Administrators).
* Frontend shortcode requires the user to be **logged in** and to have at least one of the student flags assigned.

---

## Bootstrapping & theme compatibility

* The frontend markup uses Bootstrap classes (`container`, `row`, `form-control`, `form-select`, `btn`, `table`, `badge`, etc.).
* If your theme already loads Bootstrap, the table inherits Bootstrap styling.
* If your theme does **not** load Bootstrap, the table still renders with semantic HTML and your theme’s base styles.

---

## Security

* All admin actions use WordPress nonces.
* Capability checks guard admin pages and CRUD endpoints.
* Database access uses `$wpdb->prepare()` and sanitized inputs.
* AJAX endpoints are `admin-ajax.php` with nonce & capability checks.

---

## Troubleshooting

**“Database error … ALTER TABLE … `type` …” in logs**

* Fixed in **v1.0.1**. We removed inline SQL comments and renamed the `type` column to `link_type`.
* The plugin includes a migration that safely renames legacy installs. If issues persist:
  1. Deactivate and reactivate the plugin.
  2. Ensure your DB user can `CREATE`/`ALTER` tables.
  3. Confirm table name: `wp_lm_links` (prefix may differ).

**No links appear on the frontend**

* Ensure the logged-in user is tagged in **Students** for **PRE-IELTS** and/or **Advanced IELTS**.
* Check that links are not expired (or remove the expiry to test).
* Clear filters in the page URL (remove `lm_*` params).

**Admin list shows nothing**

* Try clearing admin filters (there’s a **Reset** button).
* Verify the table contains rows (add a test link).

---

## Roadmap

* Settings tab (UI options, default filters, pagination)
* Optional CSV export of links
* Bulk actions (delete, type change)
* Per-user link overrides
* Uninstall cleanup script (drop table + remove user meta) — optional

---

## Changelog

**1.0.1**

* Fixed dbDelta issue & migration: `type → link_type` (no inline SQL comments).
* Added admin-side filters (Type, Created range, Expire range, Global search).
* Extra runtime safeguards to ensure the table exists.

**1.0.0**

* Initial release: Students toggles, Links CRUD, shortcode with Bootstrap table and filters, hide expired links.

---

## License

MIT — do what you want, just don’t hold us liable.
Copyright © 2025.

---

**Made for fast, practical link management for IELTS resources.**
