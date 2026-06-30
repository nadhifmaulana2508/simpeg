<?php
/*********************************************************
 * FILE    : pages/ref-jabatan/ajax-jabatan-aktif.php
 * MODULE  : SIMPEG — DataTables Server-side: Jabatan Aktif
 * VERSION : v1.1 (PHP 5.6 compatible)
 * DATE    : 2025-09-06
 * CHANGE  : Tampilkan kolom Unit Kerja sebagai nama_kantor (join tb_kantor)
 *           Filter unit bisa pakai kode_kantor_detail atau nama_kantor
 *********************************************************/

if (session_id()==='') session_start();
@header('Content-Type: application/json; charset=UTF-8');

@include_once __DIR__ . '/../../dist/koneksi.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; }
@include_once __DIR__ . '/../../config.php';

if (!$conn) { echo json_encode(array('data'=>array(), 'recordsTotal'=>0,'recordsFiltered'=>0,'error'=>'No DB connection')); exit; }

function esc($c,$s){ return mysqli_real_escape_string($c, $s); }
function is_kode($v){ return preg_match('~^\d{4,6}$~', $v); }
function jabatan_ajax_page_url($page, $params = array()) {
  if (function_exists('page_url')) {
    return page_url($page, $params);
  }

  $url = 'home-admin.php?page=' . rawurlencode($page);
  if (!empty($params)) {
    $url .= '&' . http_build_query($params);
  }
  return $url;
}

$draw   = isset($_GET['draw'])   ? (int)$_GET['draw']   : 1;
$start  = isset($_GET['start'])  ? (int)$_GET['start']  : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
if ($length < 1) $length = 10; if ($length > 500) $length = 500;

$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
$search = esc($conn, $search);

$filter_unit = isset($_GET['filter_unit']) ? esc($conn, trim($_GET['filter_unit'])) : '';
$filter_jab  = isset($_GET['filter_jab'])  ? esc($conn, trim($_GET['filter_jab']))  : '';

// Mapping index kolom DataTables → kolom DB yang aman
$columns = array(
  0 => 'j.id_jab',
  1 => 'j.id_peg',
  2 => 'p.nama',
  3 => 'j.kode_jabatan',
  4 => 'j.jabatan',
  5 => 'k.nama_kantor',   // tampilkan nama kantor
  6 => 'j.tmt_jabatan',
  7 => 'j.sampai_tgl',
  8 => 'j.no_sk',
  9 => 'j.tgl_sk',
  10 => 'j.status_jab'
);

$order_col_index = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 5;
$order_col = isset($columns[$order_col_index]) ? $columns[$order_col_index] : 'k.nama_kantor';
$order_dir = isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir'])==='desc' ? 'DESC' : 'ASC';

$base_from  = " FROM tb_jabatan j ";
$base_from .= " LEFT JOIN tb_pegawai p ON p.id_peg = j.id_peg ";
$base_from .= " LEFT JOIN tb_kantor  k ON k.kode_kantor_detail = j.unit_kerja ";
$base_where = " WHERE j.status_jab='Aktif' ";

// Filter custom (kode atau nama)
if ($filter_unit !== '') {
  if (is_kode($filter_unit)) {
    $base_where .= " AND j.unit_kerja = '".$filter_unit."' ";
  } else {
    $base_where .= " AND k.nama_kantor = '".$filter_unit."' ";
  }
}
if ($filter_jab  !== '') { $base_where .= " AND (j.jabatan = '".$filter_jab."' OR j.kode_jabatan='".$filter_jab."') "; }

// Pencarian global (termasuk nama_kantor)
if ($search !== '') {
  $like = " AND (p.nama LIKE '%$search%' OR j.id_peg LIKE '%$search%' OR j.jabatan LIKE '%$search%' OR j.kode_jabatan LIKE '%$search%' OR k.nama_kantor LIKE '%$search%' OR j.unit_kerja LIKE '%$search%' OR j.no_sk LIKE '%$search%') ";
  $base_where .= $like;
}

// Hitung total dan filtered
$sql_cnt_total = "SELECT COUNT(*) AS c FROM tb_jabatan j WHERE j.status_jab='Aktif'";
$res_total = mysqli_query($conn, $sql_cnt_total);
$row_total = $res_total ? mysqli_fetch_assoc($res_total) : array('c'=>0);
$recordsTotal = (int)$row_total['c'];

$sql_cnt_filtered = "SELECT COUNT(*) AS c $base_from $base_where";
$res_filtered = mysqli_query($conn, $sql_cnt_filtered);
$row_filtered = $res_filtered ? mysqli_fetch_assoc($res_filtered) : array('c'=>0);
$recordsFiltered = (int)$row_filtered['c'];

// Ambil data (tampilkan nama_kantor di kolom unit_kerja)
$sql_data = "SELECT j.id_jab, j.id_peg, p.nama, j.kode_jabatan, j.jabatan,
                    COALESCE(k.nama_kantor, j.unit_kerja) AS unit_kerja,
                    j.tmt_jabatan, j.sampai_tgl, j.no_sk, j.tgl_sk, j.status_jab
             $base_from $base_where
             ORDER BY $order_col $order_dir
             LIMIT $start, $length";
$res = mysqli_query($conn, $sql_data);

$data = array();
if ($res) {
  while($r = mysqli_fetch_assoc($res)){
    $aksi = '<a class="btn btn-xs btn-outline-info" title="Profil Pegawai" href="'.htmlspecialchars(jabatan_ajax_page_url('view-detail-data-pegawai', array('id_peg' => $r['id_peg'])),ENT_QUOTES,'UTF-8').'"><i class="fa fa-user"></i></a>';
    $aksi .= ' <a class="btn btn-xs btn-outline-primary" title="Entry Jabatan Baru" href="'.htmlspecialchars(jabatan_ajax_page_url('form-master-data-jabatan', array('uid' => $r['id_peg'])),ENT_QUOTES,'UTF-8').'"><i class="fa fa-briefcase"></i></a>';

    $data[] = array(
      'id_peg'       => $r['id_peg'],
      'nama'         => $r['nama'],
      'kode_jabatan' => $r['kode_jabatan'],
      'jabatan'      => $r['jabatan'],
      'unit_kerja'   => $r['unit_kerja'], // sudah nama_kantor
      'tmt_jabatan'  => ($r['tmt_jabatan'] && $r['tmt_jabatan']!=='0000-00-00') ? date('d-m-Y', strtotime($r['tmt_jabatan'])) : '-',
      'sampai_tgl'   => ($r['status_jab']==='Aktif') ? 'Sekarang' : (($r['sampai_tgl'] && $r['sampai_tgl']!=='0000-00-00') ? date('d-m-Y', strtotime($r['sampai_tgl'])) : '-'),
      'no_sk'        => $r['no_sk'],
      'tgl_sk'       => ($r['tgl_sk'] && $r['tgl_sk']!=='0000-00-00') ? date('d-m-Y', strtotime($r['tgl_sk'])) : '-',
      'status'       => '<span class="badge badge-success">Aktif</span>',
      'action'       => $aksi
    );
  }
}

echo json_encode(array(
  'draw' => $draw,
  'recordsTotal' => $recordsTotal,
  'recordsFiltered' => $recordsFiltered,
  'data' => $data
));
