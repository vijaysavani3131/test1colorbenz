# BasKarwaDo cPanel Same-Domain Deployment

Target domain: `https://baskarwado.isavgo.com`

This layout is for shared cPanel/Apache/LiteSpeed hosting where Node.js is not available in Terminal.

## Final public document root

The domain document root should contain:

```text
index.html
assets/
.htaccess
api.php
backend/
frontend/   # optional source copy; direct web access is blocked
```

The CI-generated `BasKarwaDo-cPanel-Frontend-Ready.zip` contains the production React `index.html`, `assets/`, `.htaccess`, `api.php`, and this guide. Extract its contents directly into the domain document root:

```text
/home/<cpanel-user>/public_html/baskarwado.isavgo.com/
```

Do not extract the ZIP into an additional nested folder.

## Laravel backend

The existing backend should remain at:

```text
/home/<cpanel-user>/public_html/baskarwado.isavgo.com/backend
```

`api.php` internally loads `backend/public/index.php`. The root `.htaccess` blocks direct browser requests to `backend/`, `frontend/`, `deploy/`, and `docs/`.

## PHP extensions

For normal Laravel operation enable the standard PHP extensions required by Composer/Laravel. For BasKarwaDo evidence-image optimisation, enable **GD** in cPanel's PHP extension selector.

Verify from Terminal:

```bash
php -m | grep -i '^gd$'
```

If `gd` is printed, upload compression is active. If GD is unavailable, uploads still work and the backend safely keeps the original image instead of failing the case.

The image optimiser targets roughly 300 KB for JPG/PNG/WebP evidence while preserving a quality/readability floor. The byte target is best-effort: when reaching 300 KB would make evidence unreadable, the safest smaller copy is retained instead. PDFs are not lossy-compressed.

## Backend .env

Use one domain for both the website and API:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://baskarwado.isavgo.com
FRONTEND_URL=https://baskarwado.isavgo.com
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_DATABASE_NAME
DB_USERNAME=YOUR_DATABASE_USERNAME
DB_PASSWORD="YOUR_DATABASE_PASSWORD"

CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=private
```

Keep all real secrets only in `backend/.env`.

## Backend commands

```bash
cd /home/<cpanel-user>/public_html/baskarwado.isavgo.com/backend
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first owner if not already created:

```bash
php artisan baskrwado:admin owner@example.com --name="Owner" --role=owner
```

## URLs to verify

```text
https://baskarwado.isavgo.com/
https://baskarwado.isavgo.com/admin
https://baskarwado.isavgo.com/track
https://baskarwado.isavgo.com/up
https://baskarwado.isavgo.com/api/services
```

Expected:

- `/` loads the React site.
- `/admin` loads the React admin UI.
- `/track` loads the React tracking UI.
- `/up` returns the Laravel health response.
- `/api/services` returns JSON from Laravel.
- Direct requests to `/backend/...` and `/frontend/...` are denied.

## Webhooks on the same domain

WhatsApp:

```text
https://baskarwado.isavgo.com/api/webhooks/whatsapp
```

Razorpay:

```text
https://baskarwado.isavgo.com/api/webhooks/razorpay
```

## Important

The frontend is already built with `VITE_API_URL=/api`, so Node.js is not required on the cPanel server. Rebuild the frontend through CI whenever frontend code or `VITE_*` build-time values change.
