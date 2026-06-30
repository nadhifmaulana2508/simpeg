<?php
// =============================================================
// FILE: pages/ref-pasangan/upload-data-pasangan.php
// TABLE: tb_suamiistri
// LOGIC: Manual ID Generation (MAX + 1) & Upsert & Linux Safe
// =============================================================

session_start();
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

// --- HELPER SQL VALUE (PENTING BUAT LINUX) ---
function getSqlVal($conn, $val, $type = 'string') {
    if ($val === '' || $val === null || $val === false || $val === 'NULL') {
        return "NULL";
    }
    
    $safe = mysqli_real_escape_string($conn, $val);
    
    if ($type === 'int') {
        // Hapus karakter non-angka
        $num = preg_replace('/[^0-9]/', '', $val);
        return ($num === '') ? "NULL" : "'$num'";
    }
    
    return "'$safe'";
}

// --- HELPER LAINNYA ---
function formatTanggal($date) {
    if (empty($date) || $date == '-' || $date == '') return NULL;
    
    // Cek Excel Serial Number
    if (is_numeric($date) && $date > 1000) {
        try {
            return Date::excelToDateTimeObject($date)->format('Y-m-d');
        } catch (Exception $e) { return NULL; }
    }

    // Cek Y-m-d
    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $date)) {
        return $date;
    }

    // Cek d-m-Y atau d/m/Y
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

