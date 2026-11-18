# Good Together — Volunteer Event Management System

Good Together is a PHP and MySQL website connecting volunteers with local community activities. It provides different functions for volunteers, event organizers, and administrators.

## Features

**Visitors:** About Us, browse and search Events, view event details, register an account, log in.

**Volunteers:** search by city/category, view event details and capacity, register or cancel eligible registrations, review attendance, change display name/password.

**Organizers:** create/edit events, see enrolled volunteers, mark attendance, view attendance reports, enter a WhatsApp group invitation link on their event details page.

**Administrators:** view platform statistics, moderate eligible events, suspend or restore user accounts.

## Technologies

PHP (PDO), MySQL, HTML, CSS, JavaScript, MAMP (or another PHP/MySQL environment).

## Database files and privacy

The repository includes **`goodtogether_schema.sql`**, which contains the structure of three tables (`users`, `events`, `registrations`) but **no account data or sample events**. You must create test accounts and events after setup. The real `database.php`, containing local credentials, is deliberately not included; use `database.example.php` as a template.

Do not commit a full SQL dump, real login credentials, or `.env` secrets to a public repository.

## Setup on macOS (MAMP)

1. Install and open MAMP. Start **Apache** and **MySQL**. In MAMP settings, verify the Apache port (often `8888`) and MySQL port (often `8889`). Your settings may be different.
2. Download the repository (GitHub **Code → Download ZIP**) and extract it. Rename the extracted folder to `goodtogether`, then place it at:
   `/Applications/MAMP/htdocs/goodtogether/`
3. Open MAMP's phpMyAdmin, usually via the MAMP WebStart page (or `http://localhost:8888/phpMyAdmin/` when Apache uses port 8888).
4. **Create a database named `goodtogether`. Click that database in the left sidebar before importing.** This prevents MySQL error `#1046 - No database selected`.
5. With the `goodtogether` database selected, click **Import → Choose File → `goodtogether_schema.sql` → Go**. Confirm the three tables `users`, `events`, `registrations` appear. Import once into a new empty database; avoid re-importing over populated tables.
6. In the downloaded project folder, duplicate `database.example.php`, naming the duplicate **`database.php`**. Keep the entire file, including its PDO connection and account-status checks.
7. In the newly created `database.php`, update the connection variables near the top to match **your own** MAMP configuration:

   ```php
   $host = "127.0.0.1";
   $database = "goodtogether";
   $username = "root";
   $password = "root";
   $port = 8889;
   ```

   These are *example* Mac MAMP settings, not universal values. Confirm your actual MySQL credentials and port in MAMP.
8. Open `http://localhost:8888/goodtogether/` if Apache uses port 8888. The landing page redirects to `about.php`. If Apache uses port 80, open `http://localhost/goodtogether/` instead.
9. Use **Sign Up** to create a volunteer and/or organizer account. Organizers can create events. Because this database export is structure-only, there are no preloaded accounts, events or registrations.

## Setup on Windows (MAMP)

1. Install/open MAMP and start Apache and MySQL. Verify both port settings (commonly Apache `80`, MySQL `3306` in this project's original Windows setup).
2. Place the `Goodtogether` project directory inside `C:\MAMP\htdocs\`.
3. In phpMyAdmin, create and select a `goodtogether` database, then import `goodtogether_schema.sql`.
4. Copy `database.example.php` to `database.php` and set its local connection values. A typical Windows configuration used by this project is:

   ```php
   $host = "127.0.0.1";
   $database = "goodtogether";
   $username = "root";
   $password = "root";
   $port = 3306;
   ```

5. If Apache uses port 80, open `http://localhost/Goodtogether/`. Otherwise include the configured Apache port in the URL.

## Website navigation

- `index.php` redirects to `about.php` (About Us).
- `opportunities.php` displays searchable events.
- `event_details.php?id=1` displays an event introduction and poster placeholder (replace `1` with an existing event's ID).
- `login.php` redirects successfully authenticated users to About Us.
- The navigation menu adapts to Volunteer, Organizer and Administrator roles.

## Common troubleshooting

| Problem | Fix |
| --- | --- |
| `#1046 - No database selected` | Create **and click** the `goodtogether` database in phpMyAdmin, then import the schema. |
| `Failed opening required 'database.php'` | Duplicate `database.example.php` as `database.php` and configure local credentials. |
| PDO database connection failed | Check MySQL is running, the chosen database exists, and the host, username, password and **MySQL** port are correct. |
| 404 Not Found | Confirm the project is located under MAMP `htdocs`, the URL folder name is correct, and the **Apache** port matches MAMP settings. |
| No events visible | This schema contains no sample event data; log in as an Organizer and create an upcoming event. |
| No Admin account | The public signup creates Volunteer/Organizer accounts only; Administrator access requires authorized local account setup. |
| Images do not load | Confirm all three photos are inside `images/` and their actual `.jpg`/`.jpeg` extensions match references in `about.php`. |

## Security notes

The PHP backend uses PDO prepared statements, PHP sessions, CSRF tokens for forms, password hashing, and role-based permissions. This is a university prototype: review authentication, permissions, error handling and server configuration before deploying publicly. This repository does not include a hosted PHP/MySQL service; GitHub Pages cannot run PHP.

## Important setup reminder

**Never overwrite the downloaded `database.example.php` with local passwords.** Keep passwords only in the separate, locally created `database.php`, which is ignored by Git.
