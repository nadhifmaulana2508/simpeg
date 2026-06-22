<?php
/*********************************************************
 * FILE    : pages/kepegawaian/form-ubah-id-peg.php
 * MODULE  : Form Pengangkatan (Secure & Offline Mode)
 * VERSION : v2.1
 *********************************************************/

if (session_id() == '') session_start();
include "dist/koneksi.php";

if (!function_exists('fuip_page_url')) {
    function fuip_page_url($page, $params = array()) {
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

// 1. SECURITY: Cek Login
if (empty($_SESSION['id_user'])) {
    die("<div class='alert alert-danger'>Akses Ditolak. Silakan login terlebih dahulu.</div>");
}

// 2. QUERY DATA (Aman karena hardcoded query, tidak ada input user)
$sqlPegawai = "SELECT id_peg, nama FROM tb_pegawai 
               WHERE id_peg LIKE 'K%' OR id_peg LIKE 'O%' 
               ORDER BY nama ASC";
$qPegawai = mysqli_query($conn, $sqlPegawai);
?>

<style>
    .mutasi-page {
        padding-top: 18px;
        padding-bottom: 32px;
    }
    .mutasi-shell {
        background: linear-gradient(180deg, #f4fbfa 0%, #ffffff 100%);
        border-radius: 22px;
        padding: 22px;
    }
    .mutasi-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 20px;
    }
    .mutasi-title {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 800;
        color: #173534;
    }
    .mutasi-subtitle {
        color: #607270;
        margin-top: 6px;
    }
    .card-ref {
        border: 1px solid #e1efed; border-radius: 20px;
        box-shadow: 0 20px 40px rgba(15,118,110,0.08); overflow: hidden; background: #fff;
    }
    .card-ref-header {
        background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: #fff; padding: 18px 22px;
        border-bottom: 1px solid rgba(255,255,255,0.18);
    }
    .card-ref-header h5 { font-weight: 700; font-size: 1.1rem; margin: 0; }
    .card-ref-header small { color: rgba(255,255,255,0.8); font-size: 0.85rem; }
    .form-label-ref { font-weight: 700; font-size: 0.85rem; color: #243c3a; margin-bottom: 6px; }
    .form-control-ref {
        border-radius: 12px; border: 1px solid #cfdedb; height: 46px;
        font-size: 0.95rem; padding: 8px 12px;
    }
    .form-control-ref:focus { border-color: #14b8a6; box-shadow: 0 0 0 0.2rem rgba(20,184,166,.16); }
    .card-ref-footer {
        padding: 20px; background-color: #fff; border-top: 1px solid #f1f1f1;
        display: flex; justify-content: space-between; align-items: center;
    }
    .btn-ref-back { background: #fff; border: 1px solid #cedbd9; color: #4a5c5b; font-weight: 700; padding: 10px 20px; border-radius: 12px; }
    .btn-ref-save { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); border: none; color: #fff; font-weight: 700; padding: 10px 30px; border-radius: 12px; box-shadow: 0 10px 24px rgba(15,118,110,0.22); }
    .btn-ref-save:hover { color:#fff; }
    .btn-ref-back:hover { background: #f8fbfb; color:#173534; }
    .form-note {
        border: 1px solid #d8ece8;
        background: #f4fbfa;
        color: #476361;
        border-radius: 14px;
        padding: 12px 14px;
        margin-bottom: 18px;
    }
    .select2-container .select2-selection--single { height: 46px !important; border: 1px solid #cfdedb !important; border-radius: 12px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 44px; padding-left: 12px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px; }
    @media(max-width: 768px) {
        .mutasi-header { flex-direction: column; }
        .card-ref-footer { flex-direction: column-reverse; gap: 12px; }
        .card-ref-footer .btn { width: 100%; }
    }
</style>

<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<section class="content mutasi-page">
    <div class="container-fluid">
        <div class="mutasi-shell">
        <div class="mutasi-header">
            <div>
                <h1 class="mutasi-title">Pengangkatan Pegawai</h1>
                <p class="mutasi-subtitle">Samakan alur perubahan ID pegawai, SK, dan dokumen pendukung dalam satu form yang lebih rapi.</p>
            </div>
            <a href="<?= fuip_page_url('form-view-data-pegawai'); ?>" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-10">
                
                <form action="pages/pegawai/proses-ubah-id.php" method="POST" enctype="multipart/form-data">
                    <div class="card-ref">
                        
                        <div class="card-ref-header">
                            <h5>Pengangkatan Pegawai</h5>
                            <small>Form Pengangkatan Pegawai</small>
                        </div>

                        <div class="card-body p-4">
                            <div class="form-note">
                                Gunakan form ini untuk pengangkatan calon pegawai atau perubahan NIP. Tampilan dan alur sengaja disamakan dengan form pegawai lainnya.
                            </div>
                            
                            <div class="form-group mb-4">
                                <label class="form-label-ref">Pilih Pegawai (ID Lama)</label>
                                <select name="id_peg_lama" class="form-control-ref select2-search" style="width: 100%;" required>
                                    <option value="">- Cari Nama / ID -</option>
                                    <?php while($p = mysqli_fetch_assoc($qPegawai)) { ?>
                                        <option value="<?= htmlspecialchars($p['id_peg']) ?>">
                                            <?= htmlspecialchars($p['nama']) ?> (ID: <?= htmlspecialchars($p['id_peg']) ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">Jenis Pengangkatan</label>
                                    <select name="jns_mutasi" class="form-control-ref" required>
                                        <option value="">- Pilih -</option>
                                        <option value="Calon Pegawai">Calon Pegawai</option>
                                        <!-- <option value="Pegawai Tetap">Pegawai Tetap</option> -->
                                        <option value="Perubahan NIP">Perubahan NIP</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">ID Pegawai Baru</label>
                                    <input type="text" name="id_peg_baru" class="form-control-ref" placeholder="Masukkan ID Baru" required autocomplete="off">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">Nomor SK</label>
                                    <input type="text" name="no_mutasi" class="form-control-ref" placeholder="No. Surat Keputusan" required autocomplete="off">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">Tanggal SK</label>
                                    <input type="date" name="tgl_mutasi" class="form-control-ref" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">TMT (Terhitung Mulai Tgl)</label>
                                    <input type="date" name="tmt" class="form-control-ref" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-ref">File SK <small class="text-muted">(opsional, pdf)</small></label>
                                    <div class="custom-file" style="height: 42px;">
                                        <input type="file" name="sk_mutasi" class="custom-file-input" id="customFile" accept=".pdf" style="height: 42px;">
                                        <label class="custom-file-label" for="customFile" style="height: 42px; line-height: 30px; border-radius:6px; border-color:#ced4da;">Pilih File...</label>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="card-ref-footer">
                            <a href="<?= fuip_page_url('form-view-data-pegawai'); ?>" class="btn btn-ref-back">
                                <i class="fa fa-arrow-left mr-1"></i> Kembali
                            </a>
                            <button type="submit" name="simpan" class="btn btn-ref-save">
                                <i class="fa fa-save mr-1"></i> Simpan
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        </div>
        </div>
    </div>
</section>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/select2/js/select2.full.min.js"></script>

<script>
    $(document).ready(function() {
        // Inisialisasi Select2
        $('.select2-search').select2({
            theme: 'default', // Menggunakan style default yg kita override di CSS
            placeholder: "- Pilih Pegawai -",
            allowClear: true
        });

        // Script Custom File Input Label (Biar nama file muncul saat dipilih)
        $(".custom-file-input").on("change", function() {
            var fileName = $(this).val().split("\\").pop();
            $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
        });
    });
</script>     
