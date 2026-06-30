<?php
// =============================================================
// FILE: pages/pegawai/upload-data-pegawai.php
// MODULE: Import Excel Pegawai (Linux Strict Mode Compatible)
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

if (session_id() == '') session_start(); 

ob_start();
header('Content-Type: application/json');

// --- 1. FUNGSI HELPER AMAN UNTUK LINUX ---

function getSqlVal($conn, $val, $type = 'string') {
    if ($val === '' || $val === null || $val === false) {
        return "NULL";
    }
    
    $safe = mysqli_real_escape_string($conn, $val);
    
    if ($type === 'int') {
        $num = preg_replace('/[^0-9]/', '', $val);
        return ($num === '') ? "NULL" : "'$num'";
    }
    
    return "'$safe'";
}

function formatTanggal($date) {
    $date = trim($date);
    if (empty($date) || $date == '-' || $date == '') return NULL;

    if (is_numeric($date)) {
        if ($date > 1000) {
            try { return Date::excelToDateTimeObject($date)->format('Y-m-d'); } catch (Exception $e) { return NULL; }
        }
    }

    $date = str_replace(['/', '.'], '-', $date);

    if (preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $date, $matches)) {
        if (checkdate($matches[2], $matches[3], $matches[1])) return $date;
    }

    if (preg_match("/^(\d{2})-(\d{2})-(\d{4})$/", $date, $matches)) {
        if (checkdate($matches[2], $matches[1], $matches[3])) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
    }

    try { $dt = new DateTime($date); return $dt->format('Y-m-d'); } catch (Exception $e) { return NULL; }
}

function hitungPensiun($tgl_lahir, $tgl_pensiun_input = '') {
    $manual = formatTanggal($tgl_pensiun_input);
    if (!empty($manual) && $manual != '1970-01-01') return $manual;

    $lahir = formatTanggal($tgl_lahir);
    if (!empty($lahir) && $lahir != '1970-01-01') {
        try {
            $date = new DateTime($lahir);
            $date->modify('+56 years'); 
            return $date->format('Y-m-d');
        } catch (Exception $e) { return NULL; }
    }
    return NULL;
}

