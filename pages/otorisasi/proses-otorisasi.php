<?php
/*********************************************************
 * FILE    : pages/otorisasi/proses-otorisasi.php
 * MODULE  : SIMPEG — Proses Otorisasi (batch per poin)
 * VERSION : v1.9 (Security Hardened)
 * DATE    : 2025-12-18
 *********************************************************/

if (session_id()==='') session_start();

/* ==== Robust include koneksi + normalisasi variabel ==== */
$__paths = array(
  __DIR__ . '/../../dist/koneksi.php',
  __DIR__ . '/../../../dist/koneksi.php',
  dirname(__DIR__) . '/dist/koneksi.php',
  __DIR__ . '/../dist/koneksi.php'
);
foreach ($__paths as $__p) { if (is_file($__p)) { include_once $__p; } } // Removed @ for safer inclusion
if (!isset($koneksi)) { if (isset($conn)) { $koneksi = $conn; } }
@include_once __DIR__ . '/../../dist/functions.php';

// --- SECURITY: ACCESS CONTROL CHECK ---
$hak_akses = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
$can_approve = function_exists('userBisaApprovalOtorisasi') ? userBisaApprovalOtorisasi() : ($hak_akses === 'kepala' || $hak_akses === 'admin' || $hak_akses === 'superadmin');
if (!$can_approve) {
    // Log the unauthorized attempt
    error_log("Unauthorized access attempt to proses-otorisasi.php by user ID: " . (isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'unknown'));
    die("Access denied.");
}
// --------------------------------------

if (!isset($_POST['id_edit']) || !is_array($_POST['id_edit'])) {
  header("Location: ../../home-admin.php?page=otorisasi-approval&msg=invalid");
  exit;
}

$otorisator = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : '0';
$tanggal    = date('Y-m-d H:i:s');
$unit_approval = function_exists('unitKerjaApprovalUser') ? unitKerjaApprovalUser() : (isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '');
$allowed_units = function_exists('simpegScopeKantorApproval') ? simpegScopeKantorApproval($unit_approval) : array(substr($unit_approval, 0, 3));

/* ===== Whitelist kolom tb_pegawai saat approve ===== */
$allowed_map = array(
  'gol_darah'    => 'gol_darah',
  'status_nikah' => 'status_nikah',
  'alamat'       => 'alamat',
  'no_hp'        => 'telp',
  'email'        => 'email',
  // tambahkan sesuai kebutuhan...
);

function po_clean($db, $s) {
  return mysqli_real_escape_string($db, trim($s));
}

function po_new_si_id($db) {
  do {
    $id = 'SI' . substr((string)time(), -4) . rand(10, 99);
    $q = mysqli_query($db, "SELECT id_si FROM tb_suamiistri WHERE id_si='".po_clean($db, $id)."' LIMIT 1");
  } while ($q && mysqli_num_rows($q) > 0);
  return $id;
}

