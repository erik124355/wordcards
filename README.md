# WordCards

A Quizlet-style web app for learning vocabulary. Users create word packs in any two languages and practice them with flashcards, written answers and multiple choice. Built with plain **PHP**, **HTML** and **CSS** on top of **MySQL**, with no frameworks and no build step.

The interface is available in **Finnish (default)** and **English**.

## Features

**Packs**
- Anyone can browse, search and study packs without an account.
- Search by title or description, or filter by tag (e.g. `spanish`, `high school`).
- Each pack has a title, a description, two languages and tags.
- Several accepted answers can be written in one cell, separated by `/` (e.g. `house / building`).

**Accounts**
- Sign up with only a nickname and a password. No email and no phone number.
- Logged-in users can create, edit and delete their own packs. Nobody can edit another user's pack.

**Editor**
- Language fields with suggestions and autocorrect (typing `swedsh` becomes Swedish, and `ruot` becomes Ruotsi in the Finnish interface).
- Card placeholders follow the chosen languages live.
- Add and remove cards one at a time, or use the **Bulk paste** button (one pair per line, separated by a tab, `;` or ` - `).
- The browser warns you if you try to leave the page with unsaved changes.

**Study modes**
- **Flashcards:** click the card to flip it (3D flip animation), with previous and next buttons.
- **Write:** type the answer.
  - Capitalization and extra spaces are ignored.
  - Small typos or missing accents count as *almost correct* and the right spelling is shown.
  - Wrong answers show which letters were wrong.
  - Buttons for ä, ö, å, é and other special characters.
- **Multiple choice:** pick the correct answer from four options (number keys 1 to 4 work).
- Switch which language is shown and which one you answer in with one button.
- Shuffle on or off, and a "practice missed words" round at the end.
- Answer submissions and flashcard previous/next actions use AJAX to update only the exercise area, avoiding a full page reload. Study progress remains managed by PHP in the session; if JavaScript is unavailable, the forms and links still work with regular page loads.

## Requirements

- PHP 7.4 or newer (8.x recommended) with the `pdo_mysql` and `mbstring` extensions
- MySQL or MariaDB (phpMyAdmin is fine for managing it)
- Apache with `.htaccess` support (recommended) or any server that can run PHP

## Setup

1. **Create the tables.** Open `schema.sql`, check that the first line (`USE ...`) names your database, then import it in phpMyAdmin or run it in the MySQL console.
2. **Create a `.env` file** in the project root:

   ```
   DB_HOST=localhost
   DB_NAME=your_database
   DB_USER=your_user
   DB_PASS=your_password
   ```

   All four keys are required. The `.env` file is blocked from web access by `.htaccess` and excluded from git by `.gitignore`. Never commit it.
3. **Copy the project** into your web root (for example `htdocs/wordcards` in XAMPP) and open it in the browser.
4. Click **Rekisteröidy / Sign up**, create an account and make your first pack.

## Project structure

| File | Purpose |
|---|---|
| `config.php` | Reads `.env`, sessions, database connection, CSRF protection, answer checking, language list and translations |
| `header.php` | Page layout, navigation and the FI/EN switch |
| `index.php` | Browse, search and "My packs" |
| `auth.php` | Sign up, log in and log out |
| `edit.php` | Create, edit and delete packs |
| `study.php` | Flashcards, write mode and multiple choice |
| `style.css` | All styling, including dark mode and the flip animation |
| `schema.sql` | Database tables |

## Database

Three tables, all prefixed with `words_`: `words_users`, `words_lists` and `words_words`. Deleting a user deletes their packs, and deleting a pack deletes its cards.

## Customizing

- **Add a UI language or change a text:** translations are in `fi_dict()` in `config.php`. Every text in the pages goes through `t('English text')`.
- **Change the language list:** edit `language_list()` and `lang_fi()` in `config.php`. The database always stores the English name, and the site shows it in the interface language.
- **Change how strict the "almost correct" check is:** edit `grade()` in `config.php`.
- **Colors and theme:** change the CSS variables at the top of `style.css`.

## Security notes

- Passwords are stored with `password_hash()` (bcrypt) and nothing else is collected about users.
- Every form uses a CSRF token, and database queries use prepared statements.
- All output is HTML-escaped.
- Use HTTPS when hosting the site for other people.
