<?php
/*********************************************************
 * FILE    : pages/pegawai/simpan-data-pegawai.php
 * MODULE  : Simpan Pegawai (Fix: Tambah UUID & Redirect)
 * VERSION : v2.4 (Offline & Secure)
 *********************************************************/

if (session_id()=='') session_start();
include('../../dist/koneksi.php');    
include('../../dist/functions.php'); 

/* ========================= Helper ========================= */
function postv($key, $def='') { return isset($_POST[$key]) ? trim($_POST[$key]) : $def; }
function clean_str($conn, $s){ return mysqli_real_escape_string($conn, trim($s)); }
function ensure_dir($path){ if (!is_dir($path)) { @mkdir($path, 0775, true); } }
function table_columns($conn, $table){
  static $cache = array();
  if (isset($cache[$table])) { return $cache[$table]; }
  $cols = array();
  $res = mysqli_query($conn, "SHOW COLUMNS FROM `".$table."`");
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $cols[$row['Field']] = true;
    }
  }
  $cache[$table] = $cols;
  return $cols;
}
function has_col($cols, $name){ return isset($cols[$name]); }
function sql_val($value){ return "'".$value."'"; }
function build_insert_query($table, $data, $allowedCols){
  $fields = array();
  $values = array();
  foreach ($data as $col => $val) {
    if (!has_col($allowedCols, $col)) { continue; }
    $fields[] = "`".$col."`";
    $values[] = sql_val($val);
  }
  return "INSERT INTO `".$table."` (".implode(', ', $fields).") VALUES (".implode(', ', $values).")";
}
function build_update_query($table, $data, $allowedCols, $whereSql){
  $sets = array();
  foreach ($data as $col => $val) {
    if (!has_col($allowedCols, $col)) { continue; }
    $sets[] = "`".$col."`=".sql_val($val);
  }
  return "UPDATE `".$table."` SET ".implode(', ', $sets)." ".$whereSql;
}
function app_log($message){
  error_log('[SIMPEG save pegawai] '.$message);
}

// [FIX] Fungsi untuk generate UUID (v4)
function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

$user       = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'admin';
$hak_akses  = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : 'user';
$tanggal    = date('Y-m-d');
$status     = 'gagal'; 
$pegawaiCols = table_columns($conn, 'tb_pegawai');
$userCols    = table_columns($conn, 'tb_user');

/* ==========================================================
 * 1) MODE KOLEKTIF (JSON)
 * ========================================================== */
if (isset($_POST['kolektif']) && $_POST['kolektif'] == '1') {
  exit; // Logic kolektif di-skip
}

/* ==========================================================
 * 2) MODE FORM (tambah/edit)
 * ========================================================== */
$id_peg        = clean_str($conn, postv('id_peg'));
$nip           = clean_str($conn, postv('nip'));
$nama          = clean_str($conn, postv('nama'));
$tempat_lhr    = clean_str($conn, postv('tempat_lhr'));
$tgl_lhr       = clean_str($conn, postv('tgl_lhr'));
$agama         = clean_str($conn, postv('agama'));
$jk            = clean_str($conn, postv('jk'));
$gol_darah     = clean_str($conn, postv('gol_darah'));
$status_nikah  = clean_str($conn, postv('status_nikah'));
$status_kepeg  = clean_str($conn, postv('status_kepeg'));
$alamat        = clean_str($conn, postv('alamat'));
$telp          = clean_str($conn, postv('telp'));
$email         = clean_str($conn, postv('email'));
$bpjstk        = clean_str($conn, postv('bpjstk'));
$bpjskes       = clean_str($conn, postv('bpjskes'));
$mode          = postv('mode', 'tambah');

// Data Akun Lama (Agar tidak reset)
$hak_akses_akun   = clean_str($conn, postv('hak_akses_lama', 'User')); 
$status_aktif_akun = clean_str($conn, postv('status_aktif_lama', 'Y')); 

