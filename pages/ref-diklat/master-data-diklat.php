<?php
/*********************************************************
 * FILE    : pages/diklat/master-data-diklat.php
 * MODULE  : Daftar Diklat (Final Fix: Admin Only Actions)
 *********************************************************/

// Session & Koneksi
if (session_id() == '') session_start();
include "dist/koneksi.php";

// 1. CEK HAK AKSES (Strict Mode)
// Pastikan huruf kecil semua dan trim spasi agar akurat
$hak_akses   = isset($_SESSION['hak_akses']) ? strtolower(trim($_SESSION['hak_akses'])) : 'user';
$kode_kantor = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';

// Definisi Admin: Hanya 'admin' dan 'superadmin' yang punya akses penuh
$is_admin    = ($hak_akses == 'admin' || $hak_akses == 'superadmin');
$is_kepala   = ($hak_akses == 'kepala');

// Filter Default
$tahun_default = date('Y');

// Query Dropdown Filter
$qTahun  = mysqli_query($conn, "SELECT DISTINCT tahun FROM tb_diklat WHERE tahun != '' ORDER BY tahun DESC");
$qDiklat = mysqli_query($conn, "SELECT DISTINCT diklat FROM tb_diklat WHERE tahun = '$tahun_default' ORDER BY diklat ASC");
$qKantor = mysqli_query($conn, "SELECT * FROM tb_kantor WHERE level IN ('KC','KP') ORDER BY nama_kantor ASC");
?>

