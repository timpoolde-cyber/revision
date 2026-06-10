# R100-CRM API Documentation

## Overview

The R100-CRM system exposes two primary APIs:
- **`api.php`** — Authenticated admin/user API (requires session)
- **`public_api.php`** — Public lead capture API (no authentication)

All endpoints return JSON responses. Authenticated endpoints require valid session cookies.

---

## Public API (`public_api.php`)

### POST /public_api.php — Create Lead

**Action:** `diagnose`

Creates a new customer and project from public lead form.

**Parameters:**
```json
{
  "action": "diagnose",
  "name": "John Doe",
  "email": "john@example.com",
  "url": "https://example.com"
}
```

**Response (Success):**
```json
{
  "success": true
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Alle Felder sind erforderlich."
}
```

**Side Effects:**
- Creates `customers` record with name/email
- Creates `projects` record with URL and tunnel='anfrage'
- Creates `interactions` record (system event)
- Sends Telegram notification to chat (if configured)
- Sends email to r400@revision100.de

**Error Cases:**
- Missing required fields (name, email, url) → 400

---

## Authenticated API (`api.php`)

All endpoints require valid authentication via `check_auth()` (session_handler.php).

### GET /api.php?action=get_customers

Retrieves all customers.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer_name": "Acme Corp",
      "email": "contact@acme.com",
      "phone_mobile": "+49 123 456789",
      "address": "Main St 1",
      "city": "Berlin",
      "postal_code": "10115",
      "secret_token": "abc123...",
      "token_expires": "2026-07-10 12:34:56",
      "created_at": "2026-01-01 10:00:00"
    }
  ]
}
```

**Logging:** Logged as API_REQUEST event with duration_ms

---

### POST /api.php — Save Customer

**Action:** `save_customer`

Creates or updates a customer.

**Parameters:**
```json
{
  "action": "save_customer",
  "id": null,
  "customer_name": "Acme Corp",
  "email": "contact@acme.com",
  "phone": "+49 123 456789",
  "address": "Main St 1",
  "city": "Berlin",
  "postal_code": "10115",
  "latitude": "52.52",
  "longitude": "13.40"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "token": "abc123..."
  }
}
```

**Error Cases:**
- Database error → returns `{'success': false, 'error': 'Integritätsfehler.'}`

**Logging:** API_REQUEST with customer_id and request duration

---

### POST /api.php — Save Project

**Action:** `save`

Creates or updates a project.

**Parameters:**
```json
{
  "action": "save",
  "id": null,
  "customer_id": 1,
  "customer_name": "Acme Corp",
  "target_url": "https://example.com",
  "tunnel": "anfrage",
  "alert_level": "normal",
  "notiz": "Optional note"
}
```

**Response:**
```json
{
  "success": true,
  "id": 42
}
```

**Tunnel Values:**
- `anfrage` — Initial inquiry state
- `bewertet` — Quick report available
- `bereit` — Deep report ready
- `kontaktiert` — Contact initiated
- `abgeschlossen` — Project closed
- `abgeschaltet` — Deactivated

**Logging:** API_REQUEST with project_id, tunnel, and timing

---

### POST /api.php — Run PSI Analysis

**Action:** `run_psi_now`

Triggers PageSpeed Insights analysis for a project. Stores results in `psi_results` table.

**Parameters:**
```json
{
  "action": "run_psi_now",
  "project_id": 42
}
```

**Response:**
```json
{
  "success": true,
  "results": {
    "mobile": {
      "success": true,
      "score": 95
    },
    "desktop": {
      "success": true,
      "score": 97
    }
  }
}
```

**Error Cases:**
- Missing project_id → `{'error': 'Projekt ID erforderlich'}`
- Project not found → `{'error': 'Projekt nicht gefunden'}`
- API key not configured → `{'error': 'Google PSI API Key nicht konfiguriert'}`
- Fetch error → stored in psi_results.error_message, triggers Telegram alert

**Logging:** API_REQUEST with project_id, results_count, timing. Errors logged with context.

---

### POST /api.php — Generate Token

**Action:** `generate_token`

Generates a new customer token.

**Parameters:**
```json
{
  "action": "generate_token",
  "customer_id": 1,
  "expiration_days": 30
}
```

**Response:**
```json
{
  "success": true,
  "token": "new-token-value"
}
```

**Details:**
- Generates 64-char hex token via `generate_secret_token()`
- Sets token_expires = NOW + expiration_days
- Sets token_created_at = NOW

---

### POST /api.php — Renew Token

**Action:** `renew_token`

Extends existing token expiration.

**Parameters:**
```json
{
  "action": "renew_token",
  "customer_id": 1,
  "days": 30
}
```

**Response:**
```json
{
  "success": true,
  "token": "existing-token-value"
}
```

---

### POST /api.php — Send Token Email

**Action:** `send_token_email`

Emails a VIP portal link to customer.

**Parameters:**
```json
{
  "action": "send_token_email",
  "customer_id": 1,
  "message": "Optional message text"
}
```

**Response:**
```json
{
  "success": true
}
```

**Details:**
- Generates token if not exists
- Sends email with r400.de/vip/?token=xxx link
- Logs interaction

---

### POST /api.php — Send Custom Email

**Action:** `send_custom_email`

Sends custom email to project customer.

**Parameters:**
```json
{
  "action": "send_custom_email",
  "project_id": 42,
  "subject": "Update",
  "body": "Email content here"
}
```

**Response:**
```json
{
  "success": true
}
```

---

### POST /api.php — Save Project Contacts

**Action:** `save_project_data`

Updates project contact information.

**Parameters:**
```json
{
  "action": "save_project_data",
  "project_id": 42,
  "contacts": [
    {
      "id": "new_1",
      "name": "John Doe",
      "role": "CEO",
      "email": "john@example.com",
      "phone_mobile": "+49 123 456789"
    }
  ],
  "default_contact": "new_1"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Kontakte gespeichert"
}
```

---

## Client Update API (`api_client_update.php`)

### POST /api_client_update.php

Updates customer data via token-based authentication (no session required).

**Parameters:**
```json
{
  "token": "secret-token-abc123",
  "address": "New Address 1",
  "latitude": "52.52",
  "longitude": "13.40",
  "new_contacts": [
    {
      "name": "Jane Doe",
      "role": "Manager",
      "email": "jane@example.com",
      "phone_mobile": "+49 987 654321"
    }
  ]
}
```

**Response:**
```json
{
  "success": true
}
```

**Token Validation:**
- Token must exist in projects.secret_token
- Token expiration checked against token_expires
- Returns `{'success': false, 'error': 'Zugriff abgelaufen.'}` if expired

**Logging:** TOKEN_VALIDATION events logged for success/failure

---

## VIP Portal (`flow/vip/index.php`)

Public customer portal for viewing audit results. Accessed via token: `r400.de/vip/?token=xxx`

**Token Validation:**
- Must match customer token with valid expiration
- Dies with "Zugriff abgelaufen" if expired

**Logging:** TOKEN_VALIDATION events in system_logs

---

## Background Jobs

### worker_psi.php

Cron-triggered background worker that:
1. Finds projects with tunnel='anfrage'
2. Fetches Lighthouse scores (PageSpeed API)
3. Generates audit analysis (Anthropic API)
4. Stores results in psi_results table
5. Updates project tunnel='bewertet'

**Logging:** JOB_EXECUTION events with project count and timing

---

### cron_sms_r400.php

Cron-triggered job that sends SMS notifications:
1. Finds projects in bewertet/bereit states with phone numbers
2. Sends quick report SMS (bewertet→bereit)
3. Sends deep report SMS (bereit→kontaktiert)
4. Updates tunnel state after successful send

**Logging:** JOB_EXECUTION events with project count

---

## Error Handling

All API errors return JSON:
```json
{
  "success": false,
  "error": "Integritätsfehler."
}
```

Exception details are logged to system_logs (not exposed to client) for security.

---

## Database Schema

See `init_db.php` for complete schema. Key tables:

- **customers**: user contact information, tokens
- **projects**: website audit projects, states, scoring
- **psi_results**: PageSpeed API responses
- **interactions**: audit trail of events
- **project_contacts**: multiple stakeholders per project
- **system_logs**: monitoring and audit trail (Stufe 4)

---

## Monitoring

Use `logs_dashboard.php` to view real-time system events:
- Filter by level (INFO/WARN/ERROR)
- Filter by event type
- View request timing and context
- Track job executions

Access: `/logs_dashboard.php` (requires authentication)

