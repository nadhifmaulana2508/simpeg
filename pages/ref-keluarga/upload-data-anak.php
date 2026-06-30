<?php
// =============================================================
// FILE: pages/ref-anak/upload-data-anak.php
// LOGIC: Check (ID_PEG + NAMA) -> IF EXIST UPDATE, ELSE INSERT
// LINUX READY: Strict Mode OFF, NULL Handling, Resource Limit OFF
// =============================================================

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// [CONFIG] : Matikan batasan memory & waktu untuk data banyak
ini_set('memory_limit', '-1'); 
set_time_limit(0); 

// [CONFIG] : Matikan error display agar JSON tidak rusak
error_reporting(0);
ini_set('display_errors', 0);

ob_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

function anak_preview_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(16);
        if ($bytes !== false) {
            return bin2hex($bytes);
        }
    }

    return sha1(uniqid('anak_preview_', true) . mt_rand());
}

// --- HELPER SQL VALUE (PENTING BUAT LINUX) ---
function getSqlVal($conn, $val, $type = 'string') {
    if ($val === '' || $val === null || $val === false || $val === 'NULL') {
        return "NULL";
    }
    
    $safe = mysqli_real_escape_string($conn, $val);
    
    if ($type === 'int') {
        $num = preg_replace('/[^0-9]/', '', $val);
        return ($num === '') ? "NULL" : "'$num'";
    }
    
    return "'$safe'";
}

// --- FUNGSI BANTUAN ---
function formatTanggal($date) {
    if (empty($date) || $date == '-' || $date == '') return NULL;
    
    // Cek Excel Serial Number
    if (is_numeric($date) && $date > 1000) {
        try {
            return Date::excelToDateTimeObject($date)->format('Y-m-d');
        } catch (Exception $e) { return NULL; }
    }

    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $date)) {
        return $date;
    }
    
    try {
        $timestamp = strtotime(str_replace('/', '-', $date));
        return $timestamp ? date('Y-m-d', $timestamp) : NULL;
    } catch (Exception $e) {
        return NULL;
    }
}

function bersihkanAngka($str) {
    return preg_replace('/[^0-9]/', '', $str);
}

