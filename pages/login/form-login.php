<?php
include "dist/koneksi.php";

$App = mysqli_query($conn, "SELECT * FROM tb_config WHERE id_app='1'");
$set = mysqli_fetch_array($App);
$alias = $set['nama_app'];
$parts = preg_split('/\s+/', trim($alias), 2);
$als = isset($parts[0]) ? $parts[0] : $alias;
$app = isset($parts[1]) ? $parts[1] : '';

$awareness_slides = array(
    array(
        'title' => 'Password Bersifat Pribadi',
        'desc' => 'Jangan pernah membagikan password SIMPEG kepada rekan kerja, atasan, atau pihak yang mengatasnamakan admin.',
        'icon' => 'fa-user-shield',
        'url' => 'https://www.cisa.gov/news-events/news/good-security-habits',
        'cta' => 'Baca panduan password aman',
        'source' => 'CISA'
    ),
    array(
        'title' => 'Waspadai Titip Login',
        'desc' => 'Titip login dapat memicu penyalahgunaan data dan membuat jejak audit tidak sesuai dengan pemilik akun.',
        'icon' => 'fa-user-lock',
        'url' => 'https://www.cisa.gov/secure-our-world/partner-resources',
        'cta' => 'Lihat materi Secure Our World',
        'source' => 'CISA'
    ),
    array(
        'title' => 'Jaga Kerahasiaan Token',
        'desc' => 'OTP, token, dan akses akun hanya boleh digunakan oleh pemilik akun yang sah.',
        'icon' => 'fa-key',
        'url' => 'https://www.cisa.gov/audiences/high-risk-communities/projectupskill/module2',
        'cta' => 'Pelajari proteksi akun',
        'source' => 'CISA'
    ),
    array(
        'title' => 'Verifikasi Permintaan Mencurigakan',
        'desc' => 'Abaikan pesan yang meminta password, file pegawai, atau akses sistem di luar jalur resmi SDM dan IT.',
        'icon' => 'fa-triangle-exclamation',
        'url' => 'https://iasc.ojk.go.id/',
        'cta' => 'Cek Indonesia Anti-Scam Centre',
        'source' => 'OJK IASC'
    ),
    array(
        'title' => 'Login Hanya di Kanal Resmi',
        'desc' => 'Pastikan Anda mengakses SIMPEG dari alamat resmi untuk menghindari phishing dan pencurian kredensial.',
        'icon' => 'fa-shield-halved',
        'url' => 'https://ojk.go.id/en/berita-dan-kegiatan/siaran-pers/Pages/The-Rise-of-Scams-OJK-and-the-Government-Launch-National-Campaign-to-Combat-Scam-and-Illegal-Financial-Activities.aspx',
        'cta' => 'Baca kampanye anti-scam OJK',
        'source' => 'OJK'
    ),
    array(
        'title' => 'Segera Laporkan Kejanggalan',
        'desc' => 'Jika akun terasa dipakai pihak lain atau ada aktivitas mencurigakan, segera hubungi admin untuk penanganan cepat.',
        'icon' => 'fa-bell',
        'url' => 'https://ojk.go.id/id/regulasi/Pages/Layanan-Pengaduan-Konsumen-di-Sektor-Jasa-Keuangan.aspx',
        'cta' => 'Lihat kanal pengaduan konsumen',
        'source' => 'OJK'
    ),
    array(
        'title' => 'Gunakan Password yang Kuat',
        'desc' => 'Hindari password yang mudah ditebak seperti tanggal lahir, nomor pegawai, atau pola yang berulang.',
        'icon' => 'fa-lock',
        'url' => 'https://www.cisa.gov/resources-tools/resources/four-cybersecurity-essentials-businesses',
        'cta' => 'Lihat cyber essentials',
        'source' => 'CISA'
    ),
    array(
        'title' => 'Jangan Simpan Sembarangan',
        'desc' => 'Jangan menulis password di chat grup, screenshot, catatan terbuka, atau file bersama.',
        'icon' => 'fa-file-shield',
        'url' => 'https://www.cisa.gov/secure-our-world/partner-resources',
        'cta' => 'Unduh materi edukasi keamanan',
        'source' => 'CISA'
    ),
    array(
        'title' => 'Semua Aktivitas Tercatat',
        'desc' => 'Setiap login dan perubahan data tercatat sebagai tanggung jawab pemilik akun. Gunakan akun Anda sendiri.',
        'icon' => 'fa-fingerprint',
        'url' => 'https://ojk.go.id/id/berita-dan-kegiatan/publikasi/Pages/Jadwal-Operasional-Kontak-157.aspx',
        'cta' => 'Lihat layanan Kontak 157',
        'source' => 'OJK Kontak 157'
    )
);

