<?php
// =============================================================
// FILE: pages/biaya_pendidikan/upload-data-biaya-pendidikan.php
// MODULE: Import Excel Biaya Pendidikan (Smart Lookup & Update Logic)
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

    return sha1(uniqid('biaya_preview_', true) . mt_rand());
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

// --- 1. FUNGSI HELPER & SMART LOOKUP ---

// Helper Aman SQL (Linux Friendly)
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

// Helper Format Tanggal Excel
function formatTanggal($date) {
    $date = trim($date);
    if (empty($date) || $date == '-' || $date == '') return NULL;
    if (is_numeric($date)) {
        if ($date > 1000) { // Excel Serial Date
            try { return Date::excelToDateTimeObject($date)->format('Y-m-d'); } catch (Exception $e) { return NULL; }
        }
    }
    // Handle String YYYY-MM-DD
    try { $dt = new DateTime($date); return $dt->format('Y-m-d'); } catch (Exception $e) { return NULL; }
}

// [LOGIC CERDAS 1] : Cari Kode Pengembangan (Input bisa Kode atau Nama)
function lookup_pengembangan($conn, $input) {
    $input = trim($input);
    if (empty($input)) return NULL;

    $escInput = mysqli_real_escape_string($conn, $input);

    // 1. Cek apakah input adalah KODE (Exact Match)
    $qKode = mysqli_query($conn, "SELECT kode_sandi FROM tb_ref_pengembangan WHERE kode_sandi = '$escInput' LIMIT 1");
    if (mysqli_num_rows($qKode) > 0) {
        return $input;
    }

    // 2. Jika bukan kode, Cek apakah input adalah NAMA/KATEGORI (Like Match)
    $qNama = mysqli_query($conn, "SELECT kode_sandi FROM tb_ref_pengembangan WHERE kategori LIKE '%$escInput%' LIMIT 1");
    if ($row = mysqli_fetch_assoc($qNama)) {
        return $row['kode_sandi'];
    }

    return NULL; // Tidak ditemukan
}

// [LOGIC CERDAS 2] : Cari Kode Pihak (Input bisa Kode atau Nama)
function lookup_pihak($conn, $input) {
    $input = trim($input);
    if (empty($input)) return NULL;

    $escInput = mysqli_real_escape_string($conn, $input);

    // 1. Cek Kode
    $qKode = mysqli_query($conn, "SELECT kode_pihak FROM tb_ref_pelaksana WHERE kode_pihak = '$escInput' LIMIT 1");
    if (mysqli_num_rows($qKode) > 0) {
        return $input;
    }

    // 2. Cek Nama
    $qNama = mysqli_query($conn, "SELECT kode_pihak FROM tb_ref_pelaksana WHERE nama_pihak LIKE '%$escInput%' LIMIT 1");
    if ($row = mysqli_fetch_assoc($qNama)) {
        return $row['kode_pihak'];
    }

    return NULL;
}

