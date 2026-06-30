<?php
if (session_id() === '') session_start();

if (empty($_SESSION['id_user'])) {
    exit('Akses ditolak.');
}

include '../../dist/koneksi.php';

$hak_akses   = isset($_SESSION['hak_akses']) ? strtolower(trim($_SESSION['hak_akses'])) : 'user';
$kode_kantor = isset($_SESSION['kode_kantor']) ? trim($_SESSION['kode_kantor']) : '';
$is_kepala   = ($hak_akses === 'kepala');

function esc_export_sertif($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value));
}

$tahun       = isset($_GET['tahun']) ? trim($_GET['tahun']) : date('Y');
$sertifikasi = isset($_GET['sertifikasi']) ? trim($_GET['sertifikasi']) : '';
$kantor      = isset($_GET['kantor']) ? trim($_GET['kantor']) : '';
$type        = isset($_GET['type']) ? trim($_GET['type']) : 'excel';

if ($is_kepala && $kode_kantor !== '') {
    $kantor = $kode_kantor;
}

$baseQuery = " FROM tb_sertifikasi s
               JOIN tb_pegawai p ON s.id_peg = p.id_peg
               LEFT JOIN tb_jabatan j ON j.id_jab = (
                    SELECT j2.id_jab
                    FROM tb_jabatan j2
                    WHERE j2.id_peg = s.id_peg
                      AND j2.tmt_jabatan <= COALESCE(NULLIF(s.tgl_sertifikat, '0000-00-00'), CURDATE())
                      AND (
                            j2.sampai_tgl = '0000-00-00'
                            OR j2.sampai_tgl IS NULL
                            OR j2.sampai_tgl >= COALESCE(NULLIF(s.tgl_sertifikat, '0000-00-00'), CURDATE())
                          )
                    ORDER BY j2.tmt_jabatan DESC, j2.id_jab DESC
                    LIMIT 1
               )
               LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail ";
$where = " WHERE 1=1 ";

if ($tahun !== '' && is_numeric($tahun)) {
    $safeTahun = esc_export_sertif($conn, $tahun);
    $where .= " AND (YEAR(s.tgl_sertifikat) = '$safeTahun' OR s.tgl_sertifikat IS NULL OR s.tgl_sertifikat = '0000-00-00') ";
}
if ($sertifikasi !== '') {
    $where .= " AND s.sertifikasi = '" . esc_export_sertif($conn, $sertifikasi) . "' ";
}
if ($kantor !== '') {
    $where .= " AND j.unit_kerja = '" . esc_export_sertif($conn, $kantor) . "' ";
}

$sql = "SELECT s.*, p.nama AS nama_peg, p.id_peg, j.jabatan, k.kode_cabang
        $baseQuery $where
        ORDER BY s.tgl_sertifikat DESC";
$query = mysqli_query($conn, $sql);

if ($type === 'excel') {
    $filename = 'Data_Sertifikasi_' . date('Ymd_His') . '.xls';
    header('Content-type: application/vnd-ms-excel');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export Data Sertifikasi</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h2 { margin: 0 0 6px; font-size: 20px; }
        .header p { margin: 0; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #0f766e; color: #fff; border: 1px solid #0b4f4a; padding: 8px; text-align: center; }
        td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>DATA SERTIFIKASI PEGAWAI</h2>
        <p>
            Tahun: <?= htmlspecialchars($tahun !== '' ? $tahun : 'Semua', ENT_QUOTES, 'UTF-8') ?> |
            Jenis Sertifikasi: <?= htmlspecialchars($sertifikasi !== '' ? $sertifikasi : 'Semua', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Nama Pegawai</th>
                <th>ID Pegawai</th>
                <th>Sertifikasi</th>
                <th>Penyelenggara</th>
                <th>Tgl Sertifikat</th>
                <th>Tgl Expired</th>
                <th>Kode Cabang</th>
                <th>Jabatan</th>
                <th>No Sertifikat</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if ($query && mysqli_num_rows($query) > 0):
                while ($row = mysqli_fetch_assoc($query)):
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['nama_peg'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['id_peg'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['sertifikasi'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['penyelenggara'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(($row['tgl_sertifikat'] && $row['tgl_sertifikat'] !== '0000-00-00') ? $row['tgl_sertifikat'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(($row['tgl_expired'] && $row['tgl_expired'] !== '0000-00-00') ? $row['tgl_expired'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['kode_cabang'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['jabatan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['sertifikat'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php
                endwhile;
            else:
            ?>
            <tr>
                <td colspan="10" class="text-center">Tidak ada data ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
