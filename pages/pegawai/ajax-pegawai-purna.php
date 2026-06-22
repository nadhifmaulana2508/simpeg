<?php
ini_set('display_errors', 0);
error_reporting(0);

if (session_id() === '') {
    session_start();
}

include "../../dist/koneksi.php";

if (empty($_SESSION['id_user'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(array('error' => 'Akses ditolak')));
}

function purna_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$columns = array(
    0 => 'p.nama',
    1 => 'p.tgl_lhr',
    2 => 'j.jabatan',
    3 => 'm.jns_mutasi',
    4 => 'm.tgl_mutasi'
);

$limit = isset($_GET['length']) ? max(1, (int) $_GET['length']) : 10;
$offset = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
$draw = isset($_GET['draw']) ? (int) $_GET['draw'] : 1;
$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 0;
$orderDir = (isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';
$orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'p.nama';

$safeSearch = mysqli_real_escape_string($conn, $search);

$sqlBase = "
    FROM tb_pegawai p
    INNER JOIN (
        SELECT m1.id_peg, m1.jns_mutasi, m1.tgl_mutasi
        FROM tb_mutasi m1
        INNER JOIN (
            SELECT id_peg, MAX(tgl_mutasi) AS tgl_mutasi
            FROM tb_mutasi
            WHERE jns_mutasi IN ('Pensiun', 'Pensiun Dini', 'Meninggal Dunia', 'Pengunduran Diri', 'PTDH')
            GROUP BY id_peg
        ) last_mutasi ON last_mutasi.id_peg = m1.id_peg AND last_mutasi.tgl_mutasi = m1.tgl_mutasi
    ) m ON m.id_peg = p.id_peg
    LEFT JOIN tb_jabatan j ON j.id_jab = (
        SELECT j2.id_jab
        FROM tb_jabatan j2
        WHERE j2.id_peg = p.id_peg
        ORDER BY (CASE WHEN j2.status_jab = 'Aktif' THEN 0 ELSE 1 END), j2.tmt_jabatan DESC, j2.id_jab DESC
        LIMIT 1
    )
    WHERE 1=1
";

if (isset($_SESSION['hak_akses']) && strtolower($_SESSION['hak_akses']) === 'kepala' && !empty($_SESSION['kode_kantor'])) {
    $kode_kantor = mysqli_real_escape_string($conn, $_SESSION['kode_kantor']);
    $sqlBase .= " AND j.unit_kerja = '".$kode_kantor."'";
}

if ($search !== '') {
    $sqlBase .= " AND (
        p.id_peg LIKE '%".$safeSearch."%' OR
        p.nama LIKE '%".$safeSearch."%' OR
        p.tempat_lhr LIKE '%".$safeSearch."%' OR
        COALESCE(j.jabatan, '') LIKE '%".$safeSearch."%' OR
        m.jns_mutasi LIKE '%".$safeSearch."%'
    )";
}

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT DISTINCT p.id_peg
        ".$sqlBase."
    ) base_count
";
$queryTotal = mysqli_query($conn, $sqlTotal);
$rowTotal = $queryTotal ? mysqli_fetch_assoc($queryTotal) : array('total' => 0);
$totalFiltered = isset($rowTotal['total']) ? (int) $rowTotal['total'] : 0;

$sqlTotalAll = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT DISTINCT p.id_peg
        FROM tb_pegawai p
        INNER JOIN (
            SELECT id_peg, MAX(tgl_mutasi) AS tgl_mutasi
            FROM tb_mutasi
            WHERE jns_mutasi IN ('Pensiun', 'Pensiun Dini', 'Meninggal Dunia', 'Pengunduran Diri', 'PTDH')
            GROUP BY id_peg
        ) m ON m.id_peg = p.id_peg
    ) total_purna
";
$queryTotalAll = mysqli_query($conn, $sqlTotalAll);
$rowTotalAll = $queryTotalAll ? mysqli_fetch_assoc($queryTotalAll) : array('total' => 0);
$totalAll = isset($rowTotalAll['total']) ? (int) $rowTotalAll['total'] : 0;

$sqlData = "
    SELECT
        p.id_peg,
        p.nama,
        p.tempat_lhr,
        p.tgl_lhr,
        p.telp,
        p.foto,
        p.jk,
        COALESCE(j.jabatan, '-') AS jabatan,
        m.jns_mutasi,
        m.tgl_mutasi
    ".$sqlBase."
    GROUP BY p.id_peg
    ORDER BY ".$orderColumn." ".$orderDir."
    LIMIT ".$offset.", ".$limit."
";

$result = mysqli_query($conn, $sqlData);
$data = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ttl = purna_h($row['tempat_lhr']) . ', ' . ($row['tgl_lhr'] ? date('d-m-Y', strtotime($row['tgl_lhr'])) : '-');
        $tgl_pensiun = $row['tgl_mutasi'] ? date('d-m-Y', strtotime($row['tgl_mutasi'])) : '-';

        $data[] = array(
            'id_peg' => purna_h($row['id_peg']),
            'nama' => purna_h($row['nama']),
            'ttl' => $ttl,
            'jabatan' => purna_h($row['jabatan']),
            'status_kepeg' => purna_h($row['jns_mutasi']),
            'tgl_pensiun' => $tgl_pensiun,
            'telp' => purna_h($row['telp'])
        );
    }
}

header('Content-Type: application/json');
echo json_encode(array(
    'draw' => $draw,
    'recordsTotal' => $totalAll,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
));
