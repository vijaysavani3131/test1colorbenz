# WhatsApp Business Platform — BasKarwaDo Production Setup

BasKarwaDo is **WhatsApp-first**, but WhatsApp and the website are not separate products. Both write to the same `case_records`, `case_answers`, `case_documents`, payments, AI report and admin operations workflow.

## What is already implemented

The Laravel backend currently includes:

- Meta webhook verification (`GET /api/webhooks/whatsapp`)
- signed webhook validation with `X-Hub-Signature-256`
- idempotent incoming message/status persistence
- queued `ProcessWhatsAppEvent`
- conversation sessions + full inbound/outbound message audit
- English / Hindi / Gujarati detection and manual `EN` / `HI` / `GU` switching
- Hinglish / Gujlish-friendly AI/fallback intent classification
- routing into all 7 BasKarwaDo services
- deterministic next-question workflow
- WhatsApp-originated `CaseRecord` creation
- customer `status` command
- customer `human` / `agent` handoff command
- image/PDF download from Meta media IDs
- private case document storage
- queued document extraction + case AI report
- outbound free-form text client
- outbound reply-button client
- outbound approved-template client
- delivery/read/failure status persistence

The webhook itself stays lightweight: verify → persist → queue → `200`. Business logic runs in the queue worker.

## 1. Meta assets you need

Create or confirm these in Meta Business / Developers:

1. Meta Business Portfolio
2. Meta Developer app
3. WhatsApp product on that app
4. WhatsApp Business Account (WABA)
5. A phone number dedicated to BasKarwaDo
6. Phone Number ID
7. Meta App Secret
8. Production access token / system-user token appropriate for your Meta setup

Do not put any of these secrets into React. They belong only on the Laravel server / secret manager.

## 2. Backend environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.in
FRONTEND_URL=https://yourdomain.in

QUEUE_CONNECTION=database
FILESYSTEM_DISK=private

