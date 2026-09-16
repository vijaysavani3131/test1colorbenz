# BasKarwaDo

**Problem WhatsApp karo. Aage hum sambhalenge.**

BasKarwaDo is a WhatsApp-first life-admin and recovery platform for India. The same backend powers a responsive React website, a future WhatsApp Business Platform integration, and an internal operations dashboard.

## Product surfaces

- **Customer website (React 19 + Vite)** — discover services, start a case online, upload/describe the problem, and track a case.
- **API + workflow backend (Laravel 13 / PHP 8.3+)** — service catalogue, case intake, structured answers, rule-based readiness report, admin case queue, audit-friendly status updates.
- **WhatsApp adapter** — webhook-ready endpoints so the official WhatsApp Cloud API can use the same case engine later.
- **Admin operations** — triage, AI-ready summaries, missing-information checks, status changes, and human handoff.

## Launch services

1. Paisa Wapas — refund / money recovery assistance
2. After-Sales & Warranty — repair, warranty, replacement and service follow-up
3. Identity Repair — PAN/Aadhaar/bank/document mismatch assistance
4. Move My Life — address-change administration after moving
5. Marriage Sync — post-marriage document/nominee/address updates
6. New Baby Setup — document and benefit checklist for a newborn
7. After-Loss Admin — structured family administration after a death (human-review first)

## Repository layout

```text
baskrwado/
  frontend/    React customer + admin web UI
  backend/     Laravel JSON API + workflow engine + WhatsApp webhook adapter
  docs/        architecture and WhatsApp rollout notes
```

## Frontend

```bash
cd baskrwado/frontend
cp .env.example .env
npm install
npm run dev
```

Default API URL: `http://localhost:8000/api`.

## Backend

The `backend` directory contains the BasKarwaDo Laravel application layer and a Laravel 13-compatible skeleton. Requirements: PHP 8.3+, Composer, SQLite/MySQL/PostgreSQL.

```bash
cd baskrwado/backend
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

For production use a real queue/cache/database stack and private object storage for sensitive documents.

## Security rules

- Never collect bank passwords, UPI PINs, ATM PINs, email passwords or net-banking credentials.
- Avoid collecting OTPs in ordinary chat. Where a process requires customer authentication, the customer completes it directly on the official interface.
- Store only documents required for the case, encrypt at rest, limit staff access, keep audit logs, and define deletion/retention windows.
- AI can classify, summarize and draft; regulated/legal/financial conclusions and sensitive case actions require human review.

## WhatsApp architecture

The website and WhatsApp are **not separate products**. Incoming WhatsApp messages will be mapped to the same `cases`, `case_answers`, and report workflow used by the website. See `docs/WHATSAPP-ROLLOUT.md` once the backend is running.
