<?php
function api_db_value($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function api_fetch_all_assoc($result) {
    $rows = array();
    if (!$result) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function api_get_request_path() {
    if (isset($_GET['path'])) {
        return trim($_GET['path'], '/');
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
    $path = str_replace($script_name, '', $uri);
    $path = preg_replace('/\?.*$/', '', $path);
    return trim($path, '/');
}

function api_health_controller() {
    api_success(array(
        'name' => 'SIMPEG Internal API',
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => 'dev-app-fe-bootstrap'
    ), array());
}

function api_root_controller() {
    api_success(array(
        'name' => 'SIMPEG Internal API',
        'environment' => function_exists('base_url') ? base_url() : '',
        'routes' => array(
            '/api/health',
            '/api/pegawai',
            '/api/pegawai/{id}',
            '/api/pegawai/{id}/keluarga'
        )
    ), array());
}

function api_pegawai_index_controller($conn) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
    $unit = isset($_GET['unit_kerja']) ? trim($_GET['unit_kerja']) : '';

    $where = array("p.status_aktif = 'Y'");

    if ($keyword !== '') {
        $safe_keyword = mysqli_real_escape_string($conn, $keyword);
        $where[] = "(p.id_peg LIKE '%".$safe_keyword."%' OR p.nip LIKE '%".$safe_keyword."%' OR p.nama LIKE '%".$safe_keyword."%')";
    }

    if ($unit !== '') {
        $safe_unit = mysqli_real_escape_string($conn, $unit);
        $where[] = "j.unit_kerja = '".$safe_unit."'";
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "
        SELECT COUNT(DISTINCT p.id_peg) AS total
        FROM tb_pegawai p
        LEFT JOIN tb_jabatan j ON j.id_peg = p.id_peg AND LOWER(j.status_jab) = 'aktif'
        WHERE ".$where_sql."
    ";
    $count_result = mysqli_query($conn, $count_sql);
    $count_row = $count_result ? mysqli_fetch_assoc($count_result) : array('total' => 0);
    $total = isset($count_row['total']) ? (int) $count_row['total'] : 0;

    $sql = "
        SELECT
            p.id_peg,
            p.nip,
            p.nama,
            p.jk,
            p.email,
            p.telp,
            p.status_kepeg,
            p.status_aktif,
            p.foto,
            j.jabatan,
            j.unit_kerja,
            j.tmt_jabatan
        FROM tb_pegawai p
        LEFT JOIN tb_jabatan j ON j.id_peg = p.id_peg AND LOWER(j.status_jab) = 'aktif'
        WHERE ".$where_sql."
        GROUP BY p.id_peg
        ORDER BY p.nama ASC
        LIMIT ".$limit." OFFSET ".$offset."
    ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        api_error('Gagal mengambil data pegawai.', 500, array(mysqli_error($conn)));
    }

    api_success(api_fetch_all_assoc($result), array(
        'page' => $page,
        'limit' => $limit,
        'total' => $total
    ));
}

function api_pegawai_detail_controller($conn, $id_peg) {
    $safe_id = mysqli_real_escape_string($conn, $id_peg);
    $sql = "
        SELECT
            p.*,
            j.jabatan,
            j.unit_kerja,
            j.tmt_jabatan,
            u.id_user,
            u.hak_akses
        FROM tb_pegawai p
        LEFT JOIN tb_jabatan j ON j.id_peg = p.id_peg AND LOWER(j.status_jab) = 'aktif'
        LEFT JOIN tb_user u ON u.id_pegawai = p.id_peg
        WHERE p.id_peg = '".$safe_id."'
        LIMIT 1
    ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        api_error('Gagal mengambil detail pegawai.', 500, array(mysqli_error($conn)));
    }

    if (mysqli_num_rows($result) < 1) {
        api_error('Data pegawai tidak ditemukan.', 404, array());
    }

    api_success(mysqli_fetch_assoc($result), array());
}

function api_pegawai_keluarga_controller($conn, $id_peg) {
    $safe_id = mysqli_real_escape_string($conn, $id_peg);

    $pegawai_result = mysqli_query($conn, "SELECT id_peg, nama, nip FROM tb_pegawai WHERE id_peg = '".$safe_id."' LIMIT 1");
    if (!$pegawai_result || mysqli_num_rows($pegawai_result) < 1) {
        api_error('Data pegawai tidak ditemukan.', 404, array());
    }

    $pegawai = mysqli_fetch_assoc($pegawai_result);

    $pasangan = api_fetch_all_assoc(mysqli_query($conn, "SELECT * FROM tb_suamiistri WHERE id_peg = '".$safe_id."' ORDER BY nama ASC"));
    $anak = api_fetch_all_assoc(mysqli_query($conn, "SELECT * FROM tb_anak WHERE id_peg = '".$safe_id."' ORDER BY anak_ke ASC, nama ASC"));
    $ortu = api_fetch_all_assoc(mysqli_query($conn, "SELECT * FROM tb_ortu WHERE id_peg = '".$safe_id."' ORDER BY status_hub ASC, nama ASC"));

    api_success(array(
        'pegawai' => $pegawai,
        'pasangan' => $pasangan,
        'anak' => $anak,
        'ortu' => $ortu
    ), array());
}
