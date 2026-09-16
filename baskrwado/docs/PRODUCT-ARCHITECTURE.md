# BasKarwaDo Product Architecture

## Core thesis

One backend, many entry doors. Customers should never need to understand which department, portal, regulator or workflow applies before starting. They state the problem; BasKarwaDo converts it into a structured case.

## Customer surfaces

### WhatsApp — primary
Best for fast, low-friction conversations, screenshots, invoices, photos, short documents and status notifications.

### Website — secondary / self-service
Best for Google/Search traffic, desktop users, longer document sets and customers who prefer forms.

Both surfaces call the same Case API and create the same `CaseRecord` + `CaseAnswer` structure.

## Launch entry doors

| Service | Initial customer promise | Human review level |
| --- | --- | --- |
| Paisa Wapas | Help organise and escalate a stuck refund / failed paid service | Amber |
| After-Sales & Warranty | Organise repair, warranty, replacement or seller/service follow-up | Amber |
| Identity Repair | Find and organise name/DOB/detail mismatches across records | Amber |
| Move My Life | Build and execute an address-change task map | Amber |
| Marriage Sync | Build a post-marriage record/nominee/address update plan | Amber |
| New Baby Setup | Create a newborn paperwork/benefit roadmap | Amber |
| After-Loss Admin | Organise family administration after a death | Red / human-first |

## Case lifecycle

```text
intake
  -> needs_info
  -> ready_for_review
  -> in_progress
  -> waiting_external
  -> resolved
  -> closed
```

Statuses should represent operational state, not optimistic promises.

## Three automation levels

### GREEN — safe to automate
- Language detection
- Intent/service classification
- Structured questions
- OCR / field extraction
- Missing-field detection
- Case readiness score
- Status notifications
- Internal summaries
- Reminder scheduling

### AMBER — AI drafts, human approves
- Merchant/company escalation drafts
- Complaint drafts
- Document checklists
- Suggested next process
- Interpretation of policy text
- Recommendations that could materially affect a case

### RED — human or qualified partner only
- Legal representation
- Regulated financial/investment/tax decisions
- Complex deceased/estate conclusions
- Actions needing a signature, OTP, biometric or personal authentication
- Any action with irreversible financial/legal consequences

## Security rules

1. Never request or store UPI PIN, ATM PIN, net-banking password, email password or card PIN.
2. Do not use ordinary chat to collect OTPs. Guide the customer to authenticate on the official interface.
3. Use least-privilege document access. Agents should only see files needed for assigned cases.
4. Store sensitive files in private encrypted object storage, not a public web directory.
5. Add audit logs before staff scale.
6. Add explicit retention/deletion rules per case type.
7. Keep original customer text and a separately generated internal translation; never overwrite the original.

## AI contract

The AI layer is not the source of truth for law, policy or eligibility. It may:
- detect language (English, Hindi, Gujarati, Hinglish, Gujlish initially)
- extract facts from customer messages/documents
- map free text into workflow fields
- ask only the next relevant question
- translate agent messages
- create internal case summaries
- draft communications for review

The workflow engine remains deterministic about required fields, status transitions and human-review gates.

## Admin dashboard — MVP

- Case queue, status, readiness, service and language
- Case detail: original messages/answers, documents, internal summary, missing fields
- Assign owner/team
- Ask customer for information
- Generate a draft
- Mark external submission / response
- Resolve/close with outcome and recovered/saved amount where relevant

## Metrics that matter

- Paid case conversion rate
- Cost per paid case
- Resolution rate by service
- Median days to resolution
- Human minutes per resolved case
- Average revenue per case
- Contribution margin per case
- Repeat household usage
- Document-complete rate after first intake
- Escalation success by company/process

The long-term moat is not “we use AI”. It is the resolution knowledge graph: which facts, documents, sequence and escalation path reliably resolve each recurring Indian life-admin problem.
