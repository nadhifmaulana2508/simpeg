<?php
// =============================================================
// FILE: pages/ref-diklat/upload-data-diklat.php
// MODULE: Backend Import Diklat (Linux Safe & Smart Update)
// =============================================================

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

session_start();

// [CONFIG] : Matikan batasan memory & waktu untuk data banyak
ini_set('memory_limit', '-1'); 
set_time_limit(0); 

// [CONFIG] : Matikan error display agar JSON tidak rusak
error_reporting(0);
ini_set('display_errors', 0);

ob_start();
header('Content-Type: application/json; charset=utf-8');

function import_preview_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(16);
        if ($bytes !== false) {
            return bin2hex($bytes);
        }
    }

    return sha1(uniqid('diklat_preview_', true) . mt_rand());
}

function import_token_equals($known, $user) {
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user);
    }

    $known = (string) $known;
    $user = (string) $user;

    if (strlen($known) !== strlen($user)) {
        return false;
    }

    $result = 0;
    $length = strlen($known);
    for ($i = 0; $i < $length; $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }

    return $result === 0;
}

// SECURITY
if (empty($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis. Silakan login ulang.']);
    exit;
}

// --- HELPER SQL VALUE (PENTING BUAT LINUX) ---
function getSqlVal($conn, $val, $type = 'string') {
    if ($val === '' || $val === null || $val === false || $val === 'NULL') {
        return "NULL";
    }
    
    $safe = mysqli_real_escape_string($conn, $val);
    
    if ($type === 'int') {
        // Bersihkan Rp, titik, koma -> Ambil angka saja
        $num = preg_replace('/[^0-9]/', '', $val);
        return ($num === '') ? "NULL" : "'$num'";
    }
    
    return "'$safe'";
}

// --- FUNGSI TANGGAL SAKTI ---
function parseTanggalSakti($val) {
    $val = trim($val);
    if (empty($val) || $val == '-' || $val == '0000-00-00') return NULL;

    // 1. Cek Excel Serial Number
    if (is_numeric($val) && $val > 1000) {
        try {
            return Date::excelToDateTimeObject($val)->format('Y-m-d');
        } catch (Exception $e) { return date('Y-m-d'); }
    }

    // 2. Cek Format Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

    // 3. Cek Format Indonesia
    $val = str_replace(['/', '.'], '-', $val);
    $ts = strtotime($val);
    
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);

    return date('Y-m-d'); 
}

