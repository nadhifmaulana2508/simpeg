<?php
/*********************************************************
 * FILE    : pages/pegawai/profil-pegawai.php
 * MODULE  : Profil Pegawai (User Only Change Photo)
 * VERSION : v8.0
 *********************************************************/

if (session_id() === '') session_start();

// --- 1. CEK LOGIN ---
if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

include "dist/koneksi.php";
include_once "dist/functions.php";

if (!function_exists('profile_e')) {
    function profile_e($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('profile_clean')) {
    function profile_clean($conn, $s) {
        return mysqli_real_escape_string($conn, trim($s));
    }
}

if (!function_exists('profile_get_family_row')) {
    function profile_get_family_row($conn, $table, $pk, $id, $id_peg) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $pk = preg_replace('/[^a-zA-Z0-9_]/', '', $pk);
        $id_safe = profile_clean($conn, $id);
        $peg_safe = profile_clean($conn, $id_peg);
        $q = mysqli_query($conn, "SELECT * FROM $table WHERE $pk='$id_safe' AND id_peg='$peg_safe' LIMIT 1");
        return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
    }
}

if (!function_exists('profile_insert_pending')) {
    function profile_insert_pending($conn, $id_peg, $jenis, $data_lama, $data_baru, $id_user, $kode_kantor = '') {
        $id_peg = profile_clean($conn, $id_peg);
        $jenis = profile_clean($conn, $jenis);
        $data_lama = profile_clean($conn, $data_lama);
        $data_baru = profile_clean($conn, $data_baru);
        $id_user = profile_clean($conn, $id_user);
        $kode_kantor = profile_clean($conn, $kode_kantor);

        $sql = "INSERT INTO tb_edit_pending
                (kode_kantor, id_peg, jenis_data, data_lama, data_baru, id_user, status_otorisasi, tanggal_pengajuan)
                VALUES ('$kode_kantor', '$id_peg', '$jenis', '$data_lama', '$data_baru', '$id_user', 'Menunggu', NOW())";
        if (mysqli_query($conn, $sql)) {
            return true;
        }

        $sql = "INSERT INTO tb_edit_pending
                (kode_kantor, id_peg, jenis_data, data_lama, data_baru, id_user, status_otorisasi, tanggal_pengajuan)
                VALUES ('$kode_kantor', '$id_peg', '$jenis', '$data_lama', '$data_baru', '$id_user', 'pending', NOW())";
        if (mysqli_query($conn, $sql)) {
            return true;
        }

        $sql = "INSERT INTO tb_edit_pending
                (id_peg, jenis_data, data_lama, data_baru, id_user, status_otorisasi, tanggal_pengajuan)
                VALUES ('$id_peg', '$jenis', '$data_lama', '$data_baru', '$id_user', 'Menunggu', NOW())";
        return mysqli_query($conn, $sql);
    }
}

if (!function_exists('profile_get_kode_kantor_approval')) {
    function profile_get_kode_kantor_approval($conn, $id_peg) {
        $id_peg = profile_clean($conn, $id_peg);
        $q = mysqli_query($conn, "
            SELECT unit_kerja
            FROM tb_jabatan
            WHERE id_peg = '$id_peg' AND LOWER(status_jab) = 'aktif'
            ORDER BY tmt_jabatan DESC, id_jab DESC
            LIMIT 1
        ");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            return function_exists('simpegKodeKantorApproval')
                ? simpegKodeKantorApproval($row['unit_kerja'])
                : substr($row['unit_kerja'], 0, 3);
        }

        $fallback = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';
        return function_exists('simpegKodeKantorApproval')
            ? simpegKodeKantorApproval($fallback)
            : substr($fallback, 0, 3);
    }
}

if (!function_exists('profile_field')) {
    function profile_field($row, $key, $default = '-') {
        return (isset($row[$key]) && trim((string) $row[$key]) !== '') ? $row[$key] : $default;
    }
}

if (!function_exists('profile_page_url')) {
    function profile_page_url($page, $params = array()) {
        if (function_exists('page_url')) {
            return page_url($page, $params);
        }

        $url = 'home-admin.php?page=' . urlencode($page);
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return $url;
    }
}

if (!function_exists('profile_current_biodata')) {
    function profile_current_biodata($peg) {
        return array(
            'nip' => profile_field($peg, 'nip', ''),
            'nama' => profile_field($peg, 'nama', ''),
            'tempat_lhr' => profile_field($peg, 'tempat_lhr', ''),
            'tgl_lhr' => profile_field($peg, 'tgl_lhr', ''),
            'jk' => profile_field($peg, 'jk', ''),
            'agama' => profile_field($peg, 'agama', ''),
            'gol_darah' => profile_field($peg, 'gol_darah', ''),
            'status_nikah' => profile_field($peg, 'status_nikah', ''),
            'status_kepeg' => profile_field($peg, 'status_kepeg', ''),
            'alamat' => profile_field($peg, 'alamat', ''),
            'telp' => profile_field($peg, 'telp', ''),
            'email' => profile_field($peg, 'email', ''),
            'bpjstk' => profile_field($peg, 'bpjstk', ''),
            'bpjskes' => profile_field($peg, 'bpjskes', '')
        );
    }
}

if (!function_exists('profile_get_pegawai_row')) {
    function profile_get_pegawai_row($conn, $id_peg) {
        $id_peg = profile_clean($conn, $id_peg);
        $q = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg = '$id_peg' LIMIT 1");
        return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
    }
}

// --- 2. LOGIKA ID PEGAWAI & PERMISSION ---
$hak_akses_session = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : 'user';
$id_session_peg    = isset($_SESSION['id_pegawai']) ? $_SESSION['id_pegawai'] : '';

// Tentukan ID Pegawai yang akan ditampilkan
$id_peg = null;

if (isset($_GET['id_peg'])) {
    $id_peg = mysqli_real_escape_string($conn, $_GET['id_peg']);
} elseif (!empty($id_session_peg)) {
    $id_peg = $id_session_peg;
}

if (empty($id_peg)) {
    echo '<div class="alert alert-danger m-3">ID Pegawai tidak ditemukan.</div>';
    exit;
}

// --- LOGIKA HAK AKSES BARU ---
$is_admin_or_kepala = ($hak_akses_session == 'admin' || $hak_akses_session == 'kepala');
$is_own_profile     = ($id_peg == $id_session_peg);
$bisa_request_keluarga = ($hak_akses_session == 'user' && $is_own_profile);
$bisa_request_biodata = ($hak_akses_session == 'user' && $is_own_profile);
$bisa_kelola_keluarga = ($is_admin_or_kepala || $bisa_request_keluarga);

// 1. Hak Ganti Foto: Boleh Admin/Kepala ATAU Pemilik Profil Sendiri
$bisa_ganti_foto = ($is_admin_or_kepala || $is_own_profile);

// 2. Hak Edit Data (Biodata/Riwayat): HANYA Boleh Admin/Kepala
$bisa_edit_data  = $is_admin_or_kepala;

