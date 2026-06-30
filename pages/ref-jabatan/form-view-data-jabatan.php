<?php
/*********************************************************
 * FILE    : pages/ref-jabatan/form-view-data-jabatan.php
 * MODULE  : SIMPEG — Daftar Jabatan Aktif (Modern UI + Date Fix)
 * VERSION : v2.1 (Fix Invalid Date)
 *********************************************************/

if (session_id()==='') session_start();
@include_once __DIR__ . '/../../dist/koneksi.php';
@include_once __DIR__ . '/../../dist/functions.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; $conn = isset($koneksi)?$koneksi:null; }
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function jabatan_page_url($page, $params = array()) {
    if (function_exists('page_url')) {
        return page_url($page, $params);
    }

    $url = 'home-admin.php?page=' . rawurlencode($page);
    if (!empty($params)) {
        $url .= '&' . http_build_query($params);
    }
    return $url;
}

// ====== 1. Ambil Data untuk Dropdown Filter (Unit & Jabatan) ======
$units = [];
$jabs = [];

if($conn){
    // Ambil Unit Kerja
    $qUnit = mysqli_query($conn, "SELECT DISTINCT k.kode_kantor_detail, k.nama_kantor FROM tb_jabatan j LEFT JOIN tb_kantor k ON k.kode_kantor_detail=j.unit_kerja WHERE j.status_jab='Aktif' AND k.nama_kantor IS NOT NULL ORDER BY k.nama_kantor");
    if($qUnit) while($r=mysqli_fetch_assoc($qUnit)) $units[] = $r['nama_kantor'];
    
    // Ambil Nama Jabatan
    $qJab = mysqli_query($conn, "SELECT DISTINCT jabatan FROM tb_jabatan WHERE status_jab='Aktif' AND jabatan IS NOT NULL AND jabatan<>'' ORDER BY jabatan");
    if($qJab) while($r=mysqli_fetch_assoc($qJab)) $jabs[] = $r['jabatan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Data Jabatan Aktif</title>
  
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

  <style>
    body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; color: #343a40; }
    
    /* Modern Card */
    .card-modern { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: #fff; margin-bottom: 20px; overflow: hidden; }
    .card-header-modern { background: #fff; border-bottom: 1px solid #f0f2f5; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    
    /* Typography */
    .page-title { font-size: 1.1rem; font-weight: 700; color: #111827; margin: 0; }
    .page-subtitle { font-size: 0.85rem; color: #6b7280; margin-top: 4px; }
    
    /* Table Styling */
    .table thead th { background-color: #f9fafb; color: #374151; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb !important; padding: 12px 15px; }
    .table tbody td { vertical-align: middle; padding: 12px 15px; font-size: 0.9rem; border-bottom: 1px solid #f3f4f6; }
    
    /* Buttons & Badges */
    .btn-custom { border-radius: 8px; font-weight: 500; font-size: 0.875rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; text-decoration: none; }
    .btn-primary-soft { background: #4f46e5; color: white; border: none; } .btn-primary-soft:hover { background: #4338ca; color: white; transform: translateY(-1px); }
    .btn-success-soft { background: #10b981; color: white; border: none; } .btn-success-soft:hover { background: #059669; color: white; transform: translateY(-1px); }
    .btn-light-soft { background: #fff; border: 1px solid #d1d5db; color: #374151; } .btn-light-soft:hover { background: #f9fafb; border-color: #9ca3af; }
    
    .badge-soft-success { background-color: #d1fae5; color: #065f46; padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    
    /* Filter Bar */
    .filter-wrapper { background: #f8fafc; border-bottom: 1px solid #edf2f7; padding: 15px 25px; }
    .form-label-sm { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 5px; display: block; }
  </style>
</head>
<body>

<?php
  $detailBaseUrl = jabatan_page_url('view-detail-data-pegawai');
  $manageBaseUrl = jabatan_page_url('form-master-data-jabatan');
  $createHistoryUrl = jabatan_page_url('form-master-create-history-jabatan');
  $homeUrl = jabatan_page_url('dashboard');
?>

<div class="container-fluid py-4">
  <div class="card card-modern">
    
    <div class="card-header-modern">
      <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="page-title"><i class="fas fa-briefcase text-primary me-2"></i>Daftar Jabatan Aktif</h4>
        </div>
        <div class="page-subtitle">Menampilkan seluruh jabatan pegawai yang berstatus Aktif saat ini.</div>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= e($homeUrl) ?>" class="btn-custom btn-light-soft"><i class="fas fa-home"></i></a>
        <a href="<?= e(jabatan_page_url('form-import-jabatan')) ?>" class="btn-custom btn-success-soft"><i class="fas fa-file-excel"></i> Import</a>
        <a href="<?= e($createHistoryUrl) ?>" class="btn-custom btn-primary-soft"><i class="fas fa-plus"></i> Tambah Data</a>
      </div>
    </div>

    <div class="filter-wrapper">
        <div class="row g-3">
            <div class="col-md-4">
                <span class="form-label-sm">Filter Unit Kerja</span>
                <select id="filterUnit" class="form-select select2">
                    <option value="">Semua Unit Kerja</option>
                    <?php foreach($units as $u): ?>
                        <option value="<?= e($u) ?>"><?= e($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <span class="form-label-sm">Filter Nama Jabatan</span>
                <select id="filterJabatan" class="form-select select2">
                    <option value="">Semua Jabatan</option>
                    <?php foreach($jabs as $j): ?>
                        <option value="<?= e($j) ?>"><?= e($j) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="tblJabatan" class="table table-hover w-100">
          <thead>
            <tr>
              <th width="5%" class="text-center">No</th>
              <th width="25%">Nama Pegawai</th>
              <th width="20%">Jabatan</th>
              <th width="15%">Unit Kerja</th>
              <th>Masa Jabatan (TMT)</th>
              <th>No. SK & Tanggal</th>
              <th>Status</th>
              <th width="10%" class="text-center">Aksi</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function(){
  var detailBaseUrl = <?= json_encode($detailBaseUrl) ?>;
  var manageBaseUrl = <?= json_encode($manageBaseUrl) ?>;
  
  // 1. Inisialisasi Select2
  $('.select2').select2({
    theme: 'bootstrap-5',
    width: '100%',
    allowClear: true,
    placeholder: 'Pilih...'
  });

  // 2. Helper Function untuk Format Tanggal yang Aman
  // Fungsi ini mencegah "Invalid Date"
  function formatTglIndo(dateStr) {
      if (!dateStr || dateStr === '0000-00-00' || dateStr === 'null' || dateStr === '') {
          return '-';
      }
      var d = new Date(dateStr);
      // Validasi apakah d adalah tanggal yang sah
      if (isNaN(d.getTime())) return '-';
      
      // Format ke ID (dd-mm-yyyy)
      return d.toLocaleDateString('id-ID', {
          day: '2-digit', month: '2-digit', year: 'numeric'
      }).replace(/\//g, '-'); 
  }

  // 3. Inisialisasi DataTables Server-Side
  var table = $('#tblJabatan').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: 'pages/ref-jabatan/ajax-jabatan-aktif.php',
      type: 'GET',
      data: function(d){
        d.filter_unit = $('#filterUnit').val();
        d.filter_jab  = $('#filterJabatan').val();
      }
    },
    columns: [
      { 
        data: null, orderable:false, className: 'text-center fw-bold text-secondary', 
        render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } 
      },
      
      { 
        data: 'nama', 
        render: function(data, type, row) {
             var nama = data || 'Tanpa Nama';
             var id = row.id_peg || row.nip || '-';
             return '<div class="fw-bold"><a href="' + detailBaseUrl + '?id_peg=' + encodeURIComponent(id) + '" class="text-dark text-decoration-none">'+nama+'</a></div><small class="text-muted"><i class="fas fa-id-card me-1"></i>'+id+'</small>';
        }
      },

      { 
        data: 'jabatan',
        className: 'text-primary fw-medium',
        render: function(data, type, row) {
            return data + (row.kode_jabatan ? ' <span class="badge bg-light text-dark border">'+row.kode_jabatan+'</span>' : '');
        }
      },

      { data: 'unit_kerja', defaultContent: '-' },

      // KOLOM TMT (DIPERBAIKI)
      { 
        data: 'tmt_jabatan',
        render: function(data, type, row) {
            var tmt = formatTglIndo(data);
            var smp = formatTglIndo(row.sampai_tgl);
            
            // Logika untuk menampilkan tanggal sampai
            if(smp !== '-' && !smp.includes('1970')) {
                return '<div>'+tmt+'</div><small class="text-danger">s.d '+smp+'</small>';
            }
            return tmt;
        }
      },

      // KOLOM SK (DIPERBAIKI)
      { 
        data: 'no_sk',
        render: function(data, type, row) {
            var no = data || '-';
            var tgl = formatTglIndo(row.tgl_sk);
            return '<div>'+no+'</div><small class="text-muted">'+tgl+'</small>';
        }
      },

      { 
        data: 'status', 
        className: 'text-center',
        render: function(data) {
            return '<span class="badge badge-soft-success">Aktif</span>';
        }
      },

      { 
        data: null, orderable: false, className: 'text-center',
        render: function(data, type, row) {
            var detailUrl = detailBaseUrl + '?id_peg=' + encodeURIComponent(row.id_peg);
            var manageUrl = manageBaseUrl + '?uid=' + encodeURIComponent(row.id_peg);
            return `
              <div class="btn-group" role="group">
                <a href="${detailUrl}" class="btn btn-sm btn-light-soft" title="Lihat Profil"><i class="fas fa-user"></i></a>
                <a href="${manageUrl}" class="btn btn-sm btn-light-soft text-primary" title="Mutasi/Edit"><i class="fas fa-pencil-alt"></i></a>
              </div>
            `;
        }
      }
    ],
    language: {
      search: "",
      searchPlaceholder: "Cari pegawai atau jabatan...",
      processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"></div> Memuat...'
    },
    dom: "<'row px-3 pt-3 align-items-center'<'col-6 col-md-6'l><'col-6 col-md-6'f>>" + "<'row px-3'<'col-sm-12'tr>>" + "<'row px-3 pb-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
  });

  // Reload table saat filter berubah
  $('#filterUnit, #filterJabatan').on('change', function(){
      table.ajax.reload();
  });

});
</script>
</body>
</html>