try {
    // 1. KONEKSI
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File koneksi tidak ditemukan");
    include $path_koneksi;

    // [BARIS SAKTI] : SOLUSI AGAR LINUX TIDAK ERROR KARENA DATA KOSONG
    mysqli_query($conn, "SET SESSION sql_mode = ''"); 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request");
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // --- 1. PREVIEW DATA EXCEL ---
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih");
        
        $file = $_FILES['file_excel'];
        // Validasi Extensi
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) throw new Exception("Format file harus Excel (.xlsx/.xls)");

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) <= 1) throw new Exception("File Excel kosong");
        $header = array_shift($rows); 

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap" style="font-size: 0.9em;">';
        $html .= '<thead class="bg-success text-white"><tr>'; // Pake bg-success biar beda dikit :D
        $html .= '<th>No</th>';
        foreach ($header as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
        $html .= '</tr></thead><tbody>';

        $limit = 10; $count = 0; $no = 1;
        $json_rows = [];

        foreach ($rows as $row) {
            if (empty($row[0])) continue; // Skip baris kosong

            $json_rows[] = $row; // Simpan untuk JSON

            if ($count < $limit) {
                $html .= '<tr>';
                $html .= '<td>' . $no++ . '</td>';
                foreach ($row as $index => $cell) {
                    if ($index == 1 || $index == 9 || $index == 10) { // NIK, Anak Ke, BPJS
                         $html .= '<td>' . htmlspecialchars(bersihkanAngka($cell)) . '</td>';
                    } elseif ($index == 4) { // Tgl Lahir
                         $tgl = formatTanggal($cell);
                         $html .= '<td>' . ($tgl ? $tgl : '-') . '</td>';
                    } else {
                         $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            $count++;
        }
        $html .= '</tbody></table></div>';
        
        if ($count > $limit) $html .= '<div class="alert alert-info py-2 mt-2"><i class="fas fa-info-circle"></i> Menampilkan 10 dari '.$count.' data.</div>';
        
        $preview_token = anak_preview_token();
        $_SESSION['import_anak_preview_rows'] = $json_rows;
        $_SESSION['import_anak_preview_token'] = $preview_token;
        $_SESSION['import_anak_preview_created_at'] = time();
        session_write_close();

        $html .= '<hr><div class="text-right">';
        $html .= '<button type="button" class="btn btn-success" id="btnSimpanAnak"><i class="fas fa-save"></i> Proses Simpan & Update</button>';
        $html .= '</div>';
        
        $json_str = json_encode($json_rows);
        $html .= '<textarea id="json_data_anak" style="display:none;">' . $json_str . '</textarea>';
        $html .= '<input type="hidden" id="import_anak_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

        ob_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
        exit;
    }

    // --- 2. PROSES SAVE (INSERT OR UPDATE - LINUX SAFE) ---
    elseif ($action === 'save') {
        $data = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';

        if (
            $posted_token !== '' &&
            isset($_SESSION['import_anak_preview_token']) &&
            import_token_equals($_SESSION['import_anak_preview_token'], $posted_token) &&
            isset($_SESSION['import_anak_preview_rows']) &&
            is_array($_SESSION['import_anak_preview_rows'])
        ) {
            $data = $_SESSION['import_anak_preview_rows'];
        } elseif (isset($_POST['data_anak'])) {
            $data = json_decode($_POST['data_anak'], true);
        }

        if (!$data || !is_array($data)) throw new Exception("Data tidak diterima atau sesi preview sudah berakhir");

        unset($_SESSION['import_anak_preview_rows'], $_SESSION['import_anak_preview_token'], $_SESSION['import_anak_preview_created_at']);
        session_write_close();
        
        $total_insert = 0;
        $total_update = 0;
        $gagal = 0;
        $list_gagal = [];

        foreach ($data as $row) {
            // [0] ID Pegawai
            $id_peg_raw = isset($row[0]) ? trim($row[0]) : '';
            if(empty($id_peg_raw)) continue;
            
            $v_id_peg = mysqli_real_escape_string($conn, $id_peg_raw);

            // Cek Validitas ID Pegawai
            $cek_peg = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE id_peg = '$v_id_peg'");
            
            if (mysqli_num_rows($cek_peg) > 0) {
                
                // --- DATA PREPARATION (LINUX SAFE) ---
                $raw_nama = isset($row[2]) ? trim($row[2]) : '';

                $sql_nik          = getSqlVal($conn, isset($row[1]) ? bersihkanAngka($row[1]) : '');
                $sql_nama         = getSqlVal($conn, $raw_nama);
                $sql_tmp_lhr      = getSqlVal($conn, isset($row[3]) ? $row[3] : '');
                
                $tgl_lhr          = isset($row[4]) ? formatTanggal($row[4]) : NULL;
                $sql_tgl_lhr      = ($tgl_lhr) ? "'$tgl_lhr'" : "NULL";
                
                $sql_pendidikan   = getSqlVal($conn, isset($row[5]) ? $row[5] : '');
                $sql_id_pekerjaan = getSqlVal($conn, isset($row[6]) ? $row[6] : '', 'int'); 
                $sql_pekerjaan    = getSqlVal($conn, isset($row[7]) ? $row[7] : '');
                $sql_status_hub   = getSqlVal($conn, isset($row[8]) ? $row[8] : '');
                
                $sql_anak_ke      = getSqlVal($conn, isset($row[9]) ? bersihkanAngka($row[9]) : '', 'int'); // Pastikan Int
                $sql_bpjs         = getSqlVal($conn, isset($row[10]) ? bersihkanAngka($row[10]) : '');
                
                $date_reg         = date('Y-m-d H:i:s');

                // LOGIKA UTAMA: CEK BERDASARKAN ID_PEG DAN NAMA ANAK
                $safe_nama_search = mysqli_real_escape_string($conn, $raw_nama);
                
                $cek_anak_sql = "SELECT id_anak FROM tb_anak WHERE id_peg = '$v_id_peg' AND nama = '$safe_nama_search' LIMIT 1";
                $cek_anak     = mysqli_query($conn, $cek_anak_sql);

                if (mysqli_num_rows($cek_anak) > 0) {
                    // --- KONDISI: DATA SUDAH ADA -> UPDATE ---
                    $row_old = mysqli_fetch_assoc($cek_anak);
                    $id_target = $row_old['id_anak'];

                    $query_update = "UPDATE tb_anak SET 
                                        nik = $sql_nik,
                                        tmp_lhr = $sql_tmp_lhr,
                                        tgl_lhr = $sql_tgl_lhr,
                                        pendidikan = $sql_pendidikan,
                                        id_pekerjaan = $sql_id_pekerjaan,
                                        pekerjaan = $sql_pekerjaan,
                                        status_hub = $sql_status_hub,
                                        anak_ke = $sql_anak_ke,
                                        bpjs_anak = $sql_bpjs
                                        -- Nama tidak diupdate karena jadi kunci pencarian
                                     WHERE id_anak = '$id_target'";
                    
                    if (mysqli_query($conn, $query_update)) {
                        $total_update++;
                    } else {
                        $gagal++;
                    }

                } else {
                    // --- KONDISI: DATA BELUM ADA -> INSERT ---
                    // Kolom id_anak biasanya Auto Increment
                    $query_insert = "INSERT INTO tb_anak (
                        id_peg, nik, nama, tmp_lhr, tgl_lhr, pendidikan, 
                        id_pekerjaan, pekerjaan, status_hub, anak_ke, bpjs_anak, date_reg
                    ) VALUES (
                        '$v_id_peg', $sql_nik, $sql_nama, $sql_tmp_lhr, $sql_tgl_lhr, $sql_pendidikan,
                        $sql_id_pekerjaan, $sql_pekerjaan, $sql_status_hub, $sql_anak_ke, $sql_bpjs, '$date_reg'
                    )";

                    if (mysqli_query($conn, $query_insert)) {
                        $total_insert++;
                    } else {
                        $gagal++;
                    }
                }

            } else {
                // ID PEGAWAI TIDAK DITEMUKAN
                $gagal++;
                if (!in_array($v_id_peg, $list_gagal)) {
                    $list_gagal[] = $v_id_peg; 
                }
            }
        }

        ob_clean();
        
        $pesan = "<b>Proses Selesai!</b><br>";
        $pesan .= "<span class='text-success'>Data Baru (Insert): $total_insert</span><br>";
        $pesan .= "<span class='text-info'>Data Diperbarui (Update): $total_update</span><br>";
        
        if($gagal > 0) {
            $limit_msg = 5;
            $ids = array_slice($list_gagal, 0, $limit_msg);
            $sisa = count($list_gagal) - $limit_msg;
            $txt_ids = implode(", ", $ids);
            if($sisa > 0) $txt_ids .= " dan $sisa lainnya";

            $pesan .= "<br><span class='text-danger'><b>Gagal:</b> $gagal baris.</span>";
            if (!empty($list_gagal)) {
                $pesan .= "<br><small class='text-muted'>Kemungkinan ID Pegawai tidak ditemukan: $txt_ids</small>";
            }
        }

        echo json_encode(['status' => 'success', 'message' => $pesan]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
