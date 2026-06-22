<?php
/*********************************************************
 * FILE    : pages/otorisasi/otorisasi-approval.php
 * MODULE  : SIMPEG — Daftar Otorisasi Edit Data (Modern UI)
 * VERSION : v2.0 (Responsive & Modern)
 * DATE    : 2025-11-20
 *********************************************************/

if (session_id()==='') session_start();

/* ==== include koneksi + normalisasi variabel ==== */
$__root = dirname(dirname(__DIR__));
if (!isset($conn)) {
  include_once $__root . '/dist/koneksi.php';
}
if (!isset($koneksi)) { if (isset($conn)) { $koneksi = $conn; } }
include_once $__root . '/dist/functions.php';

/* ===== Guard ===== */
// Good practice: Explicit access control check
$hak_akses = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
$can_approve = function_exists('userBisaApprovalOtorisasi') ? userBisaApprovalOtorisasi() : ($hak_akses === 'kepala' || $hak_akses === 'admin' || $hak_akses === 'superadmin');
if (!$can_approve) {
  echo "<script>alert('Anda tidak memiliki akses.'); window.location='home-admin.php';</script>";
  exit;
}

/* ===== Helpers ===== */
// Good practice: Output escaping function
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Good practice: Input normalization
function norm_date($s){
  if (!$s) return '';
  $s = trim($s);
  if (strpos($s,'/') !== false) { // dd/mm/yyyy
    $p = explode('/', $s);
    if (count($p)===3) return sprintf('%04d-%02d-%02d', (int)$p[2], (int)$p[1], (int)$p[0]);
  }
  $t = strtotime($s);
  return $t ? date('Y-m-d', $t) : '';
}

/* ===== Params ===== */
$kode_kantor   = function_exists('unitKerjaApprovalUser') ? unitKerjaApprovalUser() : (isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '');
$kode_kantor_approval = function_exists('simpegKodeKantorApproval') ? simpegKodeKantorApproval($kode_kantor) : substr($kode_kantor, 0, 3);
$scope_kantor_approval = function_exists('simpegScopeKantorApproval') ? simpegScopeKantorApproval($kode_kantor) : array($kode_kantor_approval);
$filter_kode_kantor = isset($_GET['kode_kantor']) ? preg_replace('/[^0-9A-Za-z]/', '', $_GET['kode_kantor']) : '';
if ($filter_kode_kantor !== '' && !in_array($filter_kode_kantor, $scope_kantor_approval)) {
  $filter_kode_kantor = '';
}
$label_kantor_akses = ($kode_kantor_approval === '000') ? 'Konsolidasi' : $kode_kantor_approval;

