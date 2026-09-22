(async function(){
  const msg = document.getElementById('loginMsg');
  function show(text, ok=false){ msg.textContent=text; msg.className='message '+(ok?'ok':'error'); }
  try{
    const cfgRes = await fetch('api/supabase-config.php',{cache:'no-store'});
    const cfg = await cfgRes.json();
    if(!cfg.success) throw new Error(cfg.message || 'Konfigurasi Supabase tidak tersedia.');
    const { createClient } = supabase;
    const client = createClient(cfg.url,cfg.anonKey);

    const { data:{session} } = await client.auth.getSession();
    if(session){ location.href='admin-pesanan.html'; return; }

    document.getElementById('loginForm').addEventListener('submit', async e=>{
      e.preventDefault();
      show('Memproses login...',true);
      const {data,error} = await client.auth.signInWithPassword({
        email:document.getElementById('email').value.trim(),
        password:document.getElementById('password').value
      });
      if(error){ show(error.message || 'Login gagal.'); return; }

      const {data:profile,error:profileError} = await client
        .from('profiles').select('role').eq('id',data.user.id).maybeSingle();

      if(profileError || !profile || profile.role !== 'admin'){
        await client.auth.signOut();
        show('Akun berhasil login, tetapi belum memiliki role admin.');
        return;
      }
      location.href='admin-pesanan.html';
    });
  }catch(err){ show(err.message || 'Gagal memuat sistem login.'); }
})();
