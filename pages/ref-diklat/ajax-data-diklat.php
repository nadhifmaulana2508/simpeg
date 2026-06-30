<?php
/***********************
 * FILE    : pages/ref-diklat/ajax-data-diklat.php
 * MODULE  : Backend JSON DataTables (Secure Version + Delete)
 ***********************/

// --- 1. CLEAN OUTPUT & SESSION START ---
if (session_id() === '') session_start();
ini_set('display_errors', 0);
while(ob_get_level()){ ob_end_clean(); } 
header('Content-Type: application/json; charset=utf-8');

// --- 2. SECURITY CHECK (WAJIB ADA) ---
if (empty($_SESSION['id_user'])) {
    echo json_encode(['error' => 'Akses ditolak. Silakan login.']);
    exit;
}

// --- 3. KONEKSI DATABASE ---
$path_koneksi = __DIR__ . '/../../dist/koneksi.php';
if (file_exists($path_koneksi)) {
    include $path_koneksi;
} else {
    // Fallback jika struktur folder berbeda
    @include_once __DIR__ . '/../../config/koneksi.php'; 
}

// Pastikan $conn ada
if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

// Helper: Escape SQL Injection
function esc($conn, $s){ 
    return mysqli_real_escape_string($conn, trim($s)); 
}

// Helper: Escape HTML (Anti XSS)
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- 4. PARAMETER DATATABLES ---
$draw   = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$len    = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

// --- 5. PARAMETER FILTER ---
$f_tahun   = isset($_GET['tahun']) ? esc($conn, $_GET['tahun']) : date('Y');
$f_diklat  = isset($_GET['diklat']) ? esc($conn, $_GET['diklat']) : '';
$f_kantor  = isset($_GET['kantor']) ? esc($conn, $_GET['kantor']) : '';

// --- 6. QUERY BUILDER ---
$baseQuery = " FROM tb_diklat d 
               JOIN tb_pegawai p ON d.id_peg = p.id_peg 
               LEFT JOIN tb_jabatan j ON j.id_jab = (
                    SELECT j2.id_jab
                    FROM tb_jabatan j2
                    WHERE j2.id_peg = d.id_peg
                      AND j2.tmt_jabatan <= COALESCE(NULLIF(d.date_reg, '0000-00-00'), CURDATE())
                      AND (
                            j2.sampai_tgl = '0000-00-00'
                            OR j2.sampai_tgl IS NULL
                            OR j2.sampai_tgl >= COALESCE(NULLIF(d.date_reg, '0000-00-00'), CURDATE())
                          )
                    ORDER BY j2.tmt_jabatan DESC, j2.id_jab DESC
                    LIMIT 1
               )
               LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail ";

$where = " WHERE 1=1 ";

// Filter Custom
if($f_tahun !== '') { $where .= " AND d.tahun = '$f_tahun' "; }
if($f_diklat !== ''){ $where .= " AND d.diklat = '$f_diklat' "; }
if($f_kantor !== ''){ $where .= " AND j.unit_kerja = '$f_kantor' "; }

// Pencarian Global
if($search !== ''){
    $s = esc($conn, $search);
    $where .= " AND (
        p.nama LIKE '%$s%' OR 
        p.id_peg LIKE '%$s%' OR
        d.diklat LIKE '%$s%' OR 
        d.penyelenggara LIKE '%$s%'
    ) ";
}

// --- 7. HITUNG TOTAL DATA (PAGINATION) ---
$total = 0;
$qCount = mysqli_query($conn, "SELECT COUNT(*) AS c $baseQuery $where");
if($qCount){ $r = mysqli_fetch_assoc($qCount); $total = (int)$r['c']; }

// --- 8. AMBIL DATA UTAMA ---
$sql = "SELECT d.*, p.nama AS nama_peg, p.id_peg, j.unit_kerja, j.jabatan, k.kode_cabang
        $baseQuery $where
        ORDER BY d.date_reg DESC
        LIMIT $start, $len";

$q = mysqli_query($conn, $sql);
$data = array();
$no = $start + 1;

if($q){
    while($r = mysqli_fetch_assoc($q)){
        
        // [ANTI XSS] Bungkus output dengan fungsi h()
        $nama_peg   = h($r['nama_peg']);
        $id_peg     = h($r['id_peg']);
        $penyelenggara = h($r['penyelenggara']);
        $tempat     = h($r['tempat']);
        $jns_diklat = h($r['diklat']);
        $kode_cabang = h($r['kode_cabang']);
        $jabatan_histori = h($r['jabatan']);
        $tahun      = h($r['tahun']);
        $id_diklat  = h($r['id_diklat']);

        // Format HTML (Aman karena variabel sudah di-escape di atas)
        $nama_html = '<div class="font-weight-bold text-dark">'.$nama_peg.'</div>
                      <small class="text-muted">'.$id_peg.'</small>';
        
        $lokasi_html = '<div>'.$penyelenggara.'</div>
                        <small class="text-muted"><i class="fa fa-map-marker-alt text-danger mr-1"></i> '.$tempat.'</small>';

        $unit_html = '<div class="font-weight-bold text-dark">' . ($kode_cabang ?: '-') . '</div>
                      <small class="text-muted">' . ($jabatan_histori ?: '-') . '</small>';

        // --- UPDATE DISINI: MENAMBAHKAN TOMBOL DELETE ---
        $aksi_html = '<div style="white-space:nowrap;">
                        <a href="home-admin.php?page=form-diklat&id='.$id_diklat.'" class="btn btn-sm btn-light border shadow-sm rounded-circle text-primary me-1" title="Edit">
                            <i class="fa fa-pen"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-light border shadow-sm rounded-circle text-danger btn-delete" data-id="'.$id_diklat.'" title="Hapus">
                            <i class="fa fa-trash"></i>
                        </button>
                      </div>';

        $data[] = array(
            'no'            => $no++,
            'nama_peg'      => $nama_html,
            'diklat'        => $jns_diklat,
            'penyelenggara' => $lokasi_html,
            'unit_kerja'    => $unit_html,
            'tahun'         => $tahun,
            'aksi'          => $aksi_html
        );
    }
}

// --- 9. OUTPUT JSON ---
echo json_encode(array(
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $total,
    'data'            => $data
)); 
exit;
?>
