<?php
/*********************************************************
 * FILE     : pages/user/form-master-data-user.php
 * MODULE   : Manajemen User (Final Fix: No Auto-Complete)
 * STATUS   : PHP 5.6 Ready | Validation OK | Clean Inputs
 *********************************************************/

if (session_id() == '') session_start();
include "dist/koneksi.php";
include_once "dist/sso-auth.php";

// --- HELPERS (PHP 5.6 SAFE) ---
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function clean($c, $s){ return mysqli_real_escape_string($c, trim($s)); }
function v($arr, $key, $def=''){ return (isset($arr[$key]) && $arr[$key]!==null) ? $arr[$key] : $def; }

// --- 1. INISIALISASI ---
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'create';
$id   = isset($_GET['id']) ? clean($conn, $_GET['id']) : '';
$prefill_id_pegawai = isset($_GET['id_pegawai']) ? clean($conn, $_GET['id_pegawai']) : '';
$admin_login = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'System';

// Default Data
$data = array('id_user'=>'','nama_user'=>'','hak_akses'=>'','id_pegawai'=>'','status_aktif'=>'Y');

// Ambil Data Edit
if ($mode == 'edit' && !empty($id)) {
    $q = mysqli_query($conn, "SELECT * FROM tb_user WHERE id_user = '$id'");
    if (mysqli_num_rows($q) > 0) { $data = mysqli_fetch_assoc($q); }
    else { echo "<script>window.location='home-admin.php?page=form-view-data-user';</script>"; exit; }
}

