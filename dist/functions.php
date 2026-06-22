<?php
function cekAkses($roles = []) {
  if (!isset($_SESSION['id_user']) || !in_array($_SESSION['hak_akses'], $roles)) {
    echo "<script>alert('Akses ditolak'); window.location='index.php';</script>";
    exit;
  }
}

function aturSessionTimeout($detik, $redirect) {
  if (isset($_SESSION['start_session'])) {
    if (time() - $_SESSION['start_session'] >= $detik) {
      // Pastikan tidak ada output sebelumnya
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
      }

      // Bersihkan buffer jika ada
      if (ob_get_length()) ob_end_clean();

      echo "
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset='UTF-8'>
          <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
          <script>
            Swal.fire({
              title: 'Sesi Habis',
              text: 'Sesi Anda telah berakhir. Anda akan dialihkan ke halaman login.',
              icon: 'warning',
              confirmButtonText: 'OK',
              allowOutsideClick: false
            }).then((result) => {
              if (result.isConfirmed) {
                window.location = '$redirect';
              }
            });
          </script>
        </body>
        </html>
      ";
      exit;
    }
  } else {
    // Inisialisasi awal waktu sesi jika belum ada
    $_SESSION['start_session'] = time();
  }
}

function simpeg_role_otomatis_dari_jabatan($jabatan) {
  $jabatan = strtolower(trim((string) $jabatan));

  if ($jabatan === '') {
    return 'User';
  }

  if (strpos($jabatan, 'direktur') !== false || strpos($jabatan, 'direksi') !== false) {
    return 'Superadmin';
  }

  $is_kepala_unit = strpos($jabatan, 'kepala cabang') !== false || strpos($jabatan, 'kepala kantor') !== false;
  $is_kabid_operasional = strpos($jabatan, 'operasional') !== false
      && (strpos($jabatan, 'kabid') !== false || strpos($jabatan, 'kepala bidang') !== false);

  return ($is_kepala_unit || $is_kabid_operasional) ? 'Kepala' : 'User';
}


