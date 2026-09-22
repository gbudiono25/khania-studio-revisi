KHANIA STUDIO — DIAGNOSTIC ORDER V5

Tujuan:
Membaca RPC create_public_order() per baris dan function yang benar-benar
dipanggil oleh trigger orders, sehingga tidak perlu menebak dari screenshot
yang terpotong.

Semua query READ-ONLY.

Hasil yang paling penting:
A = definisi create_public_order(), satu baris per line.
B = trigger orders.
C = function yang benar-benar dipanggil trigger.
D = semua nilai yang diizinkan untuk orders.status.
E = kolom orders yang dipakai RPC.
F = kolom packages yang dipakai RPC.
G = kolom vouchers yang dipakai RPC.

Cara menjalankan:
1. Supabase > SQL Editor > New query.
2. Paste seluruh isi SQL V5.
3. Klik Run.
4. Kirim screenshot hasil A terlebih dahulu. Jika panjang, kirim beberapa
   screenshot dari line awal sampai akhir.
5. Setelah itu kirim B, C, dan D.

Tidak perlu mengirim credential apa pun.
