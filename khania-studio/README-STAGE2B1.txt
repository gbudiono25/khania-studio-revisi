KHANIA STUDIO — STAGE 2B-1
Form Brief V3 Integrated

FILES IN THIS PATCH
1. formulir-permintaan-website-khania-studio-v3.html
2. proses-formulir.php

FOCUS
- Form Brief V3 is now tied to an existing Order ID.
- Package and client identity are treated as server-authoritative data from the order/client/package tables.
- Client Brief submission does NOT create a new order.
- Server requires the existing order to have a verified-payment status before accepting the brief.
- Website brief is saved to website_briefs with status SUBMITTED and brief_data JSONB.
- Adds Section R: Permintaan Tambahan / Kebutuhan Khusus.
- Uploads remain supported and are grouped under the existing Order ID.
- Existing email notification remains active.

IMPORTANT
- This patch does NOT modify pemesanan.html, proses-pemesanan.php, payment confirmation files, Admin files, or Supabase SQL.
- Stage 2A SQL migration should be applied before production use so website_briefs has client_code, version, brief_data, and the new status values.
- The secure workflow link should include at least ?order_id=ORDER_ID. Optional display parameters supported by the HTML are: client_code, order_number, package, pic, email, whatsapp, business_name.
- The PHP does NOT trust those display parameters; it verifies the order, client, package and payment status from Supabase.
- Because the exact production order-status enum/value must match the already-tested payment flow, the PHP accepts these verified values: payment_verified, paid, verified, confirmed, completed_payment, pembayaran_terverifikasi. If the live system uses another exact value, add it to $verifiedStatuses after checking the production payment handler.
- SMTP credentials are not changed or displayed by this patch.

TESTING SUGGESTION
1. Open the brief link with a real verified Order ID.
2. Confirm Order ID, package and client data are displayed automatically.
3. Confirm package cannot be changed in the form.
4. Submit a brief with a small test attachment.
5. Confirm no second order is created.
6. Confirm website_briefs receives status SUBMITTED.
7. Confirm the brief contains client_code and Section R data.
8. Confirm email notification still arrives.
9. Test an unverified Order ID and confirm submission is rejected.

Do not overwrite the working order/payment files while testing this patch.