// --- CORE LOGIC ---
try {
    // 1. Koneksi Database
    $path_koneksi = '../../dist/koneksi.php'; 
    if (!file_exists($path_koneksi)) throw new Exception("File koneksi database tidak ditemukan.");
    include $path_koneksi;

    // Mode santuy untuk Linux
    mysqli_query($conn, "SET SESSION sql_mode = ''"); 

    // 2. Cek Request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request tidak valid.");
    if (empty($_SESSION['id_user'])) throw new Exception("Sesi kadaluarsa. Silakan login ulang.");

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $tgl_sekarang = date('Y-m-d');
    $id_user_login = $_SESSION['id_user'];

    // ==========================================================
    // A. MODE PREVIEW
    // ==========================================================
    if ($action === 'preview') {
        if (!isset($_FILES['file_excel'])) throw new Exception("File belum dipilih.");

        $file = $_FILES['file_excel'];
        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        } catch (Exception $e) { throw new Exception("Gagal membaca file Excel."); }

        if (count($rows) <= 1) throw new Exception("File Excel kosong.");
        
        $header = array_shift($rows); // Buang Header
        $limit_preview = 15; 
        $preview_rows = array_slice($rows, 0, $limit_preview);

        // Generate Table Preview
        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped table-sm text-nowrap" style="font-size: 0.85em;">';
        $html .= '<thead class="bg-primary text-white"><tr><th>Status Data</th>'; 
        // Header Manual Biar Rapi
        $headers_view = ['Kode Kat', 'Kode Pihak', 'Judul Pengembangan', 'Waktu', 'Nama Pihak', 'Jml SDM', 'Total Biaya', 'Tgl Mulai'];
        foreach ($headers_view as $h) $html .= '<th>' . $h . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($preview_rows as $row) {
            // Mapping Kolom Excel
            $in_kode_kat   = isset($row[0]) ? trim($row[0]) : '';
            $in_kode_pihak = isset($row[1]) ? trim($row[1]) : '';
            $in_judul      = isset($row[2]) ? trim($row[2]) : '';
            $in_waktu      = isset($row[3]) ? trim($row[3]) : '';
            $in_nm_pihak   = isset($row[4]) ? trim($row[4]) : '';
            $in_jml        = isset($row[5]) ? trim($row[5]) : '';
            $in_biaya      = isset($row[6]) ? trim($row[6]) : '';
            $in_tgl        = formatTanggal(isset($row[7]) ? $row[7] : '');

            // Cek Status (Logic Update vs Insert)
            $status_row = '<span class="badge badge-success">New Insert</span>';
            
            // Cek Duplikat di DB (Judul + Pihak + Tgl)
            if(!empty($in_judul) && !empty($in_nm_pihak) && !empty($in_tgl)) {
                $judul_esc = mysqli_real_escape_string($conn, $in_judul);
                $pihak_esc = mysqli_real_escape_string($conn, $in_nm_pihak);
                $cekDup = mysqli_query($conn, "SELECT biaya_id FROM tb_biaya_pendidikan 
                                               WHERE pengembangan_sdm = '$judul_esc' 
                                               AND pihak_pelaksana = '$pihak_esc' 
                                               AND tgl_pengembangan_sdm = '$in_tgl'");
                if(mysqli_num_rows($cekDup) > 0) {
                    $status_row = '<span class="badge badge-warning">Update Data</span>';
                }
            }

            // Logic Lookup Preview (Biar user tau kodenya ketemu atau nggak)
            $found_kat = lookup_pengembangan($conn, $in_kode_kat);
            $found_pihak = lookup_pihak($conn, $in_kode_pihak);

            $display_kat = $found_kat ? "$found_kat <i class='fas fa-check text-success'></i>" : "<span class='text-danger'>$in_kode_kat (?)</span>";
            $display_pihak = $found_pihak ? "$found_pihak <i class='fas fa-check text-success'></i>" : "<span class='text-danger'>$in_kode_pihak (?)</span>";

            $html .= '<tr>';
            $html .= '<td>' . $status_row . '</td>';
            $html .= '<td>' . $display_kat . '</td>';
            $html .= '<td>' . $display_pihak . '</td>';
            $html .= '<td>' . htmlspecialchars($in_judul) . '</td>';
            $html .= '<td>' . htmlspecialchars($in_waktu) . '</td>';
            $html .= '<td>' . htmlspecialchars($in_nm_pihak) . '</td>';
            $html .= '<td>' . htmlspecialchars($in_jml) . '</td>';
            $html .= '<td>Rp ' . number_format((float)$in_biaya, 0, ',', '.') . '</td>';
            $html .= '<td>' . ($in_tgl ? $in_tgl : '-') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        
        if (count($rows) > $limit_preview) {
            $html .= '<div class="alert alert-info py-2 my-2"><i class="fas fa-info-circle"></i> Menampilkan 15 dari '.count($rows).' data.</div>';
        }

        // Tombol Simpan
        $html .= '<hr><div class="text-right"><button type="button" class="btn btn-primary" id="btnSimpanKolektif"><i class="fas fa-save"></i> Proses Import & Update</button></div>';
        
        // Simpan Data Mentah ke Textarea untuk dikirim balik saat Save
        $json_rows = json_encode($rows);
        $preview_token = import_preview_token();
        $_SESSION['import_biaya_preview_rows'] = $rows;
        $_SESSION['import_biaya_preview_token'] = $preview_token;
        $_SESSION['import_biaya_preview_created_at'] = time();
        session_write_close();

        $html .= '<textarea id="json_data_biaya" style="display:none;">' . htmlspecialchars($json_rows) . '</textarea>';
        $html .= '<input type="hidden" id="import_biaya_preview_token" value="' . htmlspecialchars($preview_token, ENT_QUOTES, 'UTF-8') . '">';

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
            isset($_SESSION['import_biaya_preview_token']) &&
            import_token_equals($_SESSION['import_biaya_preview_token'], $posted_token) &&
            isset($_SESSION['import_biaya_preview_rows']) &&
            is_array($_SESSION['import_biaya_preview_rows'])) {
            $data = $_SESSION['import_biaya_preview_rows'];
        } elseif (isset($_POST['data_biaya'])) {
            $data = json_decode($_POST['data_biaya'], true);
        }

        if (!$data || !is_array($data)) throw new Exception("Data import tidak ditemukan atau sesi preview sudah berakhir.");

        unset($_SESSION['import_biaya_preview_rows'], $_SESSION['import_biaya_preview_token'], $_SESSION['import_biaya_preview_created_at']);
        session_write_close();

        $berhasil = 0; $updated = 0; $gagal = 0;
        $pesan_error_db = "";

        foreach ($data as $row) {
            // 1. Ambil Data Raw
            $raw_kode_kat   = isset($row[0]) ? $row[0] : '';
            $raw_kode_pihak = isset($row[1]) ? $row[1] : '';
            $raw_judul      = isset($row[2]) ? trim($row[2]) : '';
            $raw_waktu      = isset($row[3]) ? trim($row[3]) : '';
            $raw_nm_pihak   = isset($row[4]) ? trim($row[4]) : '';
            $raw_jml        = isset($row[5]) ? trim($row[5]) : '0';
            $raw_biaya      = isset($row[6]) ? trim($row[6]) : '0';
            $raw_tgl        = formatTanggal(isset($row[7]) ? $row[7] : '');

            // Skip jika data kunci kosong
            if(empty($raw_judul) || empty($raw_tgl)) {
                $gagal++; continue; 
            }

            // 2. Resolve Kode (Cari ID based on Kode/Nama)
            $fix_kode_kat   = lookup_pengembangan($conn, $raw_kode_kat);
            $fix_kode_pihak = lookup_pihak($conn, $raw_kode_pihak);

            // 3. Persiapan Variabel SQL
            $sql_kode_kat   = ($fix_kode_kat) ? "'$fix_kode_kat'" : "NULL";
            $sql_kode_pihak = ($fix_kode_pihak) ? "'$fix_kode_pihak'" : "NULL";
            
            $sql_judul      = getSqlVal($conn, $raw_judul);
            $sql_waktu      = getSqlVal($conn, $raw_waktu);
            $sql_nm_pihak   = getSqlVal($conn, $raw_nm_pihak);
            $sql_jml        = getSqlVal($conn, $raw_jml, 'int');
            $sql_biaya      = getSqlVal($conn, $raw_biaya, 'int');
            $sql_tgl        = "'$raw_tgl'";

            // 4. CEK DUPLIKAT (Kunci: Judul, Pihak Pelaksana, Tgl)
            $esc_judul = mysqli_real_escape_string($conn, $raw_judul);
            $esc_pihak = mysqli_real_escape_string($conn, $raw_nm_pihak);
            
            $cekQ = "SELECT biaya_id FROM tb_biaya_pendidikan 
                     WHERE pengembangan_sdm = '$esc_judul' 
                     AND pihak_pelaksana = '$esc_pihak' 
                     AND tgl_pengembangan_sdm = $sql_tgl LIMIT 1";
            
            $resCek = mysqli_query($conn, $cekQ);

            if(mysqli_num_rows($resCek) > 0) {
                // === UPDATE DATA ===
                $d = mysqli_fetch_assoc($resCek);
                $id_update = $d['biaya_id'];

                $qUpdate = "UPDATE tb_biaya_pendidikan SET 
                            kode_pengembangan = $sql_kode_kat,
                            kode_pihak = $sql_kode_pihak,
                            waktu_pelaksanaan = $sql_waktu,
                            jumlah_sdm = $sql_jml,
                            total_biaya = $sql_biaya,
                            updated_at = NOW(),
                            updated_by = '$id_user_login'
                            WHERE biaya_id = '$id_update'";
                
                if(mysqli_query($conn, $qUpdate)) { $updated++; } 
                else { $gagal++; if(empty($pesan_error_db)) $pesan_error_db = mysqli_error($conn); }

            } else {
                // === INSERT BARU ===
                $qInsert = "INSERT INTO tb_biaya_pendidikan 
                            (kode_pengembangan, kode_pihak, waktu_pelaksanaan, pengembangan_sdm, pihak_pelaksana, jumlah_sdm, total_biaya, tgl_pengembangan_sdm, created, created_by)
                            VALUES 
                            ($sql_kode_kat, $sql_kode_pihak, $sql_waktu, $sql_judul, $sql_nm_pihak, $sql_jml, $sql_biaya, $sql_tgl, '$tgl_sekarang', '$id_user_login')";
                
                if(mysqli_query($conn, $qInsert)) { $berhasil++; } 
                else { $gagal++; if(empty($pesan_error_db)) $pesan_error_db = mysqli_error($conn); }
            }
        }

        ob_clean();
        $msg = "<b>Proses Selesai!</b><br>
                <span class='text-success'>Data Baru: $berhasil</span><br>
                <span class='text-warning'>Data Diupdate: $updated</span><br>
                <span class='text-danger'>Gagal: $gagal</span>";
        
        if($pesan_error_db) $msg .= "<br><small>Error DB: $pesan_error_db</small>";

        echo json_encode(['status' => 'success', 'message' => $msg]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
