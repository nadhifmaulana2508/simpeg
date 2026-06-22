<?php
/*********************************************************
 * FILE     : pages/pegawai/form-view-data-pegawai.php
 * MODULE   : SIMPEG — Data Pegawai (Smart Filter UI)
 * STATUS   : SECURE & OFFLINE READY
 *********************************************************/

// Pastikan session dimulai
if (session_id() == '') session_start(); 

$hak_akses_user = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
$kode_kantor_session = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';

// Link Kembali
if ($hak_akses_user === 'kepala') {
    $link_back = function_exists('page_url') ? page_url('dashboard-cabang') : "home-admin.php?page=dashboard-cabang";
} else {
    $link_back = function_exists('page_url') ? page_url('dashboard') : "home-admin.php";
}
?>

<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

<style>
    .content-header { display: none !important; }
    .pegawai-page .avatar-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid rgba(255,255,255,0.9);
        box-shadow: 0 8px 18px rgba(15, 35, 26, 0.12);
        background: #eef4ef;
    }
    .pegawai-page .avatar-img { width: 100%; height: 100%; object-fit: cover; }
    .pegawai-page .text-pegawai-name { font-weight: 800; color: #1e2b24; font-size: 0.95rem; display: block; }
    .pegawai-page .text-pegawai-id {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-family: monospace;
        color: #537063;
        font-size: 0.82rem;
        background: #eef5f0;
        padding: 3px 8px;
        border-radius: 999px;
    }
    .pegawai-page .text-jabatan { font-weight: 800; color: #1e2b24; font-size: 0.92rem; display: block; margin-bottom: 0.15rem; }
    .pegawai-page .text-kantor { color: #0f766e; font-weight: 700; font-size: 0.8rem; display: block; }
    .pegawai-page .text-divisi { color: #70837a; font-size: 0.8rem; display: block; }
    .pegawai-page .dt-controls-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .pegawai-page .btn-action-blue,
    .pegawai-page .btn-action-orange {
        width: 36px;
        min-width: 36px;
        height: 36px;
        min-height: 36px;
        border-radius: 12px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 !important;
    }
    .pegawai-page .btn-action-blue {
        background: linear-gradient(135deg, #5e97e1, #3f83d5);
        color: #fff;
    }
    .pegawai-page .btn-action-orange {
        background: linear-gradient(135deg, #f7ca6e, #f3b847);
        color: #2f2514;
    }
    .pegawai-page .card-header {
        gap: 1rem;
    }
    .pegawai-page .card-header .simpeg-toolbar {
        margin-left: auto;
        justify-content: flex-end;
    }
    @media (max-width: 768px) {
        .pegawai-page .dt-controls-wrapper { align-items: stretch; }
        .pegawai-page .dataTables_filter { width: 100%; }
        .pegawai-page .dataTables_filter input { width: 100% !important; margin-left: 0 !important; }
        .pegawai-page .card-header .simpeg-toolbar {
            margin-left: 0;
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>

<section class="content simpeg-page pegawai-page">
  <div class="container-fluid">
    
    <div class="simpeg-page-header">
        <div>
            <h3 class="simpeg-page-title">Data Pegawai</h3>
            <p class="simpeg-page-subtitle">Kelola data pegawai, jabatan, status aktif, dan histori purna dengan tampilan yang lebih konsisten.</p>
        </div>
        <a href="<?= $link_back; ?>" class="btn btn-light border shadow-sm">
            <i class="fa fa-arrow-left mr-2"></i> Kembali
        </a>
    </div>

    <div class="card simpeg-table-card">
      
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
          <ul class="nav nav-pills simpeg-tabset" id="pegawaiTab" role="tablist">
              <li class="nav-item">
                  <a class="nav-link active" id="aktif-tab" data-bs-toggle="pill" href="#aktif" role="tab">
                      <i class="fa fa-users mr-1"></i> Aktif
                  </a>
              </li>

              <?php if ($hak_akses_user === 'admin'): ?>
              <li class="nav-item">
                  <a class="nav-link" id="nonjob-tab" data-bs-toggle="pill" href="#nonjob" role="tab">
                      <i class="fa fa-user-tag mr-1"></i> Belum Ada Jabatan
                  </a>
              </li>
              <?php endif; ?>

              <li class="nav-item">
                  <a class="nav-link" id="purna-tab" data-bs-toggle="pill" href="#purna" role="tab">
                      <i class="fa fa-history mr-1"></i> Purna
                  </a>
              </li>
          </ul>

          <?php if ($hak_akses_user === 'admin'): ?>
          <div class="simpeg-toolbar">
              <a href="home-admin.php?page=form-master-data-pegawai" class="btn btn-primary shadow-sm"><i class="fa fa-plus mr-2"></i> Tambah</a>
              <a href="home-admin.php?page=form-upload-data-pegawai" class="btn btn-light border"><i class="fa fa-file-excel mr-2 text-success"></i> Import Excel</a>
          </div>
          <?php endif; ?>
      </div>

      <div class="card-body p-0">
        <div class="tab-content" id="pegawaiTabContent">

          <div class="tab-pane fade show active" id="aktif" role="tabpanel">
            
            <div class="simpeg-filter-panel">
                 <div class="row g-3">
                    
                    <div class="col-md-4 col-12 mb-3 mb-md-0">
                        <label class="simpeg-filter-label"><i class="fa fa-building mr-1"></i> Kantor / Area</label>
                        <select id="filter_kantor" class="form-control select2">
                            <?php
                                if ($hak_akses_user === 'admin') {
                                    echo '<option value="">-- Semua Kantor --</option>';
                                    $qUnit = mysqli_query($conn, "SELECT * FROM tb_kantor WHERE level IN ('KP','KANWIL','KC') ORDER BY kode_kantor_detail ASC");
                                    while ($u = mysqli_fetch_assoc($qUnit)) {
                                        // [SECURITY] Pakai htmlspecialchars agar aman dari XSS
                                        echo "<option value='".htmlspecialchars($u['kode_kantor_detail'])."'>".htmlspecialchars($u['nama_kantor'])."</option>";
                                    }
                                } 
                                elseif ($hak_akses_user === 'kepala') {
                                    $safe_kantor = mysqli_real_escape_string($conn, $kode_kantor_session);
                                    $qUnit = mysqli_query($conn, "SELECT * FROM tb_kantor WHERE kode_kantor_detail = '$safe_kantor'");
                                    while ($u = mysqli_fetch_assoc($qUnit)) {
                                        echo "<option value='".htmlspecialchars($u['kode_kantor_detail'])."' selected>".htmlspecialchars($u['nama_kantor'])."</option>";
                                    }
                                }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 col-12 mb-3 mb-md-0">
                        <label class="simpeg-filter-label"><i class="fa fa-sitemap mr-1"></i> Divisi / Unit Kerja</label>
                        <select id="filter_divisi" class="form-control select2" disabled>
                            <option value="">-- Pilih Kantor Dulu --</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-12">
                        <label class="simpeg-filter-label"><i class="fa fa-id-badge mr-1"></i> Jabatan</label>
                        <select id="filter_jabatan" class="form-control select2" disabled>
                            <option value="">-- Pilih Unit Dulu --</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablePegawai" class="table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th width="30%">Pegawai</th> 
                            <th>TTL</th>
                            <th width="30%">Jabatan & Unit</th> 
                            <th>Mulai</th>
                            <th>Kontak</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
          </div>
          
          <?php if ($hak_akses_user === 'admin'): ?>
          <div class="tab-pane fade" id="nonjob" role="tabpanel">
             <div class="p-4">
                 <div class="alert alert-warning border-0 shadow-sm rounded-lg d-flex align-items-center mb-0">
                    <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
                    <div>
                        <h6 class="font-weight-bold mb-1">Data Pegawai Non-Jabatan</h6>
                        <span class="small">Pegawai berikut berstatus <b>Aktif</b> namun belum memiliki jabatan. Klik tombol aksi untuk mengatur.</span>
                    </div>
                 </div>
             </div>
             <div class="table-responsive">
                <table id="tableNonJob" class="table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th width="40%">Pegawai</th>
                            <th>Status</th>
                            <th>Kontak</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
             </div>
          </div>
          <?php endif; ?>

          <div class="tab-pane fade" id="purna" role="tabpanel">
            <div class="table-responsive">
                <table id="tablePurna" class="table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th width="35%">Pegawai</th>
                            <th>TTL</th>
                            <th>Jabatan Terakhir</th>
                            <th>Status</th>
                            <th>Tgl Pensiun</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
          </div>

        </div> 
      </div> 
    </div> 
  </div>
</section>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/select2/js/select2.full.min.js"></script>

<script>
$(document).ready(function() {

  $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

  // Fungsi Render Kolom Pegawai (Foto + Nama + ID)
  function renderPegawai(fotoHtml, namaHtml, idHtml) {
      return `<div class="d-flex align-items-center">
                <div class="mr-3">${fotoHtml}</div>
                <div>
                    <span class="text-pegawai-name">${namaHtml}</span>
                    <span class="text-pegawai-id">${idHtml}</span>
                </div>
              </div>`;
  }

  // Fungsi Render Kolom Jabatan
  function renderJabatan(jabatan, kantor, divisi) {
      var j = jabatan ? jabatan : '<span class="text-danger font-italic small">Belum ada jabatan</span>';
      var k = kantor ? kantor : '-';
      var d = divisi ? divisi : ''; 
      
      var html = `<div>
                    <span class="text-jabatan">${j}</span>
                    <span class="text-kantor"><i class="fas fa-building mr-1"></i> ${k}</span>`;
      if(d) { html += `<span class="text-divisi"><i class="fas fa-sitemap mr-1"></i> ${d}</span>`; }
      html += `</div>`;
      return html;
  }

  var dtOptions = {
      processing: true, serverSide: true, autoWidth: false,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      dom: '<"dt-controls-wrapper"lf>rtip',
      language: {
          search: "", searchPlaceholder: "Cari nama/NIP...", lengthMenu: "Tampil _MENU_",
          zeroRecords: "Data tidak ditemukan",
          info: "Menampilkan _START_ - _END_ dari _TOTAL_",
          paginate: { next: '<i class="fas fa-chevron-right"></i>', previous: '<i class="fas fa-chevron-left"></i>' }
      }
  };

  // 1. TABEL PEGAWAI AKTIF
  var tableAktif = $('#tablePegawai').DataTable($.extend({}, dtOptions, {
      ajax: {
          url: 'pages/pegawai/ajax-data-pegawai.php', 
          type: 'GET',
          data: function(d){ 
              d.kantor  = $('#filter_kantor').val();
              d.divisi  = $('#filter_divisi').val();
              d.jabatan = $('#filter_jabatan').val();
          }
      },
      columns: [
          { data: 'nama_teks', render: function(d,t,r) { return renderPegawai(r.nama_foto, r.nama_teks, r.id_peg); } },
          { data: 'ttl', render: function(d){ return `<span style="font-size:0.85rem; color:#64748b;">${d||'-'}</span>`; } },
          { data: 'jabatan', render: function(d,t,r) { return renderJabatan(r.jabatan, r.kantor, r.divisi); } },
          { data: 'tgl_masuk', className: 'text-nowrap', render: function(d){ return `<span class="badge badge-light border text-muted">${d||'-'}</span>`; } },
          { data: 'no_telp', render: function(d){ return `<span style="color:#64748b; font-size:0.9rem;">${d||'-'}</span>`; } },
          { data: 'action', orderable: false, className: "text-center" }
      ]
  }));

  // === CASCADING DROPDOWN LOGIC ===
  $('#filter_kantor').on('change', function(){
      var kodeKantor = $(this).val();
      $('#filter_divisi').html('<option value="">-- Loading... --</option>').prop('disabled', true).trigger('change');
      $('#filter_jabatan').html('<option value="">-- Pilih Unit Dulu --</option>').prop('disabled', true).trigger('change');
      tableAktif.ajax.reload();

      if(kodeKantor) {
          $.ajax({
              url: 'pages/pegawai/ajax-get-options.php', type: 'POST',
              data: { type: 'get_divisi', kode_kantor: kodeKantor },
              success: function(response){ 
                  $('#filter_divisi').html(response).prop('disabled', false).trigger('change');
              }
          });
      } else {
          $('#filter_divisi').html('<option value="">-- Pilih Kantor Dulu --</option>').trigger('change');
      }
  });

  // Trigger otomatis jika user adalah Kepala
  if ($('#filter_kantor').val()) { $('#filter_kantor').trigger('change'); }

  $('#filter_divisi').on('change', function(){
      var divisi = $(this).val();
      var kodeKantor = $('#filter_kantor').val();
      $('#filter_jabatan').html('<option value="">-- Loading... --</option>').prop('disabled', true).trigger('change');
      tableAktif.ajax.reload();

      if(divisi) {
          $.ajax({
              url: 'pages/pegawai/ajax-get-options.php', type: 'POST',
              data: { type: 'get_jabatan', kode_kantor: kodeKantor, divisi: divisi },
              success: function(response){ 
                  $('#filter_jabatan').html(response).prop('disabled', false).trigger('change');
              }
          });
      } else {
          $('#filter_jabatan').html('<option value="">-- Pilih Unit Dulu --</option>').trigger('change');
      }
  });

  $('#filter_jabatan').on('change', function(){ tableAktif.ajax.reload(); });

  // 2. TABEL NONJOB
  <?php if ($hak_akses_user === 'admin'): ?>
  $('#nonjob-tab').on('click', function(){
      if ($.fn.DataTable.isDataTable('#tableNonJob')) return;
      $('#tableNonJob').DataTable($.extend({}, dtOptions, {
          ajax: { url: 'pages/pegawai/ajax-data-pegawai.php', type: 'GET', data: function(d){ d.filter_type = 'nonjob'; } },
          columns: [
              { data: 'nama_teks', render: function(d,t,r) { return renderPegawai(r.nama_foto, r.nama_teks, r.id_peg); } },
              { data: 'status_kepeg', render: function(d){ return `<span class="badge badge-danger px-3 py-2">${d||'Unknown'}</span>`; } },
              { data: 'no_telp' },
              { data: 'action', orderable: false, className: "text-center" }
          ]
      }));
  });
  <?php endif; ?>

  // 3. TABEL PURNA (FIX: Hapus CDN ui-avatars.com)
  $('#purna-tab').on('click', function(){
      if ($.fn.DataTable.isDataTable('#tablePurna')) return;
      $('#tablePurna').DataTable($.extend({}, dtOptions, {
          ajax: { url: 'pages/pegawai/ajax-pegawai-purna.php', type: 'GET' },
          columns: [
              { data: 'nama', render: function(d,t,r) { 
                  // [OFFLINE FIX] Gunakan avatar lokal default
                  var fotoUrl = 'dist/img/avatar5.png';
                  // Jika ada data foto dari DB, bisa ditambahkan logic di sini:
                  // if(r.foto) fotoUrl = 'pages/assets/foto/'+r.foto;
                  
                  var foto = '<div class="avatar-wrapper"><img src="'+fotoUrl+'" class="avatar-img"></div>';
                  return renderPegawai(foto, r.nama, r.id_peg);
              }},
              { data: 'ttl' }, { data: 'jabatan' }, 
              { data: 'status_kepeg', render: function(d){ return `<span class="badge badge-secondary">${d}</span>`; } }, 
              { data: 'tgl_pensiun' }
          ]
      }));
  });

});
</script>