$slide_group_index = ((int) date('z')) % 3;
$daily_slides = array_slice($awareness_slides, $slide_group_index * 3, 3);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Access | <?= htmlspecialchars($alias) ?></title>

  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">

  <style>
    :root {
      --primary: #0f766e;
      --primary-dark: #0b5f59;
      --primary-soft: #dff5f2;
      --accent: #d4a63f;
      --accent-soft: #f7edd1;
      --teal: #0f766e;
      --slate-900: #0f172a;
      --slate-800: #1e293b;
      --slate-600: #475569;
      --slate-500: #64748b;
      --slate-300: #cbd5e1;
      --slate-200: #e2e8f0;
      --panel: #ffffff;
      --panel-soft: #f8fbff;
      --bg-top: #f7fbf9;
      --bg-bottom: #edf4ef;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(180deg, var(--bg-top) 0%, var(--bg-bottom) 100%);
    }

    body {
      overflow: hidden;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-shell {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 28px 24px 42px;
      background:
        radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 28%),
        radial-gradient(circle at bottom right, rgba(212, 166, 63, 0.10), transparent 24%);
    }

    .login-frame {
      width: 100%;
      max-width: 1180px;
      height: min(648px, calc(100vh - 64px));
      display: grid;
      grid-template-columns: 1.08fr 0.92fr;
      overflow: hidden;
      border-radius: 32px;
      border: 1px solid rgba(255,255,255,0.9);
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(14px);
      box-shadow: 0 28px 70px -30px rgba(15, 23, 42, 0.35);
      animation: fadeInUp 0.8s ease-out;
    }

    .login-visual {
      position: relative;
      overflow: hidden;
      color: #fff;
      background:
        radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 24%),
        radial-gradient(circle at bottom left, rgba(212, 166, 63, 0.16), transparent 28%),
        linear-gradient(145deg, #0f766e 0%, #176a88 46%, #3f83d5 100%);
    }

    .login-visual::before {
      content: '';
      position: absolute;
      top: -80px;
      right: -60px;
      width: 240px;
      height: 240px;
      border-radius: 50%;
      background: rgba(255,255,255,0.12);
      filter: blur(4px);
    }

    .login-visual::after {
      content: '';
      position: absolute;
      inset: 24px;
      border-radius: 26px;
      border: 1px solid rgba(255,255,255,0.08);
      pointer-events: none;
    }

    .visual-topbar {
      position: absolute;
      top: 30px;
      left: 30px;
      right: 30px;
      z-index: 2;
      display: flex;
      justify-content: flex-start;
    }

    .visual-brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 18px;
      background: rgba(255,255,255,0.14);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
      backdrop-filter: blur(10px);
    }

    .visual-brand img {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      object-fit: contain;
      background: rgba(255,255,255,0.96);
      padding: 4px;
    }

    .visual-brand strong {
      display: block;
      color: #fff;
      font-size: 1rem;
      letter-spacing: -0.3px;
      line-height: 1.1;
    }

    .visual-brand span {
      display: block;
      margin-top: 3px;
      color: rgba(255,255,255,0.82);
      font-size: 0.76rem;
      line-height: 1.2;
    }

    .visual-carousel,
    .visual-carousel .carousel-inner,
    .visual-carousel .carousel-item {
      width: 100%;
      height: 100%;
    }

    .visual-carousel.carousel-fade .carousel-item {
      opacity: 0;
      transition: opacity 0.65s ease-in-out;
    }

    .visual-carousel.carousel-fade .carousel-item.active,
    .visual-carousel.carousel-fade .carousel-item-next.carousel-item-left,
    .visual-carousel.carousel-fade .carousel-item-prev.carousel-item-right {
      opacity: 1;
    }

    .visual-carousel.carousel-fade .active.carousel-item-left,
    .visual-carousel.carousel-fade .active.carousel-item-right {
      opacity: 0;
    }

    .visual-slide {
      height: 100%;
      display: flex;
      align-items: flex-end;
      padding: 34px 36px 54px;
      position: relative;
      z-index: 1;
    }

    .visual-content {
      max-width: 470px;
    }

    .visual-mini-grid {
      position: absolute;
      top: 108px;
      left: 36px;
      right: 36px;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .visual-mini-card {
      padding: 14px 15px;
      border-radius: 18px;
      background: rgba(255,255,255,0.12);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
      backdrop-filter: blur(10px);
    }

    .visual-mini-card strong {
      display: block;
      font-size: 0.8rem;
      color: #fff;
      margin-bottom: 5px;
      letter-spacing: 0.1px;
    }

    .visual-mini-card p {
      margin: 0;
      font-size: 0.74rem;
      line-height: 1.45;
      color: rgba(255,255,255,0.8);
    }

    .visual-badge-row {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 18px;
    }

    .visual-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 14px;
      border-radius: 999px;
      background: rgba(255,255,255,0.18);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.16);
      backdrop-filter: blur(8px);
      font-size: 0.76rem;
      font-weight: 700;
    }

    .visual-icon {
      width: 52px;
      height: 52px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 18px;
      background: rgba(255,255,255,0.18);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.16);
      backdrop-filter: blur(8px);
      font-size: 1.2rem;
      color: #fff;
    }

    .visual-title {
      margin: 0 0 12px;
      font-size: clamp(1.62rem, 2.15vw, 2.28rem);
      line-height: 1.05;
      letter-spacing: -0.7px;
      font-weight: 800;
      color: #fff;
      max-width: 360px;
    }

    .visual-desc {
      margin: 0;
      max-width: 400px;
      font-size: 0.88rem;
      line-height: 1.56;
      color: rgba(255,255,255,0.95);
    }

    .visual-link-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 14px;
      flex-wrap: wrap;
    }

    .visual-link-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(255,255,255,0.18);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 700;
      text-decoration: none;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.14);
      backdrop-filter: blur(8px);
      transition: transform 0.2s ease, background 0.2s ease;
    }

    .visual-link-btn:hover {
      color: #fff;
      text-decoration: none;
      transform: translateY(-1px);
      background: rgba(255,255,255,0.22);
    }

    .visual-link-source {
      color: rgba(255,255,255,0.78);
      font-size: 0.72rem;
    }

    .visual-footer {
      margin-top: 18px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: rgba(255,255,255,0.88);
      font-size: 0.74rem;
      letter-spacing: 0.2px;
    }

    .visual-carousel .carousel-indicators {
      justify-content: flex-start;
      margin-left: 40px;
      margin-right: 0;
      bottom: 14px;
    }

    .visual-carousel .carousel-indicators li {
      width: 8px;
      height: 8px;
      margin: 0 4px;
      border: none;
      border-radius: 999px;
      background: rgba(255,255,255,0.45);
      transition: all 0.25s ease;
    }

    .visual-carousel .carousel-indicators .active {
      width: 28px;
      background: #fff5d9;
    }

    .login-panel {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 22px 24px 28px;
      background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(249,251,248,0.98) 100%);
    }

    .login-panel-card {
      width: 100%;
      max-width: 470px;
      padding: 22px 22px 24px;
      border-radius: 28px;
      border: 1px solid rgba(226,232,240,0.9);
      background: rgba(255,255,255,0.98);
      box-shadow: 0 18px 40px -28px rgba(15, 23, 42, 0.32);
    }

    .panel-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
    }

    .panel-logo img {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      object-fit: contain;
      background: #fff;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
      padding: 4px;
    }

    .panel-logo strong {
      display: block;
      color: var(--slate-900);
      font-size: 1.18rem;
      line-height: 1.1;
      letter-spacing: -0.4px;
    }

    .panel-logo span {
      display: block;
      margin-top: 2px;
      color: var(--slate-500);
      font-size: 0.78rem;
    }

    .panel-heading h1 {
      margin: 0 0 6px;
      font-size: 1.86rem;
      line-height: 1.08;
      letter-spacing: -0.8px;
      color: #10231d;
      font-weight: 800;
    }

    .panel-heading p {
      margin: 0 0 14px;
      font-size: 0.92rem;
      line-height: 1.56;
      color: #5d6d64;
    }

    .panel-note {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 14px;
      padding: 11px 13px;
      border-radius: 16px;
      border: 1px solid rgba(15, 118, 110, 0.12);
      background: linear-gradient(180deg, #f9fcfa 0%, #f2faf6 100%);
      color: #51645d;
      font-size: 0.84rem;
      line-height: 1.55;
    }

    .panel-note i {
      margin-top: 1px;
      color: var(--primary);
    }

    .form-group-modern {
      margin-bottom: 12px;
    }

    .form-label-modern {
      display: block;
      margin-bottom: 8px;
      color: #22312a;
      font-size: 0.85rem;
      font-weight: 700;
    }

    .form-field {
      position: relative;
    }

    .form-field-icon {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 1rem;
      z-index: 1;
    }

    .form-control-modern {
      width: 100%;
      height: 52px;
      padding: 0 16px 0 48px;
      border-radius: 14px;
      border: 1px solid var(--slate-200);
      background: #f8fbff;
      color: var(--slate-900);
      font-size: 0.95rem;
      outline: none;
      transition: all 0.25s ease;
    }

    .form-control-modern:focus {
      border-color: var(--primary);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.08);
    }

    .toggle-password {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      cursor: pointer;
      transition: color 0.2s ease;
    }

    .toggle-password:hover {
      color: var(--primary);
    }

    .panel-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin: 8px 0 14px;
    }

    .panel-meta label,
    .panel-meta a {
      font-size: 0.89rem;
    }

    .panel-meta a {
      color: var(--primary);
      font-weight: 700;
      text-decoration: none;
    }

    .btn-login {
      width: 100%;
      height: 52px;
      border: none;
      border-radius: 16px;
      background: linear-gradient(135deg, #0f766e 0%, #176a88 100%);
      color: #fff;
      font-size: 1rem;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.25s ease;
      box-shadow: 0 16px 28px -16px rgba(15, 118, 110, 0.45);
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #0b5f59 0%, #145b73 100%);
      transform: translateY(-1px);
    }

    .panel-security {
      margin-top: 14px;
      padding: 13px 14px 14px;
      border-radius: 16px;
      border: 1px solid rgba(203, 213, 225, 0.7);
      background: linear-gradient(180deg, #fbfdff 0%, #f6f9fe 100%);
      color: var(--slate-500);
      font-size: 0.81rem;
      line-height: 1.56;
    }

    .panel-security strong {
      display: block;
      margin-bottom: 4px;
      color: var(--slate-800);
      font-size: 0.9rem;
    }

    .panel-footer {
      margin-top: 16px;
      padding-top: 8px;
      text-align: center;
      color: var(--slate-500);
      font-size: 0.84rem;
    }

    .panel-footer a {
      color: var(--slate-900);
      font-weight: 700;
      text-decoration: none;
    }

    @media (max-width: 1200px) {
      .login-frame {
        max-width: 1040px;
        grid-template-columns: 1fr 0.95fr;
      }

      .visual-slide {
        padding: 28px 30px 48px;
      }

      .visual-mini-grid {
        top: 100px;
        left: 30px;
        right: 30px;
      }

      .login-panel {
        padding: 18px 20px 22px;
      }

      .login-panel-card {
        padding: 20px 20px 22px;
      }

      .visual-title {
        max-width: 380px;
        font-size: clamp(1.7rem, 2.35vw, 2.35rem);
      }

      .visual-desc {
        max-width: 360px;
      }
    }

    @media (max-width: 992px) {
      html,
      body {
        height: auto;
      }

      body {
        overflow-y: auto;
      }

      .login-shell {
        min-height: 100vh;
        padding: 22px 16px;
        align-items: flex-start;
      }

      .login-frame {
        display: block;
        width: 100%;
        max-width: 520px;
        height: auto;
        min-height: auto;
        border-radius: 0;
        border: none;
        background: transparent;
        box-shadow: none;
      }

      .login-visual {
        display: none;
      }

      .login-panel {
        padding: 0;
        background: transparent;
      }

      .login-panel-card {
        max-width: none;
        border: 1px solid rgba(226,232,240,0.9);
        box-shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.20);
        border-radius: 22px;
        padding: 22px 18px 22px;
      }

      .panel-heading h1 {
        font-size: 1.68rem;
      }

      .panel-meta {
        flex-wrap: wrap;
      }
    }

    @media (max-width: 576px) {
      .login-shell {
        padding: 12px 10px 18px;
      }

      .login-frame {
        max-width: 100%;
      }

      .login-panel-card {
        padding: 20px 14px 20px;
        border-radius: 18px;
      }

      .panel-logo {
        margin-bottom: 14px;
      }

      .panel-logo strong {
        font-size: 1.08rem;
      }

      .panel-heading h1 {
        font-size: 1.52rem;
      }

      .panel-heading p {
        margin-bottom: 16px;
        font-size: 0.9rem;
      }

      .panel-note {
        margin-bottom: 16px;
        padding: 11px 12px;
        font-size: 0.79rem;
      }

      .form-control-modern,
      .btn-login {
        height: 50px;
      }

      .panel-security {
        font-size: 0.8rem;
      }
    }

    @media (max-height: 780px) and (min-width: 993px) {
      .login-frame {
        height: min(648px, calc(100vh - 56px));
      }

      .login-panel {
        padding: 20px 18px 28px;
      }

      .login-panel-card {
        padding: 20px 20px 24px;
      }

      .panel-heading p,
      .panel-note,
      .panel-meta,
      .panel-security,
      .panel-footer {
        margin-bottom: 14px;
      }

      .panel-security {
        margin-top: 12px;
      }

      .visual-mini-grid {
        display: none;
      }
    }
  </style>
</head>
<body>

<div class="login-shell">
  <div class="login-frame">
    <section class="login-visual">
      <div class="visual-topbar">
        <div class="visual-brand">
          <img src="dist/img/bkk.png" alt="BKK">
          <div>
            <strong>BKK SimPeg</strong>
            <span>Dashboard Kepegawaian</span>
          </div>
        </div>
      </div>

      <div class="visual-mini-grid">
        <div class="visual-mini-card">
          <strong>Fraud Awareness</strong>
          <p>Pelajari kanal resmi pelaporan scam dan pencegahan penipuan finansial.</p>
        </div>
        <div class="visual-mini-card">
          <strong>Password Hygiene</strong>
          <p>Pakai password unik dan jangan pernah berbagi kredensial akun kerja.</p>
        </div>
      </div>

      <div id="loginCarousel" class="carousel slide carousel-fade visual-carousel" data-ride="carousel" data-interval="6500">
        <ol class="carousel-indicators">
          <?php foreach ($daily_slides as $index => $slide): ?>
            <li data-target="#loginCarousel" data-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>"></li>
          <?php endforeach; ?>
        </ol>

        <div class="carousel-inner">
          <?php foreach ($daily_slides as $index => $slide): ?>
            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
              <div class="visual-slide">
                <div class="visual-content">
                  <div class="visual-badge-row">
                    <div class="visual-badge">
                      <i class="fas <?= htmlspecialchars($slide['icon']) ?>"></i>
                      Awareness Keamanan SIMPEG
                    </div>
                    <div class="visual-icon">
                      <i class="fas <?= htmlspecialchars($slide['icon']) ?>"></i>
                    </div>
                  </div>
                  <h2 class="visual-title"><?= htmlspecialchars($slide['title']) ?></h2>
                  <p class="visual-desc"><?= htmlspecialchars($slide['desc']) ?></p>
                  <div class="visual-link-row">
                    <a href="<?= htmlspecialchars($slide['url']) ?>" target="_blank" rel="noopener noreferrer" class="visual-link-btn">
                      <i class="fas fa-external-link-alt"></i>
                      <span><?= htmlspecialchars($slide['cta']) ?></span>
                    </a>
                    <span class="visual-link-source">Sumber: <?= htmlspecialchars($slide['source']) ?></span>
                  </div>
                  <div class="visual-footer">
                    <i class="fas fa-shield-alt"></i>
                    <span>Akses internal pegawai BKK Jateng</span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-panel-card">
        <div class="panel-logo">
          <img src="dist/img/bkk.png" alt="BKK SimPeg" onerror="this.style.display='none'">
          <div>
            <strong><?= htmlspecialchars($als) ?><?php if ($app !== ''): ?> <span style="color:#0f766e;"><?= htmlspecialchars($app) ?></span><?php endif; ?></strong>
            <span>Portal internal kepegawaian</span>
          </div>
        </div>

        <!-- <div class="panel-heading">
          <h1>Selamat Datang Kembali</h1>
          <p>Masuk ke akun Anda untuk melanjutkan akses SIMPEG secara aman.</p>
        </div> -->

        <div class="panel-note">
          <i class="fas fa-shield-alt"></i>
          <div>Gunakan akun pribadi Anda. Seluruh aktivitas login dan perubahan data tercatat pada sistem internal SIMPEG.</div>
        </div>


        <form action="index.php?page=act-login&op=in" method="post">
          <div class="form-group-modern">
            <label class="form-label-modern">ID Pegawai</label>
            <div class="form-field">
              <i class="fas fa-user form-field-icon"></i>
              <input type="text" name="id_user" class="form-control-modern" required autocomplete="username">
            </div>
          </div>

          <div class="form-group-modern">
            <label class="form-label-modern">Password</label>
            <div class="form-field">
              <i class="fas fa-lock form-field-icon"></i>
              <input type="password" name="password" id="passwordInput" class="form-control-modern" required autocomplete="current-password">
              <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>
          </div>

          <div class="panel-meta">
            <div class="icheck-primary d-flex align-items-center">
              <input type="checkbox" id="remember" style="width:16px; height:16px; margin-right:8px; accent-color:#0f766e; cursor:pointer;">
              <label for="remember" style="color:#64748b; cursor:pointer; margin:0; font-weight:500;">Ingat Saya</label>
            </div>
            <a href="#" data-toggle="modal" data-target="#register">Lupa Password?</a>
          </div>

          <button type="submit" class="btn-login">
            Masuk Sekarang <i class="fas fa-arrow-right ml-2" style="font-size:0.8rem;"></i>
          </button>
        </form>

        <!-- <div class="panel-security">
          <strong>Perhatian Keamanan</strong>
          Password SIMPEG tidak boleh dibagikan, dipinjamkan, atau digunakan bersama. Seluruh aktivitas login dan perubahan data tercatat sebagai tanggung jawab pemilik akun.
        </div> -->

        <div class="panel-footer">
          Belum punya akun? <a href="#" data-toggle="modal" data-target="#register">Hubungi Admin</a>
        </div>
      </div>
    </section>
  </div>
</div>

<div class="modal fade" id="register">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:20px; overflow:hidden;">
      <div class="modal-body text-center p-5">
        <div style="width:80px; height:80px; background:#eff6ff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto;">
          <i class="fas fa-user-shield fa-2x" style="color:#0f766e;"></i>
        </div>
        <h4 class="font-weight-bold mb-2">Akses Terbatas</h4>
        <p class="text-muted mb-4">Aplikasi ini bersifat internal. Silakan hubungi <b>Bagian SDM / IT</b> untuk bantuan akun atau reset password.</p>
        <button type="button" class="btn px-5 rounded-pill font-weight-bold text-white" style="background:#0f766e; border-color:#0f766e;" data-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#passwordInput');

  if (togglePassword && password) {
    togglePassword.addEventListener('click', function () {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });
  }
</script>

</body>
</html>
