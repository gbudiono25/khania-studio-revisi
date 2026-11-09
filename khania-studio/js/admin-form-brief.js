(async function(){
  const $=id=>document.getElementById(id);
  let client=null;
  const params=new URLSearchParams(location.search);
  const orderId=params.get('order_id')||'';
  const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const label=v=>String(v??'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
  const statusLabel=s=>({DRAFT:'DRAFT',SUBMITTED:'SUBMITTED',UNDER_REVIEW:'Dalam Pemeriksaan',NEED_CLIENT_INFO:'Perlu Info Klien',SCOPE_CONFIRMED:'Scope Dikonfirmasi',READY_FOR_PRODUCTION:'Siap Produksi'}[s]||label(s));
  const rupiah=n=>'Rp'+Number(n||0).toLocaleString('id-ID');

  function showError(m){$('errorBox').textContent=m;$('errorBox').classList.remove('hidden');}

  function valueHtml(v){
    if(v===null||v===undefined||v==='') return '<span class="empty-value">Tidak diisi</span>';
    if(Array.isArray(v)){
      if(!v.length)return '<span class="empty-value">Tidak dipilih</span>';
      return '<ul class="array-list">'+v.map(x=>'<li>'+valueHtml(x)+'</li>').join('')+'</ul>';
    }
    if(typeof v==='object'){
      return '<div class="object-box">'+Object.entries(v).map(([k,x])=>'<div class="data-row"><div class="data-key">'+esc(label(k))+'</div><div class="data-value">'+valueHtml(x)+'</div></div>').join('')+'</div>';
    }
    return esc(String(v));
  }

  function renderFieldRows(data){
    if(data===null||data===undefined||data==='') return '<div class="data-value">'+valueHtml(data)+'</div>';
    if(typeof data!=='object' || Array.isArray(data)) return '<div class="data-value">'+valueHtml(data)+'</div>';
    return Object.entries(data).map(([k,v])=>'<div class="data-row"><div class="data-key">'+esc(label(k))+'</div><div class="data-value">'+valueHtml(v)+'</div></div>').join('');
  }

  function renderSection(title,data){
    return '<details class="brief-section"><summary>'+esc(title)+'</summary><div class="section-body">'+renderFieldRows(data)+'</div></details>';
  }

  function renderSummary(data,brief){
    const rows=[
      ['Nama PIC',data.pic],['Peran PIC',data.pic_role],['Email PIC',data.pic_email],['WhatsApp PIC',data.pic_whatsapp],
      ['Kota PIC',data.pic_city],['Nama Bisnis',data.business_name],['Kategori Bisnis',data.business_category],
      ['Jenis Website',data.site_type],['Paket',data.package],['Kode Klien',data.client_code],['Order ID',data.order_id],
      ['Submission',data.submitted_via]
    ];
    return '<section class="brief-summary"><div class="summary-title">Ringkasan Data Brief</div><div class="summary-grid">'+
      rows.map(([k,v])=>'<div class="summary-item"><label>'+esc(k)+'</label><strong>'+valueHtml(v)+'</strong></div>').join('')+
      '</div></section>';
  }

  async function start(){
    if(!orderId){showError('Order ID tidak tersedia pada link Review Brief.');return;}
    const cfgRes=await fetch('api/supabase-config.php',{cache:'no-store'});const cfg=await cfgRes.json();
    if(!cfg.success)throw new Error(cfg.message||'Konfigurasi Supabase tidak tersedia.');
    client=supabase.createClient(cfg.url,cfg.anonKey);
    const {data:{session}}=await client.auth.getSession();
    if(!session){location.href='admin-login.html';return;}
    const {data:profile,error:pe}=await client.from('profiles').select('role,full_name').eq('id',session.user.id).maybeSingle();
    if(pe||!profile||profile.role!=='admin'){await client.auth.signOut();location.href='admin-login.html';return;}
    $('adminEmail').textContent=session.user.email||profile.full_name||'Admin';

    const {data:order,error:oe}=await client.from('orders').select('id,order_number,order_date,total_amount,status,clients(id,client_code,full_name,business_name,email,whatsapp),packages(name)').eq('id',orderId).maybeSingle();
    if(oe||!order)throw new Error(oe?.message||'Order tidak ditemukan.');
    const {data:brief,error:be}=await client.from('website_briefs').select('id,order_id,client_id,client_code,version,status,submitted_at,created_at,brief_data').eq('order_id',orderId).order('created_at',{ascending:false}).limit(1).maybeSingle();
    if(be||!brief)throw new Error(be?.message||'Client Brief belum ditemukan untuk order ini.');

    const c=order.clients||{};
    $('orderTitle').textContent=order.order_number||'-';
    $('orderSubtitle').textContent=(c.full_name||'-')+' — '+(c.business_name||'-');
    $('orderMeta').innerHTML=[
      ['Kode Klien',c.client_code||brief.client_code||'-'],['Paket',order.packages?.name||'-'],['Email',c.email||'-'],['WhatsApp',c.whatsapp||'-'],
      ['Total Order',rupiah(order.total_amount)],['Status Pembayaran',order.status==='payment_verified'?'Pembayaran Terverifikasi':label(order.status)],['Order Date',order.order_date||'-'],['Brief ID',brief.id||'-']
    ].map(x=>'<div class="meta-item"><label>'+esc(x[0])+'</label><strong>'+esc(x[1])+'</strong></div>').join('');

    $('briefStatus').textContent=statusLabel(brief.status);
    $('briefMeta').innerHTML='<div><label>Version</label><strong>'+esc(brief.version||'-')+'</strong></div><div><label>Dikirim</label><strong>'+esc(brief.submitted_at||'-')+'</strong></div><div><label>Dibuat</label><strong>'+esc(brief.created_at||'-')+'</strong></div>';

    const data=brief.brief_data||{};
    const sectionData=data.sections||{};
    const preferred=[
      'A. DATA ORDER & PEMESAN','B. INFORMASI BISNIS','C. TUJUAN UTAMA WEBSITE','D. STRUKTUR HALAMAN WEBSITE',
      'E. FITUR & FUNGSI WEBSITE','F. DOMAIN WEBSITE','G. WEBSITE LAMA & STATUS PEKERJAAN','H. PRODUK / JASA',
      'I. IDENTITAS BRAND & ARAH DESAIN','J. REFERENSI DESAIN','K. PESAN UTAMA / COPYWRITING','L. DATA SPESIFIK INDUSTRI',
      'M. KREDIBILITAS & TESTIMONIAL','N. UPLOAD ASET & DOKUMEN','O. KONTAK BISNIS & MEDIA SOSIAL','P. SEO & TARGET PASAR',
      'Q. PERIKSA & KONFIRMASI','R. PERMINTAAN TAMBAHAN / KEBUTUHAN KHUSUS'
    ];
    const sectionKeys=Object.keys(sectionData);
    const orderedSections=[...preferred.filter(k=>Object.prototype.hasOwnProperty.call(sectionData,k)),...sectionKeys.filter(k=>!preferred.includes(k))];

    let html=renderSummary(data,brief);
    if(data.products){
      html+=renderSection('H. DAFTAR PRODUK / JASA & UNGGULAN',data.products);
    }
    if(orderedSections.length){
      html+=orderedSections.map(k=>renderSection(k,sectionData[k])).join('');
    } else {
      html+='<section class="panel"><span class="empty-value">Data section Client Brief belum tersedia.</span></section>';
    }
    $('briefSections').innerHTML=html;

    $('loading').classList.add('hidden');$('app').classList.remove('hidden');
  }

  $('logoutBtn').addEventListener('click',async()=>{await client?.auth.signOut();location.href='admin-login.html';});
  try{await start();}catch(e){$('loading').classList.add('hidden');$('app').classList.remove('hidden');showError(e.message||'Gagal memuat Client Brief.');}
})();