function pasangan_preview_token() {
    return sha1(uniqid('pasangan-import-', true) . mt_rand());
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

try {
    // 1. KONEKSI
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File koneksi tidak ditemukan");
    include $path_koneksi;

    // [BARIS SAKTI] : SOLUSI AGAR LINUX TIDAK ERROR KARENA DATA KOSONG
    mysqli_query($conn, "SET SESSION sql_mode = ''"); 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request");
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // --- 2. PREVIEW DATA EXCEL ---
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih");
        
        $file = $_FILES['file_excel'];
        // Validasi Ekstensi
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) throw new Exception("Format file harus Excel (.xlsx/.xls)");

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) <= 1) throw new Exception("File Excel kosong");
        $header = array_shift($rows); 

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap" style="font-size: 0.9em;">';
        $html .= '<thead class="bg-primary text-white"><tr>';
        $html .= '<th>No</th>';
        foreach ($header as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
        $html .= '</tr></thead><tbody>';

        $limit = 10; $count = 0; $no = 1;
        $json_rows = []; // Tampung data bersih untuk JSON

        foreach ($rows as $row) {
            // Skip jika kolom pertama (ID Pegawai) kosong
            if (empty($row[0])) continue; 

            // Simpan ke array JSON
            $json_rows[] = $row;

            // Tampilkan Preview (dibatasi 10 baris)
            if ($count < $limit) {
                $html .= '<tr>';
                $html .= '<td>' . $no++ . '</td>';
                foreach ($row as $index => $cell) {
                    if ($index == 4) { // Tgl Lahir
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

        $preview_token = pasangan_preview_token();
        $_SESSION['import_pasangan_preview_rows'] = $json_rows;
        $_SESSION['import_pasangan_preview_token'] = $preview_token;
        $_SESSION['import_pasangan_preview_created_at'] = time();
        session_write_close();

        $html .= '<hr><div class="text-right">';
        $html .= '<button type="button" class="btn btn-primary" id="btnSimpanPasangan"><i class="fas fa-save"></i> Proses Simpan & Update</button>';
        $html .= '</div>';
        $html .= '<input type="hidden" id="import_pasangan_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

        ob_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
        exit;
    }

    // --- 3. PROSES SAVE (MANUAL ID & UPSERT & LINUX SAFE) ---
    elseif ($action === 'save') {
        $data = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';
        if ($posted_token !== '' &&
            isset($_SESSION['import_pasangan_preview_token']) &&
            import_token_equals($_SESSION['import_pasangan_preview_token'], $posted_token) &&
            isset($_SESSION['import_pasangan_preview_rows']) &&
            is_array($_SESSION['import_pasangan_preview_rows'])) {
            $data = $_SESSION['import_pasangan_preview_rows'];
        } elseif (isset($_POST['data_pasangan'])) {
            $data = json_decode($_POST['data_pasangan'], true);
        }
        if (!$data || !is_array($data)) throw new Exception("Data import tidak diterima atau sudah kadaluarsa");

        unset($_SESSION['import_pasangan_preview_rows'], $_SESSION['import_pasangan_preview_token'], $_SESSION['import_pasangan_preview_created_at']);
        session_write_close();
        
        $total_insert = 0;
        $total_update = 0;
        $gagal = 0;
        
        // Kunci tabel WRITE agar generate ID aman (Concurrency Safe)
        mysqli_query($conn, "LOCK TABLES tb_suamiistri WRITE, tb_pegawai READ");

        try {
            // Ambil counter ID terakhir sekali saja di awal
            $q_max = mysqli_query($conn, "SELECT MAX(id_si) as max_id FROM tb_suamiistri");
            $r_max = mysqli_fetch_assoc($q_max);
            
            // Bersihkan ID dari karakter non-angka agar bisa di-increment
            $current_max_id = 0;
            if ($r_max['max_id']) {
                $current_max_id = intval(preg_replace('/[^0-9]/', '', $r_max['max_id']));
            }

            foreach ($data as $row) {
                $id_peg_raw = isset($row[0]) ? trim($row[0]) : '';
                if(empty($id_peg_raw)) continue;

                $v_id_peg = mysqli_real_escape_string($conn, $id_peg_raw);

                // Cek Validitas Pegawai (Hanya proses jika pegawai ada di database)
                $cek_peg = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE id_peg = '$v_id_peg'");
                
                if (mysqli_num_rows($cek_peg) > 0) {
                    // --- PREPARE DATA DENGAN getSqlVal (AMAN LINUX) ---
                    $raw_nama = isset($row[2]) ? trim($row[2]) : '';
                    
                    $sql_nik          = getSqlVal($conn, isset($row[1]) ? bersihkanAngka($row[1]) : '');
                    $sql_nama         = getSqlVal($conn, $raw_nama);
                    $sql_tmp_lhr      = getSqlVal($conn, isset($row[3]) ? $row[3] : '');
                    
                    $tgl_lhr          = isset($row[4]) ? formatTanggal($row[4]) : NULL;
                    $sql_tgl_lhr      = ($tgl_lhr) ? "'$tgl_lhr'" : "NULL";
                    
                    $sql_pendidikan   = getSqlVal($conn, isset($row[5]) ? $row[5] : '');
                    $sql_id_pekerjaan = getSqlVal($conn, isset($row[6]) ? $row[6] : '', 'int'); // Handle Integer
                    $sql_pekerjaan    = getSqlVal($conn, isset($row[7]) ? $row[7] : '');
                    $sql_status_hub   = getSqlVal($conn, isset($row[8]) ? $row[8] : '');
                    $sql_hp           = getSqlVal($conn, isset($row[9]) ? bersihkanAngka($row[9]) : '');
                    $sql_bpjs         = getSqlVal($conn, isset($row[10]) ? bersihkanAngka($row[10]) : '');
                    
                    $date_reg         = date('Y-m-d H:i:s');

                    // --- LOGIKA UPSERT (Cek ID Pegawai & Nama) ---
                    // Menggunakan variable raw untuk pencarian
                    $safe_nama_search = mysqli_real_escape_string($conn, $raw_nama);
                    $cek_ada_sql = "SELECT id_si FROM tb_suamiistri WHERE id_peg = '$v_id_peg' AND nama = '$safe_nama_search' LIMIT 1";
                    $cek_ada     = mysqli_query($conn, $cek_ada_sql);

                    if (mysqli_num_rows($cek_ada) > 0) {
                        // --- UPDATE (ID Sudah Ada) ---
                        $row_old   = mysqli_fetch_assoc($cek_ada);
                        $id_target = $row_old['id_si'];

                        $query_update = "UPDATE tb_suamiistri SET 
                                            nik             = $sql_nik,
                                            tmp_lhr         = $sql_tmp_lhr,
                                            tgl_lhr         = $sql_tgl_lhr,
                                            pendidikan      = $sql_pendidikan,
                                            id_pekerjaan    = $sql_id_pekerjaan,
                                            pekerjaan       = $sql_pekerjaan,
                                            status_hub      = $sql_status_hub,
                                            hp              = $sql_hp,
                                            bpjs_pasangan   = $sql_bpjs
                                         WHERE id_si = '$id_target'";
                        
                        if (mysqli_query($conn, $query_update)) $total_update++; else $gagal++;

                    } else {
                        // --- INSERT (Generate ID Baru) ---
                        $current_max_id++; 
                        // Format ID jadi 8 digit angka (sesuai contoh 00001273)
                        $new_id_si = str_pad($current_max_id, 8, "0", STR_PAD_LEFT);

                        $query_insert = "INSERT INTO tb_suamiistri (
                            id_si, id_peg, nik, nama, tmp_lhr, tgl_lhr, pendidikan, 
                            id_pekerjaan, pekerjaan, status_hub, hp, bpjs_pasangan, date_reg
                        ) VALUES (
                            '$new_id_si', '$v_id_peg', $sql_nik, $sql_nama, $sql_tmp_lhr, $sql_tgl_lhr, $sql_pendidikan,
                            $sql_id_pekerjaan, $sql_pekerjaan, $sql_status_hub, $sql_hp, $sql_bpjs, '$date_reg'
                        )";

                        if (mysqli_query($conn, $query_insert)) {
                            $total_insert++;
                        } else {
                            $gagal++;
                            $current_max_id--; // Rollback counter jika gagal
                        }
                    }

                } else {
                    $gagal++; // ID Pegawai tidak ditemukan di master pegawai
                }
            }

        } catch (Exception $e) {
            throw $e; // Lempar ke catch luar untuk response error
        } finally {
            // PENTING: Selalu buka kunci tabel apapun yang terjadi
            mysqli_query($conn, "UNLOCK TABLES");
        }

        ob_clean();
        $pesan = "<b>Proses Selesai!</b><br>";
        $pesan .= "<span class='text-success'>Data Baru (Insert): $total_insert</span><br>";
        $pesan .= "<span class='text-info'>Data Diperbarui (Update): $total_update</span><br>";
        
        if($gagal > 0) {
            $pesan .= "<br><span class='text-danger'><b>Gagal/Skip:</b> $gagal baris (Cek ID Pegawai atau Format Data).</span>";
        }

        echo json_encode(['status' => 'success', 'message' => $pesan]);
        exit;
    }

} catch (Exception $e) {
    // Safety Unlock
    if (isset($conn)) mysqli_query($conn, "UNLOCK TABLES");
    
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
