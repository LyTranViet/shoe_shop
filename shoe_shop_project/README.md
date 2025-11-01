# ShoeStoreDemo (minimal PHP + SQLite demo)

This workspace contains a minimal demo e-commerce site (ShoeStoreDemo) organized into public pages, admin pages, includes, assets, and a simple SQLite database.

Quick start (Windows PowerShell):

1. Run the migration to create the SQLite DB and seed demo data:

```powershell
php migrate.php
```

2. Start the built-in PHP server from the project root:

```powershell
php -S localhost:8000
```

3. Open http://localhost:8000 in your browser.

Notes:
- This is a tiny demo for local development only. It uses SQLite and stores the DB at `data/shoestore.db`.
- Authentication and admin controls are intentionally minimal.
