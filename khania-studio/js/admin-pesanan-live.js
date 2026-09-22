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
  function briefLabel(o){
    const b=(o.website_briefs||[])[0];
    if(!b) return 'Belum Dikirim';
    return b.status==='DRAFT' ? 'Form Brief Dikirim' : b.status;
  }
  function briefClass(v){return ['Belum Dikirim','Form Brief Dikirim'].includes(v)?(v==='Belum Dikirim'?'waiting':'brief'):'brief';}
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
        website_briefs(id,status,submitted_at,created_at)
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
      ['Status Form Brief',b],['Tanggal Order',o.order_date||'-']
    ].map(x=>`<div class="detail-item"><label>${x[0]}</label><strong>${esc(x[1])}</strong></div>`).join('');
    $('detailBriefBtn').disabled=!canSend(o);
    $('detailBriefBtn').textContent=b==='Form Brief Dikirim'?'Kirim Ulang Form Brief':'Kirim Form Brief';

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

  document.addEventListener('click',e=>{
    const a=e.target.closest('[data-action]');
    if(a){
      const o=orders.find(x=>x.id===a.dataset.id);
      if(!o)return;
      if(a.dataset.action==='detail')showDetail(o);
      else if(a.dataset.action==='verify')verifyPayment(o);
      else if(a.dataset.action==='brief')toast('Tahap pengiriman Form Brief akan kita sambungkan setelah verifikasi pembayaran stabil.',true);
    }
    const c=e.target.closest('[data-close]');
    if(c)$(c.dataset.close).classList.add('hidden');
  });

  $('detailBriefBtn').addEventListener('click',()=>toast('Tahap pengiriman Form Brief akan kita sambungkan setelah verifikasi pembayaran stabil.',true));
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
