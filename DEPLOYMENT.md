# R100-CRM Deployment Runbook

## Pre-Deployment Checklist

- [ ] All changes committed to git
- [ ] `.env` file configured (not in git)
- [ ] `.htaccess` properly deployed
- [ ] Database backup taken
- [ ] All tests passing: `php tests/integration_tests.php`
- [ ] Monitoring dashboard accessible: `/logs_dashboard.php`

---

## Initial Deployment (Fresh Installation)

### 1. Upload Files to Server

Upload all files from this repository to your hosting, except:
- `.env` (create separately)
- `.git` directory (optional)
- `tests/` directory (optional, for local testing)

Required directories:
```
/                  # Root
├── api.php
├── api_customers.php
├── api_client_update.php
├── public_api.php
├── update.php
├── index.php
├── auth.php
├── init_db.php
├── Logger.php
├── logs_dashboard.php
├── session_handler.php
├── functions.php
├── .htaccess
├── data/           # Must be writable by web server (755 or 777)
├── flow/
│   ├── vip/
│   │   └── index.php (VIP portal)
│   │   └── worker_psi.php (background worker)
│   └── cron/
│       └── cron_sms_r400.php (SMS cron job)
└── views/          # Template files
```

### 2. Create `.env` File

Create `.env` in the root directory with the following variables:

```env
APP_ENV=production

# API Keys
GOOGLE_MAPS_KEY=your_google_maps_key_here
LIGHTHOUSE_KEY=your_google_lighthouse_key_here
GOOGLE_PSI_API_KEY=your_google_psi_key_here
PAGESPEED_API_KEY=your_pagespeed_key_here

# Messaging
TELEGRAM_TOKEN=your_telegram_bot_token
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_CHAT_ID=your_chat_id
SIPGATE_API_TOKEN=your_sipgate_api_token
SIPGATE_API_TOKEN_ID=your_sipgate_token_id

# Email
ADMIN_EMAIL=info@revision100.de
SMTP_HOST=send.one.com
SMTP_USER=system@revision100.de
SMTP_PASS=your_smtp_password
SMTP_PORT=587

# LLM (for worker_psi.php)
ANTHROPIC_API_KEY=your_anthropic_key_here
ANTHROPIC_MODEL=claude-sonnet-4-6
```

**Security:**
- Do NOT commit `.env` to git
- Ensure `.env` is not readable by web server (protected by `.htaccess`)
- Rotate credentials quarterly
- Use strong, unique passwords

### 3. Set File Permissions

```bash
# Make data directory writable
chmod 755 data/
chmod 755 data/rockets.db  # If already created

# Ensure .env is not world-readable
chmod 600 .env

# Standard PHP files
chmod 644 *.php
chmod 644 .htaccess
```

### 4. Initialize Database

SSH into server and run:

```bash
php init_db.php
```

Expected output:
```
SYSTEM-MELDUNG: Datenbank rockets.db (CRM 2.8) erfolgreich initialisiert und Schema validiert.
```

This creates:
- `data/rockets.db` (SQLite database)
- All required tables (customers, projects, psi_results, system_logs, etc.)
- Indexes for performance

### 5. Create Admin User

SSH and run:

```bash
php -r "
require 'session_handler.php';
\$db = new PDO('sqlite:data/rockets.db');
\$stmt = \$db->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 1)');
\$stmt->execute(['admin', password_hash('your-strong-password', PASSWORD_BCRYPT)]);
echo 'Admin user created: admin\n';
"
```

**Important:** Use a strong, unique password!

---

## Post-Deployment Verification

### 1. Database Verification

```bash
sqlite3 data/rockets.db ".tables"
```

Should list all tables:
```
customers       email_templates interactions  psi_results    project_contacts
projects        system_logs     users
```

### 2. Login Test

1. Open `https://yourdomain.com/index.php`
2. Login with admin credentials
3. Verify dashboard loads

### 3. API Test

```bash
# Test public_api.php
curl -X POST https://yourdomain.com/public_api.php \
  -d 'action=diagnose&name=Test&email=test@example.com&url=https://example.com'

# Should return: {"success": true}
```

### 4. Logging Test

1. Access logs dashboard: `https://yourdomain.com/logs_dashboard.php`
2. Login with admin credentials
3. Verify you can see system events
4. Check statistics for past 24 hours

### 5. Email Test

1. Create a test lead via public_api.php
2. Check that email arrives at ADMIN_EMAIL
3. Verify no errors in logs_dashboard.php

### 6. Background Jobs Test

```bash
# Test PSI worker
php flow/vip/worker_psi.php

# Test SMS cron
php flow/cron/cron_sms_r400.php
```

Check `logs_dashboard.php` for JOB_EXECUTION events. They should be logged as INFO level with timing.

---

## Scheduled Jobs (Cron)

Set up these cron jobs on your server:

### Every 5 minutes: PSI Analysis Worker

```bash
*/5 * * * * cd /path/to/r100-crm && php flow/vip/worker_psi.php >> /tmp/worker_psi.log 2>&1
```

This processes pending projects (`tunnel='anfrage'`), fetches Lighthouse scores, and updates their state to `bewertet`.

### Every 10 minutes: SMS Notifications

```bash
*/10 * * * * cd /path/to/r100-crm && php flow/cron/cron_sms_r400.php >> /tmp/cron_sms.log 2>&1
```

This sends SMS notifications for new reports (`bewertet` and `bereit` states).

### Verify Cron Setup

```bash
# Check if cron jobs are running
tail -f /tmp/worker_psi.log
tail -f /tmp/cron_sms.log
```

