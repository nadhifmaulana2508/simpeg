<?php
// FILE: pages/report/ajax-nominatif-pegawai.php
if (session_id() == '') session_start();
ini_set('display_errors', 0);
while(ob_get_level()){ ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

@include_once __DIR__ . '/../../dist/koneksi.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; $conn = isset($koneksi)?$koneksi:null; }

function esc($conn, $str){ return mysqli_real_escape_string($conn, trim($str)); }
function h($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// PARAMETER DATATABLES
$draw   = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$len    = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search = isset($_GET['search']['value']) ? esc($conn, $_GET['search']['value']) : '';

// PARAMETER FILTER
$unit_kerja   = isset($_GET['unit_kerja']) ? esc($conn, $_GET['unit_kerja']) : '';
$jabatan      = isset($_GET['jabatan']) ? esc($conn, $_GET['jabatan']) : '';
$status_kepeg = isset($_GET['status_kepeg']) ? esc($conn, $_GET['status_kepeg']) : '';

// HAK AKSES KEPALA
$hak_akses = isset($_SESSION['hak_akses']) ? $_SESSION['hak_akses'] : '';
$kode_kantor_user = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';
if ($hak_akses == 'kepala') { $unit_kerja = $kode_kantor_user; }

// --- QUERY BUILDER ---
$sqlJoin = "FROM tb_pegawai p
            LEFT JOIN tb_jabatan j ON p.id_peg = j.id_peg AND j.status_jab = 'Aktif'
            LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail
            LEFT JOIN tb_pendidikan s ON s.id_pendidikan = (
                SELECT s2.id_pendidikan
                FROM tb_pendidikan s2
                WHERE s2.id_peg = p.id_peg
                ORDER BY
                    CASE 
                        WHEN s2.tgl_ijazah IS NULL OR s2.tgl_ijazah = '0000-00-00' THEN 1 
                        ELSE 0 
                    END ASC,
                    s2.tgl_ijazah DESC,
                    CASE
                        WHEN s2.th_lulus IS NULL OR s2.th_lulus = '' OR s2.th_lulus = '0000' THEN 0
                        ELSE CAST(s2.th_lulus AS UNSIGNED)
                    END DESC,
                    s2.id_pendidikan DESC
                LIMIT 1
            )";

$where = "WHERE p.status_aktif = 1";

// 1. SMART FILTER KANTOR
if ($unit_kerja != '') {
    $qK = mysqli_query($conn, "SELECT level, kode_cabang FROM tb_kantor WHERE kode_kantor_detail = '$unit_kerja'");
    $dK = mysqli_fetch_assoc($qK);

    if ($dK && $dK['level'] == 'KC') {
        // Jika KC, ambil Induk + semua KK (berdasarkan kode cabang)
        $kode_cabang = $dK['kode_cabang'];
        $where .= " AND k.kode_cabang = '$kode_cabang'";
    } else {
        // Exact match untuk KP atau Unit lain
        $where .= " AND j.unit_kerja = '$unit_kerja'";
    }
}

// 2. FILTER LAIN
if ($jabatan != '') { $where .= " AND j.jabatan = '$jabatan'"; }
if ($status_kepeg != '') { $where .= " AND p.status_kepeg = '$status_kepeg'"; }

// 3. SEARCH GLOBAL
if ($search != '') {
    $where .= " AND (p.nama LIKE '%$search%' OR p.id_peg LIKE '%$search%' OR j.jabatan LIKE '%$search%' OR k.nama_kantor LIKE '%$search%')";
}

// EKSEKUSI DATA
$qCount = mysqli_query($conn, "SELECT COUNT(*) AS total $sqlJoin $where");
$totalData = ($qCount) ? mysqli_fetch_assoc($qCount)['total'] : 0;

$query = "SELECT p.id_peg, p.nama, p.id_peg, p.status_kepeg, j.jabatan, j.tmt_jabatan, k.nama_kantor, s.nama_sekolah, s.jenjang 
          $sqlJoin $where 
          ORDER BY p.nama ASC LIMIT $start, $len";
$q = mysqli_query($conn, $query);

$data = [];
$no = $start + 1;
if($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        // Badge Status (Logic diperbaiki biar nangkep 'Pegawai Tetap' & 'Tetap')
        $st = strtolower($row['status_kepeg']);
        $statusClass = 'status-default';
        if (strpos($st, 'tetap') !== false) {
            $statusClass = 'status-tetap';
        } elseif (strpos($st, 'calon') !== false || strpos($st, 'capeg') !== false) {
            $statusClass = 'status-calon';
        } elseif (strpos($st, 'kontrak') !== false || strpos($st, 'pkwt') !== false) {
            $statusClass = 'status-kontrak';
        } elseif (strpos($st, 'thl') !== false || strpos($st, 'outsource') !== false) {
            $statusClass = 'status-thl';
        }

        $status = "<span class='badge-pill-status " . $statusClass . "'>" . h($row['status_kepeg']) . "</span>";
        
        // Data Formatting
        $pend = empty($row['jenjang'])
            ? "<span class='pendidikan-sekolah'>Belum ada data pendidikan</span>"
            : "<span class='pendidikan-jenjang'>" . h($row['jenjang']) . "</span><span class='pendidikan-sekolah'>" . h($row['nama_sekolah']) . "</span>";
        $tmt = ($row['tmt_jabatan'] && $row['tmt_jabatan']!='0000-00-00') ? date('d-m-Y', strtotime($row['tmt_jabatan'])) : '-';

        $data[] = [
            "no" => $no++,
            "nama" => "<div class='pegawai-name'>" . h($row['nama']) . "</div>",
            "nip" => "<div class='pegawai-id'>" . h($row['id_peg']) . "</div>",
            "jabatan" => "<span class='jabatan-chip'>" . h($row['jabatan'] ?: '-') . "</span>",
            "unit_kerja" => "<span class='kantor-name'>" . h($row['nama_kantor'] ?: '-') . "</span>",
            "status" => $status,
            "tmt" => $tmt,
            "pendidikan" => $pend
        ];
    }
}

echo json_encode(["draw" => $draw, "recordsTotal" => $totalData, "recordsFiltered" => $totalData, "data" => $data]);
?>
