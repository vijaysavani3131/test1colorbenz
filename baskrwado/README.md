# BasKarwaDo

**Problem WhatsApp karo. Aage hum organise karenge.**

BasKarwaDo is a WhatsApp-first India life-admin, after-sales and money-recovery assistance platform. A React customer website and the official WhatsApp Business Platform feed the **same Laravel case engine**, private evidence vault, payment records, AI-assisted reports and human operations console.

## What is implemented

### Customer experience — React 19 / Vite

- premium responsive desktop/tablet/mobile UI
- English / Hindi / Gujarati
- 7 problem-led service entry points
- guided online case intake
- service-specific questions returned by the backend
- secure PDF/image evidence upload
- customer consent / credential warnings
- case tracking with case ID + matching mobile number
- customer-visible staff updates
- AI-assisted report summary/next-step display
- Razorpay checkout integration
- direct WhatsApp CTA / continue-on-WhatsApp path
- privacy, security and service-terms product drafts

### WhatsApp Business Platform — Laravel

- Meta webhook verification
- `X-Hub-Signature-256` validation
- idempotent incoming message/status storage
- queued message processor
- conversation sessions and message audit
- English / Hindi / Gujarati + Hinglish/Gujlish intent handling
- all 7 service routes
- deterministic next-question engine
- WhatsApp case creation
- customer `status` command
- customer `human` / `agent` handoff
- WhatsApp image/PDF media download
- private evidence storage
- delivery/read status tracking
- text, button and template send helpers

### Human operations console

- real staff login with expiring bearer tokens
- owner/admin/agent/reviewer roles
- queue metrics
- search + status/priority filters
- case assignment
- priority + workflow status control
- service fee control
- structured intake viewer
- AI intake report viewer
- private document download / verify / reject
- internal notes
- customer-visible notes
- audit timeline
- payment status

### AI assistance — OpenAI Responses API

- multilingual intent classification
- document fact extraction
- structured case report
- missing-question suggestions
- warnings/risk classification
- `store=false` on BasKarwaDo Responses API requests
- deterministic fallback remains available if OpenAI is disabled/unavailable

AI does **not** own legal/regulatory/financial decisions. Sensitive actions remain human-reviewed.

### Payments — Razorpay

- server-created orders
- checkout signature verification
- webhook HMAC verification
- idempotent webhook event storage
- payment status attached to the case

## Launch services

1. **Paisa Wapas** — refunds, cancelled bookings, failed paid services, money stuck
2. **After-Sales & Warranty** — repair, warranty, replacement and service follow-up
3. **Identity Repair** — PAN/Aadhaar/bank/document mismatch assistance
4. **Move My Life** — address-change administration after moving
5. **Marriage Sync** — post-marriage name/address/nominee record updates
6. **New Baby Setup** — newborn document/benefit checklist
7. **After-Loss Admin** — family administration after a death; human-review first

## Repository layout

```text
baskrwado/
  frontend/                  React customer UI + operations console
  backend/                   Laravel API, workflows, queues and integrations
  docs/
    PRODUCT-ARCHITECTURE.md
    WHATSAPP-ROLLOUT.md
    DEPLOYMENT.md
  deploy/
    nginx-api.conf
    nginx-web.conf
    supervisor-worker.conf
```

Frontend and backend dependency lockfiles are committed so CI and production installs use the same tested dependency graph.

## Local development

### Backend

Requirements: PHP 8.3+, Composer, SQLite/MySQL/PostgreSQL.

```bash
cd baskrwado/backend
cp .env.example .env
composer install
php artisan key:generate
mkdir -p database storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch database/database.sqlite
php artisan migrate
php artisan baskrwado:admin owner@example.com --name="Owner" --role=owner
php artisan serve
```

For local queue development `.env.example` uses:

```env
QUEUE_CONNECTION=sync
```

For production use `database` or Redis and a supervised worker.

### Frontend

```bash
cd baskrwado/frontend
cp .env.example .env
npm ci
npm run dev
```

Example frontend environment:

```env
VITE_API_URL=http://localhost:8000/api
VITE_WHATSAPP_NUMBER=919876543210
```

## Integration environment

All secrets are backend-only.

```env
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

Do not commit production values.

## Important URLs

Local frontend:

```text
http://localhost:5173
http://localhost:5173/start
http://localhost:5173/track
http://localhost:5173/admin
```

Local backend:

```text
http://localhost:8000/up
http://localhost:8000/api/services
http://localhost:8000/api/webhooks/whatsapp
http://localhost:8000/api/webhooks/razorpay
```

## Tests / CI

GitHub Actions runs:

- locked React dependency install + production build
- locked Composer validation/install
- Laravel fresh SQLite migrations
- Laravel route boot
- PHP syntax checks
- backend unit + feature test suite

Locally:

```bash
cd baskrwado/backend
php artisan test

cd ../frontend
npm ci
npm run build
```

## Security rules

- never collect bank passwords, UPI PIN, ATM PIN, CVV, email passwords or netbanking credentials
- do not collect OTPs in ordinary chat; customer-authenticated actions stay with the customer where required
- documents are stored on the private filesystem disk, not a public uploads directory
- customer case modifications require the matching phone number
- staff use bearer-token authentication; no browser-stored admin password
- Meta and Razorpay webhook signatures are verified
- webhook processing is idempotent
- document/case AI calls are server-side only
- sensitive/legal/financial/high-risk actions require human review
- production must define retention/deletion policy for identity and financial evidence

## Production setup

Read in this order:

1. `docs/DEPLOYMENT.md`
2. `docs/WHATSAPP-ROLLOUT.md`
3. `docs/PRODUCT-ARCHITECTURE.md`

The included Nginx and Supervisor files are deployment templates, not a substitute for configuring your real domain/certificates/database/backup policy.

## Product principle

The website and WhatsApp are **two interfaces to one case operating system**.

Do not fork WhatsApp into a separate chatbot database. Do not let AI improvise mandatory workflows. Do not allow a customer-support channel to become an uncontrolled legal/financial agent.