<style>
    .content-wrapper { background-color: #f8f9fa; }
    .card-clean { border: 1px solid #e3e6f0; border-radius: 16px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06); background: #fff; overflow: hidden; }
    .card-header-clean { background-color: #fff; border-bottom: 1px solid #f1f3f9; padding: 20px 25px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .title-text { font-size: 1.25rem; font-weight: 700; color: #2e343a; margin: 0; }
    .subtitle-text { font-size: 0.85rem; color: #858796; margin-top: 4px; display: block; }
    
    /* Tombol Custom */
    .header-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; }
    .btn-custom-import { background-color: #1cc88a; border: none; color: white; padding: 9px 16px; border-radius: 10px; font-weight: 600; font-size: 0.9rem; }
    .btn-custom-export { background-color: #0f766e; border: none; color: white; padding: 9px 16px; border-radius: 10px; font-weight: 600; font-size: 0.9rem; }
    .btn-custom-add { background-color: #4e73df; border: none; color: white; padding: 9px 16px; border-radius: 10px; font-weight: 600; font-size: 0.9rem; }
    .btn-custom-import:hover { background-color: #17a673; color: white; }
    .btn-custom-export:hover { background-color: #115e59; color: white; }
    .btn-custom-add:hover { background-color: #2e59d9; color: white; }
    
    .label-filter { font-size: 0.7rem; font-weight: 700; color: #b7b9cc; text-transform: uppercase; margin-bottom: 5px; display: block; letter-spacing: 0.5px; }
    .form-control-clean { border-radius: 6px; height: 38px; border: 1px solid #d1d3e2; font-size: 0.85rem; color: #6e707e; }
    .filters-panel { padding: 18px; border: 1px solid #eef2f7; border-radius: 14px; background: #fbfdff; margin-bottom: 16px; }
    
    /* Table Styling */
    table.dataTable thead th { background-color: #fff; color: #5a5c69; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid #e3e6f0 !important; padding: 15px !important; }
    table.dataTable tbody td { padding: 12px 15px !important; vertical-align: middle; font-size: 0.9rem; color: #5a5c69; border-top: 1px solid #f1f3f9; }
    
    /* DataTables Controls Hidden Default Search */
    .dataTables_wrapper .dataTables_filter { display: none; } 
    .table-responsive { overflow-x: auto; }
    #customSearch { min-width: 100%; }
    
    @media (max-width: 768px) {
        .content.pt-4 { padding-top: 1rem !important; }
        .content.px-3 { padding-left: 0.85rem !important; padding-right: 0.85rem !important; }
        .card-header-clean { padding: 16px; flex-direction: column; align-items: flex-start; }
        .card-body { padding: 14px; }
        .header-actions { width: 100%; margin-top: 4px; display: grid; grid-template-columns: 1fr; gap: 8px; }
        .btn-custom-import, .btn-custom-export, .btn-custom-add { width: 100%; text-align: center; font-size: 0.82rem; }
        .filters-panel { padding: 14px; }
        .row.mb-3.align-items-end > [class*='col-'] { margin-bottom: 10px; }
        .title-text { font-size: 1.1rem; }
        .subtitle-text { font-size: 0.8rem; }
        table.dataTable thead th, table.dataTable tbody td { padding: 12px !important; }
    }
</style>

<div class="content pt-4 px-3">
    <div class="card card-clean mb-4">
        <div class="card-header-clean">
            <div>
                <div class="d-flex align-items-center">
                    <i class="fas fa-graduation-cap text-primary fa-lg mr-2"></i>
                    <h5 class="title-text">Daftar Pelatihan & Diklat</h5>
                </div>
                <span class="subtitle-text pl-1">Menampilkan seluruh data riwayat pelatihan pegawai.</span>
            </div>
            <div class="header-actions">
                
                <?php if($is_admin): ?>
                <a href="home-admin.php?page=form-import-data-diklat" class="btn btn-custom-import shadow-sm"><i class="fas fa-file-excel mr-1"></i> Import</a>
                <a href="pages/ref-diklat/export-data-diklat.php?type=excel" id="btnExportDiklat" class="btn btn-custom-export shadow-sm" target="_blank"><i class="fas fa-download mr-1"></i> Download Excel</a>
                <a href="home-admin.php?page=form-diklat" class="btn btn-custom-add shadow-sm"><i class="fas fa-plus mr-1"></i> Tambah Data</a>
                <?php endif; ?>
                
            </div>
        </div>

        <div class="card-body">
            <div class="row align-items-end filters-panel">
                <div class="col-6 col-md-2 mb-2">
                    <span class="label-filter">Tahun</span>
                    <select id="filter_tahun" class="form-control form-control-clean select2bs4">
                        <option value="">- Semua -</option>
                        <?php while ($t = mysqli_fetch_assoc($qTahun)) { ?>
                            <option value="<?= $t['tahun'] ?>" <?= ($tahun_default == $t['tahun']) ? 'selected' : '' ?>><?= $t['tahun'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <span class="label-filter">Jenis Diklat</span>
                    <select id="filter_diklat" class="form-control form-control-clean select2bs4">
                        <option value="">- Semua Jenis -</option>
                        <?php while ($d = mysqli_fetch_assoc($qDiklat)) { ?>
                            <option value="<?= $d['diklat'] ?>"><?= $d['diklat'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <span class="label-filter">Unit Kerja</span>
                    <select id="filter_kantor" class="form-control form-control-clean select2bs4" <?= $is_kepala ? 'disabled' : '' ?>>
                        <option value="">- Semua Kantor -</option>
                        <?php while ($k = mysqli_fetch_assoc($qKantor)) { ?>
                            <option value="<?= $k['kode_kantor_detail'] ?>" <?= ($kode_kantor == $k['kode_kantor_detail']) ? 'selected' : '' ?>><?= $k['nama_kantor'] ?></option>
                        <?php } ?>
                    </select>
                    <?php if($is_kepala): ?><input type="hidden" id="hidden_kantor" value="<?= $kode_kantor ?>"><?php endif; ?>
                </div>
                <div class="col-12 col-md-3 mb-2">
                    <span class="label-filter">Pencarian</span>
                    <input type="text" id="customSearch" class="form-control form-control-clean" placeholder="Cari Nama Pegawai / Diklat...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table w-100" id="tabelDiklatAjax">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">No</th>
                            <th>Nama Pegawai</th>
                            <th>Jenis Diklat</th>
                            <th>Penyelenggara</th>
                            <th>Kode Cabang / Jabatan</th>
                            <th>Tahun</th>
                            
                            <?php if($is_admin): ?>
                            <th class="text-center" width="8%">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if($is_admin): ?>
<div class="modal fade" id="modalHapus" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="close text-white btn-close-modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus data ini ke <b>Recycle Bin</b>?</p>
                <div id="dataSummary" class="alert alert-light border small">
                    <i class="fas fa-spinner fa-spin text-primary"></i> Mengambil info data...
                </div>
                <div class="form-group mt-3">
                    <label class="font-weight-bold small text-uppercase text-secondary">Alasan Penghapusan <span class="text-danger">*</span></label>
                    <textarea id="deleteReason" class="form-control" rows="3" placeholder="Contoh: Duplikat, Salah Input..."></textarea>
                </div>
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2bs4').select2({ theme: 'bootstrap4', width: '100%' });

    function getSelectedKantor() {
        var kantorVal = $('#filter_kantor').val();
        if(!kantorVal && $('#hidden_kantor').length) kantorVal = $('#hidden_kantor').val();
        return kantorVal || '';
    }

    function updateExportLink() {
        var params = new URLSearchParams();
        params.set('type', 'excel');

        var tahun = $('#filter_tahun').val();
        var diklat = $('#filter_diklat').val();
        var kantor = getSelectedKantor();
        var search = $('#customSearch').val();

        if (tahun) params.set('tahun', tahun);
        if (diklat) params.set('diklat', diklat);
        if (kantor) params.set('kantor', kantor);
        if (search) params.set('search', search);

        $('#btnExportDiklat').attr('href', 'pages/ref-diklat/export-data-diklat.php?' + params.toString());
    }

    var table = $('#tabelDiklatAjax').DataTable({
        "processing": true,
        "serverSide": true,
        "ordering": false, // Matikan sorting server-side sementara agar query simple
        "ajax": {
            "url": "pages/ref-diklat/ajax-data-diklat.php",
            "type": "GET",
            "data": function (d) {
                d.tahun  = $('#filter_tahun').val();
                d.diklat = $('#filter_diklat').val();
                d.kantor = getSelectedKantor();
            }
        },
        "columns": [
            { "data": "no", "className": "text-center font-weight-bold" },
            { "data": "nama_peg" },
            { "data": "diklat" },
            { "data": "penyelenggara" },
            { "data": "unit_kerja" },
            { "data": "tahun", "className": "text-center" },
            
            // LOGIC KOLOM AKSI (HANYA RENDER JIKA ADMIN)
            <?php if($is_admin): ?>
            { "data": "aksi", "className": "text-center" }
            <?php endif; ?>
        ],
        "language": {
            "search": "", 
            "zeroRecords": "Data tidak ditemukan",
            "processing": "<div class='spinner-border text-primary' role='status'><span class='sr-only'>Loading...</span></div>",
            "info": "Hal _PAGE_ dari _PAGES_",
            "infoEmpty": "Kosong"
        },
        "dom": "rtip" // Hilangkan search bawaan, pakai custom search
    });

    // Custom Search
    $('#customSearch').on('keyup', function() { 
        table.search(this.value).draw();
        updateExportLink();
    });
    
    // Auto Filter Change
    $('#filter_diklat, #filter_kantor').change(function(){ 
        table.ajax.reload();
        updateExportLink();
    });

    // Dinamis Tahun -> Dropdown Diklat
    $('#filter_tahun').change(function(){
        var tahunDipilih = $(this).val();
        $('#filter_diklat').prop('disabled', true).html('<option>Loading...</option>');
        $.ajax({
            url: 'pages/ref-diklat/ajax-get-jenis.php',
            type: 'POST',
            data: { tahun: tahunDipilih },
            success: function(response){
                $('#filter_diklat').html(response).prop('disabled', false);
                table.ajax.reload();
                updateExportLink();
            }
        });
    });

    updateExportLink();

    // --- LOGIC HAPUS (HANYA AKTIF JIKA ADMIN) ---
    <?php if($is_admin): ?>
    
    // Fungsi Tutup Modal Manual (Fix Stuck)
    function closeModalHapus() {
        $('#modalHapus').modal('hide');
        $('.modal-backdrop').remove(); // Hapus layar hitam paksa
        $('body').removeClass('modal-open');
    }

    $('body').on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#deleteId').val(id);
        $('#deleteReason').val('');
        $('#dataSummary').html('<i class="fas fa-spinner fa-spin text-primary"></i> Sedang mengambil data...');
        $('#modalHapus').modal('show');

        // Ajax Get Info
        $.ajax({
            url: 'pages/ref-diklat/process_soft_delete.php',
            type: 'POST',
            data: { action: 'get_info', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status == 'success') {
                    $('#dataSummary').html(
                        '<b>Diklat:</b> ' + res.data.diklat + '<br>' +
                        '<b>Pegawai:</b> ' + res.data.nama_peg + '<br>' +
                        '<b>Tahun:</b> ' + res.data.tahun
                    );
                } else {
                    $('#dataSummary').html('<span class="text-danger">Gagal info data.</span>');
                }
            }
        });
    });

    // Event Tutup Modal (Tombol X dan Batal)
    $('body').on('click', '.btn-close-modal', function() {
        closeModalHapus();
    });

    $('#btnConfirmDelete').click(function() {
        var id = $('#deleteId').val();
        var reason = $.trim($('#deleteReason').val());

        if (reason == '') { Swal.fire('Warning', 'Isi alasan hapus!', 'warning'); return; }

        var btn = $(this);
        btn.prop('disabled', true).text('Menghapus...');

        $.ajax({
            url: 'pages/ref-diklat/process_soft_delete.php',
            type: 'POST',
            data: { action: 'delete', id: id, reason: reason },
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).text('Ya, Hapus');
                closeModalHapus(); // Tutup modal bersih

                if (res.status == 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data masuk Recycle Bin', timer: 1500, showConfirmButton: false });
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Ya, Hapus');
                Swal.fire('Error', 'Server Error', 'error');
            }
        });
    });
    <?php endif; ?>
});
</script>