$family_status = '';
$family_msg = '';
$biodata_status = '';
$biodata_msg = '';
$biodata_preview_payload = array();
$kode_kantor_pengajuan = profile_get_kode_kantor_approval($conn, $id_peg);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['biodata_request'])) {
    if (!$bisa_request_biodata) {
        $biodata_status = 'error';
        $biodata_msg = 'Akses pengajuan perubahan biodata ditolak.';
    } else {
        $payload = array(
            'nip' => isset($_POST['nip']) ? trim($_POST['nip']) : '',
            'nama' => isset($_POST['nama']) ? trim($_POST['nama']) : '',
            'tempat_lhr' => isset($_POST['tempat_lhr']) ? trim($_POST['tempat_lhr']) : '',
            'tgl_lhr' => isset($_POST['tgl_lhr']) ? trim($_POST['tgl_lhr']) : '',
            'jk' => isset($_POST['jk']) ? trim($_POST['jk']) : '',
            'agama' => isset($_POST['agama']) ? trim($_POST['agama']) : '',
            'gol_darah' => isset($_POST['gol_darah']) ? trim($_POST['gol_darah']) : '',
            'status_nikah' => isset($_POST['status_nikah']) ? trim($_POST['status_nikah']) : '',
            'status_kepeg' => isset($_POST['status_kepeg']) ? trim($_POST['status_kepeg']) : '',
            'alamat' => isset($_POST['alamat']) ? trim($_POST['alamat']) : '',
            'telp' => isset($_POST['telp']) ? trim($_POST['telp']) : '',
            'email' => isset($_POST['email']) ? trim($_POST['email']) : '',
            'bpjstk' => isset($_POST['bpjstk']) ? trim($_POST['bpjstk']) : '',
            'bpjskes' => isset($_POST['bpjskes']) ? trim($_POST['bpjskes']) : ''
        );

        if ($payload['nama'] === '') {
            $biodata_status = 'error';
            $biodata_msg = 'Nama wajib diisi.';
        } else {
            $current_peg = profile_get_pegawai_row($conn, $id_peg);
            $old_json = json_encode($current_peg ? profile_current_biodata($current_peg) : array());
            $new_json = json_encode($payload);

            if (profile_insert_pending($conn, $id_peg, 'biodata_update', $old_json, $new_json, $_SESSION['id_user'], $kode_kantor_pengajuan)) {
                $biodata_status = 'success';
                $biodata_msg = 'Pengajuan perubahan biodata berhasil dikirim dan menunggu approval.';
                $biodata_preview_payload = $payload;
            } else {
                $biodata_status = 'error';
                $biodata_msg = 'Gagal menyimpan pengajuan: ' . mysqli_error($conn);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['family_request'])) {
    if (!$bisa_request_keluarga) {
        $family_status = 'error';
        $family_msg = 'Akses pengajuan perubahan keluarga ditolak.';
    } else {
        $scope = isset($_POST['family_scope']) ? $_POST['family_scope'] : '';
        $action = isset($_POST['family_action']) ? $_POST['family_action'] : '';
        $record_id = isset($_POST['record_id']) ? trim($_POST['record_id']) : '';

        $family_map = array(
            'pasangan' => array('table' => 'tb_suamiistri', 'pk' => 'id_si'),
            'anak' => array('table' => 'tb_anak', 'pk' => 'id_anak'),
            'ortu' => array('table' => 'tb_ortu', 'pk' => 'id_ortu')
        );

        if (!isset($family_map[$scope]) || !in_array($action, array('create', 'update', 'delete'))) {
            $family_status = 'error';
            $family_msg = 'Jenis pengajuan tidak valid.';
        } else {
            $data_lama = null;
            if ($action !== 'create') {
                $data_lama = profile_get_family_row($conn, $family_map[$scope]['table'], $family_map[$scope]['pk'], $record_id, $id_peg);
                if (!$data_lama) {
                    $family_status = 'error';
                    $family_msg = 'Data keluarga tidak ditemukan.';
                }
            }

            if ($family_status !== 'error') {
                $payload = array(
                    'id_peg' => $id_peg,
                    'nik' => isset($_POST['nik']) ? trim($_POST['nik']) : '',
                    'nama' => isset($_POST['nama']) ? trim($_POST['nama']) : '',
                    'tmp_lhr' => isset($_POST['tmp_lhr']) ? trim($_POST['tmp_lhr']) : '',
                    'tgl_lhr' => isset($_POST['tgl_lhr']) ? trim($_POST['tgl_lhr']) : '',
                    'pendidikan' => isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '',
                    'id_pekerjaan' => isset($_POST['id_pekerjaan']) ? trim($_POST['id_pekerjaan']) : '',
                    'pekerjaan' => isset($_POST['pekerjaan']) ? trim($_POST['pekerjaan']) : '',
                    'status_hub' => isset($_POST['status_hub']) ? trim($_POST['status_hub']) : ''
                );

                if ($scope === 'pasangan') {
                    $payload['hp'] = isset($_POST['hp']) ? trim($_POST['hp']) : '';
                    $payload['bpjs_pasangan'] = isset($_POST['bpjs_pasangan']) ? trim($_POST['bpjs_pasangan']) : '';
                }

                if ($scope === 'anak') {
                    $payload['anak_ke'] = isset($_POST['anak_ke']) ? trim($_POST['anak_ke']) : '';
                    $payload['bpjs_anak'] = isset($_POST['bpjs_anak']) ? trim($_POST['bpjs_anak']) : '';
                }

                $request = array(
                    'scope' => $scope,
                    'action' => $action,
                    'record_id' => $record_id,
                    'payload' => $payload
                );

                $jenis_data = 'keluarga_' . $scope . '_' . $action;
                $old_json = $data_lama ? json_encode($data_lama) : '';
                $new_json = json_encode($request);

                if ($action !== 'delete' && trim($payload['nama']) === '') {
                    $family_status = 'error';
                    $family_msg = 'Nama wajib diisi.';
                } elseif (profile_insert_pending($conn, $id_peg, $jenis_data, $old_json, $new_json, $_SESSION['id_user'], $kode_kantor_pengajuan)) {
                    $family_status = 'success';
                    $family_msg = 'Pengajuan perubahan keluarga berhasil dikirim dan menunggu approval.';
                } else {
                    $family_status = 'error';
                    $family_msg = 'Gagal menyimpan pengajuan: ' . mysqli_error($conn);
                }
            }
        }
    }
}


// --- 3. QUERY DATA UTAMA ---
$tampilPeg = mysqli_query($conn, "SELECT p.*, u.id_user, u.hak_akses FROM tb_pegawai p LEFT JOIN tb_user u ON u.id_pegawai = p.id_peg WHERE p.id_peg = '$id_peg'");

if (mysqli_num_rows($tampilPeg) == 0) {
    echo '<div class="alert alert-warning m-3">Data pegawai tidak ditemukan.</div>';
    exit;
}
$peg = mysqli_fetch_array($tampilPeg);
if (!empty($biodata_preview_payload)) {
    foreach ($biodata_preview_payload as $field => $value) {
        $peg[$field] = $value;
    }
}

$jabatan_aktif_info = null;
$qJabAktifInfo = mysqli_query($conn, "
    SELECT
        j.id_jab,
        j.kode_jabatan,
        j.unit_kerja,
        j.tmt_jabatan,
        j.status_jab,
        COALESCE(m.nama_jabatan, j.jabatan) AS nm_jab,
        k.kode_cabang,
        k.nama_kantor
    FROM tb_jabatan j
    LEFT JOIN tb_master_jabatan m ON m.kode_jabatan = j.kode_jabatan
    LEFT JOIN tb_kantor k ON k.kode_kantor_detail = j.unit_kerja
    WHERE j.id_peg = '$id_peg' AND LOWER(j.status_jab) = 'aktif'
    ORDER BY j.tmt_jabatan DESC, j.id_jab DESC
    LIMIT 1
");
if ($qJabAktifInfo && mysqli_num_rows($qJabAktifInfo) > 0) {
    $jabatan_aktif_info = mysqli_fetch_assoc($qJabAktifInfo);
}

$family_pendidikan_options = array('Belum Sekolah', 'PAUD', 'TK', 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3');
$biodata_agama_options = array('Islam', 'Protestan', 'Katolik', 'Hindu', 'Budha', 'KongHuCu');
$biodata_gol_darah_options = array('-', 'A', 'B', 'AB', 'O');
$biodata_status_nikah_options = array('Menikah', 'Belum Menikah', 'Janda', 'Duda');
$biodata_status_kepeg_options = array('Tetap', 'Kontrak', 'Outsource');
$family_pekerjaan_options = array();
$qFamilyJob = mysqli_query($conn, "SELECT id_pekerjaan, desc_pekerjaan FROM tb_master_pekerjaan ORDER BY desc_pekerjaan ASC");
if ($qFamilyJob) {
    while ($job = mysqli_fetch_assoc($qFamilyJob)) {
        $family_pekerjaan_options[] = $job;
    }
}

// --- 4. ASSETS FOTO ---
$foto_db    = isset($peg['foto']) ? trim($peg['foto']) : '';
$jk         = isset($peg['jk']) ? strtolower(trim($peg['jk'])) : '';
$avatar_def = function_exists('simpeg_avatar_default') ? simpeg_avatar_default($jk) : 'dist/img/avatar5.png';
$src_foto   = function_exists('simpeg_resolve_photo_path') ? simpeg_resolve_photo_path($foto_db, $jk) : $avatar_def;
?>

<style>
    .profile-shell {
        background: linear-gradient(180deg, #f4fbfa 0%, #ffffff 100%);
        border-radius: 22px;
        padding: 18px;
    }
    .profile-header-cover {
        background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
        height: 142px;
        border-radius: 18px 18px 0 0;
    }
    .profile-user-img {
        width: 130px; height: 130px; margin-top: -65px;
        border: 5px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        background: #fff; object-fit: cover;
    }
    .profile-card {
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(15, 118, 110, 0.08);
    }
    .profile-badge {
        background: rgba(20, 184, 166, 0.12);
        color: #0f766e;
        padding: 8px 14px;
        border-radius: 999px;
        font-weight: 700;
    }
    .nav-pills-custom { border-bottom: 1px solid #e8efee; margin-bottom: 20px; }
    .nav-pills-custom .nav-link {
        color: #5f6f6e; font-weight: 700; padding: 14px 18px;
        border-radius: 0; border-bottom: 3px solid transparent;
    }
    .nav-pills-custom .nav-link.active {
        background-color: #0f766e !important; color: #fff !important; border-bottom: 3px solid #14b8a6;
    }
    .modal .modal-content {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 26px 70px rgba(15, 35, 26, 0.22);
    }
    .modal .modal-header {
        background: #0f766e !important;
        color: #fff !important;
        border: 0;
        padding: 1rem 1.25rem;
    }
    .modal .modal-title { font-weight: 800; }
    .modal .close { opacity: .8; text-shadow: none; }
    .modal .modal-body { padding: 1rem 1.25rem; }
    .modal .table { margin-bottom: 0; border-radius: 14px; overflow: hidden; }
    .modal .table thead th {
        background: #f6faf7;
        color: #51645d;
        font-size: .78rem;
        text-transform: uppercase;
        border-bottom: 1px solid #dbe8df;
    }
    .modal .table tbody td { vertical-align: middle; }
    @media (max-width: 576px) {
        .profile-shell {
            padding: 0.25rem 0;
        }
        .profile-header-cover {
            height: 108px;
            border-radius: 16px 16px 0 0;
        }
        .profile-user-img {
            width: 104px;
            height: 104px;
            margin-top: -52px;
            border-width: 4px;
        }
        .profile-card {
            border-radius: 16px;
            margin-bottom: 1rem;
        }
        .profile-card .card-body {
            padding: 1rem;
        }
        .nav-pills-custom { display: flex; flex-wrap: nowrap; overflow-x: auto; padding: .35rem; }
        .nav-pills-custom .nav-link { white-space: nowrap; padding: 11px 14px; border-radius: 12px; }
        .table-detail tr,
        .table-detail td {
            display: block;
            width: 100% !important;
        }
        .table-detail tr {
            padding: .65rem 0;
            border-bottom: 1px solid #edf3f2;
        }
        .table-detail tr td {
            padding: .15rem 0;
            border-bottom: 0;
        }
        .btn-quick {
            min-height: 74px;
            padding: .7rem .5rem;
        }
        .tab-pane .d-flex.justify-content-between {
            align-items: flex-start !important;
            flex-direction: column;
            gap: .6rem;
        }
        .tab-pane .d-flex.justify-content-between .btn {
            width: 100%;
        }
        .modal .modal-dialog {
            margin: .5rem;
        }
        .modal .modal-header,
        .modal .modal-body {
            padding: .85rem;
        }
        .modal .table {
            min-width: 520px;
        }
    }
    .btn-quick {
        display: flex; flex-direction: column; align-items: center; gap: 5px;
        padding: 14px 10px; border-radius: 14px; border: 1px solid #dde9e7;
        background: #fff; color: #35504e; transition: 0.2s; width: 100%; cursor: pointer;
    }
    .btn-quick i { font-size: 1.35rem; color: #0f766e; }
    .btn-quick span { font-size: 0.8rem; font-weight: 600; }
    .btn-quick:hover { background: #f1fbfa; border-color: #14b8a6; text-decoration: none; color: #0f766e; }
    .table-detail tr td { padding: 12px 15px; border-bottom: 1px solid #edf3f2; }
    .table-detail tr td:first-child { width: 35%; color: #738583; font-weight: 600; }
    .table-detail tr td:last-child { font-weight: 600; color: #333; }
    .profile-section-title {
        font-size: 1rem;
        font-weight: 800;
        color: #173534;
    }
    .profile-soft-card {
        border: 1px solid #e4efee;
        border-radius: 16px;
        box-shadow: 0 16px 32px rgba(15, 118, 110, 0.06);
    }
    .profile-empty {
        border: 1px dashed #cfe1df;
        border-radius: 14px;
        background: #f7fcfb;
        color: #61706f;
        padding: 16px;
        text-align: center;
        font-weight: 600;
    }
    .profile-modal-note {
        border-radius: 14px;
        border: 1px solid #d8ece8;
        background: #f3fbfa;
        color: #35504e;
    }
    .table-responsive { display: block; width: 100%; overflow-x: auto; }
    .profile-shell .card-body { font-size: .92rem; }
    .profile-shell label,
    .modal label { color: #52635c; font-size: .82rem; font-weight: 800; margin-bottom: .35rem; }
    .profile-shell .form-control,
    .profile-shell select.form-control,
    .modal .form-control,
    .modal select.form-control { min-height: 40px; border-radius: 12px; font-size: .9rem; }
    .profile-shell .form-group,
    .modal .form-group { margin-bottom: .85rem; }
    .profile-shell .btn,
    .modal .btn { border-radius: 12px; font-weight: 800; }
    .profile-shell .table { font-size: .88rem; }
    .profile-shell .table th,
    .profile-shell .table td { padding: .62rem .75rem; }
</style>

<section class="content-header pt-4 pb-2">
</section>

<section class="content pb-5">
    <div class="container-fluid">
        <div class="profile-shell">
        <div class="row">
            
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card shadow-sm border-0 profile-card">
                    <div class="profile-header-cover"></div>
                    <div class="card-body text-center pt-0">
                        
                        <?php if ($bisa_ganti_foto): ?>
                            <a href="home-admin.php?page=form-ganti-foto&id_peg=<?= urlencode($peg['id_peg']) ?>" title="Klik untuk ganti foto">
                                <img class="profile-user-img img-fluid img-circle"
                                     src="<?php echo $src_foto; ?>"
                                     onerror="this.src='<?php echo $avatar_def; ?>';">
                            </a>
                        <?php else: ?>
                            <img class="profile-user-img img-fluid img-circle"
                                 src="<?php echo $src_foto; ?>"
                                 onerror="this.src='<?php echo $avatar_def; ?>';">
                        <?php endif; ?>

                        <h4 class="mt-3 mb-1 font-weight-bold"><?php echo $peg['nama']; ?></h4>
                        <p class="text-muted mb-2 small"><?php echo $peg['id_peg']; ?></p>
                        <span class="profile-badge mb-4 d-inline-block"><?php echo profile_field($peg, 'status_kepeg'); ?></span>
                        
                        <div class="text-left border-top pt-3">
                            <p class="text-muted small mb-1"><i class="fas fa-phone mr-2"></i> Telepon</p>
                            <h6 class="mb-3 ml-4"><?php echo $peg['telp'] ? $peg['telp'] : '-'; ?></h6>
                            <p class="text-muted small mb-1"><i class="fas fa-envelope mr-2"></i> Email</p>
                            <h6 class="mb-3 ml-4 small text-truncate"><?php echo $peg['email'] ? $peg['email'] : '-'; ?></h6>
                            <p class="text-muted small mb-1"><i class="fas fa-briefcase-medical mr-2"></i> BPJS TK</p>
                            <h6 class="mb-3 ml-4 small"><?php echo profile_field($peg, 'bpjstk') ? profile_field($peg, 'bpjstk') : '-'; ?></h6>
                            <p class="text-muted small mb-1"><i class="fas fa-heartbeat mr-2"></i> BPJS Kesehatan</p>
                            <h6 class="mb-0 ml-4 small"><?php echo profile_field($peg, 'bpjskes') ? profile_field($peg, 'bpjskes') : '-'; ?></h6>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 profile-card">
                    <div class="card-header bg-white font-weight-bold border-bottom-0">
                        <i class="fas fa-th mr-2 text-teal"></i> Menu Cepat
                    </div>
                    <div class="card-body p-2">
                        <div class="row no-gutters">
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#pensiun"><i class="fa fa-user-clock"></i> <span>Pensiun</span></button></div>
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#naikpkt"><i class="fa fa-layer-group"></i> <span>Pangkat</span></button></div>
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#naikgj"><i class="fa fa-money-bill"></i> <span>Gaji</span></button></div>
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#dp3"><i class="fa fa-chart-line"></i> <span>SKP</span></button></div>
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#bahasa"><i class="fa fa-language"></i> <span>Bahasa</span></button></div>
                            <div class="col-4 p-1"><button type="button" class="btn-quick" data-toggle="modal" data-target="#pendidikan"><i class="fa fa-graduation-cap"></i> <span>Sekolah</span></button></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8 col-lg-9">
                <div class="card shadow-sm border-0 profile-card" style="min-height: 600px;">
                    <div class="card-header p-0 border-bottom-0 bg-white rounded-top">
                        <ul class="nav nav-pills nav-pills-custom" id="custom-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" id="tab-bio" data-toggle="pill" href="#bio" role="tab">Biodata</a></li>
                            <li class="nav-item"><a class="nav-link" id="tab-keluarga" data-toggle="pill" href="#keluarga" role="tab">Keluarga</a></li>
                            <li class="nav-item"><a class="nav-link" id="tab-riwayat" data-toggle="pill" href="#riwayat" role="tab">Riwayat</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            
                            <div class="tab-pane fade show active" id="bio" role="tabpanel">
                                <?php if ($biodata_msg !== ''): ?>
                                    <div class="alert alert-<?php echo $biodata_status === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo profile_e($biodata_msg); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($bisa_request_biodata): ?>
                                    <div class="alert alert-info py-2">
                                        User bisa mengubah biodata sendiri, tapi perubahan baru berlaku setelah approval Kabid Operasional/Kepala cabang.
                                    </div>
                                <?php endif; ?>

                                <table class="table-detail w-100">
                                    <tr><td>NIK</td><td>: <?php echo profile_field($peg, 'nip'); ?></td></tr>
                                    <tr><td>Nama Lengkap</td><td>: <?php echo profile_field($peg, 'nama'); ?></td></tr>
                                    <tr><td>TTL</td><td>: <?php echo profile_field($peg, 'tempat_lhr') . ', ' . (profile_field($peg, 'tgl_lhr', '') ? date('d-m-Y', strtotime($peg['tgl_lhr'])) : '-'); ?></td></tr>
                                    <tr><td>Jenis Kelamin</td><td>: <?php echo profile_field($peg, 'jk'); ?></td></tr>
                                    <tr><td>Agama</td><td>: <?php echo profile_field($peg, 'agama'); ?></td></tr>
                                    <tr><td>Golongan Darah</td><td>: <?php echo profile_field($peg, 'gol_darah'); ?></td></tr>
                                    <tr><td>Status Nikah</td><td>: <?php echo profile_field($peg, 'status_nikah'); ?></td></tr>
                                    <tr><td>Status Kepegawaian</td><td>: <?php echo profile_field($peg, 'status_kepeg'); ?></td></tr>
                                    <tr><td>Jabatan Aktif</td><td>: <?php echo $jabatan_aktif_info ? profile_e($jabatan_aktif_info['nm_jab']) : '-'; ?></td></tr>
                                    <tr><td>Kode Cabang</td><td>: <?php echo ($jabatan_aktif_info && !empty($jabatan_aktif_info['kode_cabang'])) ? profile_e($jabatan_aktif_info['kode_cabang']) : '-'; ?></td></tr>
                                    <tr><td>Nama Kantor</td><td>: <?php echo ($jabatan_aktif_info && !empty($jabatan_aktif_info['nama_kantor'])) ? profile_e($jabatan_aktif_info['nama_kantor']) : '-'; ?></td></tr>
                                    <tr><td>Telepon</td><td>: <?php echo profile_field($peg, 'telp'); ?></td></tr>
                                    <tr><td>Email</td><td>: <?php echo profile_field($peg, 'email'); ?></td></tr>
                                    <tr><td>BPJS TK</td><td>: <?php echo profile_field($peg, 'bpjstk'); ?></td></tr>
                                    <tr><td>BPJS Kesehatan</td><td>: <?php echo profile_field($peg, 'bpjskes'); ?></td></tr>
                                    <tr><td>Alamat</td><td>: <?php echo nl2br(profile_e(profile_field($peg, 'alamat'))); ?></td></tr>
                                </table>
                                
                                <?php if($bisa_edit_data): ?>
                                <div class="mt-4 text-right">
                                    <a href="<?= profile_page_url('form-master-data-pegawai', array('mode' => 'edit', 'id' => $peg['id_peg'])); ?>" class="btn btn-warning shadow-sm"><i class="fa fa-edit"></i> Edit Biodata</a>
                                    <a href="./pages/report/print-biodata-pegawai.php?id_peg=<?= $id_peg ?>" target="_blank" class="btn btn-primary shadow-sm ml-2"><i class="fas fa-print"></i> Cetak CV</a>
                                </div>
                                <?php else: ?>
                                    <div class="mt-4 text-right">
                                        <?php if ($bisa_request_biodata): ?>
                                            <button type="button" class="btn btn-warning shadow-sm" data-toggle="modal" data-target="#biodataRequestModal">
                                                <i class="fa fa-edit"></i> Ajukan Edit Biodata
                                            </button>
                                        <?php endif; ?>
                                        <a href="./pages/report/print-biodata-pegawai.php?id_peg=<?= $id_peg ?>" target="_blank" class="btn btn-primary shadow-sm ml-2"><i class="fas fa-print"></i> Cetak CV</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="keluarga" role="tabpanel">
                                <?php if ($family_msg !== ''): ?>
                                    <div class="alert alert-<?php echo $family_status === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo profile_e($family_msg); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($bisa_request_keluarga): ?>
                                    <div class="alert alert-info py-2">
                                        Perubahan data keluarga akan masuk approval Kabid Operasional/Kepala cabang sebelum tersimpan.
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="font-weight-bold text-primary mb-0">Pasangan (Suami/Istri)</h6>
                                    <?php if ($bisa_request_keluarga): ?>
                                        <button type="button" class="btn btn-xs btn-primary js-family-open" data-scope="pasangan" data-action="create" data-title="Tambah Pasangan">
                                            <i class="fa fa-plus"></i> Tambah
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Pekerjaan</th><th>Status</th><?php if($bisa_kelola_keluarga) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php 
                                            $qSi = mysqli_query($conn,"SELECT a.*, (SELECT desc_pekerjaan FROM tb_master_pekerjaan WHERE id_pekerjaan=a.id_pekerjaan) as nm_kerja FROM tb_suamiistri a WHERE id_peg='$id_peg'");
                                            if(mysqli_num_rows($qSi)>0) {
                                                while($si=mysqli_fetch_array($qSi)){ 
                                                    $id_si = isset($si['id_si']) ? $si['id_si'] : (isset($si['id']) ? $si['id'] : 0);
                                                ?>
                                                <tr>
                                                    <td><?=$si['nama']?></td><td><?=$si['tmp_lhr']?>, <?=$si['tgl_lhr']?></td><td><?=$si['nm_kerja']?></td><td><?=$si['status_hub']?></td>
                                                    <?php if($bisa_edit_data): ?>
                                                    <td class="text-center">
                                                        <a href="home-admin.php?page=form-edit-data-suami-istri&id_si=<?=$id_si?>" class="btn btn-xs btn-info" title="Edit"><i class="fa fa-edit"></i></a>
                                                    </td>
                                                    <?php elseif($bisa_request_keluarga): ?>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-info js-family-open"
                                                            data-scope="pasangan" data-action="update" data-record="<?=profile_e($id_si)?>" data-title="Ubah Pasangan"
                                                            data-nik="<?=profile_e($si['nik'])?>" data-nama="<?=profile_e($si['nama'])?>"
                                                            data-tmp_lhr="<?=profile_e($si['tmp_lhr'])?>" data-tgl_lhr="<?=profile_e($si['tgl_lhr'])?>"
                                                            data-pendidikan="<?=profile_e($si['pendidikan'])?>" data-id_pekerjaan="<?=profile_e($si['id_pekerjaan'])?>"
                                                            data-pekerjaan="<?=profile_e($si['pekerjaan'])?>" data-status_hub="<?=profile_e($si['status_hub'])?>"
                                                            data-hp="<?=profile_e($si['hp'])?>" data-bpjs_pasangan="<?=profile_e($si['bpjs_pasangan'])?>">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-danger js-family-delete" data-scope="pasangan" data-record="<?=profile_e($id_si)?>" data-name="<?=profile_e($si['nama'])?>">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='5' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="font-weight-bold text-primary mb-0">Anak</h6>
                                    <?php if ($bisa_request_keluarga): ?>
                                        <button type="button" class="btn btn-xs btn-primary js-family-open" data-scope="anak" data-action="create" data-title="Tambah Anak">
                                            <i class="fa fa-plus"></i> Tambah
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Pendidikan</th><th>Anak Ke</th><?php if($bisa_kelola_keluarga) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php $qAnak = mysqli_query($conn,"SELECT * FROM tb_anak WHERE id_peg='$id_peg' ORDER BY anak_ke");
                                            if(mysqli_num_rows($qAnak)>0) {
                                                while($ak=mysqli_fetch_array($qAnak)){ 
                                                    $id_ak = isset($ak['id_anak']) ? $ak['id_anak'] : (isset($ak['id']) ? $ak['id'] : 0);
                                                ?>
                                                <tr>
                                                    <td><?=$ak['nama']?></td><td><?=$ak['tmp_lhr']?>, <?=$ak['tgl_lhr']?></td><td><?=$ak['pendidikan']?></td><td><?=$ak['anak_ke']?></td>
                                                    <?php if($bisa_edit_data): ?>
                                                    <td class="text-center">
                                                        <a href="home-admin.php?page=form-edit-data-anak&id_anak=<?=$id_ak?>" class="btn btn-xs btn-info" title="Edit"><i class="fa fa-edit"></i></a>
                                                    </td>
                                                    <?php elseif($bisa_request_keluarga): ?>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-info js-family-open"
                                                            data-scope="anak" data-action="update" data-record="<?=profile_e($id_ak)?>" data-title="Ubah Anak"
                                                            data-nik="<?=profile_e($ak['nik'])?>" data-nama="<?=profile_e($ak['nama'])?>"
                                                            data-tmp_lhr="<?=profile_e($ak['tmp_lhr'])?>" data-tgl_lhr="<?=profile_e($ak['tgl_lhr'])?>"
                                                            data-pendidikan="<?=profile_e($ak['pendidikan'])?>" data-id_pekerjaan="<?=profile_e($ak['id_pekerjaan'])?>"
                                                            data-pekerjaan="<?=profile_e($ak['pekerjaan'])?>" data-status_hub="<?=profile_e($ak['status_hub'])?>"
                                                            data-anak_ke="<?=profile_e($ak['anak_ke'])?>" data-bpjs_anak="<?=profile_e($ak['bpjs_anak'])?>">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-danger js-family-delete" data-scope="anak" data-record="<?=profile_e($id_ak)?>" data-name="<?=profile_e($ak['nama'])?>">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='5' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="font-weight-bold text-primary mb-0">Orang Tua</h6>
                                    <?php if ($bisa_request_keluarga): ?>
                                        <button type="button" class="btn btn-xs btn-primary js-family-open" data-scope="ortu" data-action="create" data-title="Tambah Orang Tua">
                                            <i class="fa fa-plus"></i> Tambah
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Hubungan</th><?php if($bisa_kelola_keluarga) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php $qOrtu = mysqli_query($conn,"SELECT * FROM tb_ortu WHERE id_peg='$id_peg'");
                                            if(mysqli_num_rows($qOrtu)>0) {
                                                while($or=mysqli_fetch_array($qOrtu)){ 
                                                    $id_or = isset($or['id_ortu']) ? $or['id_ortu'] : (isset($or['id']) ? $or['id'] : 0);
                                                ?>
                                                <tr>
                                                    <td><?=$or['nama']?></td><td><?=$or['tmp_lhr']?>, <?=$or['tgl_lhr']?></td><td><?=$or['status_hub']?></td>
                                                    <?php if($bisa_edit_data): ?>
                                                    <td class="text-center">
                                                        <a href="home-admin.php?page=form-edit-data-ortu&id_ortu=<?=$id_or?>" class="btn btn-xs btn-info" title="Edit"><i class="fa fa-edit"></i></a>
                                                    </td>
                                                    <?php elseif($bisa_request_keluarga): ?>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-info js-family-open"
                                                            data-scope="ortu" data-action="update" data-record="<?=profile_e($id_or)?>" data-title="Ubah Orang Tua"
                                                            data-nik="<?=profile_e($or['nik'])?>" data-nama="<?=profile_e($or['nama'])?>"
                                                            data-tmp_lhr="<?=profile_e($or['tmp_lhr'])?>" data-tgl_lhr="<?=profile_e($or['tgl_lhr'])?>"
                                                            data-pendidikan="<?=profile_e($or['pendidikan'])?>" data-id_pekerjaan="<?=profile_e($or['id_pekerjaan'])?>"
                                                            data-pekerjaan="<?=profile_e($or['pekerjaan'])?>" data-status_hub="<?=profile_e($or['status_hub'])?>">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-danger js-family-delete" data-scope="ortu" data-record="<?=profile_e($id_or)?>" data-name="<?=profile_e($or['nama'])?>">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='4' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="riwayat" role="tabpanel">
                                <div class="row">
                                    <div class="col-12"><h6 class="profile-section-title mb-3">Data Riwayat</h6></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#pengangkatan"><i class="fa fa-file-contract mr-2"></i> Pengangkatan</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#mutasi"><i class="fa fa-exchange-alt mr-2"></i> Mutasi</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#diklat"><i class="fa fa-chalkboard-teacher mr-2"></i> Diklat</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#sertifikasi"><i class="fa fa-certificate mr-2"></i> Sertifikasi</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#hukum"><i class="fa fa-gavel mr-2"></i> Pelanggaran</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#jabatan"><i class="fa fa-briefcase mr-2"></i> Jabatan</button></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</section>

<div id="pensiun" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Info Pensiun</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><table class="table"><tr><td>Tgl Lahir</td><td class="font-weight-bold"><?=$peg['tgl_lhr']?></td></tr><tr><td>Jatuh Tempo</td><td class="font-weight-bold text-danger"><?=$peg['tgl_pensiun']?></td></tr></table></div>
        </div>
    </div>
</div>

<div id="naikgj" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Proyeksi Kenaikan Gaji</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Periode</th><th>Estimasi Tanggal</th></tr></thead>
                    <tbody>
                        <?php if(!empty($peg['tgl_naikgaji']) && !empty($peg['tgl_pensiun'])){
                            $begin = new DateTime($peg['tgl_naikgaji']); $end = new DateTime($peg['tgl_pensiun']); $no=0;
                            for($i = clone $begin; $i <= $end; $i->modify('+2 year')){ $no++; if($no > 5) break; echo "<tr><td>Ke-$no</td><td>".$i->format("d-m-Y")."</td></tr>"; }
                        } else { echo "<tr><td colspan='2'>Data tanggal tidak lengkap.</td></tr>"; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="naikpkt" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pangkat</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2"><strong>Estimasi Naik:</strong> <?php if(!empty($peg['tgl_naikpangkat'])){ $next = new DateTime($peg['tgl_naikpangkat']); $next->modify('+4 year'); echo $next->format('d-m-Y'); } else { echo "Data belum tersedia"; } ?></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light"><tr><th>Pangkat</th><th>Gol</th><th>TMT</th><th>SK</th></tr></thead>
                        <tbody>
                            <?php $qPan = mysqli_query($conn,"SELECT * FROM tb_pangkat WHERE id_peg='$id_peg' ORDER BY tgl_sk DESC");
                            if ($qPan && mysqli_num_rows($qPan) > 0) {
                            while($p=mysqli_fetch_array($qPan)){ $id_p = isset($p['id_pangkat'])?$p['id_pangkat']:$p['id']; ?>
                            <tr><td><?=$p['pangkat']?></td><td><?=$p['gol']?></td><td><?=$p['tmt_pangkat']?></td><td><?=$p['no_sk']?></td></tr>
                            <?php } } else { ?>
                            <tr><td colspan="4" class="text-center text-muted">Riwayat pangkat belum tersedia.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="bahasa" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Bahasa</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <table class="table table-bordered"><thead><tr><th>Bahasa</th><th>Kemampuan</th></tr></thead><tbody>
                    <?php $qBhs = mysqli_query($conn,"SELECT * FROM tb_bahasa WHERE id_peg='$id_peg'"); while($b=mysqli_fetch_array($qBhs)){ $id_b = isset($b['id_bahasa'])?$b['id_bahasa']:$b['id']; ?>
                    <tr><td><?=$b['bahasa']?></td><td><?=$b['kemampuan']?></td></tr>
                    <?php } ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div id="pendidikan" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pendidikan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body table-responsive">
                <table class="table table-bordered table-hover"><thead><tr><th>Jenjang</th><th>Nama Sekolah</th><th>Jurusan</th><th>Lulus</th></tr></thead><tbody>
                    <?php $qSek = mysqli_query($conn,"SELECT * FROM tb_pendidikan WHERE id_peg='$id_peg' ORDER BY tgl_ijazah DESC"); while($s=mysqli_fetch_array($qSek)){ $id_s = isset($s['id_sekolah']) ? $s['id_sekolah'] : (isset($s['id']) ? $s['id'] : ''); ?>
                    <tr><td><?=$s['jenjang']?></td><td><?=$s['nama_sekolah']?></td><td><?=$s['jurusan']?></td><td><?=$s['tgl_ijazah']?></td></tr>
                    <?php } ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div id="jabatan" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Jabatan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><table class="table table-bordered table-striped"><thead><tr><th>Jabatan</th><th>Kode Cabang</th><th>Nama Kantor</th><th>TMT</th><th>Status</th></tr></thead>
            <tbody>
<?php 
$qJab = mysqli_query($conn, "
    SELECT DISTINCT
        j.id_jab,
        j.tmt_jabatan,
        j.status_jab,
        COALESCE(m.nama_jabatan, j.jabatan) AS nm_jab,
        k.kode_cabang,
        k.nama_kantor
    FROM tb_jabatan j
    LEFT JOIN tb_master_jabatan m ON m.kode_jabatan = j.kode_jabatan
    LEFT JOIN tb_kantor k ON k.kode_kantor_detail = j.unit_kerja
    WHERE j.id_peg='$id_peg'
    ORDER BY j.tmt_jabatan DESC, j.id_jab DESC
");
while($j = mysqli_fetch_array($qJab)){ ?>
    <tr>
        <td><?= $j['nm_jab'] ?></td>
        <td><?= !empty($j['kode_cabang']) ? profile_e($j['kode_cabang']) : '-' ?></td>
        <td><?= !empty($j['nama_kantor']) ? profile_e($j['nama_kantor']) : '-' ?></td>
        <td><?= $j['tmt_jabatan'] ?></td>
        <td>
            <?php if($j['status_jab'] == 'Aktif'): ?>
                <span class="badge badge-success">Aktif</span>
            <?php else: ?>
                <span class="badge badge-secondary"><?= $j['status_jab'] ?></span>
            <?php endif; ?>
        </td>
    </tr>
<?php } ?>
            </tbody></table></div>
        </div>
    </div>
</div>

<div id="dp3" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white"><h5 class="modal-title">Sasaran Kerja (SKP)</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>Periode</th><th>Nilai</th><th>Mutu</th></tr></thead><tbody>
                <?php $qDp3 = mysqli_query($conn,"SELECT * FROM tb_dp3 WHERE id_peg='$id_peg' ORDER BY periode_akhir DESC"); while($d=mysqli_fetch_array($qDp3)){ $jml = $d['nilai_kesetiaan']+$d['nilai_prestasi']+$d['nilai_tgjwb']+$d['nilai_ketaatan']+$d['nilai_kejujuran']+$d['nilai_kerjasama']+$d['nilai_prakarsa']+$d['nilai_kepemimpinan']; ?>
                <tr><td><?=$d['periode_akhir']?></td><td><?=$jml?></td><td><?=$d['hasil_penilaian']?></td></tr>
                <?php } ?>
            </tbody></table></div>
        </div>
    </div>
</div>

<div id="pengangkatan" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pengangkatan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Status</th><th>Tgl</th><th>No SK</th><th>File</th></tr></thead><tbody><?php $qAng = mysqli_query($conn,"SELECT * FROM tb_angkat WHERE id_peg_baru='$id_peg'"); while($a=mysqli_fetch_array($qAng)){ $id_a = isset($a['id_angkat'])?$a['id_angkat']:$a['id']; ?><tr><td><?=$a['jns_mutasi']?></td><td><?=$a['tgl_mutasi']?></td><td><?=$a['no_mutasi']?></td><td><a href="home-admin.php?page=view-pengangkatan&id_angkat=<?=$id_a?>" target="_blank"><i class="fa fa-file-pdf"></i></a></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="mutasi" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Mutasi</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Jenis</th><th>Tgl</th><th>No SK</th></tr></thead><tbody><?php $qMut = mysqli_query($conn,"SELECT * FROM tb_mutasi WHERE id_peg='$id_peg'"); while($m=mysqli_fetch_array($qMut)){ $id_m = isset($m['id_mutasi'])?$m['id_mutasi']:$m['id']; ?><tr><td><?=$m['jns_mutasi']?></td><td><?=$m['tgl_mutasi']?></td><td><?=$m['no_mutasi']?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="diklat" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Diklat</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Nama</th><th>Penyelenggara</th><th>Tahun</th></tr></thead><tbody><?php $qDik = mysqli_query($conn,"SELECT * FROM tb_diklat WHERE id_peg='$id_peg'"); while($d=mysqli_fetch_array($qDik)){ $id_d = isset($d['id_diklat'])?$d['id_diklat']:$d['id']; ?><tr><td><?=$d['diklat']?></td><td><?=$d['penyelenggara']?></td><td><?=$d['tahun']?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="sertifikasi" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Sertifikasi</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Sertifikasi</th><th>Exp</th><th>Status</th></tr></thead><tbody><?php $qSer = mysqli_query($conn,"SELECT *, DATEDIFF(tgl_expired, CURDATE()) AS selisih FROM tb_sertifikasi WHERE id_peg='$id_peg'"); while($s=mysqli_fetch_array($qSer)){ $id_s = isset($s['id_sertif'])?$s['id_sertif']:$s['id']; ?><tr><td><?=$s['sertifikasi']?></td><td><?=$s['tgl_expired']?></td><td><?= ($s['selisih'] < 0) ? 'Exp' : 'Aktif' ?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="hukum" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pelanggaran</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Hukuman</th><th>Tgl SK</th></tr></thead><tbody><?php $qHuk = mysqli_query($conn,"SELECT * FROM tb_hukuman WHERE id_peg='$id_peg'"); while($h=mysqli_fetch_array($qHuk)){ $id_h = isset($h['id_hukum'])?$h['id_hukum']:$h['id']; ?><tr><td><?=$h['hukuman']?></td><td><?=$h['tgl_sk']?></td></tr><?php } ?></tbody></table></div></div></div></div>

<?php if ($bisa_request_keluarga): ?>
<div id="familyRequestModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" class="modal-content">
            <input type="hidden" name="family_request" value="1">
            <input type="hidden" name="family_scope" id="family_scope">
            <input type="hidden" name="family_action" id="family_action">
            <input type="hidden" name="record_id" id="family_record_id">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="family_modal_title">Pengajuan Data Keluarga</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small">
                    Data tidak langsung tersimpan. Pengajuan ini menunggu approval Kabid Operasional/Kepala cabang.
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>NIK</label>
                        <input type="text" class="form-control" name="nik" id="family_nik" maxlength="16">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Nama</label>
                        <input type="text" class="form-control" name="nama" id="family_nama" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" class="form-control" name="tmp_lhr" id="family_tmp_lhr">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" class="form-control" name="tgl_lhr" id="family_tgl_lhr">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Pendidikan</label>
                        <select class="form-control" name="pendidikan" id="family_pendidikan">
                            <option value="">- Pilih -</option>
                            <?php foreach ($family_pendidikan_options as $pendidikan_opt): ?>
                                <option value="<?php echo profile_e($pendidikan_opt); ?>"><?php echo profile_e($pendidikan_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Status Hubungan</label>
                        <select class="form-control" name="status_hub" id="family_status_hub">
                            <option value="">- Pilih -</option>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Pekerjaan</label>
                        <input type="hidden" name="id_pekerjaan" id="family_id_pekerjaan">
                        <input type="hidden" name="pekerjaan" id="family_pekerjaan">
                        <select class="form-control" id="family_picker_pekerjaan">
                            <option value="">- Pilih -</option>
                            <?php foreach ($family_pekerjaan_options as $job_opt): ?>
                                <option value="<?php echo profile_e($job_opt['id_pekerjaan']); ?>" data-nama="<?php echo profile_e($job_opt['desc_pekerjaan']); ?>">
                                    <?php echo profile_e($job_opt['desc_pekerjaan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group family-field-pasangan">
                        <label>No HP Pasangan</label>
                        <input type="text" class="form-control" name="hp" id="family_hp">
                    </div>
                    <div class="col-md-6 form-group family-field-pasangan">
                        <label>BPJS Pasangan</label>
                        <input type="text" class="form-control" name="bpjs_pasangan" id="family_bpjs_pasangan">
                    </div>
                    <div class="col-md-6 form-group family-field-anak">
                        <label>Anak Ke</label>
                        <input type="number" class="form-control" name="anak_ke" id="family_anak_ke">
                    </div>
                    <div class="col-md-6 form-group family-field-anak">
                        <label>BPJS Anak</label>
                        <input type="text" class="form-control" name="bpjs_anak" id="family_bpjs_anak">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<form method="post" id="familyDeleteForm" style="display:none">
    <input type="hidden" name="family_request" value="1">
    <input type="hidden" name="family_scope" id="delete_family_scope">
    <input type="hidden" name="family_action" value="delete">
    <input type="hidden" name="record_id" id="delete_family_record">
</form>
<?php endif; ?>

<?php if ($bisa_request_biodata): ?>
<div id="biodataRequestModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" class="modal-content">
            <input type="hidden" name="biodata_request" value="1">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">Ajukan Perubahan Biodata</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small">
                    Perubahan biodata tidak langsung tersimpan. Data akan diproses setelah approval Kabid Operasional/Kepala cabang.
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>NIK</label>
                        <input type="text" class="form-control" name="nip" value="<?php echo profile_e(profile_field($peg, 'nip', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama" required value="<?php echo profile_e(profile_field($peg, 'nama', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" class="form-control" name="tempat_lhr" value="<?php echo profile_e(profile_field($peg, 'tempat_lhr', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" class="form-control" name="tgl_lhr" value="<?php echo profile_e(profile_field($peg, 'tgl_lhr', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Jenis Kelamin</label>
                        <select class="form-control" name="jk">
                            <option value="">- Pilih -</option>
                            <option value="Laki-laki" <?php echo profile_field($peg, 'jk', '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo profile_field($peg, 'jk', '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Agama</label>
                        <select class="form-control" name="agama">
                            <option value="">-- Pilih Agama --</option>
                            <?php foreach ($biodata_agama_options as $agama_opt): ?>
                                <option value="<?php echo profile_e($agama_opt); ?>" <?php echo profile_field($peg, 'agama', '') === $agama_opt ? 'selected' : ''; ?>><?php echo profile_e($agama_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Golongan Darah</label>
                        <select class="form-control" name="gol_darah">
                            <?php foreach ($biodata_gol_darah_options as $gol_opt): ?>
                                <option value="<?php echo profile_e($gol_opt); ?>" <?php echo profile_field($peg, 'gol_darah', '') === $gol_opt ? 'selected' : ''; ?>><?php echo profile_e($gol_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Status Nikah</label>
                        <select class="form-control" name="status_nikah">
                            <?php foreach ($biodata_status_nikah_options as $status_nikah_opt): ?>
                                <option value="<?php echo profile_e($status_nikah_opt); ?>" <?php echo profile_field($peg, 'status_nikah', '') === $status_nikah_opt ? 'selected' : ''; ?>><?php echo profile_e($status_nikah_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Telepon</label>
                        <input type="text" class="form-control" name="telp" value="<?php echo profile_e(profile_field($peg, 'telp', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Status Kepegawaian</label>
                        <select class="form-control" name="status_kepeg">
                            <?php foreach ($biodata_status_kepeg_options as $status_kepeg_opt): ?>
                                <option value="<?php echo profile_e($status_kepeg_opt); ?>" <?php echo profile_field($peg, 'status_kepeg', '') === $status_kepeg_opt ? 'selected' : ''; ?>><?php echo profile_e($status_kepeg_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>BPJS Ketenagakerjaan</label>
                        <input type="text" class="form-control" name="bpjstk" value="<?php echo profile_e(profile_field($peg, 'bpjstk', '')); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>BPJS Kesehatan</label>
                        <input type="text" class="form-control" name="bpjskes" value="<?php echo profile_e(profile_field($peg, 'bpjskes', '')); ?>">
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo profile_e(profile_field($peg, 'email', '')); ?>">
                    </div>
                    <div class="col-md-12 form-group mb-0">
                        <label>Alamat</label>
                        <textarea class="form-control" name="alamat" rows="3"><?php echo profile_e(profile_field($peg, 'alamat', '')); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">Kirim Approval</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php if ($bisa_request_keluarga): ?>
<script>
$(function(){
    var familyStatusOptions = {
        pasangan: ['Suami', 'Istri'],
        anak: ['Anak Kandung', 'Anak Tiri', 'Anak Angkat'],
        ortu: ['Ayah Kandung', 'Ibu Kandung', 'Ayah Tiri', 'Ibu Tiri', 'Mertua L', 'Mertua P', 'Wali']
    };

    function setVal(name, value) {
        $('#family_' + name).val(value || '');
    }

    function familyData(btn, key) {
        var value = btn.attr('data-' + key);
        return typeof value === 'undefined' ? btn.data(key) : value;
    }

    function renderFamilyStatusOptions(scope, selected) {
        var options = familyStatusOptions[scope] || [];
        var select = $('#family_status_hub');
        select.html('<option value="">- Pilih -</option>');
        for (var i = 0; i < options.length; i++) {
            var value = options[i];
            select.append($('<option>', { value: value, text: value }));
        }
        select.val(selected || '');
    }

    $('#family_picker_pekerjaan').on('change', function(){
        var selected = $(this).find(':selected');
        $('#family_id_pekerjaan').val($(this).val() || '');
        $('#family_pekerjaan').val(selected.data('nama') || '');
    });

    $('.js-family-open').on('click', function(){
        var btn = $(this);
        var scope = btn.data('scope');

        $('#family_scope').val(scope);
        $('#family_action').val(familyData(btn, 'action'));
        $('#family_record_id').val(familyData(btn, 'record') || '');
        $('#family_modal_title').text(familyData(btn, 'title') || 'Pengajuan Data Keluarga');

        setVal('nik', familyData(btn, 'nik'));
        setVal('nama', familyData(btn, 'nama'));
        setVal('tmp_lhr', familyData(btn, 'tmp_lhr'));
        setVal('tgl_lhr', familyData(btn, 'tgl_lhr'));
        setVal('pendidikan', familyData(btn, 'pendidikan'));
        setVal('id_pekerjaan', familyData(btn, 'id_pekerjaan'));
        setVal('pekerjaan', familyData(btn, 'pekerjaan'));
        renderFamilyStatusOptions(scope, familyData(btn, 'status_hub'));
        $('#family_picker_pekerjaan').val(familyData(btn, 'id_pekerjaan') || '').trigger('change');
        if (!familyData(btn, 'id_pekerjaan') && familyData(btn, 'pekerjaan')) {
            $('#family_pekerjaan').val(familyData(btn, 'pekerjaan'));
        }
        setVal('hp', familyData(btn, 'hp'));
        setVal('bpjs_pasangan', familyData(btn, 'bpjs_pasangan'));
        setVal('anak_ke', familyData(btn, 'anak_ke'));
        setVal('bpjs_anak', familyData(btn, 'bpjs_anak'));

        $('.family-field-pasangan').toggle(scope === 'pasangan');
        $('.family-field-anak').toggle(scope === 'anak');
        $('#familyRequestModal').modal('show');
    });

    $('.js-family-delete').on('click', function(){
        var name = $(this).data('name') || 'data ini';
        if (confirm('Ajukan hapus ' + name + '? Penghapusan tetap menunggu approval.')) {
            $('#delete_family_scope').val($(this).data('scope'));
            $('#delete_family_record').val($(this).data('record'));
            $('#familyDeleteForm').submit();
        }
    });
});
</script>
<?php endif; ?>
