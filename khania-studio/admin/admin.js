const statusMap={
 pending_payment:'Menunggu Pembayaran',payment_received:'Menunggu Verifikasi Pembayaran',payment_verified:'Pembayaran Terverifikasi',brief_sent:'Form Brief Dikirim',brief_received:'Brief Diterima',in_progress:'Dalam Pengerjaan',revision:'Revisi',completed:'Selesai',cancelled:'Dibatalkan'
};
let orders=[];let activeFilter='all';
const money=n=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(n||0));
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
async function api(url,opts){const r=await fetch(url,opts);const d=await r.json();if(r.status===401){location.href='login.html';throw new Error('Sesi berakhir');}if(!r.ok||!d.ok)throw new Error(d.message||'Terjadi kesalahan');return d;}
function render(){
 const filtered=activeFilter==='all'?orders:orders.filter(o=>o.status===activeFilter);
 document.getElementById('ordersBody').innerHTML=filtered.length?filtered.map(o=>{
   const c=o.clients||{},p=o.packages||{};
   return `<tr><td><strong>${esc(o.order_number)}</strong></td><td>${esc(c.client_code||'—')}</td><td><strong>${esc(c.business_name||'—')}</strong><br><span class="muted">${esc(c.full_name||'')}</span></td><td>${esc(p.name||'—')}</td><td>${money(o.total_amount)}</td><td><span class="status-pill">${esc(statusMap[o.status]||o.status)}</span></td><td>${esc(o.order_date||'—')}</td></tr>`;
 }).join(''):'<tr><td colspan="7">Tidak ada pesanan pada filter ini.</td></tr>';
}
function renderCards(counts){
 const items=[['Semua Pesanan',counts.all],['Menunggu Verifikasi',counts.payment_received],['Pembayaran Terverifikasi',counts.payment_verified],['Perlu Tindak Lanjut',counts.brief_received]];
 document.getElementById('summaryCards').innerHTML=items.map(x=>`<div class="stat"><div class="label">${x[0]}</div><div class="num">${x[1]}</div></div>`).join('');
}
async function load(){
 try{const d=await api('../api/admin/orders.php');orders=d.orders||[];renderCards(d.counts||{});render();}catch(e){document.getElementById('ordersBody').innerHTML=`<tr><td colspan="7">${esc(e.message)}</td></tr>`;}
}
(async()=>{try{const s=await api('../api/admin/session.php');if(!s.authenticated){location.href='login.html';return;}document.getElementById('adminName').textContent=s.name||'Admin';await load();}catch(e){location.href='login.html';}})();
document.getElementById('refreshBtn').addEventListener('click',load);
document.querySelectorAll('.filter').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.filter').forEach(x=>x.classList.remove('active'));b.classList.add('active');activeFilter=b.dataset.filter;render();}));
document.getElementById('logoutBtn').addEventListener('click',async()=>{try{await fetch('../api/admin/logout.php',{method:'POST'});}finally{location.href='login.html';}});