@mysqli_query($koneksi, "
  UPDATE tb_edit_pending ep
  LEFT JOIN tb_user u ON ep.id_user = u.id_user
  LEFT JOIN tb_jabatan target_jab ON ep.id_peg = target_jab.id_peg AND LOWER(target_jab.status_jab) = 'aktif'
  LEFT JOIN tb_jabatan pengedit ON u.id_pegawai = pengedit.id_peg AND LOWER(pengedit.status_jab) = 'aktif'
  SET ep.kode_kantor = COALESCE(LEFT(target_jab.unit_kerja, 3), LEFT(pengedit.unit_kerja, 3), ep.kode_kantor)
  WHERE (ep.kode_kantor IS NULL OR ep.kode_kantor = '')
");

// Good practice: Whitelisting input values
$status_opt = array('Semua','Menunggu','Disetujui','Ditolak');
$status = isset($_GET['status']) ? $_GET['status'] : 'Menunggu';
if (!in_array($status, $status_opt)) $status = 'Menunggu';

/* sumber tanggal untuk filter */
$date_by_opt = array('pengajuan','otorisasi');
$date_by = isset($_GET['date_by']) ? strtolower($_GET['date_by']) : 'pengajuan';
if (!in_array($date_by, $date_by_opt)) $date_by = 'pengajuan';
$date_col = ($date_by === 'otorisasi') ? 'ep.tanggal_otorisasi' : 'ep.tanggal_pengajuan';

$tgl_awal_raw  = isset($_GET['tgl_awal'])  ? $_GET['tgl_awal']  : '';
$tgl_akhir_raw = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : '';

$tgl_awal  = norm_date($tgl_awal_raw);
$tgl_akhir = norm_date($tgl_akhir_raw);

$column_kantor = "COALESCE(NULLIF(ep.kode_kantor, ''), LEFT(target_jab.unit_kerja, 3), LEFT(pengedit.unit_kerja, 3))";
$qKantorFilter = mysqli_query($koneksi, "
  SELECT DISTINCT $column_kantor AS kode_kantor
  FROM tb_edit_pending ep
  LEFT JOIN tb_user u ON ep.id_user = u.id_user
  LEFT JOIN tb_jabatan target_jab ON ep.id_peg = target_jab.id_peg AND LOWER(target_jab.status_jab) = 'aktif'
  LEFT JOIN tb_jabatan pengedit ON u.id_pegawai = pengedit.id_peg AND LOWER(pengedit.status_jab) = 'aktif'
  WHERE ".(function_exists('simpegSqlInKantorApproval')
    ? simpegSqlInKantorApproval($koneksi, $column_kantor, $kode_kantor)
    : $column_kantor . " = '".mysqli_real_escape_string($koneksi, $kode_kantor_approval)."'")."
  ORDER BY kode_kantor ASC
");
$kantor_filter_options = array();
if ($qKantorFilter) {
  while ($kf = mysqli_fetch_assoc($qKantorFilter)) {
    if (!empty($kf['kode_kantor'])) $kantor_filter_options[] = $kf['kode_kantor'];
  }
}

/* ===== Build WHERE ===== */
$where = array();

if ($status !== 'Semua') {
  if ($status === 'Menunggu') {
    $where[] = "ep.status_otorisasi IN ('Menunggu','pending')";
  } elseif ($status === 'Disetujui') {
    $where[] = "ep.status_otorisasi IN ('Disetujui','approved')";
  } elseif ($status === 'Ditolak') {
    $where[] = "ep.status_otorisasi IN ('Ditolak','rejected')";
  } else {
    $where[] = "ep.status_otorisasi = '".mysqli_real_escape_string($koneksi, $status)."'";
  }
}

if ($tgl_awal && $tgl_akhir) {
  // Safe because variables are normalized via norm_date()
  $where[] = "DATE($date_col) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'";
} elseif ($tgl_awal) {
  $where[] = "DATE($date_col) >= '".$tgl_awal."'";
} elseif ($tgl_akhir) {
  $where[] = "DATE($date_col) <= '".$tgl_akhir."'";
}

if ($filter_kode_kantor !== '') {
  $where[] = $column_kantor . " = '".mysqli_real_escape_string($koneksi, $filter_kode_kantor)."'";
} else {
  $where[] = function_exists('simpegSqlInKantorApproval')
    ? simpegSqlInKantorApproval($koneksi, $column_kantor, $kode_kantor)
    : $column_kantor . " = '".mysqli_real_escape_string($koneksi, $kode_kantor_approval)."'";
}

$where_sql = count($where) ? 'WHERE '.implode(' AND ', $where) : '';

/* ===== Query ===== */
$sql = "
  SELECT
      ep.id_edit,
      ep.id_peg,
      ep.jenis_data,
      ep.status_otorisasi,
      ep.tanggal_pengajuan,
      ep.tanggal_otorisasi,
      u.nama_user,
      p.nama AS nama_pegawai,
      COALESCE(NULLIF(ep.kode_kantor, ''), LEFT(target_jab.unit_kerja, 3), LEFT(pengedit.unit_kerja, 3), '-') AS kantor_pemohon
  FROM tb_edit_pending ep
  LEFT JOIN tb_user u ON ep.id_user = u.id_user
  LEFT JOIN tb_pegawai p ON ep.id_peg = p.id_peg
  LEFT JOIN tb_jabatan pengedit ON u.id_pegawai = pengedit.id_peg AND LOWER(pengedit.status_jab) = 'aktif'
  LEFT JOIN tb_jabatan target_jab ON ep.id_peg = target_jab.id_peg AND LOWER(target_jab.status_jab) = 'aktif'
  $where_sql
  ORDER BY COALESCE(ep.tanggal_otorisasi, ep.tanggal_pengajuan) DESC, ep.id_edit DESC
";
$qPending = mysqli_query($koneksi, $sql);
?>

<style>
  /* Layout Background */
  .content-wrapper { background-color: #f4f6f9; }

  /* Card Modern */
  .card-modern {
    border: none;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    background: #fff;
    overflow: hidden;
    margin-bottom: 20px;
  }
  .card-header-modern {
    background-color: #fff;
    border-bottom: 1px solid #f0f2f5;
    padding: 20px 25px;
  }

  /* Typography */
  .page-title { font-weight: 800; color: #343a40; margin-bottom: 5px; }
  .page-subtitle { font-size: 0.9rem; color: #6c757d; }

  /* Form Controls */
  .form-label-modern {
    font-size: 0.8rem;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
  }
  .input-modern {
    border-radius: 10px;
    border: 1px solid #dee2e6;
    padding: 10px 15px;
    height: 45px;
    font-size: 0.95rem;
    width: 100%;
    transition: all 0.3s;
  }
  .input-modern:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    outline: none;
  }

  /* Buttons */
  .btn-modern {
    border-radius: 50px;
    padding: 10px 25px;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    transition: transform 0.2s;
    height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
  .btn-primary-modern { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
  .btn-secondary-modern { background: #eff2f7; color: #5a6a85; }
  .btn-secondary-modern:hover { background: #e1e6ed; color: #333; }
  .approval-filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    align-items: end;
  }

  /* Table Styling */
  .table-responsive { border-radius: 0 0 16px 16px; }
  .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
  .table-modern thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    padding: 15px;
    border-bottom: 2px solid #edf2f7;
    white-space: nowrap;
  }
  .table-modern tbody td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f2f5;
    font-size: 0.95rem;
    color: #333;
  }
  .table-modern tbody tr:last-child td { border-bottom: none; }
  .table-modern tbody tr:hover { background-color: #fcfcfc; }

  /* Status Badges (Soft UI) */
  .badge-soft {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
  }
  .badge-soft-warning { background-color: #fff8e1; color: #d97706; }
  .badge-soft-success { background-color: #dcfce7; color: #166534; }
  .badge-soft-danger  { background-color: #fee2e2; color: #991b1b; }
  .badge-soft-secondary { background-color: #f3f4f6; color: #4b5563; }

  /* Responsive Tweaks */
  @media (max-width: 768px) {
    .btn-modern { width: 100%; margin-bottom: 10px; }
    .col-sm-3, .col-sm-2 { margin-bottom: 15px; }
    .filter-actions { flex-direction: column; }
  }
</style>

<section class="content-header pt-4 pb-2">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="page-title">Otorisasi Edit Data</h3>
        <p class="page-subtitle mb-0">
          Kantor Akses: <strong><?php echo e($label_kantor_akses); ?></strong>
        </p>
      </div>
    </div>
  </div>
</section>

<script>
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('approvalFilterForm');
    if (!form) return;
    var timer = null;
    var submitFilter = function() {
      window.clearTimeout(timer);
      timer = window.setTimeout(function() { form.submit(); }, 250);
    };
    var fields = form.querySelectorAll('select, input[type="date"]');
    for (var i = 0; i < fields.length; i++) {
      fields[i].addEventListener('change', submitFilter);
    }
  });
})();
</script>

<section class="content">
  <div class="container-fluid">

    <div class="card card-modern">
      <div class="card-header-modern">
        <form method="get" class="approval-filter-form" id="approvalFilterForm">
          <input type="hidden" name="page" value="otorisasi-approval">

          <?php if (count($kantor_filter_options) > 1): ?>
          <div>
            <label class="form-label-modern">Kode Kantor</label>
            <select name="kode_kantor" class="form-control input-modern">
              <option value="">Semua Akses</option>
              <?php foreach ($kantor_filter_options as $kode_opt): ?>
                <option value="<?php echo e($kode_opt); ?>" <?php echo ($filter_kode_kantor === $kode_opt ? 'selected' : ''); ?>>
                  <?php echo e($kode_opt); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div>
            <label class="form-label-modern">Status Otorisasi</label>
            <select name="status" class="form-control input-modern">
              <?php foreach ($status_opt as $opt): ?>
                <option value="<?php echo e($opt); ?>" <?php echo ($status===$opt?'selected':''); ?>>
                  <?php echo e($opt); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label-modern">Tgl Berdasar</label>
            <select name="date_by" class="form-control input-modern">
              <option value="pengajuan" <?php echo ($date_by==='pengajuan'?'selected':''); ?>>Pengajuan</option>
              <option value="otorisasi" <?php echo ($date_by==='otorisasi'?'selected':''); ?>>Otorisasi</option>
            </select>
          </div>

          <div>
            <label class="form-label-modern">Dari</label>
            <input type="date" name="tgl_awal" class="form-control input-modern" value="<?php echo e($tgl_awal ? $tgl_awal : ''); ?>">
          </div>
          <div>
            <label class="form-label-modern">Sampai</label>
            <input type="date" name="tgl_akhir" class="form-control input-modern" value="<?php echo e($tgl_akhir ? $tgl_akhir : ''); ?>">
          </div>

        </form>
      </div>
    </div>

    <div class="card card-modern">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-modern">
            <thead>
              <tr>
                <th class="text-center" style="width:50px">No</th>
                <th>ID Pegawai</th>
                <th>Nama Pegawai</th>
                <th>Perubahan</th>
                <th class="text-center">Status</th>
                <th>Diajukan Oleh</th>
                <th>Kantor</th>
                <th>Tanggal</th>
                <th class="text-center" style="width:100px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 1;
              if ($qPending && mysqli_num_rows($qPending) > 0):
                while ($row = mysqli_fetch_assoc($qPending)):
                  
                  // Menentukan warna badge soft
                  $badge = 'badge-soft-secondary';
                  $status_row = $row['status_otorisasi'];
                  if ($status_row === 'pending') $status_row = 'Menunggu';
                  if ($status_row === 'approved') $status_row = 'Disetujui';
                  if ($status_row === 'rejected') $status_row = 'Ditolak';
                  if ($status_row=='Menunggu')  $badge='badge-soft-warning';
                  if ($status_row=='Disetujui') $badge='badge-soft-success';
                  if ($status_row=='Ditolak')   $badge='badge-soft-danger';

                  $waktu = ($date_by==='otorisasi' && $row['tanggal_otorisasi'])
                             ? $row['tanggal_otorisasi'] : $row['tanggal_pengajuan'];
              ?>
              <tr>
                <td class="text-center font-weight-bold"><?php echo $no++; ?></td>
                <td><span class="text-muted"><?php echo e($row['id_peg']); ?></span></td>
                <td class="font-weight-bold"><?php echo e($row['nama_pegawai']); ?></td>
                <td><?php echo e(ucfirst($row['jenis_data'])); ?></td>
                <td class="text-center">
                  <span class="badge badge-soft <?php echo $badge; ?>">
                    <?php echo e($status_row); ?>
                  </span>
                </td>
                <td>
                  <div style="line-height:1.2;">
                    <div class="font-weight-bold" style="font-size:0.85rem"><?php echo e($row['nama_user']); ?></div>
                  </div>
                </td>
                <td><span class="text-primary font-weight-bold"><?php echo e($row['kantor_pemohon']); ?></span></td>
                <td><?php echo $waktu ? date('d M Y H:i', strtotime($waktu)) : '-'; ?></td>
                <td class="text-center">
                  <a href="home-admin.php?page=otorisasi-detail&id_edit=<?php echo e($row['id_edit']); ?>"
                     class="btn btn-sm btn-outline-primary rounded px-3" title="Lihat Detail">
                    <i class="fa fa-arrow-right ml-1"></i>
                  </a>
                </td>
              </tr>
              <?php
                endwhile;
              else:
              ?>
              <tr>
                <td colspan="9" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa fa-search mb-3" style="font-size: 2rem; opacity: 0.5;"></i><br>
                    Tidak ada data yang ditemukan sesuai filter.
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</section>
