<?php
if (!defined('SIMPEG_SSO_BASE_URL')) {
    define('SIMPEG_SSO_BASE_URL', 'https://apisso.bkkjateng.co.id');
}
if (!defined('SIMPEG_SSO_COOKIE')) {
    define('SIMPEG_SSO_COOKIE', 'bkk_sso_token');
}
if (!defined('SIMPEG_SSO_LEGACY_COOKIE')) {
    define('SIMPEG_SSO_LEGACY_COOKIE', 'simpeg_sso_token');
}
if (!defined('SIMPEG_SSO_TIMEOUT')) {
    define('SIMPEG_SSO_TIMEOUT', 6);
}
if (!defined('SIMPEG_SSO_CONNECT_TIMEOUT')) {
    define('SIMPEG_SSO_CONNECT_TIMEOUT', 3);
}

function simpeg_sso_cookie_domain() {
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    $host = preg_replace('/:\d+$/', '', $host);

    if ($host === 'bkkjateng.co.id' || substr($host, -16) === '.bkkjateng.co.id') {
        return '.bkkjateng.co.id';
    }

    return '';
}

function simpeg_sso_cookie_names() {
    if (SIMPEG_SSO_COOKIE === SIMPEG_SSO_LEGACY_COOKIE) {
        return array(SIMPEG_SSO_COOKIE);
    }

    return array(SIMPEG_SSO_COOKIE, SIMPEG_SSO_LEGACY_COOKIE);
}

function simpeg_sso_cookie_token() {
    foreach (simpeg_sso_cookie_names() as $name) {
        if (!empty($_COOKIE[$name])) {
            return $_COOKIE[$name];
        }
    }

    return '';
}

function simpeg_role_rank($role) {
    $role = strtolower(trim((string) $role));
    if ($role === 'superadmin') return 4;
    if ($role === 'admin') return 3;
    if ($role === 'kepala') return 2;
    return 1;
}

function simpeg_auto_role_from_text($text) {
    $text = strtolower(trim((string) $text));
    if ($text === '') {
        return 'User';
    }

    if (strpos($text, 'direktur') !== false || strpos($text, 'direksi') !== false) {
        return 'Superadmin';
    }

    $is_kepala_unit = strpos($text, 'kepala cabang') !== false
        || strpos($text, 'kepala kantor') !== false;
    $is_kabid_operasional = strpos($text, 'operasional') !== false
        && (strpos($text, 'kabid') !== false || strpos($text, 'kepala bidang') !== false);

    if ($is_kepala_unit || $is_kabid_operasional) {
        return 'Kepala';
    }

    return 'User';
}

function simpeg_auto_role_for_employee($conn, $id_peg, $whoami = array()) {
    $texts = array();
    foreach (array('job_position', 'level', 'group_jabatan') as $key) {
        if (isset($whoami[$key])) {
            $texts[] = $whoami[$key];
        }
    }

    $safe_id = mysqli_real_escape_string($conn, $id_peg);
    $q = mysqli_query($conn, "
        SELECT jabatan
        FROM tb_jabatan
        WHERE id_peg = '$safe_id'
          AND LOWER(status_jab) = 'aktif'
        ORDER BY tmt_jabatan DESC
        LIMIT 1
    ");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        $texts[] = $row['jabatan'];
    }

    $best = 'User';
    foreach ($texts as $text) {
        $role = simpeg_auto_role_from_text($text);
        if (simpeg_role_rank($role) > simpeg_role_rank($best)) {
            $best = $role;
        }
    }

    return $best;
}

function simpeg_sso_json_request($method, $path, $payload = null, $token = '') {
    $url = rtrim(SIMPEG_SSO_BASE_URL, '/') . $path;
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $body = $payload !== null ? json_encode($payload) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, SIMPEG_SSO_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, SIMPEG_SSO_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return array('ok' => false, 'status' => 0, 'message' => $err, 'json' => null);
        }
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body !== null ? $body : '',
                'timeout' => SIMPEG_SSO_TIMEOUT,
                'ignore_errors' => true
            )
        ));
        $response = @file_get_contents($url, false, $context);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($response === false) {
            return array('ok' => false, 'status' => $code, 'message' => 'Tidak bisa menghubungi SSO.', 'json' => null);
        }
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return array('ok' => false, 'status' => $code, 'message' => 'Response SSO tidak valid.', 'json' => null);
    }

    $status = isset($json['status']) ? (int) $json['status'] : $code;
    return array(
        'ok' => ($code >= 200 && $code < 300 && $status >= 200 && $status < 300),
        'status' => $status,
        'message' => isset($json['message']) ? $json['message'] : '',
        'json' => $json
    );
}

function simpeg_sso_login($id_peg, $password) {
    return simpeg_sso_json_request('POST', '/api/auth/login', array(
        'id_peg' => $id_peg,
        'password' => $password,
        'app' => 'simpeg'
    ));
}

function simpeg_sso_whoami($token) {
    return simpeg_sso_json_request('GET', '/api/auth/whoami', null, $token);
}

