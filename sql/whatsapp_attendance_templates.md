# WhatsApp attendance templates (Meta Business Manager)

Create both as **Utility** templates, language **English (`en`)**.  
The app sends to each admin’s **`admins.phone_number`** (E.164 after formatting).

Set in `.env`:

```env
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_DEFAULT_COUNTRY_CODE=250

WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_NAME=pcvc_checkout_attendance
WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_LANG=en
WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_PARAMS=6

WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_NAME=pcvc_daily_attendance_summary
WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_LANG=en
WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_PARAMS=6
```

---

## 1) Checkout (instant when staff checks out)

**Template name:** `pcvc_checkout_attendance`  
**Category:** Utility  
**Language:** English  

**Body (copy into Meta — 6 variables):**

```
Hello {{1}},

You have checked out for {{2}} at ScholarSync Global.

Check-in: {{3}}
Check-out: {{4}}
Time worked: {{5}}
Daily salary: {{6}}

Thank you for your work today.

— ScholarSync MIS
```

| Variable | Sample for Meta review | App sends |
|----------|------------------------|-----------|
| {{1}} | Jean Methode | Admin full name |
| {{2}} | 2026-06-10 | Date YYYY-MM-DD |
| {{3}} | 8:30 AM | Check-in time |
| {{4}} | 5:15 PM | Check-out time |
| {{5}} | 6h 30m | Duration worked |
| {{6}} | RWF 3,998 | Daily salary |

---

## 2) Daily digest (cron `php daily_check.php`)

**Template name:** `pcvc_daily_attendance_summary`  
**Category:** Utility  
**Language:** English  

**Body:**

```
Hello {{1}},

Here is your attendance summary for {{2}} at ScholarSync Global:

Check-in: {{3}}
Check-out: {{4}}
Time worked: {{5}}
Salary earned: {{6}}

If anything looks incorrect, contact your supervisor. Thank you for your work today.

— ScholarSync MIS
```

Same 6 variables as checkout table above.

---

## Admin phone numbers

Ensure every staff/superadmin has a valid number in **`admins.phone_number`**:

- International: `+250788284544`, `+254711807646`, `+1 (438) 290-6688`
- Local Rwanda: `0785042772` (requires `WHATSAPP_DEFAULT_COUNTRY_CODE=250`)

Test send (after template approved):

```bash
php tools/test_attendance_whatsapp.php --admin-id=1
```
