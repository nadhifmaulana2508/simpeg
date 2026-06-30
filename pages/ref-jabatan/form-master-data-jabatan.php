<?php
/*********************************************************
 * FILE     : pages/ref-jabatan/form-master-data-jabatan.php
 * MODULE   : SIMPEG — Entry Jabatan (Create Update vs Mutasi)
 *********************************************************/

if (session_id()==='') session_start();
@include_once __DIR__ . '/../../dist/koneksi.php';
@include_once __DIR__ . '/../../dist/functions.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; $conn = isset($koneksi)?$koneksi:null; }

function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function postv($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function clean($c,$s){ return mysqli_real_escape_string($c, trim($s)); }
function form_jabatan_page_url($page, $params = array()) {
    if (function_exists('page_url')) {
        return page_url($page, $params);
    }

    $url = 'home-admin.php?page=' . rawurlencode($page);
    if (!empty($params)) {
        $url .= '&' . http_build_query($params);
    }
    return $url;
}

$today      = date('Y-m-d');
$user_login = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'system';

// --- 1. AMBIL DATA PEGAWAI ---
$uid = isset($_GET['uid']) ? preg_replace('~[^A-Za-z0-9_\-]~','', $_GET['uid']) : '';
$pegawai = null;
$jabAktif = null;

if ($uid !== '') {
    $q = mysqli_query($conn, "SELECT id_peg, nama, nip, jk, foto FROM tb_pegawai WHERE id_peg='".clean($conn,$uid)."' LIMIT 1");
    if ($q && mysqli_num_rows($q)>0) { 
        $pegawai = mysqli_fetch_assoc($q); 
        
        // Ambil Data Jabatan AKTIF
        $qJ = mysqli_query($conn, "SELECT * FROM tb_jabatan WHERE id_peg='$uid' AND status_jab='Aktif' LIMIT 1");
        if(mysqli_num_rows($qJ)>0) {
            $jabAktif = mysqli_fetch_assoc($qJ);
            // Join manual nama kantor untuk display badge
            $qK = mysqli_query($conn, "SELECT nama_kantor FROM tb_kantor WHERE kode_kantor_detail='".$jabAktif['unit_kerja']."'");
            $dK = mysqli_fetch_assoc($qK);
            $jabAktif['nama_kantor'] = $dK['nama_kantor'];
        }
    }
}

if (!$pegawai) {
    echo "<script>alert('Data tidak ditemukan!'); window.location='home-admin.php?page=form-view-data-jabatan';</script>";
    exit;
}

$listUrl = form_jabatan_page_url('form-view-data-jabatan');
$detailUrl = form_jabatan_page_url('view-detail-data-pegawai', array('id_peg' => $uid));
$selfUrl = form_jabatan_page_url('form-master-data-jabatan', array('uid' => $uid));
$updateUrl = form_jabatan_page_url('form-master-data-jabatan', array('uid' => $uid, 'mode' => 'update'));
$mutasiUrl = form_jabatan_page_url('form-master-data-jabatan', array('uid' => $uid, 'mode' => 'mutasi'));

// --- 2. LOGIC MODE ---
$mode = isset($_GET['mode']) ? $_GET['mode'] : ''; 
// Mode: 'update' (Koreksi) atau 'mutasi' (Baru)

// Jika tidak punya jabatan, otomatis paksa ke mode mutasi (input baru)
if (!$jabAktif && $mode == '') {
    $mode = 'mutasi'; 
}

// --- 3. REFERENSI ---
$rsJ = mysqli_query($conn, "SELECT kode_jabatan, nama_jabatan FROM tb_master_jabatan ORDER BY nama_jabatan ASC");
$ref_jabatan = [];
if ($rsJ) { while($r=mysqli_fetch_assoc($rsJ)){ $ref_jabatan[]=$r; } }

$rsU = mysqli_query($conn, "SELECT kode_kantor_detail, nama_kantor FROM tb_kantor ORDER BY kode_kantor_detail");
$ref_unit = [];
if ($rsU) { while($u=mysqli_fetch_assoc($rsU)){ $ref_unit[]=$u; } }

// --- 4. PROSES SIMPAN ---
$status = ''; $msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_peg       = clean($conn, postv('id_peg')); 
    $kode_jabatan = clean($conn, postv('kode_jabatan'));
    $nama_jabatan = clean($conn, postv('nama_jabatan')); 
    $unit_kerja   = clean($conn, postv('unit_kerja'));
    $no_sk        = clean($conn, postv('no_sk'));
    $tgl_sk       = clean($conn, postv('tgl_sk'));
    $tmt_jabatan  = $tgl_sk; 
    $status_jab   = 'Aktif'; 

    if ($id_peg === '' || $kode_jabatan === '') {
        $status = 'gagal'; $msg_error = 'Data tidak lengkap.';
    } else {
        mysqli_begin_transaction($conn);
        $ok = true;

        if ($mode == 'update') {
            // --- LOGIC UPDATE (KOREKSI DATA) ---
            // Update row yang sedang aktif saja
            $sqlUpdate = "UPDATE tb_jabatan SET 
                            kode_jabatan = '$kode_jabatan',
                            jabatan      = '$nama_jabatan',
                            unit_kerja   = '$unit_kerja',
                            no_sk        = '$no_sk',
                            tgl_sk       = '$tgl_sk',
                            tmt_jabatan  = '$tgl_sk',
                            updated_at   = NOW(),
                            updated_by   = '$user_login'
                          WHERE id_peg='$id_peg' AND status_jab='Aktif'";
            $ok = mysqli_query($conn, $sqlUpdate);

        } else {
            // --- LOGIC MUTASI (TAMBAH HISTORY) ---
            // 1. Matikan jabatan lama
            if ($jabAktif) {
                $tgl_tutup = date('Y-m-d', strtotime('-1 day', strtotime($tgl_sk)));
                $sqlClose = "UPDATE tb_jabatan SET status_jab='Non', sampai_tgl='$tgl_tutup', updated_at=NOW(), updated_by='$user_login' 
                             WHERE id_peg='$id_peg' AND status_jab='Aktif'";
                $ok = mysqli_query($conn, $sqlClose);
            }
            // 2. Insert baru
            if ($ok) {
                $sqlInsert = "INSERT INTO tb_jabatan (id_peg, kode_jabatan, jabatan, unit_kerja, tmt_jabatan, sampai_tgl, status_jab, no_sk, tgl_sk, date_reg, created_by) 
                        VALUES ('$id_peg', '$kode_jabatan', '$nama_jabatan', '$unit_kerja', '$tmt_jabatan', NULL, '$status_jab', '$no_sk', '$tgl_sk', '$today', '$user_login')";
                $ok = mysqli_query($conn, $sqlInsert);
            }
        }

        if ($ok) {
            mysqli_commit($conn);
            $status = 'sukses';
        } else {
            mysqli_rollback($conn);
            $status = 'gagal'; $msg_error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen Jabatan</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
  
  <style>
    .form-section { max-width: 850px; margin: 30px auto; }
    .card { border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
    .card-header { background: #fff; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; }
    
    /* Menu Card Style */
    .btn-menu { 
        display: block; width: 100%; padding: 25px; text-align: left; 
        border: 2px solid #f1f5f9; border-radius: 16px; margin-bottom: 15px; 
        transition: all 0.2s; background: #fff; color: #333; text-decoration: none; 
    }
    .btn-menu:hover { 
        border-color: #4f46e5; background: #f8fbff; transform: translateY(-3px); 
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.10); 
    }
    .btn-menu .icon-box {
        width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        margin-right: 20px; font-size: 1.5rem; float: left;
    }
    .bg-soft-primary { background-color: #eef2ff; color: #4f46e5; }
    .bg-soft-warning { background-color: #fef3c7; color: #d97706; }
    
    .btn-menu h6 { margin: 5px 0 5px 0; font-weight: 800; font-size: 1.1rem; color: #1e293b; }
    .btn-menu p { margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.4; }
    .form-control, .form-select, .select2-container .select2-selection--single {
        min-height: 44px !important;
        border-radius: 10px !important;
        border-color: #d1d5db !important;
        box-shadow: none !important;
    }
    .select2-container .select2-selection--single {
        display: flex !important;
        align-items: center !important;
    }
    .section-subtitle {
        font-size: 0.86rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    .btn-primary-soft {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #fff;
    }
    .btn-primary-soft:hover {
        background: #4338ca;
        border-color: #4338ca;
        color: #fff;
    }
    @media (max-width: 767.98px) {
        .form-section { margin: 20px auto; }
        .card-header,
        .card-body { padding-left: 18px !important; padding-right: 18px !important; }
        .btn-menu { padding: 18px; }
    }
  </style>
  <script src="assets/js/core/jquery.3.2.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="background-color: #f4f6f9;">

<div class="container form-section">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold text-dark mb-0">Manajemen Jabatan</h4>
      <div class="section-subtitle">Kelola koreksi data jabatan dan mutasi dengan tampilan yang konsisten.</div>
    </div>
    <a href="<?= e($listUrl) ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fas fa-arrow-left me-2"></i> Kembali
    </a>
  </div>

  <div class="card mb-4">
      <div class="card-body">
          <div class="d-flex align-items-center">
              <div class="me-3">
                  <img src="pages/assets/foto/<?= ($pegawai['jk']=='Perempuan'?'no-foto-female.png':'no-foto-male.png') ?>" 
                       class="rounded-circle" width="70" height="70" style="object-fit: cover; border: 3px solid #e9ecef;">
              </div>
              <div>
                  <h5 class="fw-bold mb-1"><?= e($pegawai['nama']) ?></h5>
                  <div class="text-muted small mb-1"><i class="fas fa-id-badge me-1"></i> NIP: <?= e($pegawai['id_peg']) ?></div>
                  <?php if($jabAktif): ?>
                      <span class="badge bg-primary rounded-pill px-3">
                          <?= e($jabAktif['jabatan']) ?> - <?= e($jabAktif['nama_kantor']) ?>
                      </span>
                  <?php else: ?>
                      <span class="badge bg-secondary rounded-pill px-3">Belum Memiliki Jabatan</span>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>

  <?php if ($mode === '' && $jabAktif): ?>
      <div class="row g-4">
          <div class="col-md-6">
              <a href="<?= e($updateUrl) ?>" class="btn-menu">
                  <div class="icon-box bg-soft-primary"><i class="fas fa-edit"></i></div>
                  <div style="overflow: hidden;">
                      <h6>Update / Koreksi Data</h6>
                      <p>Perbaiki data jabatan atau unit kerja yang salah input (tanpa membuat riwayat baru).</p>
                  </div>
              </a>
          </div>
          <div class="col-md-6">
              <a href="<?= e($mutasiUrl) ?>" class="btn-menu">
                  <div class="icon-box bg-soft-warning"><i class="fas fa-exchange-alt"></i></div>
                  <div style="overflow: hidden;">
                      <h6>Mutasi / Promosi</h6>
                      <p>Pegawai pindah tugas atau naik jabatan (data lama dinonaktifkan, buat data baru).</p>
                  </div>
              </a>
          </div>
      </div>

  <?php else: ?>
      
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-primary">
                <?php if($mode=='update'): ?>
                    <i class="fas fa-edit me-2"></i> Form Update Data (Koreksi)
                <?php else: ?>
                    <i class="fas fa-plus-circle me-2"></i> Form Mutasi / Jabatan Baru
                <?php endif; ?>
            </h6>
            
            <?php if($jabAktif): ?>
                <a href="<?= e($selfUrl) ?>" class="btn btn-sm btn-light border text-muted px-3">Ganti Aksi</a>
            <?php endif; ?>
        </div>
        
        <div class="card-body p-4">
            
            <?php if ($status === 'sukses'): ?>
                <script>Swal.fire({icon:'success',title:'Berhasil!',text:'Data berhasil disimpan.',timer:1500,showConfirmButton:false}).then(()=>{window.location=<?= json_encode($detailUrl) ?>});</script>
            <?php elseif ($status === 'gagal'): ?>
                <div class="alert alert-danger"><?= $msg_error ?></div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off">
                <input type="hidden" name="id_peg" value="<?= e($pegawai['id_peg']) ?>">

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold">JABATAN</label>
                        <select name="kode_jabatan" id="kode_jabatan" class="form-select select2" required>
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach ($ref_jabatan as $j): ?>
                                <?php 
                                    // Auto Select jika mode Update dan datanya cocok
                                    $sel = ($mode=='update' && $jabAktif && $jabAktif['kode_jabatan']==$j['kode_jabatan']) ? 'selected' : '';
                                ?>
                                <option value="<?= e($j['kode_jabatan']) ?>" data-nama="<?= e($j['nama_jabatan']) ?>" <?= $sel ?>>
                                    <?= e($j['nama_jabatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="nama_jabatan" id="nama_jabatan_hidden" value="<?= ($mode=='update' && $jabAktif) ? e($jabAktif['jabatan']) : '' ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold">UNIT KERJA <span class="text-danger">*</span></label>
                        <select name="unit_kerja" class="form-select select2" required>
                            <option value="">-- Pilih Unit Kerja --</option>
                            <?php foreach ($ref_unit as $u): ?>
                                <?php 
                                    // Auto Select jika mode Update dan datanya cocok
                                    $sel = ($mode=='update' && $jabAktif && $jabAktif['unit_kerja']==$u['kode_kantor_detail']) ? 'selected' : '';
                                ?>
                                <option value="<?= e($u['kode_kantor_detail']) ?>" <?= $sel ?>>
                                    <?= e($u['nama_kantor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold">NOMOR SK</label>
                        <input type="text" name="no_sk" class="form-control" placeholder="Contoh: SK/001/HRD/2025" 
                               value="<?= ($mode=='update' && $jabAktif) ? e($jabAktif['no_sk']) : '' ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold">TANGGAL SK / TMT</label>
                        <input type="date" name="tgl_sk" class="form-control" 
                               value="<?= ($mode=='update' && $jabAktif) ? e($jabAktif['tgl_sk']) : $today ?>" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-soft fw-bold py-2 shadow-sm">
                        <i class="fas fa-save me-2"></i> SIMPAN DATA
                    </button>
                </div>

            </form>
        </div>
      </div>

  <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

<script>
  $(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    
    $('#kode_jabatan').on('change', function() {
        var nama = $(this).find(':selected').data('nama');
        if(nama) $('#nama_jabatan_hidden').val(nama);
    });
  });
</script>

</body>
</html>