You should see log entries like:
```
[2026-06-11 14:30:00] [INFO] === Worker Start ===
[2026-06-11 14:30:05] [INFO] Found 3 pending projects
[2026-06-11 14:30:45] [INFO] === Worker Complete ===
```

---

## Database Backups

### Automatic Backups (Recommended)

Set up a daily cron job:

```bash
0 2 * * * /path/to/backup.sh
```

Where `backup.sh` contains:

```bash
#!/bin/bash
BACKUP_DIR="/home/user/backups"
DB_FILE="/path/to/r100-crm/data/rockets.db"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
cp $DB_FILE $BACKUP_DIR/rockets_$TIMESTAMP.db.bak
gzip $BACKUP_DIR/rockets_$TIMESTAMP.db.bak

# Keep only last 30 days
find $BACKUP_DIR -name "rockets_*.db.bak.gz" -mtime +30 -delete
```

### Manual Backup

```bash
cp data/rockets.db data/rockets.db.backup.$(date +%Y%m%d)
```

### Restore from Backup

```bash
cp data/rockets.db.backup.20260611 data/rockets.db
```

---

## Monitoring

### Check System Logs

```bash
sqlite3 data/rockets.db "
SELECT timestamp, level, event_type, message 
FROM system_logs 
WHERE timestamp > datetime('now', '-1 hour')
ORDER BY timestamp DESC 
LIMIT 20;
"
```

### Error Rate Check

```bash
sqlite3 data/rockets.db "
SELECT level, COUNT(*) as count 
FROM system_logs 
WHERE timestamp > datetime('now', '-24 hours')
GROUP BY level;
"
```

### Job Execution Status

```bash
sqlite3 data/rockets.db "
SELECT event_type, MAX(timestamp), COUNT(*) 
FROM system_logs 
WHERE event_type LIKE 'JOB%' 
AND timestamp > datetime('now', '-24 hours')
GROUP BY event_type;
"
```

---

## Troubleshooting

### Database Locked Error

**Symptom:** `database is locked` errors in logs

**Solution:**
1. Check if cron jobs are running too frequently
2. Ensure SQLite WAL mode is enabled in code (it is)
3. Restart web server
4. If persistent, disable one cron job and scale back

### Missing Emails

**Symptom:** Leads aren't receiving emails

**Solution:**
1. Check SMTP configuration in `.env`
2. Verify `ADMIN_EMAIL` is correct
3. Check mail logs: `logs_dashboard.php` → filter by `event_type=API_REQUEST`
4. Test mail delivery:
   ```bash
   php -r "
   require '.env';
   \$to = getenv('ADMIN_EMAIL');
   \$result = mail(\$to, 'Test', 'Test message');
   echo \$result ? 'Mail sent' : 'Mail failed';
   "
   ```

### SMS Not Sending

**Symptom:** Projects stuck in `bewertet` state, SMS logs show errors

**Solution:**
1. Verify `SIPGATE_API_TOKEN` and `SIPGATE_API_TOKEN_ID` in `.env`
2. Check Sipgate account has credit
3. Verify phone numbers are in correct format (+49...)
4. Check `logs_dashboard.php` for JOB_EXECUTION errors
5. Manually run: `php flow/cron/cron_sms_r400.php`

### PSI Worker Not Running

**Symptom:** Projects stuck in `anfrage` state

**Solution:**
1. Check cron job is configured: `crontab -l`
2. Manually run: `php flow/vip/worker_psi.php`
3. Check for errors: `logs_dashboard.php` → filter `JOB_EXECUTION` → `ERROR`
4. Verify `GOOGLE_PSI_API_KEY` and `ANTHROPIC_API_KEY` in `.env`
5. Check system resources (disk space, memory)

### Performance Issues

**Solution:**
1. Check logs_dashboard.php for slow requests (filter `level=INFO` → check `duration_ms`)
2. If requests > 5000ms:
   - Check PageSpeed API timeout
   - Reduce worker frequency
   - Add database indexes if needed
3. Monitor system resources:
   - `df -h` (disk space)
   - `free -m` (memory)

---

## Updates & Maintenance

### Updating Code

1. Test changes locally first
2. Backup database: `cp data/rockets.db data/rockets.db.pre-update`
3. Pull/upload new code
4. Review any schema changes in `init_db.php`
5. If schema changed, run: `php init_db.php` (safe with IF NOT EXISTS)
6. Test critical paths: `php tests/integration_tests.php`
7. Monitor logs for 1 hour

### Security Updates

- Update `.env` credentials quarterly
- Rotate admin passwords
- Check `/logs_dashboard.php` for suspicious activity (repeated auth failures)
- Monitor for unexpected request patterns

---

## Recovery Procedures

### Full System Recovery

1. Restore from backup: `cp data/rockets.db.backup.20260611 data/rockets.db`
2. Restart web server
3. Verify in logs_dashboard.php that system is operational
4. Run tests: `php tests/integration_tests.php`

### Session Issues

If users can't login:

```bash
# Clear session database
sqlite3 data/rockets.db "DELETE FROM users WHERE 1=0;"  # This won't work, sessions are file-based

# Actually, restart web server:
# On one.com: Use control panel or contact support
```

---

## Support & Monitoring Contacts

- **Admin Panel:** `https://yourdomain.com/index.php`
- **Logs Dashboard:** `https://yourdomain.com/logs_dashboard.php`
- **Public API:** `https://yourdomain.com/public_api.php`
- **VIP Portal:** `https://yourdomain.com/flow/vip/?token=xxx`

Monitor `logs_dashboard.php` daily for any ERROR-level events.

