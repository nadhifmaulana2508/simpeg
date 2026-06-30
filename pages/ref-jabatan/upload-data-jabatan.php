<?php
// =============================================================
// FILE: pages/ref-jabatan/upload-data-jabatan.php
// MODULE: Backend Import Jabatan (Upsert by ID Pegawai & No SK)
// =============================================================

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// [CONFIG] : Matikan batasan memory & waktu untuk data banyak
ini_set('memory_limit', '-1'); 
set_time_limit(0); 

// [CONFIG] : Matikan error display agar JSON tidak rusak
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_id() == '') session_start(); 

ob_start();
header('Content-Type: application/json; charset=utf-8');

// --- Helper Response ---
function kirimJson($status, $msg, $html = '') {
    ob_clean(); 
    echo json_encode(['status' => $status, 'message' => $msg, 'html' => $html]);
    exit;
}

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

    return sha1(uniqid('jabatan_preview_', true) . mt_rand());
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

// --- Helper SQL Value (PENTING BUAT LINUX) ---
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

function normalizeNoSk($val) {
    $val = trim((string) $val);
    if ($val === '' || strtoupper($val) === 'NULL' || $val === '0') {
        return '-';
    }

    return $val;
}

function isPlaceholderNoSk($val) {
    $val = trim((string) $val);
    return ($val === '' || $val === '-' || strtoupper($val) === 'NULL' || $val === '0');
}

function resolveMasterJabatan($conn, $kode, $nama) {
    $kode = trim((string) $kode);
    $nama = trim((string) $nama);

    $resolved = array(
        'kode' => $kode,
        'nama' => $nama
    );

    if ($kode !== '') {
        $safe_kode = mysqli_real_escape_string($conn, $kode);
        $qKode = mysqli_query($conn, "SELECT kode_jabatan, nama_jabatan FROM tb_master_jabatan WHERE kode_jabatan = '$safe_kode' LIMIT 1");
        if ($qKode && ($rKode = mysqli_fetch_assoc($qKode))) {
            $resolved['kode'] = $rKode['kode_jabatan'];
            if ($resolved['nama'] === '') {
                $resolved['nama'] = $rKode['nama_jabatan'];
            }
            return $resolved;
        }
    }

    if ($nama !== '') {
        $safe_nama = mysqli_real_escape_string($conn, $nama);
        $qNama = mysqli_query($conn, "SELECT kode_jabatan, nama_jabatan FROM tb_master_jabatan WHERE nama_jabatan = '$safe_nama' LIMIT 1");
        if ($qNama && ($rNama = mysqli_fetch_assoc($qNama))) {
            if ($resolved['kode'] === '') {
                $resolved['kode'] = $rNama['kode_jabatan'];
            }
            $resolved['nama'] = $rNama['nama_jabatan'];
        }
    }

    return $resolved;
}

