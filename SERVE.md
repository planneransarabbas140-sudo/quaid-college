# How to serve this PHP site

Recommended: Use XAMPP / Apache

- Place the project folder inside your active XAMPP `htdocs` directory (for example `C:\xampp\htdocs\quaid-college-system-main`).
- Start Apache (and MySQL if needed) from the XAMPP Control Panel.
- Open in your browser: `http://localhost/quaid-college-system-main/`

Quick alternative: PHP built-in server

1. Ensure `php` is available on your PATH (run `php -v`).
2. From the project root run:

```powershell
php -S 127.0.0.1:8000 -t .
```

Then open `http://127.0.0.1:8000/` in your browser.

Quick helper (Windows)

- Double-click `start-server.bat` in the project root. It will run the command above and keep the window open.

Notes
- Do not use Live Server (127.0.0.1:5500) — it serves static files only and shows directory listings for PHP projects.
- If you use XAMPP but the site appears under `xampp_old`, ensure you move/copy the project into your active XAMPP installation's `htdocs` (commonly `C:\xampp\htdocs`).