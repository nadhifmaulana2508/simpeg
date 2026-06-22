<?php
/*********************************************************
 * FILE    : pages/preview-edit.php
 * MODULE  : SIMPEG - Riwayat Permintaan Edit Data
 *********************************************************/

if (session_id()==='') session_start();
@include_once __DIR__ . '/../../dist/koneksi.php';

function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function pe_status($status) {
  if ($status === 'pending') return 'Menunggu';
  if ($status === 'approved') return 'Disetujui';
  if ($status === 'rejected') return 'Ditolak';
  return $status ? $status : 'Menunggu';
}

function pe_summary($json) {
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return trim((string)$json) !== '' ? e($json) : '<span class="text-muted">Tidak ada data.</span>';
  }

  if (isset($data['payload']) && is_array($data['payload'])) {
    $data = $data['payload'];
  }

  $items = array();
  foreach ($data as $key => $value) {
    if (is_array($value) || trim((string)$value) === '') continue;
    $items[] = '<span class="history-chip"><strong>'.e(ucwords(str_replace('_', ' ', $key))).':</strong> '.e($value).'</span>';
    if (count($items) >= 6) break;
  }

  return count($items) ? implode(' ', $items) : '<span class="text-muted">Data kosong.</span>';
}

$id_user   = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : '';
$hak_akses = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
$id_peg_req = isset($_GET['id_peg']) ? $_GET['id_peg'] : '';
$id_peg_safe = '';
$page_no = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page_no < 1) $page_no = 1;
$per_page = 4;
$offset = ($page_no - 1) * $per_page;

if ($hak_akses === 'user') {
  $idu_safe = mysqli_real_escape_string($conn, $id_user);
  $ru = mysqli_query($conn, "SELECT u.id_user, u.id_pegawai, p.id_peg FROM tb_user u LEFT JOIN tb_pegawai p ON u.id_pegawai=p.id_peg WHERE u.id_user='$idu_safe' LIMIT 1");
  $usr = $ru ? mysqli_fetch_assoc($ru) : null;
  $id_peg_safe = $usr ? mysqli_real_escape_string($conn, $usr['id_peg']) : '';
} else {
  $id_peg_safe = mysqli_real_escape_string($conn, $id_peg_req);
}

if ($id_peg_safe === '' || $id_peg_safe === null) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Data pegawai tidak ditemukan atau akses ditolak.</div></div>';
  exit;
}

$qPegawai = mysqli_query($conn, "SELECT id_peg, nama, nip FROM tb_pegawai WHERE id_peg='".$id_peg_safe."' LIMIT 1");
$pegawai  = $qPegawai ? mysqli_fetch_assoc($qPegawai) : null;
if (!$pegawai) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Pegawai tidak ditemukan.</div></div>';
  exit;
}

$qTotalRiwayat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tb_edit_pending WHERE id_peg = '".$id_peg_safe."'");
$total_riwayat = ($qTotalRiwayat && ($tr = mysqli_fetch_assoc($qTotalRiwayat))) ? (int)$tr['total'] : 0;
$total_pages = max(1, (int)ceil($total_riwayat / $per_page));
if ($page_no > $total_pages) {
  $page_no = $total_pages;
  $offset = ($page_no - 1) * $per_page;
}

$sqlRiwayat = "
  SELECT
    ep.id_edit,
    ep.id_peg,
    ep.jenis_data,
    ep.data_lama,
    ep.data_baru,
    ep.status_otorisasi,
    ep.tanggal_pengajuan,
    ep.tanggal_otorisasi,
    ep.kode_kantor,
    u.nama_user,
    u.hak_akses
  FROM tb_edit_pending ep
  LEFT JOIN tb_user u ON ep.id_user = u.id_user
  WHERE ep.id_peg = '".$id_peg_safe."'
  ORDER BY COALESCE(ep.tanggal_otorisasi, ep.tanggal_pengajuan) DESC, ep.id_edit DESC
  LIMIT $offset, $per_page
";
$qRiwayat = mysqli_query($conn, $sqlRiwayat);
?>

