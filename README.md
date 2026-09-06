# Website starter

Simple static site for editing in VS Code and uploading to cPanel (`public_html`).

## Files

- `index.html` — homepage
- `about.html`
- `services.html`
- `contact.html`
- `css/style.css`
- `js/main.js`

Put images in an `images` folder and link them like:

```html
<img src="images/photo.jpg" alt="Description of the photo">
```

## Local preview

In VS Code, install the Live Server extension, then right-click `index.html` → Open with Live Server.

## Upload to Tasjeel

Upload the contents of this folder into `public_html`. Keep `index.html` in the root of `public_html`, not inside another folder.

## Mailing list database

The homepage signup stores subscribers in MySQL through `contact.php`.

1. In Tasjeel cPanel, create a MySQL database and database user, then grant the user all privileges on the database.
2. Open phpMyAdmin and run `database/schema.sql` in the new database.
3. Copy `config.example.php` to `config.php` and enter the cPanel database name, user, and password. Keep `config.php` private; it is excluded by `.gitignore`.
4. Upload `config.php` to the same directory as `contact.php` on Tasjeel.

The subscriber table stores the email address, signup source, and signup timestamp. Duplicate email addresses are handled safely.