try {
    // 3. KONEKSI DATABASE
    $paths = [
        __DIR__ . '/../../dist/koneksi.php',
        __DIR__ . '/../../config/koneksi.php'
    ];
    $conn = null;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            include $path;
            if (isset($koneksi) && $koneksi) { $conn = $koneksi; break; }
            if (isset($conn) && $conn) { break; }
        }
    }
    
    if (!$conn) throw new Exception("Koneksi Database Gagal.");

    // [BARIS SAKTI] : SOLUSI LINUX
    mysqli_query($conn, "SET SESSION sql_mode = ''");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request Method");
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // =========================================================
    // BAGIAN A: PREVIEW DATA (CEK STATUS DATABASE)
    // =========================================================
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih");

        $file = $_FILES['file_excel'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'])) throw new Exception("Format harus Excel (.xlsx)");

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true); 

        $header = array_shift($rows); 

        // HTML Table Header
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap align-middle" style="font-size: 0.9em;">';
        $html .= '<thead class="bg-primary text-white"><tr>';
        $html .= '<th width="10%">Status Import</th><th>ID Pegawai</th><th>Nama Diklat</th><th>Penyelenggara</th><th>Tempat</th><th>Biaya</th><th>Angkatan</th><th>Tahun</th><th>Tgl Mulai</th>';
        $html .= '</tr></thead><tbody>';

        $limit = 15; 
        $count = 0;
        $previewData = [];

        foreach ($rows as $row) {
            // Mapping Kolom Excel (A, B, C...)
            $id_peg        = trim($row['A']);
            if(empty($id_peg)) continue;

            $diklat        = trim($row['B']);
            $penyelenggara = trim($row['C']);
            $tempat        = trim($row['D']);
            // Bersihkan Biaya dari Rp dan titik
            $biaya         = preg_replace('/[^0-9]/', '', $row['E']); 
            $angkatan      = trim($row['F']);
            $tahun         = trim($row['G']);
            
            $tgl_mulai_db  = parseTanggalSakti($row['H']);

            // --- SMART LOGIC: CEK APAKAH DATA SUDAH ADA? ---
            // Kunci Cek: ID Pegawai + Nama Diklat + Tahun
            $id_peg_esc = mysqli_real_escape_string($conn, $id_peg);
            $diklat_esc = mysqli_real_escape_string($conn, $diklat);
            $tahun_esc  = mysqli_real_escape_string($conn, $tahun);

            $sqlCek = "SELECT id_diklat FROM tb_diklat 
                       WHERE id_peg = '$id_peg_esc' 
                       AND TRIM(LOWER(diklat)) = TRIM(LOWER('$diklat_esc'))
                       AND tahun = '$tahun_esc'";
            
            $resCek = mysqli_query($conn, $sqlCek);
            $is_exist = ($resCek && mysqli_num_rows($resCek) > 0);

            // Badge Status
            if ($is_exist) {
                $status_badge = '<span class="badge bg-warning text-dark"><i class="fas fa-edit"></i> Update Data</span>';
            } else {
                $status_badge = '<span class="badge bg-success"><i class="fas fa-plus"></i> Data Baru</span>';
            }

            // Simpan Array Bersih
            $previewData[] = [
                $id_peg, $diklat, $penyelenggara, $tempat, $biaya, $angkatan, $tahun, $tgl_mulai_db
            ];

            if ($count < $limit) {
                $html .= '<tr>';
                $html .= '<td class="text-center">' . $status_badge . '</td>';
                $html .= '<td>' . htmlspecialchars($id_peg) . '</td>';
                $html .= '<td>' . htmlspecialchars($diklat) . '</td>';
                $html .= '<td>' . htmlspecialchars($penyelenggara) . '</td>';
                $html .= '<td>' . htmlspecialchars($tempat) . '</td>';
                $html .= '<td>' . number_format((float)$biaya, 0, ',', '.') . '</td>';
                $html .= '<td>' . htmlspecialchars($angkatan) . '</td>';
                $html .= '<td>' . htmlspecialchars($tahun) . '</td>';
                $html .= '<td>' . htmlspecialchars($tgl_mulai_db) . '</td>';
                $html .= '</tr>';
                $count++;
            }
        }
        $html .= '</tbody></table></div>';
        
        if (count($previewData) > $limit) {
            $html .= '<div class="alert alert-info text-center p-1 mt-2"><small>... menampilkan 15 dari ' . count($previewData) . ' data.</small></div>';
        }

        $html .= '<hr><div class="d-flex justify-content-between align-items-center">';
        $html .= '<span class="text-muted small">Cek kembali status (Update/Baru) sebelum menyimpan.</span>';
        $html .= '<button type="button" class="btn btn-primary px-4 rounded-pill shadow-sm" id="btnSimpanDiklat"><i class="fas fa-save mr-2"></i> Proses Simpan</button>';
        $html .= '</div>';
        
        $json_rows = json_encode($previewData);
        $preview_token = import_preview_token();
        $_SESSION['import_diklat_preview_rows'] = $previewData;
        $_SESSION['import_diklat_preview_token'] = $preview_token;
        $_SESSION['import_diklat_preview_created_at'] = time();
        session_write_close();

        $html .= '<textarea id="json_data_diklat" style="display:none;">' . htmlspecialchars($json_rows) . '</textarea>';
        $html .= '<input type="hidden" id="import_diklat_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

        ob_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
        exit;
    }

    // =========================================================
    // BAGIAN B: SIMPAN DATA (INSERT OR UPDATE - LINUX SAFE)
    // =========================================================
    elseif ($action === 'save') {
        $data = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';

        if ($posted_token !== '' &&
            isset($_SESSION['import_diklat_preview_token']) &&
            import_token_equals($_SESSION['import_diklat_preview_token'], $posted_token) &&
            isset($_SESSION['import_diklat_preview_rows']) &&
            is_array($_SESSION['import_diklat_preview_rows'])) {
            $data = $_SESSION['import_diklat_preview_rows'];
        } elseif (isset($_POST['data_diklat'])) {
            $data = json_decode($_POST['data_diklat'], true);
        }

        if (!$data || !is_array($data)) throw new Exception("Data tidak diterima atau sesi preview sudah berakhir");

        unset($_SESSION['import_diklat_preview_rows'], $_SESSION['import_diklat_preview_token'], $_SESSION['import_diklat_preview_created_at']);
        session_write_close();

        $user_log = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'admin';
        
        $insert = 0;
        $update = 0;
        $gagal  = 0;

        foreach ($data as $row) {
            // [0] ID Pegawai, [1] Diklat, dll (Sesuai PreviewData)
            
            // Siapkan Variabel Safe SQL
            $id_peg_raw      = isset($row[0]) ? trim($row[0]) : '';
            $diklat_raw      = isset($row[1]) ? trim($row[1]) : '';
            $tahun_raw       = isset($row[6]) ? trim($row[6]) : '';

            if(empty($id_peg_raw) || empty($diklat_raw)) { $gagal++; continue; }

            // Gunakan getSqlVal untuk semua field yang mau di-Insert/Update
            $sql_id_peg        = getSqlVal($conn, $id_peg_raw);
            $sql_diklat        = getSqlVal($conn, $diklat_raw);
            $sql_penyelenggara = getSqlVal($conn, isset($row[2]) ? $row[2] : '');
            $sql_tempat        = getSqlVal($conn, isset($row[3]) ? $row[3] : '');
            $sql_biaya         = getSqlVal($conn, isset($row[4]) ? $row[4] : '', 'int'); // Mode Int
            $sql_angkatan      = getSqlVal($conn, isset($row[5]) ? $row[5] : '');
            $sql_tahun         = getSqlVal($conn, $tahun_raw);
            
            $tgl_raw           = isset($row[7]) ? $row[7] : NULL;
            $sql_date_reg      = ($tgl_raw) ? "'$tgl_raw'" : "NULL";

            // LOGIC CEK DUPLIKAT (Gunakan Raw Value + Escape manual untuk WHERE clause)
            $safe_id    = mysqli_real_escape_string($conn, $id_peg_raw);
            $safe_diklat= mysqli_real_escape_string($conn, $diklat_raw);
            $safe_tahun = mysqli_real_escape_string($conn, $tahun_raw);

            $sqlCek = "SELECT id_diklat FROM tb_diklat 
                       WHERE id_peg = '$safe_id' 
                       AND TRIM(LOWER(diklat)) = TRIM(LOWER('$safe_diklat'))
                       AND tahun = '$safe_tahun'";

            $resCek = mysqli_query($conn, $sqlCek);

            if ($resCek && mysqli_num_rows($resCek) > 0) {
                // --- UPDATE ---
                $rDup = mysqli_fetch_assoc($resCek);
                $id_diklat_exist = $rDup['id_diklat'];

                $sqlUpdate = "UPDATE tb_diklat SET 
                                penyelenggara = $sql_penyelenggara,
                                tempat        = $sql_tempat,
                                biaya         = $sql_biaya,
                                angkatan      = $sql_angkatan,
                                date_reg      = $sql_date_reg, 
                                updated_by    = '$user_log',
                                updated_at    = NOW()
                              WHERE id_diklat = '$id_diklat_exist'";
                
                if(mysqli_query($conn, $sqlUpdate)) { $update++; } else { $gagal++; }

            } else {
                // --- INSERT ---
                $sqlInsert = "INSERT INTO tb_diklat (
                    id_peg, diklat, penyelenggara, tempat, biaya, angkatan, tahun, date_reg, created_by
                ) VALUES (
                    $sql_id_peg, $sql_diklat, $sql_penyelenggara, $sql_tempat, $sql_biaya, $sql_angkatan, $sql_tahun, $sql_date_reg, '$user_log'
                )";
                
                if(mysqli_query($conn, $sqlInsert)) { $insert++; } else { $gagal++; }
            }
        }

        ob_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => "Proses Selesai!<br>Input Baru: <b>$insert</b><br>Update Data: <b>$update</b><br>Gagal/Skip: <b>$gagal</b>"
        ]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
