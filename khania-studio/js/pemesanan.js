document.addEventListener('DOMContentLoaded', () => {
  const packages = {
    starter: {name:'Starter', base:400000, setup:100000},
    bronze:  {name:'Bronze', base:580000, setup:200000},
    silver:  {name:'Silver', base:1100000, setup:400000},
    gold:    {name:'Gold', base:1750000, setup:600000}
  };
  const params = new URLSearchParams(window.location.search);
  const key = (params.get('paket') || params.get('package') || '').toLowerCase();
  const pkg = packages[key];
  const $ = id => document.getElementById(id);
  const rupiah = n => new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(n).replace('IDR','Rp');
  const formatDate = d => d.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});
  const dateOnly = d => { const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; };

  if (!pkg) {
    $('packageWarning').hidden = false;
    $('submitOrder').disabled = true;
    $('packageName').textContent = 'Paket belum dipilih';
    return;
  }

  $('packageName').textContent = pkg.name;
  $('packageDisplayTotal').textContent = rupiah(pkg.base + pkg.setup);

  // Transfer reference is generated automatically from the selected package and pemesan name.
  const customerName = $('customerName');
  const updateTransferNote = () => {
    const name = customerName.value.trim() || '[Nama Pemesan]';
    $('transferNote').textContent = pkg.name + ' atas nama ' + name;
  };
  customerName.addEventListener('input', updateTransferNote);
  updateTransferNote();
  $('basePrice').textContent = rupiah(pkg.base);
  $('setupPrice').textContent = rupiah(pkg.setup);
  $('packageInput').value = pkg.name;
  $('packageBaseInput').value = pkg.base;
  $('setupFeeInput').value = pkg.setup;

  // Use Indonesia/Jakarta time so the displayed order date is consistent with the business rule H+2 WIB.
  const jakartaParts = Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date()).filter(p=>p.type!=='literal').map(p=>[p.type,p.value]));
  const now = new Date(Date.UTC(Number(jakartaParts.year), Number(jakartaParts.month)-1, Number(jakartaParts.day), Number(jakartaParts.hour)-7, Number(jakartaParts.minute), Number(jakartaParts.second)));
  const due = new Date(now);
  due.setDate(due.getDate()+2);
  $('orderDateDisplay').textContent = formatDate(now);
  $('dueDateDisplay').textContent = formatDate(due) + ', 23:59 WIB';
  $('orderDateInput').value = dateOnly(now);
  $('dueDateInput').value = `${dateOnly(due)} 23:59:59`;
  $('paymentDate').max = dateOnly(now);

  let discount = 0;
  const voucherInput = $('voucher');
  const voucherMessage = $('voucherMessage');
  const voucherStatus = $('voucherStatus');
  const discountDisplay = $('discountDisplay');
  const totalPrice = $('totalPrice');

  function renderTotal(){
    const total = Math.max(0, pkg.base + pkg.setup - discount);
    discountDisplay.textContent = discount ? '− ' + rupiah(discount) : rupiah(0);
    totalPrice.textContent = rupiah(total);
    $('orderTotalInput').value = total;
    $('voucherDiscountInput').value = discount;
  }
  renderTotal();

  // Voucher engine is intentionally data-driven. The admin module can later write to /data/vouchers.json.
  async function applyVoucher(){
    const code = voucherInput.value.trim().toUpperCase();
    discount = 0;
    $('voucherCodeInput').value = '';
    voucherStatus.textContent = '(opsional)';
    if (!code){ voucherMessage.textContent='Masukkan kode voucher terlebih dahulu.'; voucherMessage.className='hint'; renderTotal(); return; }
    try {
      const res = await fetch('data/vouchers.json', {cache:'no-store'});
      if (!res.ok) throw new Error('voucher data unavailable');
      const data = await res.json();
      const v = Array.isArray(data) ? data.find(x => String(x.code||'').toUpperCase() === code && x.active !== false) : null;
      if (!v){ voucherMessage.textContent='Kode voucher tidak valid atau tidak tersedia.'; voucherMessage.className='hint voucher-error'; renderTotal(); return; }
      const today = dateOnly(now);
      if (v.start && today < v.start || v.end && today > v.end){ voucherMessage.textContent='Kode voucher sudah tidak berlaku pada tanggal pemesanan ini.'; voucherMessage.className='hint voucher-error'; renderTotal(); return; }
      if (Array.isArray(v.packages) && v.packages.length && !v.packages.map(String).map(s=>s.toLowerCase()).includes(pkg.name.toLowerCase())){ voucherMessage.textContent='Voucher ini tidak berlaku untuk Paket '+pkg.name+'.'; voucherMessage.className='hint voucher-error'; renderTotal(); return; }
      discount = v.type === 'percent' ? Math.round((pkg.base + pkg.setup) * Math.min(100, Number(v.value)||0) / 100) : Math.min(pkg.base + pkg.setup, Number(v.value)||0);
      $('voucherCodeInput').value = code;
      voucherStatus.textContent = '('+code+')';
      voucherMessage.textContent = '✓ Voucher berhasil digunakan.';
      voucherMessage.className='hint voucher-success';
      renderTotal();
    } catch(e){
      voucherMessage.textContent='Fitur voucher belum diaktifkan atau data voucher belum tersedia. Silakan hubungi Khania Studio jika Anda memiliki kode voucher.';
      voucherMessage.className='hint voucher-error';
      renderTotal();
    }
  }
  $('applyVoucher').addEventListener('click', applyVoucher);
  voucherInput.addEventListener('keydown', e => { if(e.key==='Enter'){e.preventDefault();applyVoucher();} });

  $('copyAccount').addEventListener('click', async () => {
    const text = $('accountNumber').textContent.trim();
    try { await navigator.clipboard.writeText(text); $('copyAccount').textContent='✓ Nomor rekening tersalin'; $('copyAccount').classList.add('copy-ok'); setTimeout(()=>{$('copyAccount').textContent='Salin Nomor Rekening';$('copyAccount').classList.remove('copy-ok')},2200); }
    catch(e){ alert('Nomor rekening: '+text); }
  });

  $('orderForm').addEventListener('submit', e => {
    const total = Number($('orderTotalInput').value||0);
    if (!total || !$('packageInput').value){ e.preventDefault(); return; }
    if (!$('orderForm').checkValidity()){ e.preventDefault(); $('orderForm').reportValidity(); }
  });
});
