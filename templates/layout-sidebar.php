<?php
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$role = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';

if (!function_exists('sidebar_e')) {
    function sidebar_e($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sidebar_is_active')) {
    function sidebar_is_active($current, $pages) {
        return in_array($current, $pages);
    }
}

if (!function_exists('sidebar_normalize_url')) {
    function sidebar_normalize_url($url) {
        if (!function_exists('page_url')) {
            return $url;
        }

        if ($url === 'home-admin.php' || $url === 'home-admin.php?page=dashboard') {
            return page_url('dashboard');
        }

        $parts = parse_url($url);
        if (!isset($parts['path']) || $parts['path'] !== 'home-admin.php') {
            return $url;
        }

        $query = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $page = isset($query['page']) ? $query['page'] : 'dashboard';
        unset($query['page']);

        return page_url($page, $query);
    }
}

$logo_db = isset($set['logo']) ? $set['logo'] : '';
$path_logo = 'dist/img/' . $logo_db;
$logo_src = (!empty($logo_db) && file_exists($path_logo)) ? $path_logo : 'dist/img/bkk.png';
$can_approve = function_exists('userBisaApprovalOtorisasi') ? userBisaApprovalOtorisasi() : ($role === 'admin' || $role === 'superadmin' || $role === 'kepala');

$menus = array();

if ($role === 'admin' || $role === 'superadmin' || $role === 'kepala') {
    $menus[] = array(
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => $role === 'kepala' ? 'home-admin.php?page=dashboard-cabang' : 'home-admin.php',
        'pages' => array('dashboard', 'dashboard-cabang')
    );
}

if ($role === 'admin' || $role === 'superadmin') {
    $menus[] = array(
        'label' => 'Data Pegawai',
        'icon' => 'fas fa-users',
        'pages' => array('form-view-data-pegawai', 'form-master-data-pegawai', 'form-upload-data-pegawai', 'form-ubah-id-peg', 'form-view-data-mutasi'),
        'children' => array(
            array('label' => 'Daftar Pegawai', 'url' => 'home-admin.php?page=form-view-data-pegawai', 'pages' => array('form-view-data-pegawai')),
            array('label' => 'Tambah Pegawai', 'url' => 'home-admin.php?page=form-master-data-pegawai', 'pages' => array('form-master-data-pegawai')),
            array('label' => 'Pengangkatan Pegawai', 'url' => 'home-admin.php?page=form-ubah-id-peg', 'pages' => array('form-ubah-id-peg')),
            array('label' => 'Import Excel', 'url' => 'home-admin.php?page=form-upload-data-pegawai', 'pages' => array('form-upload-data-pegawai')),
            array('label' => 'Penonaktifan', 'url' => 'home-admin.php?page=form-view-data-mutasi', 'pages' => array('form-view-data-mutasi'))
        )
    );

    $menus[] = array(
        'label' => 'Master Data',
        'icon' => 'fas fa-database',
        'pages' => array(
            'form-view-data-suami-istri', 'form-master-data-suami-istri', 'form-edit-data-suami-istri',
            'form-view-data-anak', 'form-master-data-anak', 'form-edit-data-anak',
            'form-view-data-ortu', 'form-master-data-ortu', 'form-edit-data-ortu',
            'form-view-data-jabatan', 'form-view-data-pendidikan', 'view-data-biaya-pendidikan',
            'master-data-diklat', 'form-view-data-sertifikasi'
        ),
        'children' => array(
            array('label' => 'Suami Istri', 'url' => 'home-admin.php?page=form-view-data-suami-istri', 'pages' => array('form-view-data-suami-istri', 'form-master-data-suami-istri', 'form-edit-data-suami-istri')),
            array('label' => 'Anak', 'url' => 'home-admin.php?page=form-view-data-anak', 'pages' => array('form-view-data-anak', 'form-master-data-anak', 'form-edit-data-anak')),
            array('label' => 'Orang Tua', 'url' => 'home-admin.php?page=form-view-data-ortu', 'pages' => array('form-view-data-ortu', 'form-master-data-ortu', 'form-edit-data-ortu')),
            array('label' => 'Jabatan', 'url' => 'home-admin.php?page=form-view-data-jabatan', 'pages' => array('form-view-data-jabatan')),
            array('label' => 'Pendidikan', 'url' => 'home-admin.php?page=form-view-data-pendidikan', 'pages' => array('form-view-data-pendidikan')),
            array('label' => 'Daftar Kegiatan Diklat', 'url' => 'home-admin.php?page=view-data-biaya-pendidikan', 'pages' => array('view-data-biaya-pendidikan')),
            array('label' => 'Pelatihan', 'url' => 'home-admin.php?page=master-data-diklat', 'pages' => array('master-data-diklat')),
            array('label' => 'Sertifikasi', 'url' => 'home-admin.php?page=form-view-data-sertifikasi', 'pages' => array('form-view-data-sertifikasi'))
        )
    );

    $menus[] = array(
        'label' => 'Laporan',
        'icon' => 'fas fa-file-alt',
        'pages' => array('nominatif', 'keadaan-pegawai', 'formasi', 'rekap-biaya-diklat', 'view-rekap-biaya'),
        'children' => array(
            array('label' => 'Kepegawaian', 'url' => 'home-admin.php?page=nominatif', 'pages' => array('nominatif')),
            array('label' => 'Keadaan Pegawai', 'url' => 'home-admin.php?page=keadaan-pegawai', 'pages' => array('keadaan-pegawai')),
            array('label' => 'Formasi Jabatan', 'url' => 'home-admin.php?page=formasi', 'pages' => array('formasi')),
            array('label' => 'Pelatihan Pegawai', 'url' => 'home-admin.php?page=rekap-biaya-diklat', 'pages' => array('rekap-biaya-diklat')),
            array('label' => 'Daftar Diklat', 'url' => 'home-admin.php?page=view-rekap-biaya', 'pages' => array('view-rekap-biaya'))
        )
    );

    $menus[] = array('label' => 'Data User', 'icon' => 'fas fa-user-lock', 'url' => 'home-admin.php?page=form-view-data-user', 'pages' => array('form-view-data-user', 'form-master-data-user'));
}

if ($role === 'kepala') {
    $menus[] = array('label' => 'Data Pegawai', 'icon' => 'fas fa-user-friends', 'url' => 'home-admin.php?page=form-view-data-pegawai', 'pages' => array('form-view-data-pegawai', 'view-detail-data-pegawai'));
    $menus[] = array('label' => 'Laporan Kepegawaian', 'icon' => 'fas fa-clipboard-list', 'url' => 'home-admin.php?page=nominatif', 'pages' => array('nominatif'));
}

if ($role === 'user') {
    $menus[] = array('label' => 'Profil Saya', 'icon' => 'fas fa-user-circle', 'url' => 'home-admin.php?page=profil-pegawai', 'pages' => array('profil-pegawai', 'form-ganti-foto'));
    $menus[] = array('label' => 'Riwayat Pengajuan', 'icon' => 'fas fa-history', 'url' => 'home-admin.php?page=preview-edit', 'pages' => array('preview-edit'));
}

if ($can_approve) {
    $menus[] = array('label' => 'Approval Perubahan', 'icon' => 'fas fa-user-check', 'url' => 'home-admin.php?page=otorisasi-approval', 'pages' => array('otorisasi-approval', 'otorisasi-detail'));
}

if ($role === 'admin' || $role === 'superadmin') {
    $menus[] = array('label' => 'Pengaturan Aplikasi', 'icon' => 'fas fa-cogs', 'url' => 'home-admin.php?page=form-config-aplikasi', 'pages' => array('form-config-aplikasi'));
}
?>

<style>
    :root {
        --simpeg-sidebar-open: 268px;
        --simpeg-sidebar-rail: 76px;
    }
    .simpeg-sidebar-panel {
        position: fixed !important;
        top: 0;
        left: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        width: var(--simpeg-sidebar-open) !important;
        height: 100vh !important;
        min-height: 100vh !important;
        background:
            radial-gradient(circle at top left, rgba(20, 184, 166, 0.18), transparent 26%),
            linear-gradient(180deg, #0f172a 0%, #111827 52%, #0b1220 100%) !important;
        border-right: 1px solid rgba(255,255,255,0.06);
        box-shadow: 16px 0 36px rgba(15, 23, 42, 0.14);
        overflow: hidden;
        transition: width 0.24s ease, transform 0.24s ease, box-shadow 0.24s ease;
        z-index: 1045;
    }
    .main-header {
        margin-left: var(--simpeg-sidebar-open) !important;
        transition: margin-left 0.24s ease;
    }
    .content-wrapper,
    .main-footer {
        margin-left: var(--simpeg-sidebar-open) !important;
        transition: margin-left 0.24s ease;
    }
    .simpeg-sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.42);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.22s ease;
        z-index: 1040;
    }
    body.sidebar-open .simpeg-sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
    }
    .brand-link {
        display: flex !important;
        align-items: center;
        position: relative;
        gap: 0.8rem;
        min-height: 72px;
        background: rgba(255,255,255,0.03) !important;
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
        padding: 1rem 1rem 0.95rem !important;
        backdrop-filter: blur(10px);
        text-decoration: none !important;
    }
    .brand-link:hover,
    .brand-link:focus {
        text-decoration: none !important;
    }
    .brand-link .brand-image {
        flex: 0 0 auto;
        margin: 0 !important;
    }
    .sidebar-rail-logo {
        display: none;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(45, 212, 191, 0.22);
        box-shadow: 0 10px 24px rgba(0,0,0,0.16), inset 0 1px 0 rgba(255,255,255,0.08);
        opacity: 1;
        overflow: hidden;
    }
    .sidebar-rail-logo img {
        display: block;
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
        opacity: 1;
        filter: none;
        transform: translateZ(0);
    }
    .sidebar-rail-brand {
        display: none;
        position: absolute;
        top: 18px;
        left: 14px;
        z-index: 8;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(45, 212, 191, 0.22);
        box-shadow: 0 10px 24px rgba(0,0,0,0.16), inset 0 1px 0 rgba(255,255,255,0.08);
        text-decoration: none !important;
    }
    .sidebar-rail-brand img {
        display: block;
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
        filter: none;
        opacity: 1;
    }
    .brand-link .brand-text {
        color: #f8fafc !important;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 800 !important;
        letter-spacing: 0;
        font-size: 1rem;
    }
    .sidebar-brand-caption {
        display: flex;
        flex-direction: column;
        margin-left: 0;
        line-height: 1.15;
        min-width: 0;
        opacity: 1;
        transition: opacity 0.18s ease, transform 0.18s ease;
    }
    .sidebar-brand-caption small {
        color: rgba(226,232,240,0.68) !important;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: normal;
    }
    .sidebar {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 1.25rem;
    }
    .sidebar nav.mt-2 {
        margin-top: 0.25rem !important;
    }
    .sidebar .form-inline {
        padding: 0 14px !important;
        margin: 16px 0 14px !important;
        overflow: visible !important;
        transition: opacity 0.18s ease, max-height 0.18s ease, margin 0.18s ease;
    }
    .sidebar-search-shell {
        position: relative;
        width: 100%;
    }
    .sidebar-form {
        display: flex !important;
        flex-wrap: nowrap !important;
        width: 100% !important;
        position: relative;
        align-items: center;
    }
    .sidebar-form .form-control {
        background: rgba(255,255,255,0.08) !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        color: #e2e8f0 !important;
        height: 46px !important;
        border-radius: 15px !important;
        font-size: 0.88rem;
        padding-left: 2.65rem !important;
        padding-right: 0.95rem !important;
    }
    .sidebar-form .form-control::placeholder {
        color: rgba(226,232,240,0.44) !important;
    }
    .sidebar-form .btn {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent !important;
        border: 0 !important;
        color: rgba(226,232,240,0.72) !important;
        height: 28px !important;
        width: 28px !important;
        min-height: 28px !important;
        padding: 0 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: none !important;
    }
    .sidebar-form .btn:hover {
        transform: translateY(-50%);
    }
    .simpeg-side-nav {
        list-style: none;
        margin: 0;
        padding: 0 12px 0 10px;
    }
    .nav-header {
        color: #94a3b8 !important;
        font-weight: 800;
        padding: 0 10px;
        margin: 16px 0 10px;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        transition: opacity 0.18s ease;
    }
    .simpeg-side-item {
        margin-bottom: 7px;
        position: relative;
    }
    .simpeg-side-link {
        appearance: none;
        -webkit-appearance: none;
        background: transparent;
        border-radius: 16px !important;
        color: #d7e2f1 !important;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 700;
        padding: 0.82rem 1rem !important;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        border: 1px solid transparent;
        display: flex !important;
        align-items: center;
        justify-content: flex-start;
        min-height: 52px;
        width: 100%;
        text-decoration: none !important;
        text-align: left;
    }
    .simpeg-side-link:hover {
        background-color: rgba(255,255,255,0.08) !important;
        color: #fff !important;
        border-color: rgba(255,255,255,0.08);
        transform: none;
    }
    .simpeg-side-nav > .simpeg-side-item > .simpeg-side-link.active,
    .simpeg-side-nav > .simpeg-side-item.menu-open > .simpeg-side-link {
        background: linear-gradient(135deg, rgba(20,184,166,0.22), rgba(15,118,110,0.18)) !important;
        border-color: rgba(45, 212, 191, 0.18);
        color: #f0fdfa !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
    }
    .simpeg-side-link .simpeg-side-icon {
        color: inherit;
        margin-right: 12px;
        width: 20px;
        min-width: 20px;
        text-align: center;
        font-size: 1rem;
    }
    .simpeg-side-text {
        margin: 0;
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
        white-space: normal;
        line-height: 1.35;
        opacity: 1;
        transition: opacity 0.16s ease;
    }
    .simpeg-side-chevron {
        margin-left: auto;
        font-size: 0.8rem;
        opacity: 0.72;
        transition: transform 0.18s ease;
    }
    .simpeg-side-item.menu-open > .simpeg-side-link .simpeg-side-chevron {
        transform: rotate(-90deg);
    }
    .simpeg-side-item.has-treeview > .simpeg-side-submenu {
        display: none;
    }
    .simpeg-side-item.has-treeview.menu-open > .simpeg-side-submenu {
        display: block;
    }
    .simpeg-side-submenu {
        list-style: none;
        background: rgba(255,255,255,0.04) !important;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        margin: 8px 0 0 0;
        padding: 8px 8px 8px 9px;
        position: relative;
    }
    .simpeg-side-submenu::before {
        content: "";
        position: absolute;
        left: 14px;
        top: 10px;
        bottom: 10px;
        width: 1px;
        background: linear-gradient(180deg, rgba(148,163,184,0.28), rgba(148,163,184,0.08));
    }
    .simpeg-side-subitem {
        margin-bottom: 4px;
    }
    .simpeg-side-subitem:last-child {
        margin-bottom: 0;
    }
    .simpeg-side-sublink {
        display: flex !important;
        align-items: center;
        color: #cbd5e1 !important;
        padding: 0.7rem 0.8rem 0.7rem 26px !important;
        min-height: 40px;
        font-size: 0.78rem;
        font-weight: 600;
        border-radius: 12px !important;
        white-space: normal;
        line-height: 1.35;
        text-decoration: none !important;
        border: 1px solid transparent;
    }
    .simpeg-side-sublink:hover {
        background: rgba(255,255,255,0.07) !important;
        color: #fff !important;
    }
    .simpeg-side-sublink .simpeg-side-subicon {
        margin-right: 10px;
        width: 14px;
        min-width: 14px;
        font-size: 0.68rem;
        opacity: 0.86;
    }
    .simpeg-side-sublink.active {
        background: rgba(20,184,166,0.16) !important;
        color: #a7f3d0 !important;
        border-color: rgba(20,184,166,0.14);
    }
    .sidebar::-webkit-scrollbar {
        width: 5px;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(148,163,184,0.34);
        border-radius: 10px;
    }
    @media (min-width: 992px) {
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-sidebar-panel {
            width: var(--simpeg-sidebar-rail) !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .main-header,
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .content-wrapper,
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .main-footer {
            margin-left: var(--simpeg-sidebar-rail) !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .brand-link {
            justify-content: center;
            gap: 0;
            min-height: 92px;
            padding: 1rem 0.45rem !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .sidebar-rail-brand {
            display: inline-flex;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .brand-link .brand-image {
            display: none !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .sidebar-rail-logo {
            display: inline-flex;
            position: relative;
            z-index: 2;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .sidebar-brand-caption,
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-text {
            opacity: 0;
            pointer-events: none;
            width: 0;
            max-width: 0;
            overflow: hidden;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .nav-header {
            display: none !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .sidebar nav.mt-2 {
            margin-top: 0 !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .sidebar .form-inline {
            opacity: 0;
            pointer-events: none;
            max-height: 0;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-nav {
            padding: 10px 10px 0;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-item {
            margin-bottom: 10px;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-link {
            justify-content: center;
            min-height: 52px;
            padding-left: 0.65rem !important;
            padding-right: 0.65rem !important;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-link .simpeg-side-icon {
            margin-right: 0;
            font-size: 1.05rem;
        }
        body.simpeg-sidebar-auto:not(.simpeg-sidebar-expanded):not(.simpeg-sidebar-pinned) .simpeg-side-submenu {
            display: none !important;
        }
    }
    @media (max-width: 991.98px) {
        .simpeg-sidebar-panel {
            width: min(320px, 86vw) !important;
            height: 100dvh !important;
            min-height: 100dvh !important;
            transform: translateX(-108%);
            box-shadow: 22px 0 44px rgba(15, 23, 42, 0.26);
        }
        body.sidebar-open .simpeg-sidebar-panel {
            transform: translateX(0);
        }
        body.sidebar-open {
            overflow: hidden;
        }
        .main-header,
        .content-wrapper,
        .main-footer {
            margin-left: 0 !important;
        }
        .simpeg-side-nav {
            padding-right: 10px;
        }
        .simpeg-side-link {
            min-height: 50px;
        }
        .simpeg-side-submenu {
            padding: 8px 6px 8px 8px;
        }
        .simpeg-side-submenu::before {
            left: 12px;
        }
        .simpeg-side-sublink {
            padding-left: 24px !important;
            font-size: 0.77rem;
        }
    }
</style>

<aside class="simpeg-sidebar-panel">
    <a href="<?php echo sidebar_e(sidebar_normalize_url('home-admin.php')); ?>" class="sidebar-rail-brand" aria-label="Dashboard SIMPEG">
        <img src="<?php echo sidebar_e($logo_src); ?>?t=<?php echo time(); ?>" alt="SIMPEG">
    </a>
    <a href="<?php echo sidebar_e(sidebar_normalize_url('home-admin.php')); ?>" class="brand-link">
        <img src="<?php echo sidebar_e($logo_src); ?>?t=<?php echo time(); ?>"
             alt="App Logo"
             class="brand-image img-circle elevation-3"
             style="opacity:.9;width:33px;height:33px;object-fit:cover;">
        <span class="sidebar-rail-logo" aria-hidden="true">
            <img src="<?php echo sidebar_e($logo_src); ?>?t=<?php echo time(); ?>" alt="">
        </span>
        <span class="sidebar-brand-caption">
            <span class="brand-text"><?php echo isset($set['nama_app']) ? sidebar_e($set['nama_app']) : 'SIMPEG'; ?></span>
            <small>Dashboard Kepegawaian</small>
        </span>
    </a>

    <div class="sidebar text-sm">
        <div class="form-inline">
            <div class="sidebar-search-shell">
                <div class="input-group sidebar-form">
                    <button class="btn" type="button" aria-label="Cari menu"><i class="fas fa-search fa-fw"></i></button>
                    <input class="form-control" type="search" placeholder="Cari menu..." aria-label="Cari menu">
                </div>
            </div>
        </div>

        <nav class="mt-2">
            <ul class="simpeg-side-nav" role="menu">
                <li class="nav-header">MENU UTAMA</li>
                <?php foreach ($menus as $menu): ?>
                    <?php
                    $has_children = isset($menu['children']) && is_array($menu['children']);
                    $active = sidebar_is_active($page, $menu['pages']);
                    ?>
                    <li class="simpeg-side-item <?php echo $has_children ? 'has-treeview ' : ''; ?><?php echo ($has_children && $active) ? 'menu-open' : ''; ?>">
                        <?php if ($has_children): ?>
                            <button type="button" class="simpeg-side-link <?php echo $active ? 'active' : ''; ?>" data-simpeg-submenu-toggle aria-expanded="<?php echo ($has_children && $active) ? 'true' : 'false'; ?>">
                                <i class="simpeg-side-icon <?php echo sidebar_e($menu['icon']); ?>"></i>
                                <span class="simpeg-side-text">
                                    <?php echo sidebar_e($menu['label']); ?>
                                    <i class="simpeg-side-chevron fas fa-angle-left"></i>
                                </span>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo sidebar_e(sidebar_normalize_url($menu['url'])); ?>" class="simpeg-side-link <?php echo $active ? 'active' : ''; ?>">
                                <i class="simpeg-side-icon <?php echo sidebar_e($menu['icon']); ?>"></i>
                                <span class="simpeg-side-text"><?php echo sidebar_e($menu['label']); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ($has_children): ?>
                            <ul class="simpeg-side-submenu">
                                <?php foreach ($menu['children'] as $child): ?>
                                    <li class="simpeg-side-subitem">
                                        <a href="<?php echo sidebar_e(sidebar_normalize_url($child['url'])); ?>" class="simpeg-side-sublink <?php echo sidebar_is_active($page, $child['pages']) ? 'active' : ''; ?>">
                                            <i class="simpeg-side-subicon fas fa-chevron-right"></i>
                                            <span><?php echo sidebar_e($child['label']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</aside>
<div class="simpeg-sidebar-backdrop" data-simpeg-sidebar-close></div>