function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function import_preview_token() {
    return sha1(uniqid('pegawai-import-', true) . mt_rand());
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

function sync_related_employee_id($conn, $old_id, $new_id) {
    $old_id = mysqli_real_escape_string($conn, $old_id);
    $new_id = mysqli_real_escape_string($conn, $new_id);

    $schemaRes = mysqli_query($conn, "SELECT DATABASE() AS db_name");
    $schemaRow = $schemaRes ? mysqli_fetch_assoc($schemaRes) : null;
    $dbName = ($schemaRow && !empty($schemaRow['db_name'])) ? $schemaRow['db_name'] : '';
    if ($dbName === '') {
        return;
    }
    $dbNameSafe = mysqli_real_escape_string($conn, $dbName);

    $tables = array();
    $colRes = mysqli_query($conn, "
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '$dbNameSafe'
          AND COLUMN_NAME IN ('id_peg', 'id_pegawai')
    ");
    if ($colRes) {
        while ($col = mysqli_fetch_assoc($colRes)) {
            $tableName = $col['TABLE_NAME'];
            $columnName = $col['COLUMN_NAME'];
            if (!isset($tables[$tableName])) {
                $tables[$tableName] = array();
            }
            $tables[$tableName][] = $columnName;
        }
    }

    foreach ($tables as $tableName => $columns) {
        if ($tableName === 'tb_pegawai') {
            continue;
        }

        foreach ($columns as $columnName) {
            $tableSafe = '`' . str_replace('`', '``', $tableName) . '`';
            $columnSafe = '`' . str_replace('`', '``', $columnName) . '`';
            mysqli_query($conn, "UPDATE $tableSafe SET $columnSafe = '$new_id' WHERE $columnSafe = '$old_id'");
        }
    }

    mysqli_query($conn, "UPDATE tb_user SET id_user = '$new_id' WHERE id_user = '$old_id'");
    mysqli_query($conn, "UPDATE tb_angkat SET id_peg_baru = '$new_id' WHERE id_peg_baru = '$old_id'");
}

// --- CORE LOGIC ---
try {
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File koneksi database tidak ditemukan.");
    include $path_koneksi;

    mysqli_query($conn, "SET SESSION sql_mode = ''"); 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request tidak valid.");
    if (empty($_SESSION['id_user'])) throw new Exception("Sesi kadaluarsa. Silakan login ulang.");

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $tgl_sekarang = date('Y-m-d');

    // ==========================================================
    // A. MODE PREVIEW (BACA FILE EXCEL)
    // ==========================================================
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih.");

        $file = $_FILES['file_excel'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) throw new Exception("Format file tidak didukung.");

        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        } catch (Exception $e) {
            throw new Exception("Gagal membaca file Excel.");
        }

        if (count($rows) <= 1) throw new Exception("File Excel kosong atau hanya header.");
        
        $header = array_shift($rows); 
        $limit_preview = 15; 
        $preview_rows = array_slice($rows, 0, $limit_preview);

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap" style="font-size: 0.85em;">';
        $html .= '<thead class="bg-primary text-white"><tr><th>Status System</th>'; 
        foreach ($header as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($preview_rows as $row) {
            $id_excel = isset($row[0]) ? trim($row[0]) : '';
            $nip_excel = isset($row[1]) ? trim($row[1]) : '';
            $nama_excel = isset($row[2]) ? trim($row[2]) : '';
            
            $status_row = '<span class="badge badge-success">New Input</span>';
            
            if(!empty($id_excel)) {
                $id_esc = mysqli_real_escape_string($conn, $id_excel);
                $cekId = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE id_peg = '$id_esc' LIMIT 1");
                
                if(mysqli_num_rows($cekId) > 0) {
                    $status_row = '<span class="badge badge-warning">Update Data</span>';
                } else {
                    // LOGIK BARU: Cek NIP & Nama sekaligus untuk Ganti ID
                    if(!empty($nama_excel) && !empty($nip_excel)) {
                        $nm_esc = mysqli_real_escape_string($conn, $nama_excel);
                        $np_esc = mysqli_real_escape_string($conn, preg_replace('/[^0-9]/', '', $nip_excel));
                        $cekMatch = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE nama = '$nm_esc' AND nip = '$np_esc' LIMIT 1");
                        if(mysqli_num_rows($cekMatch) > 0) {
                            $status_row = '<span class="badge badge-danger">Ganti ID (Match)</span>';
                        }
                    }
                }
            }

            $html .= '<tr><td>' . $status_row . '</td>';
            foreach ($row as $index => $cell) {
                if ($index == 1) { 
                     $val = $cell;
                     if (is_numeric($val) && stripos($val, 'E') !== false) $val = number_format($cell, 0, '', '');
                     $html .= '<td>' . htmlspecialchars($val) . '</td>';
                }
                elseif ($index == 4 || $index == 14) { 
                    $tgl = formatTanggal($cell);
                    $html .= '<td>' . ($tgl ? $tgl : '-') . '</td>';
                }
                elseif ($index == 15) { 
                    $pensiun_fix = hitungPensiun(isset($row[4])?$row[4]:'', $cell);
                    $html .= '<td>' . ($pensiun_fix ? $pensiun_fix : '-') . '</td>';
                }
                else {
                    $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        
        if (count($rows) > $limit_preview) {
            $html .= '<div class="alert alert-info py-2 my-2"><i class="fas fa-info-circle"></i> Menampilkan 15 dari '.count($rows).' baris.</div>';
        }

        $preview_token = import_preview_token();
        $_SESSION['import_pegawai_preview_rows'] = $rows;
        $_SESSION['import_pegawai_preview_token'] = $preview_token;
        $_SESSION['import_pegawai_preview_created_at'] = time();
        session_write_close();

        $html .= '<hr><div class="text-right"><button type="button" class="btn btn-primary" id="btnSimpanKolektif"><i class="fas fa-save"></i> Proses Import & Update</button></div>';
        $html .= '<input type="hidden" id="import_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

        ob_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
        exit;
    }

    // ==========================================================
    // B. MODE SAVE (EKSEKUSI DATABASE)
    // ==========================================================
    elseif ($action === 'save') {
        $data = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';

        if ($posted_token !== '' &&
            isset($_SESSION['import_pegawai_preview_token']) &&
            import_token_equals($_SESSION['import_pegawai_preview_token'], $posted_token) &&
            isset($_SESSION['import_pegawai_preview_rows']) &&
            is_array($_SESSION['import_pegawai_preview_rows'])) {
            $data = $_SESSION['import_pegawai_preview_rows'];
        } elseif (isset($_POST['data_pegawai'])) {
            $data = json_decode($_POST['data_pegawai'], true);
        }

        if (!$data || !is_array($data)) throw new Exception("Data import tidak ditemukan atau sudah kadaluarsa.");

        $created_by = isset($_SESSION['nama_user']) ? mysqli_real_escape_string($conn, $_SESSION['nama_user']) : 'System';
        unset($_SESSION['import_pegawai_preview_rows'], $_SESSION['import_pegawai_preview_token'], $_SESSION['import_pegawai_preview_created_at']);
        session_write_close();
        $berhasil = 0; $gagal = 0; $updated = 0; $updated_id = 0; $skip = 0;
        $pesan_error_db = ""; 
        $error_details = array();

        foreach ($data as $row) {
            $id_peg_raw = isset($row[0]) ? trim($row[0]) : '';
            if (empty($id_peg_raw)) { $gagal++; continue; }

            $v_id_peg   = mysqli_real_escape_string($conn, $id_peg_raw);
            $v_nip      = isset($row[1]) ? trim($row[1]) : '';
            $v_nama     = isset($row[2]) ? strip_tags(trim($row[2])) : '';
            
            $tgl_lhr        = formatTanggal(isset($row[4]) ? $row[4] : '');
            $tmt_kerja      = formatTanggal(isset($row[14]) ? $row[14] : '');
            $final_pensiun  = hitungPensiun(isset($row[4]) ? $row[4] : '', isset($row[15]) ? $row[15] : ''); 

            $sql_nama         = getSqlVal($conn, $v_nama);
            $sql_tempat_lhr   = getSqlVal($conn, isset($row[3]) ? $row[3] : '');
            $sql_tgl_lhr      = ($tgl_lhr) ? "'$tgl_lhr'" : "NULL";
            $sql_agama        = getSqlVal($conn, isset($row[5]) ? $row[5] : '');
            $sql_jk           = getSqlVal($conn, isset($row[6]) ? $row[6] : '');
            $sql_gol_darah    = getSqlVal($conn, isset($row[7]) ? $row[7] : '');
            $sql_status_nikah = getSqlVal($conn, isset($row[8]) ? $row[8] : '');
            $sql_status_kepeg = getSqlVal($conn, isset($row[9]) ? $row[9] : '');
            $sql_alamat       = getSqlVal($conn, isset($row[10]) ? $row[10] : '');
            $sql_telp         = getSqlVal($conn, isset($row[11]) ? $row[11] : '');
            $sql_email        = getSqlVal($conn, isset($row[12]) ? $row[12] : '');
            $sql_tmt_kerja    = ($tmt_kerja) ? "'$tmt_kerja'" : "NULL";
            $sql_tgl_pensiun  = ($final_pensiun) ? "'$final_pensiun'" : "NULL";
            $sql_bpjstk       = getSqlVal($conn, isset($row[16]) ? $row[16] : '', 'int');
            $sql_bpjskes      = getSqlVal($conn, isset($row[17]) ? $row[17] : '', 'int');

            $safe_nip = mysqli_real_escape_string($conn, preg_replace('/[^0-9]/', '', $v_nip));
            $sql_nip = ($safe_nip === '') ? "NULL" : "'$safe_nip'";

            $sql_foto_update = "";
            $foto_raw = isset($row[13]) ? trim($row[13]) : '';
            if (!empty($foto_raw)) $sql_foto_update = ", foto = " . getSqlVal($conn, $foto_raw);

            // 1. CEK APAKAH ID_PEG SUDAH ADA?
            $cekId = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE id_peg = '$v_id_peg' LIMIT 1");
            
            if (mysqli_num_rows($cekId) > 0) {
                // UPDATE BIASA
                $query_update = "UPDATE tb_pegawai SET 
                    nip = $sql_nip, nama = $sql_nama, tempat_lhr = $sql_tempat_lhr, tgl_lhr = $sql_tgl_lhr,
                    agama = $sql_agama, jk = $sql_jk, gol_darah = $sql_gol_darah, status_nikah = $sql_status_nikah, 
                    status_kepeg = $sql_status_kepeg, alamat = $sql_alamat, telp = $sql_telp, email = $sql_email 
                    $sql_foto_update, 
                    tmt_kerja = $sql_tmt_kerja, tgl_pensiun = $sql_tgl_pensiun, bpjstk = $sql_bpjstk, bpjskes = $sql_bpjskes
                    WHERE id_peg = '$v_id_peg'";

                if (mysqli_query($conn, $query_update)) {
                    if (mysqli_affected_rows($conn) > 0) {
                        $updated++;
                    } else {
                        $skip++;
                    }
                } else {
                    $gagal++;
                    $pesan_error_db = mysqli_error($conn);
                    $error_details[] = "ID $v_id_peg: " . $pesan_error_db;
                }

            } else {
                // 2. CEK APAKAH ADA NIP & NAMA YANG SAMA (GANTI ID)
                $cekMatch = mysqli_query($conn, "SELECT id_peg FROM tb_pegawai WHERE nama = $sql_nama AND nip = $sql_nip LIMIT 1");
                
                if (mysqli_num_rows($cekMatch) > 0) {
                    $rowMatch = mysqli_fetch_assoc($cekMatch);
                    $old_id = $rowMatch['id_peg'];
                    mysqli_begin_transaction($conn);
                    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

                    $query_update_id = "UPDATE tb_pegawai SET 
                        id_peg = '$v_id_peg',
                        nip = $sql_nip, nama = $sql_nama, tempat_lhr = $sql_tempat_lhr, tgl_lhr = $sql_tgl_lhr,
                        agama = $sql_agama, jk = $sql_jk, gol_darah = $sql_gol_darah, status_nikah = $sql_status_nikah, 
                        status_kepeg = $sql_status_kepeg, alamat = $sql_alamat, telp = $sql_telp, email = $sql_email 
                        $sql_foto_update, 
                        tmt_kerja = $sql_tmt_kerja, tgl_pensiun = $sql_tgl_pensiun, bpjstk = $sql_bpjstk, bpjskes = $sql_bpjskes
                        WHERE id_peg = '$old_id'";

                    $ok_update_id = mysqli_query($conn, $query_update_id);
                    if ($ok_update_id) {
                        sync_related_employee_id($conn, $old_id, $v_id_peg);
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                        mysqli_commit($conn);
                        $updated_id++;
                    } else {
                        $pesan_error_db = mysqli_error($conn);
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                        mysqli_rollback($conn);
                        $gagal++;
                        $error_details[] = "Ganti ID $old_id -> $v_id_peg: " . $pesan_error_db;
                    }

                } else {
                    // 3. INSERT BARU
                    $uid_baru = gen_uuid();
                    $query = "INSERT INTO tb_pegawai (
                        pegawai_uid, id_peg, nip, nama, tempat_lhr, tgl_lhr, agama, jk, gol_darah, 
                        status_nikah, status_kepeg, alamat, telp, email, foto, tmt_kerja, tgl_pensiun, 
                        bpjstk, bpjskes, status_aktif, created_by, date_reg
                    ) VALUES (
                        '$uid_baru', '$v_id_peg', $sql_nip, $sql_nama, $sql_tempat_lhr, $sql_tgl_lhr, $sql_agama, 
                        $sql_jk, $sql_gol_darah, $sql_status_nikah, $sql_status_kepeg, $sql_alamat, $sql_telp, 
                        $sql_email, ".getSqlVal($conn, $foto_raw).", $sql_tmt_kerja, $sql_tgl_pensiun, 
                        $sql_bpjstk, $sql_bpjskes, '1', '$created_by', '$tgl_sekarang'
                    )";

                    if (mysqli_query($conn, $query)) { 
                        $pass_def = md5("123456");
                        $qUser = "INSERT INTO tb_user (id_user, password, nama_user, id_pegawai, hak_akses, status_aktif) 
                                  VALUES ('$v_id_peg', '$pass_def', $sql_nama, '$v_id_peg', 'User', 'Y')";
                        mysqli_query($conn, $qUser);
                        $berhasil++; 
                    } else {
                        $gagal++;
                        $pesan_error_db = mysqli_error($conn);
                        $error_details[] = "Insert ID $v_id_peg: " . $pesan_error_db;
                    }
                }
            }
        }

        $message = "<b>Import Selesai!</b><br>
                    <span class='text-success'>Baru: $berhasil</span> | 
                    <span class='text-primary'>Update: $updated</span> | 
                    <span class='text-warning'>Ganti ID: $updated_id</span> | 
                    <span class='text-info'>Sesuai: $skip</span> | 
                    <span class='text-danger'>Gagal: $gagal</span>";

        if ($gagal > 0 && !empty($error_details)) {
            $message .= "<br><small class='text-muted'>"
                . htmlspecialchars(implode(' | ', array_slice($error_details, 0, 3)), ENT_QUOTES, 'UTF-8')
                . "</small>";
        }

        ob_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => $message
        ]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
