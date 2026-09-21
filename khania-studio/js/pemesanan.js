document.addEventListener('DOMContentLoaded', async () => {
  const $ = id => document.getElementById(id);
  const rupiah = n => new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(n).replace('IDR','Rp');
  const formatDate = d => d.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});

  const jakartaParts = Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date()).filter(p=>p.type!=='literal').map(p=>[p.type,p.value]));
  const now = new Date(Date.UTC(Number(jakartaParts.year), Number(jakartaParts.month)-1, Number(jakartaParts.day), Number(jakartaParts.hour)-7, Number(jakartaParts.minute), Number(jakartaParts.second)));
  const due = new Date(now); due.setDate(due.getDate()+2);

  let packages = {};
  try {
    const res = await fetch('api/packages.php', {cache:'no-store'});
    if (res.ok) { const data = await res.json(); if (Array.isArray(data)) data.forEach(pkg => { packages[pkg.code.toLowerCase()]={name:pkg.name,base:Number(pkg.base_price),setup:Number(pkg.setup_fee),code:pkg.code,id:pkg.id}; }); }
  } catch(e) { console.warn('Khania Studio pemesanan.js: API packages unavailable, using fallback.', e); }
  if (Object.keys(packages).length===0) packages={starter:{name:'Starter',base:400000,setup:100000},bronze:{name:'Bronze',base:580000,setup:200000},silver:{name:'Silver',base:1100000,setup:400000},gold:{name:'Gold',base:1750000,setup:600000}};

  const params = new URLSearchParams(window.location.search);
  const key = (params.get('paket') || params.get('package') || '').toLowerCase();
  const pkg = packages[key];
  if (!pkg) { $('packageWarning').hidden=false; $('submitOrder').disabled=true; $('packageName').textContent='Paket belum dipilih'; return; }

  $('packageName').textContent=pkg.name;
  $('packageDisplayTotal').textContent=rupiah(pkg.base+pkg.setup);
  const customerName=$('customerName');
  const updateTransferNote=()=>{ const name=customerName.value.trim()||'[Nama Pemesan]'; $('transferNote').textContent=pkg.name+' atas nama '+name; };
  customerName.addEventListener('input',updateTransferNote); updateTransferNote();
  $('basePrice').textContent=rupiah(pkg.base); $('setupPrice').textContent=rupiah(pkg.setup);
  $('packageInput').value=pkg.name; $('packageBaseInput').value=pkg.base; $('setupFeeInput').value=pkg.setup;
  $('orderDateDisplay').textContent=formatDate(now); $('dueDateDisplay').textContent=formatDate(due)+', 23:59 WIB';

  let discount=0;
  const voucherInput=$('voucher'), voucherMessage=$('voucherMessage'), voucherStatus=$('voucherStatus'), discountDisplay=$('discountDisplay'), totalPrice=$('totalPrice');
  function renderTotal(){ const total=Math.max(0,pkg.base+pkg.setup-discount); discountDisplay.textContent=discount?'− '+rupiah(discount):rupiah(0); totalPrice.textContent=rupiah(total); $('voucherCodeInput').value=voucherInput.value.trim().toUpperCase(); $('voucherDiscountInput').value=discount; }
  renderTotal();
  async function applyVoucher(){
    const code=voucherInput.value.trim().toUpperCase(); discount=0; $('voucherCodeInput').value=''; voucherStatus.textContent='(opsional)';
    if(!code){voucherMessage.textContent='Masukkan kode voucher terlebih dahulu.';voucherMessage.className='hint';renderTotal();return;}
    try{const res=await fetch(`api/validate-voucher.php?code=${encodeURIComponent(code)}&package=${encodeURIComponent(pkg.name)}`,{cache:'no-store'});const data=await res.json();if(!data.valid){voucherMessage.textContent=data.message||'Kode voucher tidak valid atau tidak tersedia.';voucherMessage.className='hint voucher-error';renderTotal();return;}const subtotal=pkg.base+pkg.setup;if(data.discount_type==='percent') discount=Math.round(subtotal*Math.min(100,Number(data.discount_value)||0)/100);else discount=Math.min(subtotal,Number(data.discount_value)||0);$('voucherCodeInput').value=code;voucherStatus.textContent='('+code+')';voucherMessage.textContent=data.message||'✓ Voucher berhasil digunakan.';voucherMessage.className='hint voucher-success';renderTotal();}catch(e){voucherMessage.textContent='Fitur voucher belum diaktifkan atau data voucher belum tersedia. Silakan hubungi Khania Studio jika Anda memiliki kode voucher.';voucherMessage.className='hint voucher-error';renderTotal();}
  }
  $('applyVoucher').addEventListener('click',applyVoucher); voucherInput.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();applyVoucher();}});

  $('copyAccount').addEventListener('click',async()=>{const text=$('accountNumber').textContent.trim();try{await navigator.clipboard.writeText(text);$('copyAccount').textContent='✓ Nomor rekening tersalin';$('copyAccount').classList.add('copy-ok');setTimeout(()=>{$('copyAccount').textContent='Salin Nomor Rekening';$('copyAccount').classList.remove('copy-ok')},2200);}catch(e){alert('Nomor rekening: '+text);}});
  $('copyTransferNote').addEventListener('click',async()=>{const text=$('transferNote').textContent.trim();try{await navigator.clipboard.writeText(text);$('copyTransferNote').textContent='✓ Berita transfer tersalin';$('copyTransferNote').classList.add('copy-ok');setTimeout(()=>{$('copyTransferNote').textContent='Salin Berita Transfer';$('copyTransferNote').classList.remove('copy-ok')},2200);}catch(e){alert('Berita transfer: '+text);}});

  $('orderForm').addEventListener('submit',e=>{ if(!$('packageInput').value || !($('basePrice').textContent)) {e.preventDefault();return;} if(!$('orderForm').checkValidity()){e.preventDefault();$('orderForm').reportValidity();} });
});
