<?php
/*********************************************************
 * FILE     : pages/report/export-nominatif-excel.php
 * MODULE   : Export Excel Laporan Nominatif
 * STATUS   : Sesuai Logic Ajax (Smart Filter KC+KK)
 *********************************************************/

// Set Header untuk Download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Nominatif_Pegawai_".date('dmY_His').".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Sertakan Koneksi
@include_once __DIR__ . '/../../dist/koneksi.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; $conn = isset($koneksi)?$koneksi:null; }

// --- 1. AMBIL PARAMETER FILTER ---
// Kita ambil parameter GET yang dikirim dari tombol Download Excel
$unit_kerja   = isset($_GET['unit_kerja']) ? mysqli_real_escape_string($conn, $_GET['unit_kerja']) : '';
$jabatan      = isset($_GET['jabatan']) ? mysqli_real_escape_string($conn, $_GET['jabatan']) : '';
$status_kepeg = isset($_GET['status_kepeg']) ? mysqli_real_escape_string($conn, $_GET['status_kepeg']) : '';
$search       = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// --- 2. CEK HAK AKSES (KEPALA - LOCK FILTER) ---
session_start();
$hak_akses = isset($_SESSION['hak_akses']) ? $_SESSION['hak_akses'] : '';
$kode_kantor_user = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';

if ($hak_akses == 'kepala') {
    $unit_kerja = $kode_kantor_user; 
}

// --- 3. BANGUN QUERY (SAMA PERSIS DENGAN AJAX) ---
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

// A. SMART FILTER KANTOR
if ($unit_kerja != '') {
    $qK = mysqli_query($conn, "SELECT level, kode_cabang FROM tb_kantor WHERE kode_kantor_detail = '$unit_kerja'");
    $dK = mysqli_fetch_assoc($qK);

    if ($dK && $dK['level'] == 'KC') {
        // Jika KC, ambil Induk + semua KK
        $kode_cabang = $dK['kode_cabang'];
        $where .= " AND k.kode_cabang = '$kode_cabang'";
    } else {
        // Exact match
        $where .= " AND j.unit_kerja = '$unit_kerja'";
    }
}

// B. FILTER LAIN
if ($jabatan != '') { 
    $where .= " AND j.jabatan = '$jabatan'"; 
}
if ($status_kepeg != '') { 
    $where .= " AND p.status_kepeg = '$status_kepeg'"; 
}
if ($search != '') {
    $where .= " AND (p.nama LIKE '%$search%' OR p.id_peg LIKE '%$search%' OR j.jabatan LIKE '%$search%' OR k.nama_kantor LIKE '%$search%')";
}

// --- 4. QUERY DATA ---
$query = "SELECT
            p.id_peg, p.nama, p.status_kepeg,
            j.jabatan, j.tmt_jabatan,
            k.nama_kantor AS nama_unit_kerja,
            s.nama_sekolah, s.jenjang, s.tgl_ijazah
          $sqlJoin
          $where
          ORDER BY p.nama ASC";

$result = mysqli_query($conn, $query);

// --- 5. OUTPUT TABLE EXCEL ---
?>
<table border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th style="padding: 10px;">No</th>
            <th style="padding: 10px;">Nama Pegawai</th>
            <th style="padding: 10px;">ID Pegawai</th>
            <th style="padding: 10px;">Jabatan</th>
            <th style="padding: 10px;">TMT Jabatan</th>
            <th style="padding: 10px;">Unit Kerja</th>
            <th style="padding: 10px;">Status Pegawai</th>
            <th style="padding: 10px;">Jenjang Pendidikan</th>
            <th style="padding: 10px;">Nama Sekolah/Univ</th>
            <th style="padding: 10px;">Tahun Lulus</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            // Format Tanggal
            $tmt = ($row['tmt_jabatan'] && $row['tmt_jabatan']!='0000-00-00') ? date('d-m-Y', strtotime($row['tmt_jabatan'])) : '-';
            $thn_lulus = ($row['tgl_ijazah'] && $row['tgl_ijazah']!='0000-00-00') ? date('Y', strtotime($row['tgl_ijazah'])) : '-';
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['nama']) ?></td>
            <td align="center" style="mso-number-format:'@'"><?= htmlspecialchars($row['id_peg']) ?></td>
            <td><?= htmlspecialchars($row['jabatan'] ?: '-') ?></td>
            <td align="center" style="mso-number-format:'@'"><?= $tmt ?></td>
            <td><?= htmlspecialchars($row['nama_unit_kerja'] ?: '-') ?></td>
            <td align="center"><?= htmlspecialchars($row['status_kepeg']) ?></td>
            <td align="center"><?= htmlspecialchars($row['jenjang'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['nama_sekolah'] ?: '-') ?></td>
            <td align="center"><?= $thn_lulus ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>