<style>
  .history-card { border:1px solid #dbe8df; border-radius:18px; background:#fff; box-shadow:0 14px 34px rgba(15,35,26,.06); overflow:hidden; }
  .history-head { padding:1rem 1.1rem; border-bottom:1px solid #e3eee7; background:linear-gradient(180deg,#fbfdfb,#f6faf7); }
  .history-title { margin:0; font-size:1.12rem; font-weight:800; color:#10231d; }
  .history-subtitle { margin:.15rem 0 0; color:#66756e; font-size:.86rem; }
  .history-profile { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.7rem; padding:1rem; border-bottom:1px solid #e3eee7; }
  .history-profile-item { border:1px solid #e3eee7; border-radius:14px; padding:.75rem; background:#fbfdfb; }
  .history-profile-label { color:#66756e; font-size:.74rem; font-weight:800; text-transform:uppercase; }
  .history-profile-value { color:#10231d; font-weight:800; margin-top:.2rem; }
  .history-list { padding:1rem; }
  .history-item { border:1px solid #e3eee7; border-radius:16px; padding:.9rem; margin-bottom:.85rem; background:#fff; }
  .history-item-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; margin-bottom:.75rem; }
  .history-kind { font-weight:800; color:#10231d; }
  .history-meta { color:#718179; font-size:.8rem; }
  .history-status { display:inline-flex; border-radius:999px; padding:.28rem .65rem; font-size:.72rem; font-weight:800; background:#fff3cd; color:#996100; }
  .history-status.ok { background:#dcfce7; color:#166534; }
  .history-status.no { background:#fee2e2; color:#991b1b; }
  .history-body { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
  .history-box { border:1px solid #edf3ef; border-radius:14px; padding:.75rem; background:#f8fbf9; }
  .history-box-title { color:#66756e; font-size:.74rem; font-weight:800; text-transform:uppercase; margin-bottom:.5rem; }
  .history-chip { display:inline-flex; margin:0 .35rem .35rem 0; padding:.28rem .5rem; border-radius:999px; background:#eef7f4; color:#263b33; font-size:.8rem; }
  .history-pager { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; padding:1rem; border-top:1px solid #e3eee7; }
  .history-page-info { color:#66756e; font-size:.86rem; font-weight:700; }
  .history-page-actions { display:flex; gap:.5rem; }
  .history-page-btn { border-radius:12px; border:1px solid #d9e5dc; background:#fff; color:#0f766e; font-weight:800; padding:.55rem .85rem; }
  .history-page-btn.disabled { color:#9aa8a1; pointer-events:none; background:#f5f8f6; }
  @media(max-width:767.98px){ .history-profile,.history-body{grid-template-columns:1fr;} .history-item-head{flex-direction:column;} }
</style>

<div class="container-fluid mt-4">
  <div class="history-card">
    <div class="history-head">
      <h5 class="history-title">Riwayat Perubahan</h5>
      <p class="history-subtitle">Pantau status pengajuan perubahan data pegawai.</p>
    </div>
    <div class="history-profile">
      <div class="history-profile-item"><div class="history-profile-label">ID Pegawai</div><div class="history-profile-value"><?php echo e($pegawai['id_peg']); ?></div></div>
      <div class="history-profile-item"><div class="history-profile-label">Nama</div><div class="history-profile-value"><?php echo e($pegawai['nama']); ?></div></div>
      <div class="history-profile-item"><div class="history-profile-label">NIP</div><div class="history-profile-value"><?php echo e($pegawai['nip']); ?></div></div>
    </div>
    <div class="history-list">
      <?php if ($qRiwayat && mysqli_num_rows($qRiwayat) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($qRiwayat)): ?>
          <?php
            $waktu = $row['tanggal_otorisasi'] ? $row['tanggal_otorisasi'] : $row['tanggal_pengajuan'];
            $status = pe_status($row['status_otorisasi']);
            $statusClass = $status === 'Disetujui' ? 'ok' : ($status === 'Ditolak' ? 'no' : '');
          ?>
          <div class="history-item">
            <div class="history-item-head">
              <div>
                <div class="history-kind"><?php echo e(ucwords(str_replace('_', ' ', $row['jenis_data']))); ?></div>
                <div class="history-meta">oleh <?php echo e($row['nama_user']); ?> - Kantor <?php echo e($row['kode_kantor'] ? $row['kode_kantor'] : '-'); ?> - <?php echo $waktu ? date('d M Y H:i', strtotime($waktu)) : '-'; ?></div>
              </div>
              <span class="history-status <?php echo $statusClass; ?>"><?php echo e($status); ?></span>
            </div>
            <div class="history-body">
              <div class="history-box">
                <div class="history-box-title">Data Lama</div>
                <?php echo pe_summary($row['data_lama']); ?>
              </div>
              <div class="history-box">
                <div class="history-box-title">Data Baru</div>
                <?php echo pe_summary($row['data_baru']); ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-light border mb-0">Belum ada riwayat perubahan.</div>
      <?php endif; ?>
    </div>
    <div class="history-pager">
      <div class="history-page-info">
        Halaman <?php echo $page_no; ?> dari <?php echo $total_pages; ?> · <?php echo $total_riwayat; ?> pengajuan
      </div>
      <div class="history-page-actions">
        <?php
          $base_params = array('page' => 'preview-edit');
          if ($hak_akses !== 'user') $base_params['id_peg'] = $id_peg_safe;
          $prev_params = $base_params;
          $next_params = $base_params;
          $prev_params['p'] = max(1, $page_no - 1);
          $next_params['p'] = min($total_pages, $page_no + 1);
        ?>
        <a class="history-page-btn <?php echo $page_no <= 1 ? 'disabled' : ''; ?>" href="home-admin.php?<?php echo http_build_query($prev_params); ?>">
          <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
        <a class="history-page-btn <?php echo $page_no >= $total_pages ? 'disabled' : ''; ?>" href="home-admin.php?<?php echo http_build_query($next_params); ?>">
          Next <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>
    </div>
  </div>
</div>
