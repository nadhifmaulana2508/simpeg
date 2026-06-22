<?php
function app_detect_config() {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $is_https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8081';

    $origin = $scheme . '://' . $host;
    $app_path = '/dummy/';

    if (strpos($host, 'simpeg.bkkjateng.co.id') !== false) {
        $origin = 'https://simpeg.bkkjateng.co.id';
        $app_path = '/';
    } elseif (strpos($host, 'localhost:8081') !== false) {
        $origin = 'http://localhost:8081';
        $app_path = '/dummy/';
    }

    return array(
        'origin' => rtrim($origin, '/'),
        'app_path' => '/' . trim($app_path, '/') . '/',
    );
}

function base_url($path = '') {
    $cfg = app_detect_config();
    $base = $cfg['origin'] . $cfg['app_path'];
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function asset_url($path = '') {
    return base_url($path);
}

function page_url($page = '', $params = array()) {
    if ($page === '' || $page === 'dashboard') {
        $url = base_url('dashboard');
    } else {
        $url = base_url(rawurlencode($page));
    }

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function api_url($path = '') {
    return base_url('api/' . ltrim($path, '/'));
}

function legacy_route_to_clean_url($url) {
    $trimmed = trim((string) $url);
    if ($trimmed === '') {
        return $trimmed;
    }

    $decoded = html_entity_decode($trimmed, ENT_QUOTES, 'UTF-8');

    if ($decoded === 'home-admin.php' || preg_match('/(^|\/)home-admin\.php$/', $decoded)) {
        return page_url('dashboard');
    }

    if (!preg_match('/(?:\.\.\/)*home-admin\.php\?page=([a-zA-Z0-9_-]+)(.*)$/', $decoded, $matches)) {
        return $trimmed;
    }

    $page = isset($matches[1]) ? $matches[1] : 'dashboard';
    $tail = isset($matches[2]) ? ltrim($matches[2], '&') : '';
    $params = array();

    if ($tail !== '') {
        parse_str($tail, $params);
    }

    return page_url($page, $params);
}

function simpeg_normalize_markup($buffer) {
    $pattern = '/((?:href|action)\s*=\s*[\'"])([^\'"]+)([\'"])/i';
    $buffer = preg_replace_callback($pattern, function ($matches) {
        return $matches[1] . legacy_route_to_clean_url($matches[2]) . $matches[3];
    }, $buffer);

    $pattern_js = '/([\'"])((?:\.\.\/)*home-admin\.php\?page=[^\'"]+)([\'"])/i';
    $buffer = preg_replace_callback($pattern_js, function ($matches) {
        return $matches[1] . legacy_route_to_clean_url($matches[2]) . $matches[3];
    }, $buffer);

    return $buffer;
}
?>
