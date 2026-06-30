<?php
// =============================================================
// FILE: pages/ref-sertifikasi/upload-data-sertifikasi.php
// MODULE: Backend Import Sertifikasi (Final & Stabil)
// =============================================================

// Pastikan path vendor autoload benar
if (file_exists('../../vendor/autoload.php')) {
    require '../../vendor/autoload.php';
} else {
    // Fallback jika tidak pakai composer (jarang terjadi tapi aman)
    echo json_encode(['status' => 'error', 'message' => 'Library Excel tidak ditemukan.']);
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// [CONFIG] Matikan batasan memory untuk file besar
ini_set('memory_limit', '-1'); 
set_time_limit(0); 

// [CONFIG] Matikan error display agar JSON bersih
error_reporting(0);
ini_set('display_errors', 0);

// Buffer Output agar tidak ada spasi/whitespace liar
ob_start();
header('Content-Type: application/json');
if (session_id() == '') session_start();

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

    return sha1(uniqid('sertifikasi_preview_', true) . mt_rand());
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

// --- HELPER: SQL Value Cleaner ---
function getSqlVal($conn, $val) {
    if ($val === '' || $val === null || $val === false || $val === 'NULL') {
        return "NULL";
    }
    $safe = mysqli_real_escape_string($conn, trim($val));
    return "'$safe'";
}

// --- HELPER: Format Tanggal Excel ke Y-m-d ---
function formatTanggal($val) {
    $val = trim($val);
    if (empty($val) || $val == '-' || $val == '') return NULL;

    // 1. Cek Excel Serial Number (Angka)
    if (is_numeric($val) && $val > 1000) {
        try {
            return Date::excelToDateTimeObject($val)->format('Y-m-d');
        } catch (Exception $e) { return NULL; }
    }

    // 2. Cek Format Y-m-d
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $val)) return $val;

    // 3. Cek Format Indonesia d-m-Y atau d/m/Y
    $val = str_replace(['/', '.', ' '], '-', $val);
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : NULL;
}