WHATSAPP_VERIFY_TOKEN=<long-random-token-created-by-you>
WHATSAPP_ACCESS_TOKEN=<production-access-token>
WHATSAPP_PHONE_NUMBER_ID=<phone-number-id>
WHATSAPP_APP_SECRET=<meta-app-secret>
WHATSAPP_GRAPH_VERSION=v26.0
```

`WHATSAPP_GRAPH_VERSION` is configurable intentionally. Before each production upgrade, confirm Meta's current supported Graph API version and test in staging.

Frontend:

```env
VITE_API_URL=https://api.yourdomain.in/api
VITE_WHATSAPP_NUMBER=91XXXXXXXXXX
```

`VITE_WHATSAPP_NUMBER` is digits only, including country code.

## 3. Deploy public HTTPS webhook

Use:

```text
GET  https://api.yourdomain.in/api/webhooks/whatsapp
POST https://api.yourdomain.in/api/webhooks/whatsapp
```

In Meta webhook settings:

- Callback URL: the URL above
- Verify token: exactly the same value as `WHATSAPP_VERIFY_TOKEN`
- Subscribe to WhatsApp message events / statuses required by your WABA configuration

In production the backend rejects invalid webhook signatures using the Meta App Secret.

## 4. Run the queue worker

WhatsApp will not work correctly in production if webhook jobs are never processed.

For a first VPS launch:

```env
QUEUE_CONNECTION=database
```

Then:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Use Supervisor/systemd to keep the worker alive. See `docs/DEPLOYMENT.md` and `deploy/supervisor-worker.conf`.

For higher scale, move queues/cache to Redis without changing the case model.

## 5. First customer conversation

A customer can simply send:

```text
Hi
```

If the intent is not yet clear, BasKarwaDo replies with the 7 service doors:

```text
1. Paisa Wapas / Refund
2. Product / Warranty
3. PAN-Aadhaar / Document mismatch
4. Address / House shift
5. Marriage updates
6. New baby documents
7. Family / After-loss admin
```

The customer can also type a natural sentence such as:

```text
mara 4999 refund haju aavyo nathi
```

The classifier maps it to:

```text
service_slug = money_recovery
locale = gu
```

The case engine then asks the next required question instead of letting an LLM improvise the workflow.

## 6. Conversation state

Current session states include:

```text
new
awaiting_service
collecting
ready
human_handoff
```

Case workflow status is separate:

```text
intake
needs_info
ready_for_review
in_progress
waiting_customer
waiting_external
resolved
closed
```

This separation matters: a WhatsApp conversation can be idle while the actual case is actively being processed by staff.

## 7. Language behaviour

Customer-facing locales:

- English (`en`)
- Hindi (`hi`)
- Gujarati (`gu`)

The AI classifier also accepts mixed Hinglish / Gujlish. Original messages are preserved in the conversation audit. Structured case answers remain deterministic fields.

Customers can type at any time:

```text
EN
HI
GU
```

to switch the response language.

## 8. Documents and screenshots

Supported WhatsApp media in the current worker:

- images
- documents / PDF

Flow:

```text
Meta sends media ID
→ backend requests the media metadata URL
→ backend downloads with the WhatsApp access token
→ size/type checks
→ private storage under the case
→ SHA-256 hash
→ `case_documents`
→ queued AI extraction when configured
→ human verification in admin console
```

The application does **not** create public document URLs.

Never ask customers to send:

- UPI PIN
- ATM PIN
- card CVV
- netbanking password
- email password
- OTP

If an official process needs customer authentication, guide the customer to complete it directly on the official interface.

## 9. OpenAI in WhatsApp intake

Configure server-side only:

```env
OPENAI_API_KEY=...
OPENAI_MODEL=gpt-5-mini
```

The code uses the Responses API with `store=false` for BasKarwaDo case/document requests.

AI responsibilities:

- intent classification
- language detection
- document fact extraction
- concise case summary
- missing questions
- operational next-step draft

AI is **not** allowed to invent legal rights/deadlines or automatically make regulated/legal/financial conclusions. High-risk items stay human-reviewed.

If OpenAI is not configured or temporarily unavailable, the deterministic case workflow and keyword classifier continue to work.

## 10. Support window and templates

Replies to a customer who is actively messaging can be sent as free-form messages according to Meta's current customer-service-window rules.

For business-initiated messages outside the permitted window, create approved templates in WhatsApp Manager. Recommended template set:

```text
bkw_case_received
bkw_missing_information
bkw_case_status_changed
bkw_external_response
bkw_case_resolved
```

`WhatsAppCloudService::sendTemplate()` is already available; template names/languages/components should be configured after Meta approves the exact templates.

Do not hard-code unapproved template text into production automation.

## 11. Human handoff

The customer can type:

```text
human
agent
```

The worker then:

- sets the case to `ready_for_review`
- raises priority to `high`
- changes conversation state to `human_handoff`
- records an audit event
- confirms handoff to the customer

The admin console becomes the source of truth for the case.

## 12. WhatsApp Flows — optional enhancement

The current product is fully usable with conversational questions; WhatsApp Flows are not required for the MVP.

Add Flows later for data-heavy paths such as:

- airline refund metadata
- warranty/product metadata
- document-mismatch checklists
- address-change record selection

A Flow must write to the same Case API fields. Never create a second workflow/data model for Flows.

## 13. Production go-live checklist

Before connecting the live business number:

- [ ] production HTTPS domain deployed
- [ ] `APP_DEBUG=false`
- [ ] MySQL/PostgreSQL production DB backed up
- [ ] queue worker supervised
- [ ] private document storage backed up/encrypted at infrastructure level
- [ ] Meta App Secret configured
- [ ] production WhatsApp token configured
- [ ] webhook verified and signed webhook tested
- [ ] duplicate webhook delivery tested
- [ ] image and PDF intake tested
- [ ] Hindi / English / Gujarati path tested
- [ ] approved outbound templates created
- [ ] agent handoff tested
- [ ] privacy/retention policy reviewed for production
- [ ] legal/regulated partner boundaries reviewed
- [ ] alerting for failed queue jobs / webhook failures enabled

That is the WhatsApp production path for the code currently in this repository.
