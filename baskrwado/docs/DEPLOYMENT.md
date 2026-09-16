# BasKarwaDo Production Deployment Runbook

This runbook assumes a conventional India-region VPS/cloud deployment:

- `yourdomain.in` → React static frontend
- `api.yourdomain.in` → Laravel/PHP-FPM backend
- MySQL or PostgreSQL
- Nginx
- database queue for first launch; Redis later
- Supervisor for queue workers

The same architecture also works with a managed frontend host plus a Laravel host.

## 1. Server requirements

Backend:

- PHP 8.3+
- PHP extensions: curl, mbstring, openssl, pdo, tokenizer, xml, fileinfo, sodium; plus your DB driver
- Composer 2
- Nginx
- MySQL 8+ or PostgreSQL 15+
- Supervisor (or systemd) for `queue:work`

Frontend build:

- Node.js 22+
- npm

Use HTTPS everywhere in production.

## 2. Backend deploy

Example location:

```text
/var/www/baskrwado/backend
```

Install:

```bash
cd /var/www/baskrwado/backend
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
cp .env.example .env
php artisan key:generate
```

Configure `.env` with production DB, domains and integrations. Never commit `.env`.

Recommended baseline:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.in
FRONTEND_URL=https://yourdomain.in
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=baskrwado
DB_USERNAME=baskrwado
DB_PASSWORD=<strong-password>

CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private
```

Then:

```bash
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first owner:

```bash
php artisan baskrwado:admin owner@yourdomain.in --name="Owner" --role=owner
```

The command securely prompts for a password if `--password` is omitted.

## 3. Frontend deploy

```bash
cd /var/www/baskrwado/frontend
cp .env.example .env.production
```

Set:

```env
VITE_API_URL=https://api.yourdomain.in/api
VITE_WHATSAPP_NUMBER=91XXXXXXXXXX
```

Build:

```bash
npm install --no-audit --no-fund
npm run build
```

A lockfile is not currently committed, so do not use `npm ci` until the project adopts and commits one. Serve the generated `dist/` directory through Nginx. The example config in `deploy/nginx-web.conf` includes SPA fallback to `index.html`.

## 4. Backend Nginx / PHP-FPM

Copy/adapt `deploy/nginx-api.conf`.

Critical points:

- document root must be Laravel `public/`, not the project root
- block hidden files
- never alias `storage/app/private` publicly
- HTTPS required for Meta/Razorpay webhooks
- allow enough request size for the product's upload limit (`client_max_body_size 10m` is adequate for current 8 MB upload validation)

## 5. Queue worker

Copy/adapt `deploy/supervisor-worker.conf`:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start baskrwado-worker:*
```

After each deploy:

```bash
php artisan queue:restart
```

Monitor:

```bash
php artisan queue:failed
```

Retry only after fixing the underlying issue:

```bash
php artisan queue:retry all
```

## 6. OpenAI

```env
OPENAI_API_KEY=<server-side-secret>
OPENAI_MODEL=gpt-5-mini
```

The application continues to accept/manage cases without OpenAI. AI failures must not block a human case workflow.

## 7. Razorpay

```env
RAZORPAY_KEY_ID=rzp_live_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
```

Razorpay webhook URL:

```text
https://api.yourdomain.in/api/webhooks/razorpay
```

Subscribe to the events used by the backend, including paid/captured events appropriate to your Razorpay account configuration.

Test the complete flow with Razorpay test keys before switching to live keys.

## 8. WhatsApp / Meta

See `docs/WHATSAPP-ROLLOUT.md`.

Webhook URL:

```text
https://api.yourdomain.in/api/webhooks/whatsapp
```

Ensure the queue worker is running before you connect a live number.

## 9. Backups

Minimum production policy:

- database: automated daily backup + point-in-time recovery where available
- private documents: encrypted storage backup matching your retention policy
- `.env` / secrets: secret manager or protected server backup, never source control
- test restore procedure, not only backup creation

Sensitive evidence should have deletion/retention rules per service category. Do not retain identity/banking evidence indefinitely by default.

## 10. Logging and alerts

Alert on:

- 5xx API error spikes
- failed jobs
- WhatsApp webhook signature failures
- repeated outbound WhatsApp failures
- Razorpay webhook verification failures
- disk/object-storage capacity
- DB connection saturation
- queue age/depth

Application logs are in `storage/logs` unless you configure another channel.

## 11. Deploy sequence

Safe application deploy:

```bash
# backend
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart

# frontend
npm install --no-audit --no-fund
npm run build
```

If you use a symlink/release-based deployment, run migrations after new code is present and before switching workers to the new release.

## 12. Production verification

Verify from outside the server/network:

```text
GET  /up                                      -> 200
GET  /api/services                            -> 200
POST /api/cases                               -> 201 with valid payload
GET  /api/webhooks/whatsapp?hub...            -> Meta verification works
POST /api/webhooks/razorpay                    -> invalid signature is rejected
POST /api/admin/auth/login                     -> valid owner logs in
```

Then test end-to-end:

1. website case
2. evidence upload
3. AI report (if enabled)
4. admin review
5. Razorpay test payment
6. WhatsApp test-number case
7. WhatsApp image/PDF
8. human handoff
9. customer tracking page
10. resolved status

Do not switch ad traffic to production until all ten paths have been exercised in staging/test mode.
