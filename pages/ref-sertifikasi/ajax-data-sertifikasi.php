<?php
/*********************************************************
 * FILE    : pages/ref-sertifikasi/ajax-data-sertifikasi.php
 * MODULE  : Backend JSON Sertifikasi (Secure & Sanitized)
 *********************************************************/

if (session_id() == '') session_start();

// Matikan error display agar tidak merusak format JSON jika ada warning PHP
ini_set('display_errors', 0);
while(ob_get_level()){ ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

// --- 1. KONEKSI DATABASE ---
@include_once __DIR__ . '/../../dist/koneksi.php';
if (!isset($conn)) { 
    @include_once __DIR__ . '/../../config/koneksi.php'; 
    $conn = isset($koneksi) ? $koneksi : null; 
}

// Jika koneksi gagal, kirim JSON kosong agar DataTables tidak error
if (!$conn) {
    echo json_encode(['draw'=>0, 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'error'=>'Koneksi DB Gagal']);
    exit;
}

// --- 2. FUNGSI KEAMANAN (HELPER) ---

// A. Anti SQL Injection untuk String (Escape)
function esc($s){ 
    global $conn;
    return mysqli_real_escape_string($conn, trim($s)); 
}

// B. Anti XSS (Cross-Site Scripting) untuk Output HTML
// Wajib dipakai saat menampilkan data nama, sertifikat, dll ke layar
function h($s){
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- 3. MENANGKAP PARAMETER DATATABLES ---

// Casting ke (int) untuk mencegah injeksi di limit/offset
$draw   = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$len    = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

// --- 4. MENANGKAP PARAMETER FILTER CUSTOM ---

$f_tahun   = isset($_GET['tahun']) ? esc($_GET['tahun']) : date('Y');
$f_sertif  = isset($_GET['sertifikasi']) ? esc($_GET['sertifikasi']) : '';
$f_kantor  = isset($_GET['kantor']) ? esc($_GET['kantor']) : '';

// --- 5. KONSTRUKSI QUERY ---

$baseQuery = " FROM tb_sertifikasi s 
               JOIN tb_pegawai p ON s.id_peg = p.id_peg 
               LEFT JOIN tb_jabatan j ON j.id_jab = (
                    SELECT j2.id_jab
                    FROM tb_jabatan j2
                    WHERE j2.id_peg = s.id_peg
                      AND j2.tmt_jabatan <= COALESCE(NULLIF(s.tgl_sertifikat, '0000-00-00'), CURDATE())
                      AND (
                            j2.sampai_tgl = '0000-00-00'
                            OR j2.sampai_tgl IS NULL
                            OR j2.sampai_tgl >= COALESCE(NULLIF(s.tgl_sertifikat, '0000-00-00'), CURDATE())
                          )
                    ORDER BY j2.tmt_jabatan DESC, j2.id_jab DESC
                    LIMIT 1
               )
               LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail ";

$where = " WHERE 1=1 ";

// Filter Tahun (Validasi Numeric agar aman)
if($f_tahun !== '' && is_numeric($f_tahun)){ 
    $where .= " AND (YEAR(s.tgl_sertifikat) = '$f_tahun' OR s.tgl_sertifikat IS NULL OR s.tgl_sertifikat = '0000-00-00') "; 
}

// Filter Sertifikasi
if($f_sertif !== ''){ 
    $where .= " AND s.sertifikasi = '$f_sertif' "; 
}

// Filter Kantor
if($f_kantor !== ''){ 
    $where .= " AND j.unit_kerja = '$f_kantor' "; 
}

// Global Search
if($search !== ''){
    $s = esc($search); // Escape input pencarian
    $where .= " AND (
        p.nama LIKE '%$s%' OR 
        s.sertifikasi LIKE '%$s%' OR 
        s.penyelenggara LIKE '%$s%' OR
        s.sertifikat LIKE '%$s%'
    ) ";
}

// --- 6. HITUNG TOTAL DATA ---
$total = 0;
$qCount = mysqli_query($conn, "SELECT COUNT(*) AS c $baseQuery $where");
if($qCount){ 
    $r = mysqli_fetch_assoc($qCount); 
    $total = (int)$r['c']; 
}

// --- 7. AMBIL DATA UTAMA ---
$sql = "SELECT s.*, p.nama AS nama_peg, p.id_peg, j.jabatan, k.kode_cabang
        $baseQuery $where
        ORDER BY s.tgl_sertifikat DESC
        LIMIT $start, $len"; // $start dan $len sudah di-cast (int) jadi aman

$q = mysqli_query($conn, $sql);
$data = array();
$no = $start + 1;

if($q){
    while($r = mysqli_fetch_assoc($q)){
        
        // --- PROSES DATA (SANITASI OUTPUT DENGAN h()) ---

        // Nama & NIP (Gunakan h() agar script tidak jalan)
        $nama_html = '<div class="font-weight-bold text-dark">'.h($r['nama_peg']).'</div>
                      <small class="text-muted">'.h($r['id_peg']).'</small>';

        // Sertifikasi & No Sertifikat
        $sertif_html = '<div class="font-weight-bold text-primary">'.h($r['sertifikasi']).'</div>';
        if(!empty($r['sertifikat']) && $r['sertifikat'] != '-'){
             $sertif_html .= '<small class="text-muted">'.h($r['sertifikat']).'</small>';
        }

        // Tanggal & Penyelenggara
        $tgl_sert = ($r['tgl_sertifikat'] != '0000-00-00') ? date('d M Y', strtotime($r['tgl_sertifikat'])) : '-';
        $lokasi_html = '<div>'.h($r['penyelenggara']).'</div>
                        <small class="text-muted"><i class="fa fa-calendar-alt mr-1"></i> '.$tgl_sert.'</small>';

        // Status (Logika Tanggal)
        $tgl_exp = isset($r['tgl_expired']) ? $r['tgl_expired'] : '';
        if($tgl_exp == '' || $tgl_exp == '0000-00-00'){
            $status_html = '<span class="badge badge-success">Seumur Hidup</span>';
        } else {
            if($tgl_exp < date('Y-m-d')){
                $status_html = '<span class="badge badge-danger">Expired</span><br><small class="text-danger" style="font-size:11px;">'.date('d-m-Y', strtotime($tgl_exp)).'</small>';
            } else {
                $status_html = '<span class="badge badge-success">Aktif</span><br><small class="text-muted" style="font-size:11px;">Exp: '.date('d-m-Y', strtotime($tgl_exp)).'</small>';
            }
        }

        $id_val = $r['id_sertif']; // ID biasanya auto-increment/uuid, relatif aman, tapi bisa di h() juga

        // Tombol Aksi
        $aksi_html = '<div class="btn-group">
                        <a href="home-admin.php?page=form-master-data-sertifikasi&id='.h($id_val).'" class="btn btn-sm btn-light border shadow-sm rounded-circle text-primary" title="Edit">
                            <i class="fa fa-pen"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-light border shadow-sm rounded-circle text-danger btn-delete" data-id="'.h($id_val).'" title="Hapus">
                            <i class="fa fa-trash"></i>
                        </button>
                      </div>';

        $data[] = array(
            'no'            => $no++,
            'nama_peg'      => $nama_html,
            'sertifikasi'   => $sertif_html,
            'penyelenggara' => $lokasi_html,
            'unit_kerja'    => '<div class="font-weight-bold text-dark">'.(h($r['kode_cabang']) ?: '-').'</div><small class="text-muted">'.(h($r['jabatan']) ?: '-').'</small>',
            'status'        => $status_html,
            'aksi'          => $aksi_html
        );
    }
}

// Output JSON
echo json_encode(array(
    'draw' => $draw, 
    'recordsTotal' => $total, 
    'recordsFiltered' => $total, 
    'data' => $data
)); 
exit;
?>