try {
    // 1. KONEKSI DATABASE
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File Koneksi tidak ditemukan.");
    include $path_koneksi;

    if (!$conn) throw new Exception("Koneksi database gagal.");

    // [BARIS SAKTI] : SOLUSI AGAR LINUX TIDAK ERROR KARENA DATA KOSONG
    mysqli_query($conn, "SET SESSION sql_mode = ''"); 

    // --- FUNGSI FORMAT TANGGAL CERDAS ---
    function formatTanggal($val) {
        $val = trim($val);
        if (empty($val) || $val == '-' || $val == '' || $val == '0000-00-00') return NULL;

        if (is_numeric($val) && $val > 1000) {
            try {
                return Date::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (Exception $e) { return NULL; }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

        $val = str_replace(['/', '.', ' '], '-', $val);
        $ts = strtotime($val);
        if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);

        return NULL;
    }

    // --- FUNGSI UPDATE HISTORY OTOMATIS (LOGIC H-1) ---
    function perbaikiHistoryJabatan($conn, $id_peg) {
        $q = mysqli_query($conn, "SELECT id_jab, tmt_jabatan FROM tb_jabatan WHERE id_peg='$id_peg' ORDER BY tmt_jabatan ASC");
        $data = [];
        while($r = mysqli_fetch_assoc($q)) {
            $data[] = $r;
        }

        $total = count($data);
        if ($total > 0) {
            for ($i = 0; $i < $total - 1; $i++) {
                $curr = $data[$i];
                $next = $data[$i+1];
                $tgl_tutup = date('Y-m-d', strtotime('-1 day', strtotime($next['tmt_jabatan'])));
                mysqli_query($conn, "UPDATE tb_jabatan SET sampai_tgl='$tgl_tutup', status_jab='Non' WHERE id_jab='".$curr['id_jab']."'");
            }

            $last = $data[$total - 1];
            mysqli_query($conn, "UPDATE tb_jabatan SET sampai_tgl='0000-00-00', status_jab='Aktif' WHERE id_jab='".$last['id_jab']."'");
        }
    }

    // --- FUNGSI SORTING PREVIEW ---
    function compareJabatanDate($a, $b) {
        $t1 = $a['tgl_timestamp'];
        $t2 = $b['tgl_timestamp'];
        if ($t1 == $t2) return 0;
        return ($t1 > $t2) ? -1 : 1; 
    }

    function ambilLatestTmtExisting($conn, $id_peg) {
        $id_peg = mysqli_real_escape_string($conn, $id_peg);
        $res = mysqli_query($conn, "SELECT MAX(tmt_jabatan) AS latest_tmt FROM tb_jabatan WHERE id_peg='$id_peg' AND tmt_jabatan IS NOT NULL AND tmt_jabatan <> '0000-00-00'");
        if ($res && ($row = mysqli_fetch_assoc($res)) && !empty($row['latest_tmt'])) {
            return $row['latest_tmt'];
        }

        return NULL;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request Method");
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ============================================================
    // ACTION: PREVIEW
    // ============================================================
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih");
        
        $file = $_FILES['file_excel'];
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        
        if (count($rows) <= 1) throw new Exception("File Excel kosong");
        array_shift($rows); 

        $groupedData = [];
        foreach ($rows as $row) {
            $id_peg = isset($row['A']) ? trim($row['A']) : '';
            if (empty($id_peg)) continue;

            $tgl_sk_fix = formatTanggal(isset($row['F']) ? $row['F'] : '');
            $groupedData[$id_peg][] = [
                'raw' => $row,
                'tgl_sk_fix' => $tgl_sk_fix,
                'tgl_timestamp' => $tgl_sk_fix ? strtotime($tgl_sk_fix) : 0
            ];
        }

        $html = '<div class="table-responsive"><table class="table table-bordered table-striped table-sm text-nowrap" style="font-size:0.85em;">';
        $html .= '<thead class="bg-primary text-white"><tr><th>Status System</th><th>ID Pegawai</th><th>Kode Jab</th><th>Jabatan</th><th>Unit Kerja</th><th>No SK</th><th>TMT</th></tr></thead><tbody>';

        $jsonFull = [];
        foreach ($groupedData as $id_peg => $items) {
            usort($items, 'compareJabatanDate');
            $existing_latest_tmt = ambilLatestTmtExisting($conn, $id_peg);
            $existing_latest_ts = $existing_latest_tmt ? strtotime($existing_latest_tmt) : 0;
            $allow_active_from_import = false;

            if (!empty($items) && isset($items[0]['tgl_timestamp'])) {
                $latest_import_ts = (int) $items[0]['tgl_timestamp'];
                $allow_active_from_import = ($latest_import_ts > 0 && $latest_import_ts >= $existing_latest_ts);
            }

            foreach ($items as $idx => $item) {
                $row = $item['raw'];
                $resolvedJabatan = resolveMasterJabatan($conn, isset($row['B']) ? $row['B'] : '', isset($row['C']) ? $row['C'] : '');
                $status_final = ($allow_active_from_import && $idx === 0) ? 'Aktif' : 'Non';
                $badge = ($status_final === 'Aktif') ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Non</span>';

                $kode_preview = htmlspecialchars($resolvedJabatan['kode']);
                $nama_preview = htmlspecialchars($resolvedJabatan['nama']);
                $unit_preview = htmlspecialchars(isset($row['D']) ? $row['D'] : '');
                $nosk_preview = htmlspecialchars(isset($row['E']) ? $row['E'] : '');

                $html .= "<tr><td>$badge</td><td>$id_peg</td><td>{$kode_preview}</td><td>{$nama_preview}</td><td>{$unit_preview}</td><td>{$nosk_preview}</td><td>{$item['tgl_sk_fix']}</td></tr>";
                
                $jsonFull[] = [
                    $id_peg, $resolvedJabatan['kode'], $resolvedJabatan['nama'], isset($row['D']) ? $row['D'] : '', isset($row['E']) ? $row['E'] : '', $item['tgl_sk_fix'], $status_final
                ];
            }
        }
        $html .= '</tbody></table></div>';
        $html .= '<hr><div class="text-right"><button type="button" class="btn btn-success" id="btnSimpanJabatan"><i class="fas fa-save"></i> Proses Import</button></div>';
        
        $json_data = json_encode($jsonFull);
        $preview_token = import_preview_token();
        $_SESSION['import_jabatan_preview_rows'] = $jsonFull;
        $_SESSION['import_jabatan_preview_token'] = $preview_token;
        $_SESSION['import_jabatan_preview_created_at'] = time();
        session_write_close();

        kirimJson('success', '', $html . '<textarea id="json_data_jabatan" style="display:none;">' . $json_data . '</textarea><input type="hidden" id="import_jabatan_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">');
    }

    // ============================================================
    // ACTION: SAVE (UPSERT BY ID PEGAWAI + NO SK)
    // ============================================================
    elseif ($action === 'save') {
        $data_raw = array();
        $posted_token = isset($_POST['preview_token']) ? trim($_POST['preview_token']) : '';

        if ($posted_token !== '' &&
            isset($_SESSION['import_jabatan_preview_token']) &&
            import_token_equals($_SESSION['import_jabatan_preview_token'], $posted_token) &&
            isset($_SESSION['import_jabatan_preview_rows']) &&
            is_array($_SESSION['import_jabatan_preview_rows'])) {
            $data_raw = $_SESSION['import_jabatan_preview_rows'];
        } elseif (isset($_POST['data_jabatan'])) {
            $data_raw = json_decode($_POST['data_jabatan'], true);
        }

        if (!$data_raw || !is_array($data_raw)) throw new Exception("Data import tidak diterima atau sesi preview sudah berakhir.");

        $created_by = isset($_SESSION['nama_user']) ? mysqli_real_escape_string($conn, $_SESSION['nama_user']) : 'System';
        unset($_SESSION['import_jabatan_preview_rows'], $_SESSION['import_jabatan_preview_token'], $_SESSION['import_jabatan_preview_created_at']);
        session_write_close();
        $berhasil = 0; $update = 0; $gagal = 0;
        $processed_pegawai = [];
        $error_details = array();

        foreach ($data_raw as $row) {
            $id_peg       = trim($row[0]);
            $kode_jab_xls = trim($row[1]);
            $nama_jab_xls = trim($row[2]);
            $unit_kerja   = getSqlVal($conn, $row[3]);
            $no_sk_raw    = trim($row[4]);
            $no_sk_norm   = normalizeNoSk($row[4]);
            $tgl_sk       = $row[5];
            $status_jab   = getSqlVal($conn, $row[6]);

            if (empty($id_peg) || empty($tgl_sk)) {
                $gagal++;
                $error_details[] = "Baris {$id_peg}: ID pegawai atau TMT kosong.";
                continue;
            }
            $processed_pegawai[$id_peg] = true;

            // Lookup Master Jabatan 2 arah:
            // kode -> nama, nama -> kode, atau pakai keduanya jika sudah lengkap
            $resolvedJabatan = resolveMasterJabatan($conn, $kode_jab_xls, $nama_jab_xls);
            $f_kode = $resolvedJabatan['kode'];
            $f_nama = $resolvedJabatan['nama'];

            $sql_kode = getSqlVal($conn, $f_kode);
            $sql_nama = getSqlVal($conn, $f_nama);
            $sql_tgl  = "'$tgl_sk'";
            $sql_no_sk = getSqlVal($conn, $no_sk_norm);

            // LOGIC UPSERT:
            // - Jika No SK valid, pakai ID Pegawai + No SK
            // - Jika No SK kosong / '-', fallback ke ID Pegawai + TMT + Jabatan
            $v_id_peg = mysqli_real_escape_string($conn, $id_peg);
            $v_no_sk  = mysqli_real_escape_string($conn, $no_sk_norm);
            $v_kode_jab = mysqli_real_escape_string($conn, $f_kode);
            $v_nama_jab = mysqli_real_escape_string($conn, $f_nama);

            if (!isPlaceholderNoSk($no_sk_raw)) {
                $cek_sql = "SELECT id_jab FROM tb_jabatan WHERE id_peg='$v_id_peg' AND no_sk='$v_no_sk' LIMIT 1";
            } else {
                $cek_sql = "SELECT id_jab
                            FROM tb_jabatan
                            WHERE id_peg='$v_id_peg'
                              AND tmt_jabatan=$sql_tgl
                              AND (
                                    (kode_jabatan = '$v_kode_jab' AND '$v_kode_jab' <> '')
                                    OR
                                    (jabatan = '$v_nama_jab' AND '$v_nama_jab' <> '')
                                  )
                            LIMIT 1";
            }

            $cek = mysqli_query($conn, $cek_sql);
            
            if (mysqli_num_rows($cek) > 0) {
                $rowOld = mysqli_fetch_assoc($cek);
                $query = "UPDATE tb_jabatan SET 
                            kode_jabatan=$sql_kode, jabatan=$sql_nama, unit_kerja=$unit_kerja, 
                            no_sk=$sql_no_sk, tgl_sk=$sql_tgl, tmt_jabatan=$sql_tgl, status_jab=$status_jab,
                            updated_at=NOW(), updated_by='$created_by'
                          WHERE id_jab='{$rowOld['id_jab']}'";
                if (mysqli_query($conn, $query)) {
                    $update++;
                } else {
                    $gagal++;
                    $error_details[] = "Update {$id_peg} ({$tgl_sk}): " . mysqli_error($conn);
                }
            } else {
                $query = "INSERT INTO tb_jabatan (id_peg, kode_jabatan, jabatan, unit_kerja, no_sk, tgl_sk, tmt_jabatan, status_jab, created_by, date_reg) 
                          VALUES ('$v_id_peg', $sql_kode, $sql_nama, $unit_kerja, $sql_no_sk, $sql_tgl, $sql_tgl, $status_jab, '$created_by', NOW())";
                if (mysqli_query($conn, $query)) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $error_details[] = "Insert {$id_peg} ({$tgl_sk}): " . mysqli_error($conn);
                }
            }
        }

        foreach (array_keys($processed_pegawai) as $id_p) perbaikiHistoryJabatan($conn, $id_p);

        $message = "Selesai!<br>Baru: $berhasil<br>Update (SK Sama): $update<br>Gagal: $gagal";
        if (!empty($error_details)) {
            $message .= "<br><br><small style='text-align:left;display:inline-block;max-width:100%;'>" . implode("<br>", array_slice($error_details, 0, 5)) . "</small>";
        }

        kirimJson('success', $message);
    }

} catch (Exception $e) {
    kirimJson('error', $e->getMessage());
}
?>
