# BasKarwaDo single-domain deployment

Target domain: `https://baskarwado.isavgo.com`

This is the recommended configuration when the React frontend and Laravel backend must live on one hostname.

## Public URLs

- Website: `https://baskarwado.isavgo.com`
- Admin: `https://baskarwado.isavgo.com/admin`
- Case tracking: `https://baskarwado.isavgo.com/track`
- API base: `https://baskarwado.isavgo.com/api`
- WhatsApp webhook: `https://baskarwado.isavgo.com/api/webhooks/whatsapp`
- Razorpay webhook: `https://baskarwado.isavgo.com/api/webhooks/razorpay`
- Health check: `https://baskarwado.isavgo.com/up`

No `api.` subdomain is required.

## Directory layout

Recommended server paths:

```text
/var/www/baskrwado/
  frontend/
    dist/
  backend/
    public/
    storage/app/private/
```

Nginx serves `frontend/dist` for normal browser routes and internally sends only `/api/*` and `/up` to Laravel.

Use `deploy/nginx-single-domain.conf` as the production Nginx template.

## Frontend environment

Create `frontend/.env.production`:

```env
VITE_API_URL=/api
VITE_WHATSAPP_NUMBER=91XXXXXXXXXX
```

Because the API is same-origin, `/api` is preferred over an absolute URL.

Build:

```bash
cd /var/www/baskrwado/frontend
npm ci
npm run build
```

## Backend environment

Create `backend/.env` from `.env.example` and configure production values:

```env
APP_NAME=BasKarwaDo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://baskarwado.isavgo.com
FRONTEND_URL=https://baskarwado.isavgo.com
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=baskrwado
DB_USERNAME=baskrwado
DB_PASSWORD=CHANGE_ME

CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private

OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-mini

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

WHATSAPP_VERIFY_TOKEN=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_APP_SECRET=
WHATSAPP_GRAPH_VERSION=v26.0
```

Never commit the real `.env`.

## Backend install

```bash
cd /var/www/baskrwado/backend
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan baskrwado:admin owner@isavgo.com --name="Owner" --role=owner
```

## Queue worker

For production, keep `QUEUE_CONNECTION=database` (or Redis later) and run the included Supervisor worker config.

After deployments:

```bash
php artisan queue:restart
```

## Nginx

Copy:

```text
baskrwado/deploy/nginx-single-domain.conf
```

to your Nginx sites configuration, then verify paths and PHP-FPM socket.

Enable and test:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Use Certbot or your provider to issue TLS for only:

```text
baskarwado.isavgo.com
```

## Integration URLs

### Meta WhatsApp

Callback URL:

```text
https://baskarwado.isavgo.com/api/webhooks/whatsapp
```

Use the same value configured as `WHATSAPP_VERIFY_TOKEN` when Meta asks for the verify token.

### Razorpay

Webhook URL:

```text
https://baskarwado.isavgo.com/api/webhooks/razorpay
```

Set the webhook secret in `RAZORPAY_WEBHOOK_SECRET`.

## Final verification

Verify in this order:

1. `https://baskarwado.isavgo.com` loads the React app.
2. `https://baskarwado.isavgo.com/up` returns Laravel health response.
3. `https://baskarwado.isavgo.com/api/services` returns JSON.
4. Create a website case.
5. Upload evidence.
6. Login at `/admin` and review the case.
7. Complete a Razorpay test payment.
8. Verify WhatsApp webhook and send a test message.
9. Send a WhatsApp image/PDF and confirm it appears in the case.
10. Test `status` and human handoff.

Do not send paid traffic until all ten checks pass.