function po_apply_family_change($db, $idPeg, $dataBaru) {
  $req = json_decode($dataBaru, true);
  if (!$req || !isset($req['scope']) || !isset($req['action'])) return false;

  $scope = $req['scope'];
  $action = $req['action'];
  $recordId = isset($req['record_id']) ? $req['record_id'] : '';
  $payload = isset($req['payload']) && is_array($req['payload']) ? $req['payload'] : array();
  $idPeg = po_clean($db, $idPeg);

  $maps = array(
    'pasangan' => array('table' => 'tb_suamiistri', 'pk' => 'id_si', 'cols' => array('nik','nama','tmp_lhr','tgl_lhr','pendidikan','id_pekerjaan','pekerjaan','status_hub','hp','bpjs_pasangan')),
    'anak' => array('table' => 'tb_anak', 'pk' => 'id_anak', 'cols' => array('nik','nama','tmp_lhr','tgl_lhr','pendidikan','id_pekerjaan','pekerjaan','status_hub','anak_ke','bpjs_anak')),
    'ortu' => array('table' => 'tb_ortu', 'pk' => 'id_ortu', 'cols' => array('nik','nama','tmp_lhr','tgl_lhr','pendidikan','id_pekerjaan','pekerjaan','status_hub'))
  );

  if (!isset($maps[$scope])) return false;
  $map = $maps[$scope];
  $table = $map['table'];
  $pk = $map['pk'];
  $recordSafe = po_clean($db, $recordId);

  if ($action === 'delete') {
    if ($recordSafe === '') return false;
    return mysqli_query($db, "DELETE FROM $table WHERE $pk='$recordSafe' AND id_peg='$idPeg' LIMIT 1");
  }

  $sets = array();
  $insertCols = array();
  $insertVals = array();
  foreach ($map['cols'] as $col) {
    $val = isset($payload[$col]) ? $payload[$col] : '';
    $sets[] = "$col='".po_clean($db, $val)."'";
    $insertCols[] = $col;
    $insertVals[] = "'".po_clean($db, $val)."'";
  }

  if ($action === 'update') {
    if ($recordSafe === '') return false;
    return mysqli_query($db, "UPDATE $table SET ".implode(',', $sets)." WHERE $pk='$recordSafe' AND id_peg='$idPeg' LIMIT 1");
  }

  if ($action === 'create') {
    array_unshift($insertCols, 'id_peg');
    array_unshift($insertVals, "'$idPeg'");
    $insertCols[] = 'date_reg';
    $insertVals[] = "CURDATE()";

    if ($scope === 'pasangan') {
      array_unshift($insertCols, 'id_si');
      array_unshift($insertVals, "'".po_clean($db, po_new_si_id($db))."'");
    }

    return mysqli_query($db, "INSERT INTO $table (".implode(',', $insertCols).") VALUES (".implode(',', $insertVals).")");
  }

  return false;
}

function po_apply_biodata_change($db, $idPeg, $dataBaru, $otorisator) {
  $payload = json_decode($dataBaru, true);
  if (!$payload || !is_array($payload)) return false;

  $fieldMap = array(
    'nip' => 'nip',
    'nama' => 'nama',
    'tempat_lhr' => 'tempat_lhr',
    'tgl_lhr' => 'tgl_lhr',
    'jk' => 'jk',
    'agama' => 'agama',
    'gol_darah' => 'gol_darah',
    'status_nikah' => 'status_nikah',
    'alamat' => 'alamat',
    'telp' => 'telp',
    'email' => 'email'
  );

  $sets = array();
  foreach ($fieldMap as $jsonKey => $dbField) {
    if (!array_key_exists($jsonKey, $payload)) continue;
    $sets[] = $dbField . "='" . po_clean($db, $payload[$jsonKey]) . "'";
  }

  if (empty($sets)) return false;

  $sets[] = "updated_by='" . po_clean($db, $otorisator) . "'";
  $sets[] = "updated_at=NOW()";

  return mysqli_query(
    $db,
    "UPDATE tb_pegawai SET " . implode(',', $sets) . " WHERE id_peg='" . po_clean($db, $idPeg) . "' LIMIT 1"
  );
}

/* ===== Penampung untuk notifikasi distinct per pemohon ===== */
$notif_users = array(); // key: id_user, val: array('id_peg'=>last, 'nama'=>last)

/* ===== Proses batch dalam transaksi ===== */
mysqli_autocommit($koneksi, false);
$jumlah_ok = 0;

