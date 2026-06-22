<?php
function api_response($payload, $status_code) {
    http_response_code((int) $status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    echo json_encode($payload);
    exit;
}

function api_success($data, $meta) {
    $response = array(
        'success' => true,
        'data' => $data,
    );

    if (!empty($meta)) {
        $response['meta'] = $meta;
    }

    api_response($response, 200);
}

function api_error($message, $status_code, $errors) {
    $response = array(
        'success' => false,
        'message' => $message,
    );

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    api_response($response, $status_code);
}