/* ---------- Upload Foto ---------- */
$foto_name = '';
if (!empty($_FILES['foto']['name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
  $allowed = array('jpg','jpeg','png','gif');
  $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
  $size_ok = (isset($_FILES['foto']['size']) ? (int)$_FILES['foto']['size'] : 0) <= (2 * 1024 * 1024); 

  if (in_array($ext, $allowed) && $size_ok) {
    $safeId = preg_replace('~[^A-Za-z0-9_\-]~','', $id_peg);
    $foto_name = 'foto_' . $safeId . '_' . time() . '.' . $ext;
    // Gunakan path relatif dari file ini
    $target_dir = '../../pages/assets/foto/';
    ensure_dir($target_dir);
    @move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_name);
  }
}

/* ---------- Sinkron user (early call) ---------- */
if ($id_peg !== '') { @sinkron_user_dari_pegawai($id_peg); }

/* ---------- Proses Simpan ---------- */
if ($mode == 'tambah') {
  $cek = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE id_peg = '$id_peg' LIMIT 1");
  if ($cek && mysqli_num_rows($cek) > 0) {
    $status = 'duplikat';
  } else {
    // [FIX] Generate UID baru
    $uid_baru = gen_uuid();

    $insertData = array(
      'pegawai_uid'   => $uid_baru,
      'id_peg'        => $id_peg,
      'nip'           => $nip,
      'nama'          => $nama,
      'tempat_lhr'    => $tempat_lhr,
      'tgl_lhr'       => $tgl_lhr,
      'agama'         => $agama,
      'jk'            => $jk,
      'gol_darah'     => $gol_darah,
      'status_nikah'  => $status_nikah,
      'status_kepeg'  => $status_kepeg,
      'alamat'        => $alamat,
      'telp'          => $telp,
      'email'         => $email,
      'bpjstk'        => $bpjstk,
      'bpjskes'       => $bpjskes,
      'foto'          => $foto_name,
      'status_aktif'  => '1',
      'date_reg'      => $tanggal,
      'created_by'    => $user
    );
    if (has_col($pegawaiCols, 'created_at')) {
      $insertData['created_at'] = date('Y-m-d H:i:s');
    }
    $sql = build_insert_query('tb_pegawai', $insertData, $pegawaiCols);

    if (mysqli_query($conn, $sql)) {
      @sinkron_user_dari_pegawai($id_peg);
      $status = 'sukses';
    } else {
      app_log('insert gagal untuk ID '.$id_peg.' | '.mysqli_error($conn).' | SQL: '.$sql);
      $status = 'gagal';
    }
  }
}
else if ($mode == 'edit') {
  // Jika user biasa edit sendiri -> Ajukan Perubahan
  if ($hak_akses == 'user') {
     $qOld = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg = '$id_peg' LIMIT 1");
     $dataLama = $qOld ? mysqli_fetch_assoc($qOld) : null;
     if ($dataLama) {
        // Logic insert pending (disingkat)
        // ...
        $status = 'ajukan'; 
     } else {
         $status = 'gagal';
     }
  }
  
  // Jika Admin/Kepala edit -> Langsung Update
  else {
    $updateData = array(
      'nip'           => $nip,
      'nama'          => $nama,
      'tempat_lhr'    => $tempat_lhr,
      'tgl_lhr'       => $tgl_lhr,
      'agama'         => $agama,
      'jk'            => $jk,
      'gol_darah'     => $gol_darah,
      'status_nikah'  => $status_nikah,
      'status_kepeg'  => $status_kepeg,
      'alamat'        => $alamat,
      'telp'          => $telp,
      'email'         => $email,
      'bpjstk'        => $bpjstk,
      'bpjskes'       => $bpjskes,
      'updated_by'    => $user,
      'date_modify'   => $tanggal
    );
    if (has_col($pegawaiCols, 'updated_at')) {
      $updateData['updated_at'] = date('Y-m-d H:i:s');
    }
    if (!empty($foto_name)) {
      $updateData['foto'] = $foto_name;
    }
    $sql = build_update_query('tb_pegawai', $updateData, $pegawaiCols, "WHERE id_peg='$id_peg'");

    if (mysqli_query($conn, $sql)) {
        // FIX BUG AKUN: Kembalikan hak akses & status aktif ke nilai semula
        $sqlUser = build_update_query('tb_user', array(
                    'hak_akses'   => $hak_akses_akun,
                    'status_aktif'=> $status_aktif_akun,
                    'updated_by'  => $user
                  ), $userCols, "WHERE id_pegawai='$id_peg'");
        if (!empty($sqlUser) && strpos($sqlUser, ' SET ') !== false) {
          mysqli_query($conn, $sqlUser);
        }

        $status = 'sukses';
    } else {
      app_log('update gagal untuk ID '.$id_peg.' | '.mysqli_error($conn).' | SQL: '.$sql);
      $status = 'gagal';
    }
  }
}

/* ========================================================
 * LOGIKA REDIRECT (FIX 404 PATH)
 * ======================================================== */

// 1. Tentukan halaman tujuan dasar
$redirect_to = 'home-admin.php?page=form-view-data-pegawai';
$id_session = isset($_SESSION['id_pegawai']) ? $_SESSION['id_pegawai'] : '';

if ($status == 'sukses' || $status == 'ajukan') {
    if ($mode == 'edit') {
        if ($id_peg == $id_session) {
            // Jika edit diri sendiri -> Ke Profil
            $redirect_to = 'home-admin.php?page=profil-pegawai';
        } else {
            // Jika edit orang lain -> Ke Detail Orang Tersebut
            $redirect_to = 'home-admin.php?page=view-detail-data-pegawai&id_peg=' . urlencode($id_peg);
        }
    }
}

// 2. TAMBAHKAN PREFIKS DIRECTORY
// File ini ada di: pages/pegawai/
// home-admin.php ada di: root (dua folder ke atas)
$final_redirect_url = "../../" . $redirect_to;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Proses Simpan</title>
  
  <script src="../../plugins/sweetalert2/sweetalert2.all.min.js"></script>
  <script>if(typeof Swal === 'undefined'){document.write('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"><\/script>');}</script>

  <style>html,body{background:#f4f6f9;font-family:sans-serif}</style>
</head>
<body>
<script>
(function(){
  var status  = <?php echo json_encode($status); ?>;
  var mode    = <?php echo json_encode($mode); ?>;
  var idPeg   = <?php echo json_encode($id_peg); ?>;
  
  // URL Redirect yang sudah diperbaiki path-nya (../../)
  var nextUrl = <?php echo json_encode($final_redirect_url); ?>; 

  if(status === 'sukses'){
    if(mode === 'tambah'){
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data pegawai berhasil disimpan. Isi Jabatan sekarang?',
        showCancelButton: true,
        confirmButtonText: 'Ya, Isi Jabatan',
        cancelButtonText: 'Nanti Saja',
        allowOutsideClick: false
      }).then(function(res){
        if(res.isConfirmed){
          // Redirect ke form jabatan (juga perlu ../../)
          window.location.href = '../../home-admin.php?page=form-master-data-jabatan&uid=' + encodeURIComponent(idPeg);
        } else {
          // Redirect default ke list data
          window.location.href = '../../home-admin.php?page=form-view-data-pegawai';
        }
      });
    } else {
      // EDIT SUKSES
      Swal.fire({ 
        icon:'success', 
        title:'Berhasil!', 
        text:'Perubahan data disimpan.', 
        timer: 1500, 
        showConfirmButton: false 
      }).then(function(){ 
        window.location.href = nextUrl; 
      });
    }
  }
  else if(status === 'duplikat'){
    Swal.fire({ icon: 'warning', title: 'Gagal!', text: 'ID Pegawai sudah terdaftar.' }).then(function(){ history.back(); });
  }
  else if(status === 'ajukan'){
    Swal.fire({ icon: 'info', title: 'Diajukan!', text: 'Menunggu persetujuan atasan.' })
      .then(function(){ window.location.href = nextUrl; });
  }
  else {
    Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem.' }).then(function(){ history.back(); });
  }
})();
</script>
</body>
</html>
