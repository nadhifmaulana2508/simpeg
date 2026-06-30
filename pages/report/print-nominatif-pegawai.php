<?php
// File: print-nominatif-pegawai.php
ini_set('memory_limit', '768M');
ini_set('max_execution_time', '300');
set_time_limit(300);

require_once '../../plugins/mpdf/mpdf.php';
include '../../dist/koneksi.php';

function esc_pdf($conn, $value) {
  return mysqli_real_escape_string($conn, trim($value));
}

function h_pdf($value) {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$status_kepeg = isset($_GET['status_kepeg']) ? esc_pdf($conn, $_GET['status_kepeg']) : '';
$unit_kerja   = isset($_GET['unit_kerja']) ? esc_pdf($conn, $_GET['unit_kerja']) : '';
$jabatan      = isset($_GET['jabatan']) ? esc_pdf($conn, $_GET['jabatan']) : '';
$search       = isset($_GET['search']) ? esc_pdf($conn, $_GET['search']) : '';

$where = "WHERE p.status_aktif = 1";
if ($status_kepeg != '') {
  $where .= " AND p.status_kepeg = '$status_kepeg'";
}
if ($unit_kerja != '') {
  $qK = mysqli_query($conn, "SELECT level, kode_cabang FROM tb_kantor WHERE kode_kantor_detail = '$unit_kerja'");
  $dK = mysqli_fetch_assoc($qK);
  if ($dK && $dK['level'] == 'KC') {
    $kodeCabang = mysqli_real_escape_string($conn, $dK['kode_cabang']);
    $where .= " AND kt.kode_cabang = '$kodeCabang'";
  } else {
    $where .= " AND j.unit_kerja = '$unit_kerja'";
  }
}
if ($jabatan != '') {
  $where .= " AND j.jabatan = '$jabatan'";
}
if ($search != '') {
  $where .= " AND (p.nama LIKE '%$search%' OR p.id_peg LIKE '%$search%' OR j.jabatan LIKE '%$search%' OR kt.nama_kantor LIKE '%$search%')";
}

$filterParts = array();
if ($status_kepeg != '') $filterParts[] = "Status: " . h_pdf($status_kepeg);
if ($unit_kerja != '')   $filterParts[] = "Kantor: " . h_pdf($unit_kerja);
if ($jabatan != '')      $filterParts[] = "Jabatan: " . h_pdf($jabatan);
if ($search != '')       $filterParts[] = "Pencarian: " . h_pdf($search);

$query = "SELECT
            p.id_peg,
            p.nama,
            p.status_kepeg,
            j.jabatan,
            j.tmt_jabatan,
            kt.nama_kantor AS unit_kerja,
            s.nama_sekolah,
            s.tgl_ijazah,
            s.jenjang
          FROM tb_pegawai p
          LEFT JOIN (
            SELECT j1.*
            FROM tb_jabatan j1
            INNER JOIN (
              SELECT id_peg, MAX(tmt_jabatan) AS tmt_max
              FROM tb_jabatan
              GROUP BY id_peg
            ) j2 ON j1.id_peg = j2.id_peg AND j1.tmt_jabatan = j2.tmt_max
          ) j ON p.id_peg = j.id_peg
          LEFT JOIN tb_kantor kt ON j.unit_kerja = kt.kode_kantor_detail
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
          )
          $where
          ORDER BY p.nama ASC";

$result = mysqli_query($conn, $query) or die(mysqli_error($conn));

$rowsHtml = '';
$no = 1;
while ($peg = mysqli_fetch_assoc($result)) {
  $pendidikan = '-';
  if (!empty($peg['jenjang']) || !empty($peg['nama_sekolah'])) {
    $pendidikan = h_pdf($peg['jenjang'] ?: '-') . ' / ' . h_pdf($peg['nama_sekolah'] ?: '-');
  }

  $rowsHtml .= '<tr>
    <td align="center">' . $no++ . '</td>
    <td>' . h_pdf($peg['nama']) . '</td>
    <td>' . h_pdf($peg['id_peg']) . '</td>
    <td>' . h_pdf($peg['jabatan'] ?: '-') . '</td>
    <td>' . h_pdf($peg['unit_kerja'] ?: '-') . '</td>
    <td align="center">' . h_pdf($peg['status_kepeg'] ?: '-') . '</td>
    <td align="center">' . (($peg['tmt_jabatan'] && $peg['tmt_jabatan'] != '0000-00-00') ? date('d-m-Y', strtotime($peg['tmt_jabatan'])) : '-') . '</td>
    <td>' . $pendidikan . '</td>
  </tr>';
}

$filterText = empty($filterParts) ? 'Semua data pegawai aktif' : implode(' | ', $filterParts);

$html = '
<html>
<head>
  <style>
    body { font-family: sans-serif; font-size: 9pt; color: #111; }
    .title { text-align: center; font-size: 15pt; font-weight: bold; margin-bottom: 4px; }
    .subtitle { text-align: center; font-size: 10pt; margin-bottom: 8px; }
    .meta { font-size: 8.5pt; margin-bottom: 10px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 5px 6px; vertical-align: top; }
    th { background: #e9efec; text-align: center; font-weight: bold; }
    .footer { margin-top: 22px; text-align: right; font-size: 9pt; }
  </style>
</head>
<body>
  <div class="title">DAFTAR NOMINATIF PEGAWAI</div>
  <div class="subtitle">PT BPR BKK JATENG TAHUN ' . date('Y') . '</div>
  <div class="meta"><strong>Filter:</strong> ' . $filterText . '</div>

  <table autosize="1">
    <thead>
      <tr>
        <th width="4%">No</th>
        <th width="22%">Nama Pegawai</th>
        <th width="10%">ID Pegawai</th>
        <th width="17%">Jabatan</th>
        <th width="16%">Unit Kerja</th>
        <th width="10%">Status</th>
        <th width="9%">TMT Jabatan</th>
        <th width="12%">Pendidikan</th>
      </tr>
    </thead>
    <tbody>
      ' . $rowsHtml . '
    </tbody>
  </table>

  <div class="footer">
    Dibuat di Semarang, ' . date('d-m-Y') . '<br><br>
    <strong>KEPALA DIVISI SDM DAN UMUM</strong><br>
    PT BPR BKK JATENG<br><br><br><br>
    <strong><u>...............................</u></strong><br>
    Kepala Divisi
  </div>
</body>
</html>';

$mpdf = new mPDF('utf-8', 'A4-L');
$mpdf->simpleTables = true;
$mpdf->shrink_tables_to_fit = 1;
$mpdf->useSubstitutions = false;
$mpdf->WriteHTML($html);
$mpdf->Output('Daftar_Nominatif_Pegawai_' . date('dmY_His') . '.pdf', 'I');