function sinkron_user_dari_pegawai($id_peg) {
  include 'koneksi.php';

  // Ambil data pegawai
  $q = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg = '$id_peg'");
  if (!$q || mysqli_num_rows($q) == 0) return;

  $peg = mysqli_fetch_assoc($q);
  $nama = $peg['nama'];
  $jabatan = '';
  $qJabatan = mysqli_query($conn, "
    SELECT jabatan
    FROM tb_jabatan
    WHERE id_peg = '$id_peg' AND LOWER(status_jab) = 'aktif'
    ORDER BY tmt_jabatan DESC, id_jab DESC
    LIMIT 1
  ");
  if ($qJabatan && mysqli_num_rows($qJabatan) > 0) {
    $rowJabatan = mysqli_fetch_assoc($qJabatan);
    $jabatan = $rowJabatan['jabatan'];
  }
  $status_aktif = $peg['status_aktif'];
  $hak_akses = simpeg_role_otomatis_dari_jabatan($jabatan);
  $username = strtolower(preg_replace('/[^a-z0-9]/', '', explode(' ', $peg['nama'])[0])); // e.g. linda
  $password_default = password_hash('123456', PASSWORD_DEFAULT);
  $created_by = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'system';

  // Cek apakah user dengan id_pegawai ini sudah ada
  $cek = mysqli_query($conn, "SELECT * FROM tb_user WHERE id_pegawai = '$id_peg'");

  if (mysqli_num_rows($cek) == 0 && $status_aktif == 'Y') {
    // Buat user baru
    mysqli_query($conn, "INSERT INTO tb_user 
      (id_user, nama_user, jabatan, password, hak_akses, status_aktif, created_by, id_pegawai)
      VALUES
      ('$username', '$nama', '$jabatan', '$password_default', '$hak_akses', 'Y', '$created_by', '$id_peg')");
  } else {
    // Update user yang ada
    $update_status = ($status_aktif == 'Y') ? 'Y' : 'N';
    mysqli_query($conn, "UPDATE tb_user SET
      nama_user = '$nama',
      jabatan = '$jabatan',
      hak_akses = '$hak_akses',
      status_aktif = '$update_status',
      updated_by = '$created_by'
      WHERE id_pegawai = '$id_peg'");
  }
}


function logAktivitas($id_user, $aksi, $keterangan) {
  global $conn;
  $ip = $_SERVER['REMOTE_ADDR'];
  $agent = $_SERVER['HTTP_USER_AGENT'];
  $stmt = $conn->prepare("INSERT INTO tb_log_aktivitas (id_user, aksi, keterangan, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("issss", $id_user, $aksi, $keterangan, $ip, $agent);
  $stmt->execute();
}


function hanyaAdmin() {
    $role = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
    if ($role != 'admin' && $role != 'superadmin') {
        echo "Akses ditolak.";
        exit;
    }
}

function aksesAdminKepala() {
    return isset($_SESSION['hak_akses']) && in_array(strtolower($_SESSION['hak_akses']), ['admin', 'superadmin', 'kepala']);
}

function getJabatanAktifPegawai($id_peg) {
    global $conn;
    if (!isset($conn) || !$conn || $id_peg === '') {
        return null;
    }

    $id_safe = mysqli_real_escape_string($conn, $id_peg);
    $q = mysqli_query($conn, "
        SELECT id_peg, jabatan, unit_kerja
        FROM tb_jabatan
        WHERE id_peg = '".$id_safe."'
          AND LOWER(status_jab) = 'aktif'
        ORDER BY tmt_jabatan DESC, id_jab DESC
        LIMIT 1
    ");

    return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
}

function userBisaApprovalOtorisasi() {
    $role = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
    if ($role === 'admin' || $role === 'superadmin' || $role === 'kepala') {
        return true;
    }

    $id_peg = isset($_SESSION['id_pegawai']) ? $_SESSION['id_pegawai'] : '';
    $jab = getJabatanAktifPegawai($id_peg);
    if (!$jab) {
        return false;
    }

    $nama_jabatan = strtolower($jab['jabatan']);
    return (strpos($nama_jabatan, 'operasional') !== false)
        && (strpos($nama_jabatan, 'kabid') !== false || strpos($nama_jabatan, 'kepala bidang') !== false);
}

function unitKerjaApprovalUser() {
    if (isset($_SESSION['kode_kantor']) && $_SESSION['kode_kantor'] !== '') {
        return $_SESSION['kode_kantor'];
    }

    $id_peg = isset($_SESSION['id_pegawai']) ? $_SESSION['id_pegawai'] : '';
    $jab = getJabatanAktifPegawai($id_peg);
    return ($jab && isset($jab['unit_kerja'])) ? $jab['unit_kerja'] : '';
}

function simpegKodeKantorApproval($kode_kantor) {
    $kode_kantor = preg_replace('/[^0-9A-Za-z]/', '', (string) $kode_kantor);
    if ($kode_kantor === '') {
        return '';
    }

    return substr($kode_kantor, 0, 3);
}

function simpegScopeKantorApproval($kode_kantor) {
    $kode = simpegKodeKantorApproval($kode_kantor);
    if ($kode === '') {
        return array();
    }

    if ($kode === '000') {
        $scope = array();
        for ($i = 0; $i <= 28; $i++) {
            $scope[] = sprintf('%03d', $i);
        }
        return $scope;
    }

    return array($kode);
}

function simpegSqlInKantorApproval($conn, $column, $kode_kantor) {
    $scope = simpegScopeKantorApproval($kode_kantor);
    if (empty($scope)) {
        return '1=0';
    }

    $safe = array();
    foreach ($scope as $kode) {
        $safe[] = "'" . mysqli_real_escape_string($conn, $kode) . "'";
    }

    return $column . ' IN (' . implode(',', $safe) . ')';
}

function isKepala() {
    return isset($_SESSION['hak_akses']) && $_SESSION['hak_akses'] === 'kepala';
}

function filterKantor($alias = 'j') {
  if ($_SESSION['hak_akses'] === 'kepala' && isset($_SESSION['kode_kantor'])) {
    return "AND $alias.unit_kerja = '{$_SESSION['kode_kantor']}'";
  }
  return ''; // untuk admin/user tanpa filter
}

function proteksiAksesPegawai($id_peg) {
    if ($_SESSION['hak_akses'] == 'user' && $_SESSION['id_pegawai'] != $id_peg) {
        echo "Akses ditolak.";
        exit;
    }
}

function simpeg_avatar_default($gender = '') {
    $gender = strtolower(trim((string) $gender));
    return in_array($gender, array('perempuan', 'p', 'wanita'), true)
        ? 'dist/img/avatar3.png'
        : 'dist/img/avatar5.png';
}

function simpeg_resolve_photo_path($foto_db, $gender = '', $append_bust = true) {
    $avatar = simpeg_avatar_default($gender);
    $foto_db = trim((string) $foto_db);

    if ($foto_db === '') {
        return $avatar;
    }

    $baseDir = 'pages/assets/foto/';
    $extensions = array('', '.jpg', '.jpeg', '.png', '.webp', '.JPG', '.JPEG', '.PNG', '.WEBP');

    foreach ($extensions as $ext) {
        $candidate = $baseDir . $foto_db . $ext;
        if (file_exists($candidate) && is_file($candidate)) {
            return $append_bust ? ($candidate . '?t=' . time()) : $candidate;
        }
    }

    return $avatar;
}

function getPage($page) {
  $routes = [
    // Dashboard
    'dashboard' => 'dashboard.php',

    // Dashboard Cabang
    'dashboard-cabang' => 'dashboard-cabang.php',

    // Master
    'form-view-data-pegawai'       => 'pages/pegawai/form-view-data-pegawai.php',
    'form-edit-data-pegawai'       => 'pages/pegawai/form-edit-data-pegawai.php',
    'form-upload-data-pegawai'     => 'pages/pegawai/form-upload-data-pegawai.php',
    'view-detail-data-pegawai'     => 'pages/pegawai/view-detail-data-pegawai.php',
    'form-master-data-pegawai'     => 'pages/pegawai/form-master-data-pegawai.php',
    'simpan-data-pegawai'          => 'pages/pegawai/simpan-data-pegawai.php',
    'edit-data-pegawai'            => 'pages/pegawai/edit-data-pegawai.php',
    'delete-data-pegawai'          => 'pages/pegawai/delete-data-pegawai.php',
    'upload-data-pegawai'          => 'pages/pegawai/upload-data-pegawai.php',
    'profil-pegawai'               => 'pages/pegawai/profil-pegawai.php',
    'notifikasi-user'              => 'pages/pegawai/notifikasi-user.php',
    'preview-edit'                 => 'pages/pegawai/preview-edit.php',  
    'form-ganti-foto'              => 'pages/pegawai/form-ganti-foto.php',
    'ganti-foto'                   => 'pages/pegawai/ganti-foto.php',  
    'form-ubah-id-peg'             => 'pages/pegawai/form-ubah-id-peg.php',
    'proses-ubah-id'               => 'pages/pegawai/proses-ubah-id.php',

    // Otorisasi    
    'otorisasi-approval'           => 'pages/otorisasi/otorisasi-approval.php',
    'otorisasi-detail'             => 'pages/otorisasi/otorisasi-detail.php',
    'proses-otorisasi'             => 'pages/otorisasi/proses-otorisasi.php',    
    
    // Config
    'form-config-aplikasi'         => 'pages/config/form-config-aplikasi.php',
    'config-aplikasi'              => 'pages/config/config-aplikasi.php',
    
    // Ref Keluarga
    'form-view-data-suami-istri'   => 'pages/ref-keluarga/form-view-data-suami-istri.php',
    'form-master-data-suami-istri' => 'pages/ref-keluarga/form-master-data-suami-istri.php',
    'form-edit-data-suami-istri'   => 'pages/ref-keluarga/form-edit-data-suami-istri.php',
    'form-edit-data-anak'          => 'pages/ref-keluarga/form-edit-data-anak.php',   
    'form-view-data-anak'          => 'pages/ref-keluarga/form-view-data-anak.php',
    'form-master-data-anak'        => 'pages/ref-keluarga/form-master-data-anak.php',  
    'form-import-data-anak'        => 'pages/ref-keluarga/form-import-data-anak.php',        
    'form-view-data-ortu'          => 'pages/ref-keluarga/form-view-data-ortu.php',
    'form-master-data-ortu'        => 'pages/ref-keluarga/form-master-data-ortu.php',
    'form-import-data-ortu'        => 'pages/ref-keluarga/form-import-data-ortu.php',
    'form-edit-data-ortu'          => 'pages/ref-keluarga/form-edit-data-ortu.php',
    'form-import-data-pasangan'    => 'pages/ref-keluarga/form-import-data-pasangan.php',

    // Ref Pelanggaran
    'form-view-data-pelanggaran'   => 'pages/ref-pelanggaran/form-view-data-pelanggaran.php',
    'form-edit-data-hukuman'       => 'pages/ref-pelanggaran/form-edit-data-hukuman.php',
    'delete-data-hukuman'          => 'pages/ref-pelanggaran/delete-data-hukuman.php',
    'form-master-data-pelanggaran'     => 'pages/ref-pelanggaran/form-master-data-pelanggaran.php',
    'master-data-hukuman'          => 'pages/ref-pelanggaran/master-data-hukuman.php',
    'form-upload-hukuman'          => 'pages/ref-pelanggaran/form-upload-hukuman.php',
    'proses-upload-hukuman'        => 'pages/ref-pelanggaran/proses-upload-hukuman.php',



    // Pendidikan
    'form-view-data-pendidikan'        => 'pages/ref-pendidikan/form-view-data-pendidikan.php',
    'form-master-data-pendidikan'      => 'pages/ref-pendidikan/form-master-data-pendidikan.php',
    'form-import-data-pendidikan'      => 'pages/ref-pendidikan/form-import-data-pendidikan.php',

    // Jabatan
    'form-view-data-jabatan'        => 'pages/ref-jabatan/form-view-data-jabatan.php',
    'form-master-data-jabatan'      => 'pages/ref-jabatan/form-master-data-jabatan.php',
    'form-import-jabatan'           => 'pages/ref-jabatan/form-import-jabatan.php',
    'form-add-master-data-jabatan'  => 'pages/ref-jabatan/form-add-master-data-jabatan.php',
    'form-master-create-history-jabatan' => 'pages/ref-jabatan/form-master-create-history-jabatan.php',

    // Kantor
    'form-view-data-kantor'        => 'pages/kantor/form-view-data-kantor.php',

  

    // Sertifikasi (contoh tambahan)
    'form-view-data-sertifikasi'   => 'pages/ref-sertifikasi/form-view-data-sertifikasi.php',
    'form-master-data-sertifikasi' => 'pages/ref-sertifikasi/form-master-data-sertifikasi.php',
    'form-import-data-sertifikasi' => 'pages/ref-sertifikasi/form-import-data-sertifikasi.php',
    'form-edit-data-sertifikasi'   => 'pages/ref-sertifikasi/form-edit-data-sertifikasi.php',

    // Diklat
    'form-view-data-diklat'        => 'pages/ref-diklat/form-view-data-diklat.php',
    'form-master-data-diklat'      => 'pages/ref-diklat/form-master-data-diklat.php',    
    'form-import-data-diklat'      => 'pages/ref-diklat/form-import-data-diklat.php',
    'proses-diklat'                => 'pages/ref-diklat/proses-diklat.php',
    'import-diklat'                => 'pages/ref-diklat/import-diklat.php',
    'proses-import-diklat'         => 'pages/ref-diklat/proses-import-diklat.php',

    // Laporan
    'nominatif'                    => 'pages/report/nominatif-pegawai.php',
    'formasi'                      => 'pages/report/formasi-pegawai.php',
    'keadaan-pegawai'              => 'pages/report/keadaan-pegawai.php',
    'rekap-biaya-diklat'           => 'pages/report/view-rekap.php',
    // 'rekap-biaya-diklat'           => 'pages/report/rekap-biaya-diklat.php',

    // Tambahkan lebih lanjut sesuai kebutuhan Anda...
    
    // mutasi
    'form-view-data-mutasi'        => 'pages/ref-mutasi/form-view-data-mutasi.php',
    'form-master-data-mutasi'      => 'pages/ref-mutasi/form-master-data-mutasi.php',
    'master-data-mutasi'           => 'pages/ref-mutasi/master-data-mutasi.php',
    'form-edit-data-mutasi'        => 'pages/ref-mutasi/form-edit-data-mutasi.php',


    // diklat
    'master-data-diklat'        => 'pages/ref-diklat/master-data-diklat.php',
    'form-diklat'               => 'pages/ref-diklat/form-diklat.php',
    'ref-diklat'                => 'pages/ref-diklat/ref-diklat.php',
    // 'rekap-biaya-diklat'      => 'pages/ref-diklat/rekap-biaya-diklat.php',

    // User
    'form-view-data-user'          => 'pages/user/form-view-data-user.php',
    'form-master-data-user'        => 'pages/user/form-master-data-user.php',


    // biaya-pendidikan
    'view-data-biaya-pendidikan'          => 'pages/ref-biaya-pendidikan/view-data-biaya-pendidikan.php',
    'form-biaya-pendidikan'               => 'pages/ref-biaya-pendidikan/form-biaya-pendidikan.php',
    'form-upload-data-biaya-pendidikan'   => 'pages/ref-biaya-pendidikan/form-upload-biaya-pendidikan.php',
    'view-rekap-biaya'                    => 'pages/report/view-rekap-biaya.php'

  ];

  return isset($routes[$page]) ? $routes[$page] : '404.php';
}