if ($mode == 'create' && !empty($prefill_id_pegawai)) {
    $qPrefill = mysqli_query($conn, "
        SELECT p.id_peg, p.nama
        FROM tb_apk a
        INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
        WHERE a.id_peg = '$prefill_id_pegawai'
          AND a.simpeg = 1
          AND p.status_aktif IN ('1','Y')
        LIMIT 1
    ");
    if ($qPrefill && mysqli_num_rows($qPrefill) > 0) {
        $prefill = mysqli_fetch_assoc($qPrefill);
        $data['id_user'] = $prefill['id_peg'];
        $data['id_pegawai'] = $prefill['id_peg'];
        $data['nama_user'] = $prefill['nama'];
        $data['hak_akses'] = function_exists('simpeg_auto_role_for_employee') ? simpeg_auto_role_for_employee($conn, $prefill['id_peg']) : 'User';
    }
}

// --- 2. PROSES SIMPAN ---
$status_process = '';
$msg_process = '';

if (isset($_POST['btn_simpan'])) {
    $mode_post = $_POST['mode'];
    $id_user   = clean($conn, $_POST['id_user']);
    $nama      = clean($conn, $_POST['nama_user']); 
    $akses     = $_POST['hak_akses'];
    $pass_raw  = $_POST['password'];
    $status    = isset($_POST['status_aktif']) ? 'Y' : 'N';
    $id_peg    = !empty($_POST['id_pegawai']) ? clean($conn, $_POST['id_pegawai']) : 'NULL';

    $error = '';
    
    // Validasi
    if ($mode_post == 'create') {
        $cek = mysqli_query($conn, "SELECT id_user FROM tb_user WHERE id_user = '$id_user'");
        if (mysqli_num_rows($cek) > 0) $error = "Username '$id_user' sudah dipakai!";
        elseif ($id_peg != 'NULL') {
            $cekPeg = mysqli_query($conn, "SELECT id_user FROM tb_user WHERE id_pegawai = '$id_peg'");
            if (mysqli_num_rows($cekPeg) > 0) $error = "Pegawai ini sudah punya akun!";
        }
    }

    if (empty($error)) {
        $sqlValPeg = ($id_peg == 'NULL') ? "NULL" : "'$id_peg'";
        
        if ($mode_post == 'create') {
            $pass_hash = md5($pass_raw);
            $sql = "INSERT INTO tb_user (id_user, nama_user, password, hak_akses, id_pegawai, status_aktif, created_by, created_at) 
                    VALUES ('$id_user', '$nama', '$pass_hash', '$akses', $sqlValPeg, '$status', '$admin_login', NOW())";
        } else {
            $id_lama  = clean($conn, $_POST['id_user_lama']);
            $sql_pass = !empty($pass_raw) ? ", password = '".md5($pass_raw)."'" : "";
            $sql = "UPDATE tb_user SET nama_user='$nama', hak_akses='$akses', id_pegawai=$sqlValPeg, 
                    status_aktif='$status', updated_by='$admin_login', updated_at=NOW() $sql_pass 
                    WHERE id_user='$id_lama'";
        }

        if (mysqli_query($conn, $sql)) { $status_process = 'sukses'; }
        else { $status_process = 'gagal'; $msg_process = mysqli_error($conn); }
    } else {
        $status_process = 'warning'; $msg_process = $error;
    }
}

// --- 3. DATA PEGAWAI ---
if ($mode == 'edit') {
    $id_pegawai_edit = clean($conn, v($data, 'id_pegawai'));
    $sqlPeg = "
        SELECT p.id_peg, p.nama,
          (
            SELECT j.jabatan
            FROM tb_jabatan j
            WHERE j.id_peg = p.id_peg AND LOWER(j.status_jab) = 'aktif'
            ORDER BY j.tmt_jabatan DESC
            LIMIT 1
          ) AS jabatan_live
        FROM tb_pegawai p
        WHERE p.id_peg = '$id_pegawai_edit'
        LIMIT 1
    ";
} else {
    $sqlPeg = "
        SELECT p.id_peg, p.nama,
          (
            SELECT j.jabatan
            FROM tb_jabatan j
            WHERE j.id_peg = p.id_peg AND LOWER(j.status_jab) = 'aktif'
            ORDER BY j.tmt_jabatan DESC
            LIMIT 1
          ) AS jabatan_live
        FROM tb_apk a
        INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
        LEFT JOIN tb_user u ON u.id_pegawai = a.id_peg
        WHERE p.status_aktif IN ('1','Y')
          AND a.simpeg = 1
          AND u.id_user IS NULL
        GROUP BY p.id_peg, p.nama
        ORDER BY p.nama ASC
    ";
}
$qPeg = mysqli_query($conn, $sqlPeg);
$pegawai_locked = ($mode == 'edit');
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .user-form-page { padding-top: 0.75rem; padding-bottom: 1rem; }
    .user-form-card {
        border: 1px solid rgba(217,229,220,0.95) !important;
        border-radius: 14px !important;
        background: rgba(255,255,255,0.95) !important;
        box-shadow: 0 12px 30px rgba(15,35,26,0.06) !important;
        overflow: hidden;
    }
    .user-form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid rgba(217,229,220,0.95);
        background: linear-gradient(180deg, rgba(246,250,247,0.96), rgba(255,255,255,0.96));
    }
    .user-form-title { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e2b24; }
    .user-form-subtitle { margin: 0.1rem 0 0; font-size: 0.8rem; color: #5d6d64; }
    .user-form-icon {
        width: 36px; height: 36px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #dff5f2; color: #0f766e; flex: 0 0 auto;
    }
    .user-form-body { padding: 1rem; }
    .user-form-panel {
        border: 1px solid #d9e5dc;
        border-radius: 12px;
        background: #f6faf7;
        padding: 0.8rem;
        margin-bottom: 1rem;
    }
    .user-form-label {
        display: block;
        margin-bottom: 0.4rem;
        color: #5d6d64;
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .form-control-modern,
    .select2-container .select2-selection--single {
        min-height: 40px !important;
        border-radius: 12px !important;
        border: 1px solid #d9e5dc !important;
        box-shadow: none !important;
    }
    .select2-container .select2-selection--single { display: flex; align-items: center; }
    .readonly-field,
    .select2-container--disabled .select2-selection--single {
        background-color: #eef5f0 !important;
        cursor: not-allowed !important;
    }
    .user-form-section-title {
        margin: 0 0 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #d9e5dc;
        color: #5d6d64;
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .btn-user-submit {
        border: 0 !important;
        border-radius: 12px !important;
        padding: 0.6rem 1rem !important;
        background: #0f766e !important;
        color: #fff !important;
        font-weight: 800;
    }
    .btn-user-back {
        border-radius: 12px !important;
        border: 1px solid #d9e5dc !important;
        background: #fff !important;
        color: #0f766e !important;
        font-weight: 800;
    }
    @media (max-width: 767.98px) {
        .user-form-header,
        .user-form-actions { flex-direction: column; align-items: stretch !important; }
        .btn-user-back,
        .btn-user-submit { width: 100%; }
    }
</style>

<section class="content simpeg-page user-form-page">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7"> 
                <div class="card user-form-card">
                    
                    <div class="user-form-header">
                        <div class="d-flex align-items-center" style="gap:0.65rem;">
                            <span class="user-form-icon"><i class="fas fa-user-shield"></i></span>
                            <div>
                                <h5 class="user-form-title"><?= ($mode=='edit') ? 'Edit Role User' : 'Tambah Role User' ?></h5>
                                <p class="user-form-subtitle"><?= ($mode=='edit') ? 'Ubah role, status, atau password akun.' : 'Pilih pegawai aktif yang belum memiliki role user.' ?></p>
                            </div>
                        </div>
                        <a href="home-admin.php?page=form-view-data-user" class="btn btn-sm btn-user-back px-3"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>

                    <div class="user-form-body">
                        
                        <?php if($status_process == 'sukses'): ?>
                            <script>
                                Swal.fire({icon: 'success', title: 'Berhasil!', text: 'Data user tersimpan.', timer: 1500, showConfirmButton: false})
                                .then(function() { window.location.href = 'home-admin.php?page=form-view-data-user'; });
                            </script>
                        <?php elseif($status_process == 'gagal'): ?>
                            <script>Swal.fire({icon: 'error', title: 'Gagal', text: '<?= $msg_process ?>'});</script>
                        <?php elseif($status_process == 'warning'): ?>
                            <script>Swal.fire({icon: 'warning', title: 'Perhatian', text: '<?= $msg_process ?>'});</script>
                        <?php endif; ?>

                        <form action="" method="POST" autocomplete="off">
                            
                            <input type="text" style="display:none">
                            <input type="password" style="display:none">

                            <input type="hidden" name="mode" value="<?= $mode ?>">
                            <input type="hidden" name="id_user_lama" value="<?= v($data, 'id_user') ?>">
                            <input type="hidden" name="id_pegawai" id="final_id_peg" value="<?= v($data, 'id_pegawai') ?>">

                            <div class="user-form-panel">
                                <label class="user-form-label" for="sumber_pegawai"><i class="fas fa-search mr-1"></i> Pegawai</label>
                                
                                <select id="sumber_pegawai" class="form-control select2" <?= $pegawai_locked ? 'disabled' : '' ?>>
                                    <option value=""><?= $pegawai_locked ? '-- Pegawai terkunci saat edit --' : '-- Ketik Nama / ID Pegawai --' ?></option>
                                    <?php 
                                    if($qPeg) {
                                        while($p = mysqli_fetch_assoc($qPeg)) { 
                                            $sel = (v($data, 'id_pegawai') == $p['id_peg']) ? 'selected' : ''; 
                                            $nama_full = e($p['nama']);
                                            $auto_role = function_exists('simpeg_auto_role_for_employee') ? simpeg_auto_role_for_employee($conn, $p['id_peg']) : 'User';
                                    ?>
                                        <option value="<?= $p['id_peg'] ?>" data-namauser="<?= $nama_full ?>" data-role="<?= e($auto_role) ?>" <?= $sel ?>>
                                            <?= $p['nama'] ?> (<?= $p['id_peg'] ?>)<?= !empty($p['jabatan_live']) ? ' - '.e($p['jabatan_live']) : '' ?>
                                        </option>
                                    <?php 
                                        } 
                                    }
                                    ?>
                                </select>
                                <small class="text-muted mt-2 d-block">
                                    <?= $pegawai_locked ? 'Pegawai tidak bisa diganti saat edit role.' : 'Hanya menampilkan pegawai aktif yang belum punya user/role.' ?>
                                </small>
                            </div>

                            <h6 class="user-form-section-title">Detail Akun</h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="user-form-label">Username / ID User <span class="text-danger">*</span></label>
                                    <input type="text" name="id_user" class="form-control form-control-modern <?= ($mode=='edit')?'readonly-field':'' ?>" 
                                           placeholder="Contoh: admin" 
                                           value="<?= v($data, 'id_user') ?>" 
                                           autocomplete="new-user"
                                           <?= ($mode=='edit')?'readonly':'required' ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="user-form-label">Nama Lengkap User <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_user" id="nama_user_target" class="form-control form-control-modern" 
                                           placeholder="Akan terisi otomatis..." value="<?= v($data, 'nama_user') ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="user-form-label">Level Akses <span class="text-danger">*</span></label>
                                    <select name="hak_akses" class="form-control form-control-modern" required>
                                        <option value="">-- Pilih Level --</option>
                                        <option value="Superadmin" <?= (strtolower(v($data, 'hak_akses'))=='superadmin')?'selected':'' ?>>Super Admin</option>
                                        <option value="Admin" <?= (strtolower(v($data, 'hak_akses'))=='admin')?'selected':'' ?>>Admin</option>
                                        <option value="Kepala" <?= (strtolower(v($data, 'hak_akses'))=='kepala')?'selected':'' ?>>Kepala</option>
                                        <option value="User" <?= (strtolower(v($data, 'hak_akses'))=='user')?'selected':'' ?>>User</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="user-form-label">Password <?= ($mode=='edit') ? '<small class="text-muted text-lowercase">(kosongkan jika tetap)</small>' : '<span class="text-danger">*</span>' ?></label>
                                    <input type="password" name="password" class="form-control form-control-modern" 
                                           placeholder="******" 
                                           autocomplete="new-password"
                                           <?= ($mode=='create')?'required':'' ?>>
                                </div>
                            </div>

                            <div class="row align-items-center mt-2 user-form-actions">
                                <div class="col-md-6">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="sw_aktif" name="status_aktif" value="Y" <?= (v($data, 'status_aktif')=='Y')?'checked':'' ?>>
                                        <label class="custom-control-label font-weight-bold text-success" for="sw_aktif">Status Akun Aktif</label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="submit" name="btn_simpan" class="btn btn-user-submit">
                                        <i class="fas fa-save mr-2"></i> SIMPAN
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Init Select2
    $('#sumber_pegawai').select2({
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
        placeholder: "-- Pilih Pegawai --"
    });

    <?php if($pegawai_locked): ?>
    $('#sumber_pegawai').prop('disabled', true).trigger('change.select2');
    <?php endif; ?>

    // AUTO FILL LOGIC
    $('#sumber_pegawai').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var id = $(this).val();
        var nama = selectedOption.attr('data-namauser');
        var role = selectedOption.attr('data-role');

        if(id) {
            $('#final_id_peg').val(id);
            if(nama) $('#nama_user_target').val(nama);
            <?php if($mode=='create'): ?>
            $('input[name="id_user"]').val(id);
            if(role) $('select[name="hak_akses"]').val(role);
            <?php endif; ?>
        } else {
            $('#final_id_peg').val('');
            $('#nama_user_target').val('');
            <?php if($mode=='create'): ?>
            $('input[name="id_user"]').val('');
            <?php endif; ?>
        }
    });
    
    // Extra Clear untuk memastikan form bersih saat load
    <?php if($mode=='create' && empty($prefill_id_pegawai)): ?>
    setTimeout(function() {
        $('input[name="id_user"]').val('');
        $('input[name="password"]').val('');
    }, 100);
    <?php endif; ?>
});
</script>