function simpeg_jwt_exp($token) {
    $parts = explode('.', $token);
    if (count($parts) < 2) return time() + 3600;
    $payload = strtr($parts[1], '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $json = json_decode(base64_decode($payload), true);
    if (is_array($json) && isset($json['exp']) && (int) $json['exp'] > time()) {
        return (int) $json['exp'];
    }
    return time() + 3600;
}

function simpeg_set_sso_cookie($token) {
    $expires = simpeg_jwt_exp($token);
    foreach (simpeg_sso_cookie_names() as $name) {
        setcookie($name, $token, $expires, '/', simpeg_sso_cookie_domain(), !empty($_SERVER['HTTPS']), true);
    }
}

function simpeg_clear_sso_cookie() {
    $secure = !empty($_SERVER['HTTPS']);
    foreach (simpeg_sso_cookie_names() as $name) {
        setcookie($name, '', time() - 3600, '/', simpeg_sso_cookie_domain(), $secure, true);
        setcookie($name, '', time() - 3600, '/', '', $secure, true);
    }
}

function simpeg_fetch_user_role($conn, $id_peg, $fallback_name, $whoami = array()) {
    $safe_id = mysqli_real_escape_string($conn, $id_peg);
    $auto_role = simpeg_auto_role_for_employee($conn, $id_peg, $whoami);
    $q = mysqli_query($conn, "
        SELECT id_user, nama_user, hak_akses, status_aktif, id_pegawai
        FROM tb_user
        WHERE id_pegawai = '$safe_id' OR id_user = '$safe_id'
        ORDER BY CASE WHEN id_pegawai = '$safe_id' THEN 0 ELSE 1 END
        LIMIT 1
    ");

    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $stored_role = $row['hak_akses'] !== '' ? $row['hak_akses'] : 'User';
        $final_role = simpeg_role_rank($auto_role) > simpeg_role_rank($stored_role) ? $auto_role : $stored_role;

        if ($final_role !== $stored_role) {
            $safe_role = mysqli_real_escape_string($conn, $final_role);
            $safe_user = mysqli_real_escape_string($conn, $row['id_user']);
            mysqli_query($conn, "
                UPDATE tb_user
                SET hak_akses = '$safe_role', updated_at = NOW(), updated_by = 'system-sso-role'
                WHERE id_user = '$safe_user'
                LIMIT 1
            ");
        }

        return array(
            'id_user' => $row['id_user'],
            'nama_user' => $row['nama_user'] !== '' ? $row['nama_user'] : $fallback_name,
            'hak_akses' => strtolower($final_role),
            'status_aktif' => $row['status_aktif'],
            'id_pegawai' => $row['id_pegawai'] !== '' ? $row['id_pegawai'] : $id_peg
        );
    }

    return array(
        'id_user' => $id_peg,
        'nama_user' => $fallback_name,
        'hak_akses' => strtolower($auto_role),
        'status_aktif' => 'Y',
        'id_pegawai' => $id_peg
    );
}

function simpeg_fill_session_from_sso($conn, $token, $whoami) {
    if (!isset($whoami['employee_id'])) return false;

    $id_peg = $whoami['employee_id'];
    $full_name = isset($whoami['full_name']) ? $whoami['full_name'] : $id_peg;
    $role = simpeg_fetch_user_role($conn, $id_peg, $full_name, $whoami);

    if ($role['status_aktif'] === 'N') {
        return false;
    }

    if (session_id() !== '') {
        session_regenerate_id(true);
    }

    $_SESSION['id_user'] = $role['id_user'];
    $_SESSION['nama_user'] = $role['nama_user'];
    $_SESSION['hak_akses'] = strtolower($role['hak_akses']);
    $_SESSION['id_pegawai'] = $role['id_pegawai'];
    $_SESSION['sso_token'] = $token;
    $_SESSION['sso_user'] = $whoami;
    $_SESSION['start_session'] = time();

    $safe_id = mysqli_real_escape_string($conn, $id_peg);
    $qJab = mysqli_query($conn, "SELECT unit_kerja FROM tb_jabatan WHERE id_peg = '$safe_id' AND status_jab = 'Aktif' LIMIT 1");
    if ($qJab && ($jab = mysqli_fetch_assoc($qJab))) {
        $_SESSION['kode_kantor'] = $jab['unit_kerja'];
    } else {
        $_SESSION['kode_kantor'] = isset($whoami['kode']) ? $whoami['kode'] : '-';
    }

    return true;
}

function simpeg_restore_session_from_sso_cookie($conn) {
    if (isset($_SESSION['id_user']) && $_SESSION['id_user'] !== '') {
        return true;
    }
    $token = simpeg_sso_cookie_token();
    if ($token === '') {
        return false;
    }

    $who = simpeg_sso_whoami($token);
    if (!$who['ok'] || empty($who['json']['data'])) {
        simpeg_clear_sso_cookie();
        return false;
    }

    if (!simpeg_fill_session_from_sso($conn, $token, $who['json']['data'])) {
        simpeg_clear_sso_cookie();
        return false;
    }

    return true;
}
?>
