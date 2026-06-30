<?php
if (session_id() === '') session_start();

if (empty($_SESSION['id_user'])) {
    exit('Akses ditolak.');
}

include '../../dist/koneksi.php';

$hak_akses   = isset($_SESSION['hak_akses']) ? strtolower(trim($_SESSION['hak_akses'])) : 'user';
$kode_kantor = isset($_SESSION['kode_kantor']) ? trim($_SESSION['kode_kantor']) : '';
$is_kepala   = ($hak_akses === 'kepala');

function esc_export($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value));
}

$tahun  = isset($_GET['tahun']) ? trim($_GET['tahun']) : date('Y');
$diklat = isset($_GET['diklat']) ? trim($_GET['diklat']) : '';
$kantor = isset($_GET['kantor']) ? trim($_GET['kantor']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type   = isset($_GET['type']) ? trim($_GET['type']) : 'excel';

if ($is_kepala && $kode_kantor !== '') {
    $kantor = $kode_kantor;
}

$baseQuery = " FROM tb_diklat d
               JOIN tb_pegawai p ON d.id_peg = p.id_peg
               LEFT JOIN tb_jabatan j ON j.id_jab = (
                    SELECT j2.id_jab
                    FROM tb_jabatan j2
                    WHERE j2.id_peg = d.id_peg
                      AND j2.tmt_jabatan <= COALESCE(NULLIF(d.date_reg, '0000-00-00'), CURDATE())
                      AND (
                            j2.sampai_tgl = '0000-00-00'
                            OR j2.sampai_tgl IS NULL
                            OR j2.sampai_tgl >= COALESCE(NULLIF(d.date_reg, '0000-00-00'), CURDATE())
                          )
                    ORDER BY j2.tmt_jabatan DESC, j2.id_jab DESC
                    LIMIT 1
               )
               LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail ";
$where = " WHERE 1=1 ";

if ($tahun !== '') {
    $where .= " AND d.tahun = '" . esc_export($conn, $tahun) . "' ";
}
if ($diklat !== '') {
    $where .= " AND d.diklat = '" . esc_export($conn, $diklat) . "' ";
}
if ($kantor !== '') {
    $where .= " AND j.unit_kerja = '" . esc_export($conn, $kantor) . "' ";
}
if ($search !== '') {
    $s = esc_export($conn, $search);
    $where .= " AND (
        p.nama LIKE '%$s%' OR
        p.id_peg LIKE '%$s%' OR
        d.diklat LIKE '%$s%' OR
        d.penyelenggara LIKE '%$s%' OR
        k.nama_kantor LIKE '%$s%'
    ) ";
}

$sql = "SELECT d.*, p.nama AS nama_peg, p.id_peg, j.unit_kerja, j.jabatan, k.kode_cabang
        $baseQuery $where
        ORDER BY d.date_reg DESC";
$query = mysqli_query($conn, $sql);

if ($type === 'excel') {
    $filename = 'Data_Diklat_' . date('Ymd_His') . '.xls';
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
    <title>Export Data Diklat</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h2 { margin: 0 0 6px; font-size: 20px; }
        .header p { margin: 0; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #0f766e; color: #fff; border: 1px solid #0b4f4a; padding: 8px; text-align: center; }
        td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>DATA PELATIHAN DAN DIKLAT</h2>
        <p>
            Tahun: <?= htmlspecialchars($tahun !== '' ? $tahun : 'Semua', ENT_QUOTES, 'UTF-8') ?> |
            Jenis Diklat: <?= htmlspecialchars($diklat !== '' ? $diklat : 'Semua', ENT_QUOTES, 'UTF-8') ?> |
            Unit Kerja: <?= htmlspecialchars($kantor !== '' ? $kantor : 'Semua', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Nama Pegawai</th>
                <th>ID Pegawai</th>
                <th>Jenis Diklat</th>
                <th>Penyelenggara</th>
                <th>Tempat</th>
                <th>Kode Cabang</th>
                <th>Jabatan</th>
                <th>Tahun</th>
                <th>Biaya</th>
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
                <td><?= htmlspecialchars($row['diklat'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['penyelenggara'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['tempat'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['kode_cabang'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['jabatan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center"><?= htmlspecialchars($row['tahun'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-right"><?= isset($row['biaya']) ? (float) $row['biaya'] : 0 ?></td>
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
