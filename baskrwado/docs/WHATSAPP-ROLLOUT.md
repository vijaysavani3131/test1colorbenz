# WhatsApp Rollout — BasKarwaDo

The website should launch first with the shared Case API. WhatsApp then becomes a second customer interface over the same workflow engine.

## 1. Meta assets required

Create / confirm:

1. Meta Business Portfolio
2. Meta Developer app with the WhatsApp product
3. WhatsApp Business Account (WABA)
4. A business phone number for BasKarwaDo
5. Phone Number ID
6. WABA ID
7. App Secret
8. Long-lived/system-user access token for production

Use the latest supported Graph API version through `WHATSAPP_GRAPH_VERSION`; do not hard-code it in application logic.

Typical permissions needed for production management/messaging are `whatsapp_business_messaging` and `whatsapp_business_management`, subject to Meta's current setup and review requirements.

## 2. Configure backend secrets

Never commit real values. Put them in the production secret manager / `.env`:

```env
WHATSAPP_VERIFY_TOKEN=<random-secret-you-create>
WHATSAPP_ACCESS_TOKEN=<production-system-user-token>
WHATSAPP_PHONE_NUMBER_ID=<phone-number-id>
WHATSAPP_APP_SECRET=<meta-app-secret>
WHATSAPP_GRAPH_VERSION=v26.0
```

Rotate tokens/secrets through operations procedures; never expose them to React.

## 3. Deploy public HTTPS webhook

Backend endpoints already exist:

```text
GET  /api/webhooks/whatsapp   # verification challenge
POST /api/webhooks/whatsapp   # incoming messages/statuses
```

Example callback URL:

```text
https://api.baskrwado.in/api/webhooks/whatsapp
```

The callback must be publicly reachable over HTTPS. Configure the same verify token in Meta and the backend environment.

The backend validates `X-Hub-Signature-256` with the Meta App Secret in production and stores inbound message/status events idempotently.

## 4. Subscribe the WABA

Subscribe the WhatsApp Business Account to the app's webhooks. A WABA subscription is needed for the app to receive message events for its phone numbers.

Start with message-related notifications. Store delivery/read/failure statuses because they matter for support quality and debugging.

## 5. Conversation processing layer

Do not implement business logic directly inside the webhook request.

Recommended flow:

```text
Meta webhook
 -> validate signature
 -> save WhatsAppEvent (idempotent)
 -> HTTP 200 immediately
 -> queued ProcessWhatsAppEvent job
 -> identify session/customer
 -> detect language + intent
 -> map to CaseRecord
 -> ask next workflow question
 -> save normalized answer
 -> refresh readiness report
 -> send reply through WhatsAppCloudService
```

Production should run this processor on Redis/database queues with retries and dead-letter/failed-job monitoring.

## 6. Language behaviour

First interaction:

```text
Namaste 👋
Choose your language:
1. English
2. हिंदी
3. ગુજરાતી
```

After selection, preserve:
- original customer text
- detected/selected locale
- normalized internal English field value where useful

Support mixed writing naturally: Hinglish and Gujlish are common and should not force a language switch.

## 7. First WhatsApp services

Do not launch all seven flows on day one.

Start with:

1. Paisa Wapas
2. After-Sales & Warranty
3. Identity Repair

Each begins conversationally, then asks deterministic required questions from the workflow definition.

Example Paisa Wapas:

```text
Customer: Flipkart ka 4999 refund nahi aaya
System extracts: merchant=Flipkart, amount=4999, intent=money_recovery
Bot: Refund kab se pending hai?
Customer: 14 din
Bot: Order / refund screenshot bhej dijiye.
...
Bot: Case BKW-XXXX ready hai. Team review karegi.
```

## 8. WhatsApp Flows

Use conversational chat for discovery and clarification. Use WhatsApp Flows only where structured collection is faster/cleaner, for example:

- travel refund details
- product/warranty metadata
- identity-mismatch record selection
- move/address checklist

A Flow should submit into the same Case API fields; it must not create a second data model.

## 9. Media/documents

When customers send images/PDFs:

1. receive the media reference in webhook data
2. download server-side through the Cloud API
3. malware/type/size check
4. store in private encrypted object storage
5. attach metadata to the case
6. run OCR/classification asynchronously
7. delete according to retention policy

Never use a publicly addressable `/uploads` directory for identity/bank/insurance documents.

## 10. Templates and support window

Within WhatsApp's customer support window, free-form replies can be used according to current platform rules. For business-initiated messages outside the permitted window, use approved message templates. Build templates for:

- case received
- missing information reminder
- case status changed
- external response received
- resolved / confirm outcome
- consented follow-up

Re-check Meta's current template, pricing and messaging rules before launch; these policies can change independently of the codebase.

## 11. Human handoff

Conversation state should support:

```text
BOT_INTAKE
WAITING_CUSTOMER
READY_FOR_AGENT
AGENT_ACTIVE
WAITING_EXTERNAL
RESOLVED
```

When `AGENT_ACTIVE`, automated conversational replies should pause except explicit system/status messages. The admin panel becomes the agent source of truth.

## 12. What must be added before production WhatsApp launch

- `whatsapp_sessions` model/table
- queued `ProcessWhatsAppEvent` job
- media download + private document model
- language/intent extraction service
- deterministic next-question resolver
- agent send/reply endpoint
- approved template sender
- consent + opt-out handling
- message/event audit trail
- rate limiting and abuse controls
- observability: webhook failures, queue depth, outbound errors

The existing adapter intentionally stops before these pieces rather than pretending an untested chatbot is production-ready.
