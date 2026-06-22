<?php
include dirname(__DIR__) . '/config.php';
include dirname(__DIR__) . '/dist/koneksi.php';
include __DIR__ . '/helpers/response.php';
include __DIR__ . '/controllers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    api_response(array('success' => true), 200);
}

$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$path = api_get_request_path();
$segments = $path === '' ? array() : explode('/', $path);

if (!empty($segments) && $segments[0] === 'api') {
    array_shift($segments);
}

if ($request_method !== 'GET') {
    api_error('Saat ini endpoint API baru mendukung method GET.', 405, array());
}

if (count($segments) === 0) {
    api_root_controller();
}

if ($segments[0] === 'health') {
    api_health_controller();
}

if ($segments[0] === 'pegawai' && count($segments) === 1) {
    api_pegawai_index_controller($conn);
}

if ($segments[0] === 'pegawai' && count($segments) === 2) {
    api_pegawai_detail_controller($conn, $segments[1]);
}

if ($segments[0] === 'pegawai' && count($segments) === 3 && $segments[2] === 'keluarga') {
    api_pegawai_keluarga_controller($conn, $segments[1]);
}

api_error('Endpoint API tidak ditemukan.', 404, array('path' => $path));