foreach ($_POST['id_edit'] as $id_edit_raw) {
  $id_edit  = mysqli_real_escape_string($koneksi, $id_edit_raw);
  
  // Validate action exists for this ID
  if (!isset($_POST['aksi'][$id_edit_raw])) { continue; }
  
  $aksi_raw = strtolower(trim($_POST['aksi'][$id_edit_raw]));
  
  // Validate comment exists
  $komentar_raw = isset($_POST['komentar'][$id_edit_raw]) ? $_POST['komentar'][$id_edit_raw] : '';
  $komentar = mysqli_real_escape_string($koneksi, $komentar_raw);

  // Map aksi ke status final
  $statusBaru = '';
  if ($aksi_raw === 'approve' || $aksi_raw === 'setuju') $statusBaru = 'Disetujui';
  if ($aksi_raw === 'reject'  || $aksi_raw === 'tolak')  $statusBaru = 'Ditolak';
  if ($statusBaru === '') { continue; }

  // Ambil pengajuan (dan pastikan masih Menunggu)
  $q = mysqli_query($koneksi, "
    SELECT ep.id_edit,ep.id_peg,ep.jenis_data,ep.data_baru,ep.id_user,ep.status_otorisasi,
           COALESCE(NULLIF(ep.kode_kantor, ''), LEFT(j.unit_kerja, 3)) AS kode_kantor_approval
      FROM tb_edit_pending ep
      LEFT JOIN tb_jabatan j ON j.id_peg = ep.id_peg AND LOWER(j.status_jab) = 'aktif'
     WHERE ep.id_edit = '".$id_edit."'
     LIMIT 1
  ");
  if (!$q || mysqli_num_rows($q) === 0) { continue; }
  $r = mysqli_fetch_assoc($q);

  if ($r['status_otorisasi'] !== 'Menunggu' && $r['status_otorisasi'] !== 'pending') { continue; }
  if (!in_array($r['kode_kantor_approval'], $allowed_units)) { continue; }

  $idPeg     = mysqli_real_escape_string($koneksi, $r['id_peg']);
  $jenisData = $r['jenis_data'];
  $dataBaru  = $r['data_baru']; // bisa plain text
  $idPemohon = $r['id_user'];

  $ok_item = true;

  // Jika Disetujui → apply ke tb_pegawai (whitelist kolom)
  if ($statusBaru === 'Disetujui') {
    if (strpos($jenisData, 'keluarga_') === 0) {
      $ok_item = $ok_item && po_apply_family_change($koneksi, $idPeg, $dataBaru);
      if (!$ok_item) { error_log('FAILED apply family id_edit='.$id_edit.' : '.mysqli_error($koneksi)); }
    } elseif ($jenisData === 'biodata_update') {
      $ok_item = $ok_item && po_apply_biodata_change($koneksi, $idPeg, $dataBaru, $otorisator);
      if (!$ok_item) { error_log('FAILED apply biodata id_edit='.$id_edit.' : '.mysqli_error($koneksi)); }
    } elseif (isset($allowed_map[$jenisData])) {
      $kolomPegawai = $allowed_map[$jenisData];
      $nilaiBaru    = mysqli_real_escape_string($koneksi, $dataBaru);
      
      // Update data pegawai
      $sqlUpdPeg = "
        UPDATE tb_pegawai
           SET $kolomPegawai = '".$nilaiBaru."',
               updated_by    = '".mysqli_real_escape_string($koneksi, $otorisator)."',
               updated_at    = NOW()
         WHERE id_peg = '".$idPeg."'
         LIMIT 1";
      $ok_item = $ok_item && mysqli_query($koneksi, $sqlUpdPeg);
      
      if (!$ok_item) { error_log('FAILED upd tb_pegawai id_edit='.$id_edit.' : '.mysqli_error($koneksi)); }
    }
    // jika jenisData tidak di whitelist, tetap lanjut mengubah status permintaan (logika bisnis)
  }

  // Update status pengajuan
  $sqlUpdReq = "
    UPDATE tb_edit_pending
       SET status_otorisasi  = '".$statusBaru."',
           tanggal_otorisasi = '".$tanggal."',
           otorisator        = '".mysqli_real_escape_string($koneksi, $otorisator)."',
           komentar_otorisasi= '".$komentar."'
     WHERE id_edit = '".$id_edit."'
       AND status_otorisasi IN ('Menunggu','pending')
     LIMIT 1";
  $ok_status = mysqli_query($koneksi, $sqlUpdReq);
  if (!$ok_status) {
    $fallbackStatus = ($statusBaru === 'Disetujui') ? 'approved' : 'rejected';
    $sqlUpdReq = "
      UPDATE tb_edit_pending
         SET status_otorisasi  = '".$fallbackStatus."',
             tanggal_otorisasi = '".$tanggal."',
             otorisator        = '".mysqli_real_escape_string($koneksi, $otorisator)."',
             komentar_otorisasi= '".$komentar."'
       WHERE id_edit = '".$id_edit."'
         AND status_otorisasi IN ('Menunggu','pending')
       LIMIT 1";
    $ok_status = mysqli_query($koneksi, $sqlUpdReq);
  }
  $ok_item = $ok_item && $ok_status;
  
  if (!$ok_item) { error_log('FAILED upd tb_edit_pending id_edit='.$id_edit.' : '.mysqli_error($koneksi)); }

  // Log history
  // Using explicit columns is safer
  $sqlHist = "
    INSERT INTO tb_edit_history (id_edit, action, actor, komentar, acted_at)
    VALUES ('".$id_edit."', '".$statusBaru."', '".mysqli_real_escape_string($koneksi, $otorisator)."', '".$komentar."', NOW())
  ";
  // Suppress error if table doesn't exist, but good to know
  @mysqli_query($koneksi, $sqlHist);

  if ($ok_item) {
    $jumlah_ok++;
    // catat pemohon untuk dikirim notifikasi nanti (distinct)
    $notif_users[$idPemohon] = array('id_peg' => $idPeg);
  }
}

/* ===== Commit/Rollback batch ===== */
if ($jumlah_ok > 0) {
  mysqli_commit($koneksi);
} else {
  mysqli_rollback($koneksi);
}
mysqli_autocommit($koneksi, true);

/* ===== Kirim notifikasi ringkas per pemohon ===== */
if (!empty($notif_users) && $jumlah_ok > 0) {
  foreach ($notif_users as $id_user_pemohon => $payload) {
    // Ambil nama pegawai terakhir
    $namaPeg = '';
    $idPegX  = mysqli_real_escape_string($koneksi, $payload['id_peg']);
    $rp = mysqli_query($koneksi, "SELECT nama FROM tb_pegawai WHERE id_peg='".$idPegX."' LIMIT 1");
    if ($rp && mysqli_num_rows($rp)>0) {
      $np = mysqli_fetch_assoc($rp);
      $namaPeg = $np['nama'];
    }

    $judul = "Status Permintaan Edit";
    $pesan = "Beberapa perubahan data atas nama ".$namaPeg." telah diproses oleh atasan.";
    // Ensure URL is correct relative to user view
    $link  = "home-admin.php?page=notifikasi-user"; 

    $sqlNotif = "
      INSERT INTO tb_notifikasi (id_user, judul, pesan, ref_type, ref_id, link_aksi, status_baca, waktu_notif)
      VALUES ('".mysqli_real_escape_string($koneksi, $id_user_pemohon)."',
              '".$judul."',
              '".$pesan."',
              'edit',
              NULL,
              '".$link."',
              'unread',
              NOW())
    ";
    if (!@mysqli_query($koneksi, $sqlNotif)) {
      $sqlNotif = "
        INSERT INTO tb_notifikasi (id_user, judul, pesan, ref_type, ref_id, link_aksi, status_baca, created_at)
        VALUES ('".mysqli_real_escape_string($koneksi, $id_user_pemohon)."',
                '".$judul."',
                '".$pesan."',
                'edit',
                NULL,
                '".$link."',
                'Belum',
                NOW())
      ";
      @mysqli_query($koneksi, $sqlNotif);
    }
  }
}

/* ===== Redirect ===== */
// Use urlencode for safety in GET params
header("Location: ../../home-admin.php?page=otorisasi-approval&msg=processed&jumlah=".urlencode($jumlah_ok));
exit;
?>
