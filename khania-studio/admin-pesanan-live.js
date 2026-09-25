(async function(){
  const $=id=>document.getElementById(id);
  const rupiah=n=>'Rp'+Number(n||0).toLocaleString('id-ID');
  let client=null, orders=[], selected=null;

  function showError(msg){$('errorBox').textContent=msg;$('errorBox').classList.remove('hidden');}
  function clearError(){$('errorBox').classList.add('hidden');}
  function toast(msg,ok=false){$('toast').textContent=msg;$('toast').classList.remove('hidden');$('toast').style.borderLeft=ok?'4px solid #D4AF37':'';setTimeout(()=>$('toast').classList.add('hidden'),3500);}
  function paymentLabel(o){
    if(o.status==='payment_verified') return 'Pembayaran Terverifikasi';
    if(o.status==='payment_received') return 'Menunggu Verifikasi Pembayaran';
    const p=(o.payments||[]).find(x=>x.status==='verified');
    if(p) return 'Pembayaran Terverifikasi';
    if((o.payments||[]).some(x=>x.status==='pending')) return 'Menunggu Verifikasi Pembayaran';
    return 'Menunggu Pembayaran';
  }
  function paymentClass(v){return v==='Pembayaran Terverifikasi'?'paid':v==='Menunggu Verifikasi Pembayaran'?'waiting':'danger';}
  function briefRecord(o){
    const raw=o.website_briefs;
    if(Array.isArray(raw)) return raw[0]||null;
    if(raw && typeof raw==='object') return raw;
    return null;
  }
  function briefLabel(o){
    const b=briefRecord(o);
    if(!b) return 'Belum Dikirim';
    if(b.brief_sent_at) return 'Terkirim';
    if(b.status==='DRAFT') return 'Belum Dikirim';
    if(b.status==='SUBMITTED') return 'Brief Diterima';
    if(b.status==='UNDER_REVIEW') return 'Dalam Pemeriksaan';
    if(b.status==='NEED_CLIENT_INFO') return 'Perlu Info Klien';
    if(b.status==='SCOPE_CONFIRMED') return 'Scope Dikonfirmasi';
    if(b.status==='READY_FOR_PRODUCTION') return 'Siap Produksi';
    return b.status||'Belum Dikirim';
  }
  function briefClass(v){return v==='Belum Dikirim'?'waiting':'brief';}
  function canSend(o){return paymentLabel(o)==='Pembayaran Terverifikasi';}
  function canVerify(o){return paymentLabel(o)==='Menunggu Verifikasi Pembayaran';}
  function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}

  async function load(){
    clearError();
    const {data,error}=await client
      .from('orders')
      .select(`
        id,order_number,order_date,due_date,base_price,setup_fee,voucher_code,
        voucher_discount,total_amount,status,created_at,
        clients(id,client_code,full_name,business_name,email,whatsapp),
        packages(id,code,name),
        payments(id,payment_date,amount,status,verified_at,verified_by),
        website_briefs(id,status,submitted_at,created_at,brief_sent_at)
      `)
      .order('created_at',{ascending:false});
    if(error){showError('Gagal membaca data pesanan: '+error.message);return;}
    orders=data||[];
    render();
  }

  function render(){
    const q=$('search').value.toLowerCase().trim(), pf=$('paymentFilter').value, bf=$('briefFilter').value;
    const rows=orders.filter(o=>{
      const c=o.clients||{}, p=paymentLabel(o), b=briefLabel(o);
      return (!q||[o.order_number,c.client_code,c.full_name,c.business_name,c.email,o.packages?.name].join(' ').toLowerCase().includes(q))
        &&(pf==='ALL'||p===pf)&&(bf==='ALL'||b===bf);
    });
    $('ordersBody').innerHTML=rows.map(o=>{
      const c=o.clients||{},p=paymentLabel(o),b=briefLabel(o);
      return `<tr>
        <td><span class="order-no">${esc(o.order_number)}</span><span class="sub">${esc(c.client_code||'-')}</span></td>
        <td><strong>${esc(c.full_name||'-')}</strong><span class="sub">${esc(c.business_name||'-')}</span></td>
        <td>${esc(o.packages?.name||'-')}</td><td><strong>${rupiah(o.total_amount)}</strong></td>
        <td><span class="badge ${paymentClass(p)}">${p}</span></td>
        <td><span class="badge ${briefClass(b)}">${esc(b)}</span></td>
        <td>${esc(o.order_date||'-')}</td>
        <td><div class="row-actions">
          <button class="icon-btn" data-action="detail" data-id="${o.id}">Detail</button>
          <button class="icon-btn verify-btn" data-action="verify" data-id="${o.id}" ${canVerify(o)?'':'disabled'}>Verifikasi</button>
          <button class="icon-btn" data-action="brief" data-id="${o.id}" ${canSend(o)?'':'disabled'}>${b==='Form Brief Dikirim'?'Kirim Ulang':'Kirim Brief'}</button>
        </div></td>
      </tr>`;
    }).join('');
    $('empty').classList.toggle('hidden',rows.length>0);
    $('sumTotal').textContent=orders.length;
    $('sumPending').textContent=orders.filter(o=>paymentLabel(o)==='Menunggu Pembayaran').length;
    $('sumVerify').textContent=orders.filter(o=>paymentLabel(o)==='Menunggu Verifikasi Pembayaran').length;
    $('sumPaid').textContent=orders.filter(o=>paymentLabel(o)==='Pembayaran Terverifikasi').length;
    $('navCount').textContent=orders.length;
  }

  function showDetail(o){
    selected=o;
    const c=o.clients||{},p=paymentLabel(o),b=briefLabel(o);
    $('detailTitle').textContent=o.order_number;
    $('detailSubtitle').textContent=(c.full_name||'-')+' — '+(c.business_name||'-');
    $('detailContent').innerHTML=[
      ['Kode Klien/Project',c.client_code||'-'],['Paket',o.packages?.name||'-'],
      ['Total Tagihan',rupiah(o.total_amount)],['Email',c.email||'-'],
      ['WhatsApp',c.whatsapp||'-'],['Status Pembayaran',p],
      ['Tanggal Pembayaran',((o.payments||[])[0]?.payment_date||'-')],
      ['Status Form Brief',b],
      ['Form Brief Dikirim',briefRecord(o)?.brief_sent_at||'-'],
      ['Tanggal Order',o.order_date||'-']
    ].map(x=>`<div class="detail-item"><label>${x[0]}</label><strong>${esc(x[1])}</strong></div>`).join('');
    $('detailBriefBtn').disabled=!canSend(o);
    $('detailBriefBtn').textContent=b==='Terkirim'?'Kirim Ulang Form Brief':'Kirim Form Brief';

    // Add verification action into modal without changing the existing HTML.
    let verify=$('detailVerifyBtn');
    if(!verify){
      verify=document.createElement('button');
      verify.id='detailVerifyBtn'; verify.className='btn primary';
      $('detailBriefBtn').parentElement.insertBefore(verify,$('detailBriefBtn'));
    }
    verify.textContent='Verifikasi Pembayaran';
    verify.disabled=!canVerify(o);
    verify.onclick=()=>verifyPayment(o);

    $('detailModal').classList.remove('hidden');
  }

  async function verifyPayment(o){
    if(!canVerify(o)){toast('Order ini tidak sedang menunggu verifikasi.');return;}
    const c=o.clients||{};
    const ok=confirm(
      `Verifikasi pembayaran?\n\nOrder: ${o.order_number}\nKlien: ${c.full_name||'-'}\nPaket: ${o.packages?.name||'-'}\nTotal: ${rupiah(o.total_amount)}\n\nPastikan bukti transfer sudah diperiksa.`
    );
    if(!ok)return;

    const {data,error}=await client.rpc('admin_verify_payment',{p_order_id:o.id});
    if(error){
      showError('Verifikasi gagal: '+error.message);
      return;
    }
    const result=Array.isArray(data)?data[0]:data;
    if(!result || result.success!==true){
      showError('Verifikasi gagal: '+(result?.message||'Respons server tidak valid.'));
      return;
    }

    o.status='payment_verified';
    if(Array.isArray(o.payments) && o.payments[0]){
      o.payments[0].status='verified';
      o.payments[0].verified_at=new Date().toISOString();
    }
    $('detailModal').classList.add('hidden');
    render();
    toast('Pembayaran berhasil diverifikasi. Tombol Kirim Brief sekarang aktif.',true);
  }

  async function sendBrief(o){
    if(!canSend(o)){toast('Form Brief baru dapat dikirim setelah pembayaran terverifikasi.');return;}
    const c=o.clients||{};
    const existing=briefRecord(o);
    const resend=!!(existing && existing.brief_sent_at);
    const ok=confirm(
      `${resend?'Kirim ulang':'Kirim'} Form Brief?\n\nOrder: ${o.order_number}\nKlien: ${c.full_name||'-'}\nPaket: ${o.packages?.name||'-'}\nEmail: ${c.email||'-'}\n\nLink aman akan dikirim ke email klien dan berlaku 14 hari.`
    );
    if(!ok)return;
    const {data:{session}}=await client.auth.getSession();
    if(!session?.access_token){showError('Sesi admin tidak ditemukan. Silakan login ulang.');return;}
    try{
      const res=await fetch('api/admin-kirim-brief.php',{
        method:'POST',
        headers:{'Content-Type':'application/json','Authorization':'Bearer '+session.access_token},
        body:JSON.stringify({order_id:o.id}),
        cache:'no-store'
      });
      const payload=await res.json().catch(()=>({}));
      if(!res.ok || !payload.success){
        showError('Pengiriman Form Brief gagal: '+(payload.message||'Respons server tidak valid.'));
        return;
      }
      if(payload.warning) toast(payload.warning,true);
      else toast('Form Brief berhasil dikirim ke email klien.',true);
      $('detailModal').classList.add('hidden');
      await load();
    }catch(err){showError('Pengiriman Form Brief gagal: '+(err.message||'Kesalahan jaringan.'));}
  }

  document.addEventListener('click',e=>{
    const a=e.target.closest('[data-action]');
    if(a){
      const o=orders.find(x=>x.id===a.dataset.id);
      if(!o)return;
      if(a.dataset.action==='detail')showDetail(o);
      else if(a.dataset.action==='verify')verifyPayment(o);
      else if(a.dataset.action==='brief')sendBrief(o);
    }
    const c=e.target.closest('[data-close]');
    if(c)$(c.dataset.close).classList.add('hidden');
  });

  $('detailBriefBtn').addEventListener('click',()=>{if(selected)sendBrief(selected);});
  $('refreshBtn').addEventListener('click',load);
  $('resetBtn').addEventListener('click',()=>{$('search').value='';$('paymentFilter').value='ALL';$('briefFilter').value='ALL';render();});
  ['search','paymentFilter','briefFilter'].forEach(id=>$(id).addEventListener('input',render));
  $('logoutBtn').addEventListener('click',async()=>{await client.auth.signOut();location.href='admin-login.html';});

  try{
    const cfgRes=await fetch('api/supabase-config.php',{cache:'no-store'}),cfg=await cfgRes.json();
    if(!cfg.success)throw new Error(cfg.message||'Konfigurasi Supabase tidak tersedia.');
    client=supabase.createClient(cfg.url,cfg.anonKey);
    const {data:{session}}=await client.auth.getSession();
    if(!session){location.href='admin-login.html';return;}
    const {data:profile,error:pe}=await client.from('profiles').select('role,full_name').eq('id',session.user.id).maybeSingle();
    if(pe||!profile||profile.role!=='admin'){await client.auth.signOut();location.href='admin-login.html';return;}
    $('adminEmail').textContent=session.user.email||profile.full_name||'Admin';
    $('loading').classList.add('hidden');$('app').classList.remove('hidden');
    await load();
  }catch(err){$('loading').textContent=err.message||'Gagal memuat Admin Area.';}
})();
