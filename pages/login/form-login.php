<?php
include "dist/koneksi.php";
// Cek koneksi & data config
$App = mysqli_query($conn, "SELECT * FROM tb_config WHERE id_app='1'");
$set = mysqli_fetch_array($App);
$alias = $set['nama_app'];
list($als, $app) = explode(" ", $alias);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Access | <?= $alias ?></title>
  
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  
  <style>
    :root {
        --primary-color: #2563eb;
        --primary-dark: #1d4ed8;
        --bg-color: #f3f6f9;
        --text-dark: #1e293b;
        --text-grey: #64748b;
    }

    body, html {
        height: 100%; margin: 0; padding: 0;
        /* Menggunakan System Font Stack (Offline tapi tetap modern) */
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
        background-color: var(--bg-color);
        overflow: hidden;
    }

    /* Animasi Masuk */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .login-wrapper {
        display: flex; align-items: center; justify-content: center;
        height: 100vh; width: 100%; padding: 20px;
        background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .login-card {
        width: 100%; max-width: 1000px; height: 80vh; min-height: 550px;
        background: #fff; border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        overflow: hidden; display: flex;
        animation: fadeInUp 0.8s ease-out;
    }

    /* --- BAGIAN KIRI: SLIDER --- */
    .login-left {
        flex: 1.2; position: relative; overflow: hidden;
    }
    
    .carousel, .carousel-inner, .carousel-item, .slider-bg {
        height: 100%; width: 100%;
    }

    .slider-bg {
        display: flex; flex-direction: column; justify-content: flex-end;
        padding: 60px; 
        color: #fff; position: relative;
        z-index: 1;
    }

    /* Overlay Gradient */
    .slider-bg::after {
        content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        /* Gradient lebih soft karena background sekarang warna solid */
        background: linear-gradient(to bottom, transparent 20%, rgba(0,0,0,0.6) 100%);
        z-index: -1;
    }

    /* --- BACKGROUND DIGANTI GRADIENT (Supaya Offline Ready) --- */
    /* Jika nanti punya gambar lokal, ganti 'linear-gradient(...)' dengan url('dist/img/gambar.jpg') */
    .bg-slide-1 { 
        background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
    }

    /*
    .bg-slide-1 { 
        
        background: url('dist/img/foto1.jpg') center/cover no-repeat; 
        
        background-blend-mode: overlay;
        background-color: rgba(0,0,0,0.5); 
    }
    */
    .bg-slide-2 { 
        background: linear-gradient(135deg, #8b5cf6, #6d28d9); 
    }
    .bg-slide-3 { 
        background: linear-gradient(135deg, #10b981, #047857); 
    }

    .hero-title {
        font-size: 42px; font-weight: 800; line-height: 1.1; margin-bottom: 15px;
        color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .hero-desc {
        font-size: 16px; color: rgba(255,255,255,0.95); line-height: 1.6; max-width: 90%; font-weight: 400;
    }

    /* Indikator Slider */
    .carousel-indicators { justify-content: flex-start; margin-left: 60px; bottom: 40px; }
    .carousel-indicators li { width: 8px; height: 8px; border-radius: 50%; background-color: rgba(255,255,255,0.5); border: none; margin: 0 4px; transition: all 0.3s; }
    .carousel-indicators .active { background-color: #fff; width: 25px; border-radius: 10px; }

    /* --- BAGIAN KANAN: FORM --- */
    .login-right {
        flex: 1; background: #fff;
        display: flex; flex-direction: column; justify-content: center;
        padding: 0 60px; position: relative;
    }

    .header-logo { display: flex; align-items: center; margin-bottom: 30px; }
    .header-logo img { width: 45px; height: 45px; border-radius: 12px; margin-right: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .header-logo h4 { margin: 0; font-weight: 800; color: var(--text-dark); font-size: 1.4rem; letter-spacing: -0.5px; }
    .header-logo h4 span { color: var(--primary-color); }

    .welcome-text h3 { font-weight: 800; color: var(--text-dark); margin-bottom: 8px; font-size: 1.8rem; letter-spacing: -0.5px; }
    .welcome-text p { color: var(--text-grey); font-size: 0.95rem; margin-bottom: 30px; }

    /* Input Modern */
    .input-group-modern { position: relative; margin-bottom: 20px; }
    .input-label { font-size: 0.85rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block; }
    
    .input-wrapper { position: relative; }
    .input-icon { 
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%); 
        color: #94a3b8; font-size: 1.1rem; transition: color 0.3s;
    }
    
    .form-control-modern {
        width: 100%; height: 50px; background: #fff; 
        border: 2px solid #e2e8f0; border-radius: 12px;
        padding: 0 16px 0 48px;
        font-size: 0.95rem; color: var(--text-dark); 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: inherit; /* Ikut font body */
    }
    
    .form-control-modern:focus { 
        border-color: var(--primary-color); 
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); 
        outline: none; 
    }
    .form-control-modern:focus + .input-icon { color: var(--primary-color); }

    .toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; }
    .toggle-password:hover { color: var(--primary-color); }

    .btn-modern {
        width: 100%; height: 50px; 
        background: var(--primary-color);
        border: none; border-radius: 12px; 
        color: #fff; font-weight: 700; font-size: 1rem;
        cursor: pointer; transition: all 0.3s; 
        box-shadow: 0 10px 20px -10px rgba(37, 99, 235, 0.5);
        margin-top: 15px;
    }
    .btn-modern:hover { 
        background: var(--primary-dark); 
        transform: translateY(-2px); 
        box-shadow: 0 15px 25px -10px rgba(37, 99, 235, 0.6); 
    }

    /* Responsif HP */
    @media (max-width: 992px) {
        body, html { overflow: auto; height: auto; }
        .login-card { flex-direction: column; height: auto; max-width: 100%; border-radius: 0; box-shadow: none; min-height: 100vh; }
        .login-left { display: none; }
        .login-right { padding: 40px 30px; height: 100vh; justify-content: center; }
        .login-wrapper { padding: 0; background: #fff; align-items: flex-start; }
    }
</style>
</head>

<body>

<div class="login-wrapper">
    <div class="login-card">
        
        <div class="login-left">
            <div id="loginCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
                <ol class="carousel-indicators">
                    <li data-target="#loginCarousel" data-slide-to="0" class="active"></li>
                    <li data-target="#loginCarousel" data-slide-to="1"></li>
                    <li data-target="#loginCarousel" data-slide-to="2"></li>
                </ol>

                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="slider-bg bg-slide-1">
                            <div class="hero-title">Human Capital <br>System</div>
                            <p class="hero-desc">Platform terintegrasi untuk manajemen data kepegawaian yang aman, presisi, dan real-time.</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="slider-bg bg-slide-2">
                            <div class="hero-title">Data Driven <br>Analytics</div>
                            <p class="hero-desc">Pantau kinerja dan statistik pegawai dengan dashboard yang mudah dipahami.</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="slider-bg bg-slide-3">
                            <div class="hero-title">Secure & <br>Reliable</div>
                            <p class="hero-desc">Keamanan data prioritas utama kami. Akses terbatas hanya untuk personel berwenang.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="header-logo mt-4">
                <img src="dist/img/bkk.png" alt="Logo" onerror="this.style.display='none'"> 
                <h4><?= $als ?> <span><?= $app ?></span></h4>
            </div>

            <div class="welcome-text">
                <h3>Welcome Back! 👋</h3>
                <p>Silakan masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <form action="index.php?page=act-login&op=in" method="post">
                <div class="input-group-modern">
                    <label class="input-label">ID Pegawai</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="id_user" class="form-control-modern" placeholder="Contoh: 102-120" required autocomplete="off">
                    </div>
                </div>

                <div class="input-group-modern">
                    <label class="input-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="passwordInput" class="form-control-modern" placeholder="••••••••" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="icheck-primary d-flex align-items-center">
                        <input type="checkbox" id="remember" style="width:16px; height:16px; margin-right:8px; accent-color:#2563eb; cursor: pointer;">
                        <label for="remember" style="color:#64748b; font-size:0.9rem; cursor:pointer; margin:0; font-weight: 500;">Ingat Saya</label>
                    </div>
                    <a href="#" class="small font-weight-bold" style="color:#2563eb; text-decoration:none;">Lupa Password?</a>
                </div>

                <button type="submit" class="btn-modern">
                    Masuk Sekarang <i class="fas fa-arrow-right ml-2" style="font-size: 0.8rem;"></i>
                </button>
            </form>

            <div class="mt-4 text-center">
                <p class="small text-muted">Belum punya akun? <a href="#" data-toggle="modal" data-target="#register" class="font-weight-bold" style="color: var(--text-dark);">Hubungi Admin</a></p>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="register">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius: 20px; overflow: hidden;">
        <div class="modal-body text-center p-5">
           <div style="width: 80px; height: 80px; background: #eff6ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
               <i class="fas fa-user-shield fa-2x text-primary"></i>
           </div>
           <h4 class="font-weight-bold mb-2">Akses Terbatas</h4>
           <p class="text-muted mb-4">Aplikasi ini bersifat internal. Silakan hubungi <b>Bagian SDM / IT</b> untuk pembuatan akun baru.</p>
           <button type="button" class="btn btn-primary px-5 rounded-pill font-weight-bold" data-dismiss="modal">Mengerti</button>
        </div>
      </div>
    </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
  // Script Toggle Password
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#passwordInput');
  
  togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    // toggle the eye icon
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
  });
</script>

</body>
</html>
