<?php
// Mencegah error tampil di output JSON
ini_set('display_errors', 0);
error_reporting(0);

if (session_id() === '') session_start();
include "../../dist/koneksi.php";

if (empty($_SESSION['id_user'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(array(
        'draw' => isset($_GET['draw']) ? (int) $_GET['draw'] : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => array(),
        'error' => 'Akses ditolak'
    )));
}

// Fungsi Helper
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function resolve_foto_url($filename, $jk){
    $baseDir = __DIR__ . "/../.."; 
    $newFs = $baseDir . "/uploads/foto/"; 
    $oldFs = $baseDir . "/pages/assets/foto/";  
    $newUrlBase = "uploads/foto/"; 
    $oldUrlBase = "pages/assets/foto/";

    // 1. Daftar variasi ekstensi (PENTING: Linux bedakan .jpg dan .JPG)
    $exts = ['.jpg', '.jpeg', '.png', '.JPG', '.JPEG', '.PNG'];

    if ($filename && trim($filename) !== '') {
        // 2. Looping untuk mencari file dengan ekstensi
        foreach ($exts as $ext) {
            // Cek di folder baru
            if (file_exists($newFs . $filename . $ext)) {
                return $newUrlBase . $filename . $ext;
            }
            // Cek di folder lama
            if (file_exists($oldFs . $filename . $ext)) {
                return $oldUrlBase . $filename . $ext;
            }
        }

        // 3. Jaga-jaga kalau di DB sudah ada ekstensinya (data lama)
        if (file_exists($newFs . $filename)) return $newUrlBase . $filename;
        if (file_exists($oldFs . $filename)) return $oldUrlBase . $filename;
    }
    
    $fallback = ($jk === 'Laki-laki') ? 'no-foto-male.png' : 'no-foto-female.png';
    if (file_exists($oldFs . $fallback)) {
        return $oldUrlBase . $fallback;
    } else {
        return $oldUrlBase . 'default-user.png'; 
    }
}
// --- PARAMETER & SANITASI ---
$columnsDB = [ 0 => 'p.nama', 1 => 'p.tgl_lhr', 2 => 'j.jabatan', 3 => 'p.tmt_kerja', 4 => 'p.telp' ];
$orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
$orderColumn = isset($columnsDB[$orderColumnIndex]) ? $columnsDB[$orderColumnIndex] : 'p.nama';
$orderDir = (isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';

$filterType    = isset($_GET['filter_type']) ? $_GET['filter_type'] : ''; 
$filterKantor  = isset($_GET['kantor']) ? mysqli_real_escape_string($conn, $_GET['kantor']) : '';
$filterDivisi  = isset($_GET['divisi']) ? mysqli_real_escape_string($conn, $_GET['divisi']) : '';
$filterJabatan = isset($_GET['jabatan']) ? mysqli_real_escape_string($conn, $_GET['jabatan']) : '';
$search        = isset($_GET['search']['value']) ? mysqli_real_escape_string($conn, $_GET['search']['value']) : '';

$limit  = isset($_GET['length']) ? intval($_GET['length']) : 10;
$offset = isset($_GET['start']) ? intval($_GET['start']) : 0;

// --- QUERY BUILDER ---
$activeCondition = "p.status_aktif IN (1, '1', 'Y', 'y', 'Aktif')";

$sqlBase = "
    FROM tb_pegawai p
    LEFT JOIN tb_jabatan j ON p.id_peg = j.id_peg AND j.status_jab = 'Aktif'
    LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail
    LEFT JOIN tb_master_jabatan m ON j.jabatan = m.nama_jabatan 
    WHERE ".$activeCondition."
";

// Filter Logic
if ($filterType === 'nonjob') {
    $sqlBase .= " AND j.id_jab IS NULL";
} else {
    $sqlBase .= " AND j.id_jab IS NOT NULL"; 
    
    if ($filterKantor !== '') {
        $cekK = mysqli_query($conn, "SELECT level, kode_cabang FROM tb_kantor WHERE kode_kantor_detail='$filterKantor'");
        if ($cekK && mysqli_num_rows($cekK) > 0) {
            $dK = mysqli_fetch_assoc($cekK);
            if ($dK['level'] == 'KC') {
                $kode_cabang = mysqli_real_escape_string($conn, $dK['kode_cabang']);
                $sqlBase .= " AND k.kode_cabang = '$kode_cabang'";
            } else {
                $sqlBase .= " AND j.unit_kerja = '$filterKantor'";
            }
        } else {
             $sqlBase .= " AND j.unit_kerja = '$filterKantor'";
        }
    }

    if ($filterDivisi !== '') {
        if (is_numeric($filterDivisi)) {
            $sqlBase .= " AND j.unit_kerja = '$filterDivisi'";
        } else {
            $sqlBase .= " AND m.nama_unit_kerja = '$filterDivisi'";
        }
    }

    if ($filterJabatan !== '') {
        $sqlBase .= " AND j.jabatan = '$filterJabatan'";
    }
}

if ($search !== '') {
    $sqlBase .= " AND (
        p.id_peg LIKE '%$search%' OR 
        p.nama LIKE '%$search%' OR 
        j.jabatan LIKE '%$search%' OR 
        k.nama_kantor LIKE '%$search%'
    )";
}

// --- EKSEKUSI QUERY TOTAL (Pakai DISTINCT agar hitungan tidak ganda) ---
$sqlTotalFiltered = "SELECT COUNT(DISTINCT p.id_peg) as jum " . $sqlBase;
$queryCount = mysqli_query($conn, $sqlTotalFiltered);
$rowCount   = mysqli_fetch_assoc($queryCount);
$totalFiltered = $rowCount['jum'];

// Hitung Total Semua Data
$sqlTotalRaw = "SELECT COUNT(DISTINCT p.id_peg) as jum FROM tb_pegawai p LEFT JOIN tb_jabatan j ON p.id_peg = j.id_peg AND j.status_jab = 'Aktif' WHERE ".$activeCondition;
if ($filterType === 'nonjob') $sqlTotalRaw .= " AND j.id_jab IS NULL"; 
else $sqlTotalRaw .= " AND j.id_jab IS NOT NULL";

$qTotalAll = mysqli_query($conn, $sqlTotalRaw);
$totalAll  = mysqli_fetch_assoc($qTotalAll)['jum'];

// --- EKSEKUSI QUERY DATA UTAMA ---
// PERBAIKAN 1: Tambahkan 'k.level' untuk cek dia Pusat/Cabang
// PERBAIKAN 2: Tambahkan 'GROUP BY p.id_peg' agar data pegawai TIDAK DOUBLE
$sqlData = "SELECT p.id_peg, p.nama, p.tempat_lhr, p.tgl_lhr, p.tmt_kerja, p.telp, p.foto, p.jk, p.status_kepeg, 
                   j.jabatan, k.nama_kantor, k.level as level_kantor, 
                   m.nama_unit_kerja AS divisi_master " 
         . $sqlBase 
         . " GROUP BY p.id_peg " 
         . " ORDER BY $orderColumn $orderDir LIMIT $offset, $limit";

$result = mysqli_query($conn, $sqlData);
$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // --- PERBAIKAN 3: LOGIC PENENTUAN DIVISI ---
        $jabatan_tampil = h($row['jabatan']); // Tetap "Sopir"
        $divisi_tampil  = h($row['divisi_master']); 
        $level_kantor   = isset($row['level_kantor']) ? $row['level_kantor'] : '';

        // Jika Jabatan adalah Sopir (atau jabatan lain yang namanya kembar)
        if (strtolower($jabatan_tampil) == 'sopir') {
            // Cek apakah dia di Kantor Cabang (KC) atau KCP
            // Asumsi kode level: 'KC' = Cabang, 'KCP' = Capem. Sesuaikan dengan databasemu.
            if ($level_kantor == 'KC' || $level_kantor == 'KCP') {
                $divisi_tampil = 'Kantor Cabang'; // Paksa ubah jadi Kantor Cabang
            } else {
                // Jika Pusat (KP), biarkan default dari master (Divisi SDM dan Umum)
                // Atau kalau mau dipaksa juga bisa: $divisi_tampil = 'Divisi SDM dan Umum';
            }
        }
        // ---------------------------------------------

        $fotoUrl  = resolve_foto_url($row['foto'], $row['jk']);
        $fotoHtml = '<div class="avatar-wrapper"><img src="'.h($fotoUrl).'?cb='.time().'" class="avatar-img" loading="lazy"></div>';
        
        $tgl_lhr = ($row['tgl_lhr']) ? date('d-m-Y', strtotime($row['tgl_lhr'])) : '-';
        $ttl = h($row['tempat_lhr']) . ', ' . $tgl_lhr;
        
        $action = '<div class="btn-group">';
        if ($filterType === 'nonjob') {
            $cleanID = preg_replace('/[^a-zA-Z0-9-]/', '', $row['id_peg']);
            $action .= '<a href="home-admin.php?page=form-master-data-jabatan&uid='.$cleanID.'" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm font-weight-bold"><i class="fa fa-plus-circle mr-1"></i> Set Jabatan</a>';
        } else {
            $action .= '<a href="home-admin.php?page=view-detail-data-pegawai&id_peg='.h($row['id_peg']).'" class="btn btn-sm btn-action-blue"><i class="fa fa-folder-open"></i></a>';
            if (isset($_SESSION['hak_akses']) && ($_SESSION['hak_akses']=='admin' || $_SESSION['hak_akses']=='kepala')) {
                $action .= '<a href="home-admin.php?page=form-master-data-pegawai&mode=edit&id='.h($row['id_peg']).'" class="btn btn-sm btn-action-orange ml-1"><i class="fa fa-edit"></i></a>';
            }
        }
        $action .= '</div>';

        $tgl_masuk = ($row['tmt_kerja']) ? date('d-m-Y', strtotime($row['tmt_kerja'])) : '-';

        $data[] = [
            'nama_teks' => h($row['nama']), 
            'nama_foto' => $fotoHtml, 
            'id_peg'    => h($row['id_peg']),
            'ttl'       => $ttl, 
            'jabatan'   => $jabatan_tampil, // Tampil: "Sopir"
            'kantor'    => h($row['nama_kantor']), 
            'divisi'    => $divisi_tampil, // Tampil: "Kantor Cabang" (Jika dia di cabang)
            'tgl_masuk' => $tgl_masuk, 
            'no_telp'   => h($row['telp']), 
            'action'    => $action
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    "draw"            => intval($_GET['draw']), 
    "recordsTotal"    => intval($totalAll), 
    "recordsFiltered" => intval($totalFiltered), 
    "data"            => $data
]);
?>
