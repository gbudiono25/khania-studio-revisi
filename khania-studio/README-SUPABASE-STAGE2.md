# KHANIA STUDIO — SUPABASE INTEGRATION SETUP (STAGE 2)

## Overview

This stage connects the Khania Studio PHP website to your Supabase project so that order submissions, client briefs, and voucher validation are stored in the Supabase database instead of local JSON files.

## 0. Prerequisites

- A Supabase project (URL + anon key already in `.env`)
- PHP 7.4+ (PHP 8.1/8.2 recommended) with the `curl` extension enabled on your hosting
- Write access to create directories in the project

## 1. Apply the Database Schema

1. Open your Supabase project dashboard.
2. Go to **SQL Editor**.
3. Paste and run the contents of `supabase-schema.sql`.
4. Then paste and run `supabase-schema-migration-2.sql` (adds INSERT policies for unauthenticated backend writes).

This creates:
- Tables: `profiles`, `clients`, `packages`, `vouchers`, `orders`, `payments`, `website_briefs`, `messages`, `announcements`, `announcement_recipients`, `audit_logs`
- Pre-seeded packages (Starter, Bronze, Silver, Gold)
- Row Level Security (RLS) policies

## 2. Create Storage Buckets

In the Supabase dashboard, go to **Storage > Buckets** and create two **private** buckets:

| Bucket Name          | Purpose                        |
|----------------------|--------------------------------|
| `order-proofs`       | Payment proof uploads          |
| `client-files`       | Brief form file attachments    |

## 3. (Optional) Set Service Role Key

For full backend write access without RLS restrictions:

1. In Supabase, go to **Project Settings > API**.
2. Copy the `service_role` key (the one labeled "service_role secret").
3. Paste it into `.env`:
   ```
   SUPABASE_SERVICE_ROLE_KEY=your_service_role_key_here
   ```

This is **server-side only** — the key never reaches the browser.

If left empty, the system falls back to the anon key with the INSERT policies from Step 1.

## 4. Verify .env

**Important:** `.env` is server-side only and must never be directly accessible from the browser. The supplied `.htaccess` blocks it. Do not paste the service role key into any HTML/JS file.

Ensure your `.env` file contains:

```
SUPABASE_URL=https://rdauhmspofnjvsakplxs.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIs...<your_anon_key>
SUPABASE_SERVICE_ROLE_KEY=                  # optional, leave empty or fill
SUPABASE_STORAGE_BUCKET_PAYMENT_PROOFS=order-proofs
SUPABASE_STORAGE_BUCKET_CLIENT_FILES=client-files
```

## 5. Verify Directory Structure

```
khania-studio/
├── .env
├── config-pemesanan.php      (SMTP config — unchanged)
├── lib/
│   ├── env.php               (NEW — loads .env)
│   └── SupabaseClient.php    (NEW — PHP REST API client)
├── api/
│   ├── packages.php          (NEW — fetch packages from Supabase)
│   └── validate-voucher.php  (NEW — server-side voucher validation)
├── proses-pemesanan.php      (MODIFIED — saves to Supabase + email)
├── proses-formulir.php       (MODIFIED — saves brief to Supabase + email)
├── js/
│   └── pemesanan.js          (MODIFIED — fetches packages/vouchers via API)
├── data/
│   └── .htaccess             (blocks direct JSON access)
└── supabase-schema*.sql      (run in Supabase SQL Editor)
```

## 6. Test

### a. Packages API
```
curl https://your-domain.com/api/packages.php
```
Should return a JSON array of active packages.

### b. Voucher Validation API
```
curl "https://your-domain.com/api/validate-voucher.php?code=TEST10&package=Starter"
```
Should return `{"valid":true,"discount_type":"amount","discount_value":10000,...}` or an error.

### c. Order Submission
Submit the order form at `pemesanan.html?paket=starter` and verify:
- A new record appears in Supabase `clients` table
- A new record appears in Supabase `orders` table
- A new record appears in Supabase `payments` table
- Payment proof file appears in Supabase Storage → `order-proofs` bucket
- Email notification is still sent via SMTP

### d. Brief Submission
Submit the brief form at `formulir-permintaan-website-khania-studio-v3.html` and verify:
- A new record appears in Supabase `clients` table
- A new record appears in Supabase `orders` table
- A new record appears in Supabase `website_briefs` table (with full JSON data)
- Attachments appear in Supabase Storage → `client-files` bucket

## 7. Fallback Behavior

- If Supabase is not configured (empty/invalid credentials), the system automatically falls back to the original local JSON file behavior.
- If the service_role key is empty, the anon key + INSERT policies handle writes.
- SMTP email notifications are always sent regardless of database backend.