try {
    // 1. KONEKSI DATABASE
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File koneksi database tidak ditemukan.");
    include $path_koneksi;

    if (!$conn) throw new Exception("Gagal terkoneksi ke database.");

    // [LINUX SAFE] Matikan strict mode agar INSERT/UPDATE tanggal kosong tidak error
    mysqli_query($conn, "SET SESSION sql_mode = ''");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request Method");
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // =========================================================
    // BAGIAN A: PREVIEW DATA (Baca Excel -> Tampilkan HTML)
    // =========================================================
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih");

        $file = $_FILES['file_excel'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'])) throw new Exception("Format file harus Excel (.xlsx / .xls)");

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        
        // Buang Header (Baris 1)
        $header = array_shift($rows); 

        // Siapkan HTML Tabel untuk Modal
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap table-hover" style="font-size: 0.9em;">';
        $html .= '<thead class="bg-primary text-white"><tr>';
        $html .= '<th width="5%" class="text-center">Status</th>
                  <th>ID Pegawai</th>
                  <th>Nama Sertifikasi</th>
                  <th>Penyelenggara</th>
                  <th>Tgl Sertifikat</th>
                  <th>Tgl Expired</th>
                  <th>No Sertifikat</th>';
        $html .= '</tr></thead><tbody>';

        $limit = 50; // Preview maksimal 50 baris agar ringan
        $count = 0;
        $previewData = []; // Array murni untuk JSON

        foreach ($rows as $row) {
            // Mapping Kolom Excel (Sesuaikan abjad kolom Excel kamu)
            $id_peg        = trim($row['A']);
            $nama_sertif   = trim($row['B']);
            $penyelenggara = trim($row['C']);
            
            // Skip jika ID Pegawai kosong (baris kosong)
            if(empty($id_peg)) continue;

            // Format Tanggal
            $tgl_sertif_db = formatTanggal($row['D']);
            $tgl_exp_db    = formatTanggal($row['E']);
            $no_sertif     = trim($row['F']);

            // --- CEK EKSISTENSI DATA ---
            // Unik berdasarkan: ID Pegawai + Nama Sertif + Tgl Expired
            // Logic: Jika Tgl Expired beda, dianggap perpanjangan (Insert Baru). Jika sama, Update data lama.
            
            $id_peg_esc = mysqli_real_escape_string($conn, $id_peg);
            $sertif_esc = mysqli_real_escape_string($conn, $nama_sertif);
            
            $sqlCek = "SELECT id_sertif FROM tb_sertifikasi WHERE id_peg = '$id_peg_esc' AND sertifikasi = '$sertif_esc'";
            
            if ($tgl_exp_db) {
                $sqlCek .= " AND tgl_expired = '$tgl_exp_db'";
            } else {
                $sqlCek .= " AND (tgl_expired IS NULL OR tgl_expired = '0000-00-00')";
            }

            $resCek = mysqli_query($conn, $sqlCek);
            $is_exist = ($resCek && mysqli_num_rows($resCek) > 0);

            // Tentukan Label Status
            if ($is_exist) {
                $status_badge = '<span class="badge badge-warning shadow-sm px-2">Update</span>';
            } else {
                $status_badge = '<span class="badge badge-success shadow-sm px-2">Baru</span>';
            }

            // Simpan ke Array (untuk dikirim balik via JSON)
            $previewData[] = [
                $id_peg, 
                $nama_sertif, 
                $penyelenggara, 
                $tgl_sertif_db, 
                $tgl_exp_db, 
                $no_sertif
            ];

            // Render HTML (hanya utk preview visual)
            if ($count < $limit) {
                $html .= '<tr>';
                $html .= '<td class="text-center">' . $status_badge . '</td>';
                $html .= '<td class="font-weight-bold">' . htmlspecialchars($id_peg) . '</td>';
                $html .= '<td>' . htmlspecialchars($nama_sertif) . '</td>';
                $html .= '<td>' . htmlspecialchars($penyelenggara) . '</td>';
                $html .= '<td>' . ($tgl_sertif_db ? $tgl_sertif_db : '-') . '</td>';
                $html .= '<td>' . ($tgl_exp_db ? $tgl_exp_db : '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($no_sertif) . '</td>';
                $html .= '</tr>';
                $count++;
            }
        }
        $html .= '</tbody></table></div>';
        
        if (count($previewData) > $limit) {
            $html .= '<div class="alert alert-info text-center p-2 mt-2 shadow-sm"><small><i class="fas fa-info-circle"></i> Hanya menampilkan 50 data pertama dari total <b>'.count($previewData).'</b> data.</small></div>';
        }

        // --- TOMBOL PROSES (PENTING) ---
        // Tombol ini akan muncul di dalam modal, memicu event listener di frontend
        $html .= '<div class="d-flex justify-content-between align-items-center mt-3 border-top pt-3">';
        $html .= '<span class="text-muted small font-italic">* Pastikan data di atas sudah benar.</span>';
        $html .= '<button type="button" class="btn btn-success px-4 rounded-pill shadow" id="btnSimpanSertifikasi">';
        $html .= '<i class="fas fa-cloud-upload-alt mr-2"></i> Proses Simpan Data (' . count($previewData) . ')';
        $html .= '</button>';
        $html .= '</div>';
        
        // Simpan data array ke dalam textarea tersembunyi agar bisa diambil JS
        $json_rows = json_encode($previewData);
        $preview_token = import_preview_token();
        $_SESSION['import_sertifikasi_preview_rows'] = $previewData;
        $_SESSION['import_sertifikasi_preview_token'] = $preview_token;
        $_SESSION['import_sertifikasi_preview_created_at'] = time();
        session_write_close();

        $html .= '<textarea id="json_data_sertifikasi" style="display:none;">' . htmlspecialchars($json_rows) . '</textarea>';
        $html .= '<input type="hidden" id="import_sertifikasi_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

        ob_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
        exit;
    }

    // =========================================================
    // BAGIAN B: SIMPAN DATA (Eksekusi ke Database)
    // =========================================================
    elseif ($action === 'save') {
        $data = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';

        if ($posted_token !== '' &&
            isset($_SESSION['import_sertifikasi_preview_token']) &&
            import_token_equals($_SESSION['import_sertifikasi_preview_token'], $posted_token) &&
            isset($_SESSION['import_sertifikasi_preview_rows']) &&
            is_array($_SESSION['import_sertifikasi_preview_rows'])) {
            $data = $_SESSION['import_sertifikasi_preview_rows'];
        } elseif (isset($_POST['data_sertifikasi'])) {
            $data = json_decode($_POST['data_sertifikasi'], true);
        }

        if (!$data || !is_array($data)) throw new Exception("Data tidak ditemukan atau sesi preview sudah berakhir.");

        unset($_SESSION['import_sertifikasi_preview_rows'], $_SESSION['import_sertifikasi_preview_token'], $_SESSION['import_sertifikasi_preview_created_at']);
        session_write_close();

        $jml_insert = 0;
        $jml_update = 0;
        $gagal = 0;
        $user_login = isset($_SESSION['nama_user']) ? $_SESSION['nama_user'] : 'System Import';

        foreach ($data as $row) {
            // [0]ID, [1]Nama, [2]Penyelenggara, [3]Tgl Sertif, [4]Tgl Exp, [5]No Sertif
            
            $id_peg_raw = isset($row[0]) ? trim($row[0]) : '';
            if(empty($id_peg_raw)) { $gagal++; continue; }

            // Siapkan Value SQL
            $sql_id_peg        = getSqlVal($conn, $id_peg_raw);
            $raw_sertifikasi   = isset($row[1]) ? trim($row[1]) : '';
            $sql_sertifikasi   = getSqlVal($conn, $raw_sertifikasi);
            $sql_penyelenggara = getSqlVal($conn, isset($row[2]) ? $row[2] : '');
            $sql_no_sertif     = getSqlVal($conn, isset($row[5]) ? $row[5] : '');

            // Handle Tanggal (Data dari JSON sudah terformat Y-m-d atau NULL dari tahap preview)
            $tgl_sertifikat    = isset($row[3]) ? $row[3] : NULL;
            $sql_tgl_sertif    = ($tgl_sertifikat) ? "'$tgl_sertifikat'" : "NULL";

            $tgl_expired       = isset($row[4]) ? $row[4] : NULL;
            $sql_tgl_exp       = ($tgl_expired) ? "'$tgl_expired'" : "NULL";

            // --- CEK DUPLIKASI (LOGIC) ---
            $safe_id_peg = mysqli_real_escape_string($conn, $id_peg_raw);
            $safe_sertif = mysqli_real_escape_string($conn, $raw_sertifikasi);

            $sqlCek = "SELECT id_sertif FROM tb_sertifikasi WHERE id_peg = '$safe_id_peg' AND sertifikasi = '$safe_sertif'";
            
            if ($tgl_expired) $sqlCek .= " AND tgl_expired = '$tgl_expired'";
            else $sqlCek .= " AND (tgl_expired IS NULL OR tgl_expired = '0000-00-00')";

            $resCek = mysqli_query($conn, $sqlCek);

            if ($resCek && mysqli_num_rows($resCek) > 0) {
                // --- UPDATE DATA ---
                $dt = mysqli_fetch_assoc($resCek);
                $id_exist = $dt['id_sertif'];
                
                $upd = "UPDATE tb_sertifikasi SET 
                        penyelenggara = $sql_penyelenggara,
                        tgl_sertifikat = $sql_tgl_sertif,
                        sertifikat = $sql_no_sertif,
                        date_reg = NOW() 
                        WHERE id_sertif = '$id_exist'";
                        
                if(mysqli_query($conn, $upd)) $jml_update++; else $gagal++;

            } else {
                // --- INSERT DATA ---
                $ins = "INSERT INTO tb_sertifikasi (
                            id_peg, sertifikasi, penyelenggara, tgl_sertifikat, tgl_expired, sertifikat, date_reg, created_by
                        ) VALUES (
                            $sql_id_peg, $sql_sertifikasi, $sql_penyelenggara, $sql_tgl_sertif, $sql_tgl_exp, $sql_no_sertif, NOW(), '$user_login'
                        )";
                        
                if(mysqli_query($conn, $ins)) $jml_insert++; else $gagal++;
            }
        }

        ob_clean();
        echo json_encode([
            'status' => 'success', 
            // INI SUMBER TEKSNYA:
            'message' => "Selesai! <br>Data Baru: <b>$jml_insert</b><br>Diupdate: <b>$jml_update</b>"
        ]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
