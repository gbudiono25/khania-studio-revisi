(async function(){
  const $=id=>document.getElementById(id);
  let client=null,orders=[];

  function showError(msg){$('errorBox').textContent=msg;$('errorBox').classList.remove('hidden');}
  function clearError(){$('errorBox').classList.add('hidden');}
  function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function getBrief(o){const raw=o?.website_briefs;if(!raw)return null;return Array.isArray(raw)?(raw[0]||null):raw;}
  function paymentLabel(o){
    if(o.status==='payment_verified')return 'Pembayaran Terverifikasi';
    if(o.status==='payment_received')return 'Menunggu Verifikasi Pembayaran';
    const p=(o.payments||[]).find(x=>x.status==='verified');
    if(p)return 'Pembayaran Terverifikasi';
    if((o.payments||[]).some(x=>x.status==='pending'))return 'Menunggu Verifikasi Pembayaran';
    return 'Menunggu Pembayaran';
  }
  function briefLabel(o){
    const b=getBrief(o); if(!b)return 'Belum Dikirim';
    if(b.status==='DRAFT')return b.brief_sent_at?'Form Brief Dikirim':'Belum Dikirim';
    if(b.status==='SUBMITTED')return 'Brief Diterima';
    if(b.status==='UNDER_REVIEW')return 'Dalam Pemeriksaan';
    if(b.status==='NEED_CLIENT_INFO')return 'Perlu Info Klien';
    if(b.status==='SCOPE_CONFIRMED')return 'Scope Dikonfirmasi';
    if(b.status==='READY_FOR_PRODUCTION')return 'Siap Produksi';
    return b.status||'Belum Dikirim';
  }
  function paymentClass(v){return v==='Pembayaran Terverifikasi'?'paid':v==='Menunggu Verifikasi Pembayaran'?'waiting':'danger';}
  function briefClass(v){return v==='Belum Dikirim'?'waiting':'brief';}
  function dateText(v){if(!v)return '-';const d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}

  async function load(){
    clearError();
    const {data,error}=await client.from('orders').select(`
      id,order_number,order_date,due_date,base_price,setup_fee,voucher_code,
      voucher_discount,total_amount,status,created_at,
      clients(id,client_code,full_name,business_name,email,whatsapp),
      packages(id,code,name),
      payments(id,payment_date,amount,status,verified_at,verified_by),
      website_briefs(id,order_id,status,submitted_at,created_at,brief_sent_at)
    `).order('created_at',{ascending:false});
    if(error){showError('Gagal membaca data Dashboard: '+error.message);return;}
    orders=data||[];render();
  }

  function render(){
    const pending=orders.filter(o=>paymentLabel(o)==='Menunggu Pembayaran');
    const verify=orders.filter(o=>paymentLabel(o)==='Menunggu Verifikasi Pembayaran');
    const paid=orders.filter(o=>paymentLabel(o)==='Pembayaran Terverifikasi');
    const briefReview=orders.filter(o=>briefLabel(o)==='Brief Diterima');
    const needClient=orders.filter(o=>briefLabel(o)==='Perlu Info Klien');
    const ready=orders.filter(o=>briefLabel(o)==='Siap Produksi');
    const underReview=orders.filter(o=>briefLabel(o)==='Dalam Pemeriksaan');
    const scopeConfirmed=orders.filter(o=>briefLabel(o)==='Scope Dikonfirmasi');

    $('sumTotal').textContent=orders.length;
    $('sumPending').textContent=pending.length;
    $('sumVerify').textContent=verify.length;
    $('sumPaid').textContent=paid.length;
    $('sumBriefReview').textContent=briefReview.length;
    $('sumReady').textContent=ready.length;
    $('navCount').textContent=orders.length;

    const actions=[
      {count:verify.length,title:'Pembayaran perlu diverifikasi',desc:'Bukti pembayaran masuk dan menunggu pemeriksaan Admin.',cls:'',href:'admin-pesanan.html'},
      {count:briefReview.length,title:'Client Brief perlu ditinjau',desc:'Client sudah mengirim brief dan menunggu review.',cls:'',href:briefReview[0]?'admin-form-brief.html?order_id='+encodeURIComponent(getBrief(briefReview[0])?.order_id||briefReview[0].id):'admin-pesanan.html'},
      {count:needClient.length,title:'Menunggu informasi client',desc:'Brief membutuhkan informasi tambahan dari client.',cls:'client',href:'admin-pesanan.html'},
      {count:ready.length,title:'Siap produksi',desc:'Scope sudah dikonfirmasi dan order siap masuk proses produksi.',cls:'ready',href:'admin-pesanan.html'}
    ];
    const active=actions.filter(x=>x.count>0);
    $('actionList').innerHTML=active.length?active.map(x=>`<a class="action-item" href="${x.href}"><div class="action-copy"><strong>${esc(x.title)}</strong><span>${esc(x.desc)}</span></div><span class="action-count ${x.cls}">${x.count}</span></a>`).join(''):'<div class="action-empty">Tidak ada tindakan yang menunggu saat ini.</div>';

    const workflow=[
      ['Menunggu Pembayaran',pending.length],['Perlu Verifikasi',verify.length],['Pembayaran Terverifikasi',paid.length],
      ['Brief Diterima',briefReview.length],['Dalam Pemeriksaan',underReview.length],['Perlu Info Klien',needClient.length],
      ['Scope Dikonfirmasi',scopeConfirmed.length],['Siap Produksi',ready.length]
    ];
    const max=Math.max(1,...workflow.map(x=>x[1]));
    $('workflowList').innerHTML=workflow.map(([label,n])=>`<div class="workflow-row"><div><div class="workflow-label">${esc(label)}</div><div class="workflow-bar"><span style="width:${Math.round((n/max)*100)}%"></span></div></div><div class="workflow-number">${n}</div></div>`).join('');

    const recent=orders.slice(0,8);
    $('recentBody').innerHTML=recent.map(o=>{
      const c=o.clients||{},p=paymentLabel(o),b=briefLabel(o);
      return `<tr><td><span class="order-no">${esc(o.order_number)}</span><span class="sub">${esc(c.client_code||'-')}</span></td><td><strong>${esc(c.full_name||'-')}</strong><span class="sub">${esc(c.business_name||'-')}</span></td><td>${esc(o.packages?.name||'-')}</td><td><span class="status-mini ${paymentClass(p)}">${esc(p)}</span></td><td><span class="status-mini ${briefClass(b)}">${esc(b)}</span></td><td>${esc(dateText(o.order_date||o.created_at))}</td><td><a class="icon-btn" href="admin-pesanan.html">Detail</a></td></tr>`;
    }).join('');
    $('recentEmpty').classList.toggle('hidden',recent.length>0);
  }

  $('refreshBtn').addEventListener('click',load);
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
  }catch(err){$('loading').textContent=err.message||'Gagal memuat Dashboard Admin.';}
})();
