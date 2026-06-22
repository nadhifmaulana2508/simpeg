<?php
/*********************************************************
 * FILE     : pages/user/form-view-data-user.php
 * MODULE   : Manajemen User (Supervisor Label Logic)
 *********************************************************/

if (session_id() == '') session_start();
include "dist/koneksi.php";

// Hak Akses Check
if (!isset($_SESSION['hak_akses']) || ($_SESSION['hak_akses'] != 'admin' && $_SESSION['hak_akses'] != 'superadmin')) {
    echo "<script>window.location='home-admin.php';</script>";
    exit;
}
?>

<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<style>
    .user-page {
        padding-top: 0.75rem;
        padding-bottom: 1rem;
    }
    .user-card {
        border: 1px solid rgba(217, 229, 220, 0.95) !important;
        border-radius: 14px !important;
        background: rgba(255,255,255,0.94) !important;
        box-shadow: 0 12px 30px rgba(15, 35, 26, 0.06) !important;
        overflow: hidden;
    }
    .user-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid rgba(217, 229, 220, 0.95);
        background: linear-gradient(180deg, rgba(246,250,247,0.95), rgba(255,255,255,0.95));
    }
    .user-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
    }
    .user-title-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #dff5f2;
        color: #0f766e;
        flex: 0 0 auto;
    }
    .user-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #1e2b24;
        line-height: 1.2;
    }
    .user-subtitle {
        margin: 0.08rem 0 0;
        color: #5d6d64;
        font-size: 0.8rem;
    }
    .btn-user-primary {
        border: 0 !important;
        border-radius: 12px !important;
        padding: 0.55rem 0.85rem !important;
        background: #0f766e !important;
        color: #fff !important;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(15, 118, 110, 0.14);
    }
    .btn-user-primary:hover {
        background: #0b5c56 !important;
        color: #fff !important;
        transform: translateY(-1px);
    }
    .user-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-bottom: 1px solid rgba(217, 229, 220, 0.88);
        background: rgba(246,250,247,0.72);
    }
    .user-filter-group {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
    }
    .user-filter-label {
        display: block;
        margin-bottom: 0;
        color: #5d6d64;
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .user-filter-control,
    .user-page .dataTables_length select,
    .user-page .dataTables_filter input,
    .user-delete-modal textarea {
        min-height: 38px;
        border-radius: 12px !important;
        border: 1px solid #d9e5dc !important;
        background: #fff !important;
        color: #1e2b24 !important;
        box-shadow: none !important;
    }
    .user-page .dataTables-toolbar,
    .user-page .dataTables-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        flex-wrap: wrap;
    }
    .user-page .dataTables_filter label,
    .user-page .dataTables_length label {
        margin-bottom: 0;
        color: #5d6d64;
        font-size: 0.85rem;
        font-weight: 700;
    }
    .user-page .dataTables_filter input {
        width: min(300px, 72vw) !important;
        margin-left: 0.6rem;
        padding: 0.45rem 0.8rem;
    }
    .user-page .dataTables_length select {
        width: 76px;
        margin: 0 0.45rem;
        padding: 0.3rem 0.6rem;
    }
    .user-table {
        margin: 0 !important;
    }
    .user-table.dataTable thead th {
        background: #fbfdfb;
        color: #5d6d64;
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: uppercase;
        border-top: 0 !important;
        border-bottom: 1px solid #d9e5dc !important;
        padding: 0.65rem 0.85rem;
        vertical-align: middle;
    }
    .user-table.dataTable tbody td {
        padding: 0.68rem 0.85rem;
        vertical-align: middle;
        border-bottom: 1px solid rgba(217, 229, 220, 0.72);
        color: #1e2b24;
        font-size: 0.9rem;
    }
    .user-table tbody tr:hover {
        background: rgba(223,245,242,0.28);
    }
    .badge-role,
    .user-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 0.28rem 0.62rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 800;
        border: 1px solid transparent;
    }
    .badge-role-admin { background: #dff5f2; color: #0f766e; border-color: rgba(15,118,110,0.14); }
    .badge-role-kepala { background: #eaf2ff; color: #2563a8; border-color: rgba(63,131,213,0.16); }
    .badge-role-superadmin { background: #fee2e2; color: #b91c1c; border-color: rgba(201,95,90,0.16); }
    .badge-role-user { background: #eef5f0; color: #5d6d64; border-color: #d9e5dc; }
    .user-action-group {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
    }
    .user-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 10px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #d9e5dc !important;
        background: #fff !important;
        box-shadow: none !important;
    }
    .user-action-edit { color: #9a6a13 !important; }
    .user-action-delete { color: #c95f5a !important; }
    .user-delete-modal .modal-content {
        border: 1px solid #d9e5dc !important;
        border-radius: 18px !important;
        overflow: hidden;
    }
    .user-delete-modal .modal-header {
        background: #c95f5a !important;
        border-bottom: 0;
    }
    .user-delete-modal .modal-footer {
        background: #f6faf7 !important;
        border-top: 1px solid #d9e5dc;
    }
    @media (max-width: 767.98px) {
        .user-card-header,
        .user-filter-bar,
        .user-page .dataTables-toolbar,
        .user-page .dataTables-footer {
            align-items: stretch;
            flex-direction: column;
        }
        .user-filter-group,
        .btn-user-primary,
        .user-page .dataTables_filter input {
            width: 100% !important;
        }
        .user-filter-group {
            align-items: stretch;
            flex-direction: column;
            gap: 0.35rem;
        }
        .user-card-header {
            padding: 0.8rem;
        }
        .user-page .dataTables_filter label {
            width: 100%;
        }
        .user-page .dataTables_filter input {
            margin-left: 0;
            margin-top: 0.35rem;
        }
    }
</style>

<section class="content simpeg-page user-page">
    <div class="card user-card">
        <div class="user-card-header">
            <div class="user-title-wrap">
                <span class="user-title-icon"><i class="fas fa-users-cog"></i></span>
                <div>
                    <h5 class="user-title">Manajemen User</h5>
                    <p class="user-subtitle">Kelola akun, role akses, dan status user.</p>
                </div>
            </div>
            <a href="home-admin.php?page=form-master-data-user&mode=create" class="btn btn-user-primary">
                <i class="fas fa-plus mr-1"></i> Tambah User
            </a>
        </div>

        <div class="user-filter-bar">
            <div class="user-filter-group">
                <label class="user-filter-label" for="filter_role">Role Akses</label>
                <select id="filter_role" class="form-control user-filter-control">
                    <option value="">Semua Role</option>
                    <option value="superadmin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="kepala">Kepala / Kabid</option>
                    <option value="user">User</option>
                </select>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table user-table w-100" id="tabelUserAjax">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">No</th>
                            <th width="25%">User</th>
                            <th>Jabatan</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade user-delete-modal" id="modalHapus" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-trash-alt mr-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="close text-white btn-close-modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted">Apakah Anda yakin ingin menonaktifkan user ini?</p>
                <div id="dataSummary" class="alert alert-secondary border-0 small">
                    <i class="fas fa-spinner fa-spin text-primary"></i> Mengambil info data...
                </div>
                <div class="form-group mt-3">
                    <label class="font-weight-bold small text-uppercase text-secondary">Alasan Penghapusan <span class="text-danger">*</span></label>
                    <textarea id="deleteReason" class="form-control" rows="3" placeholder="Contoh: Resign, Mutasi..."></textarea>
                </div>
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light font-weight-bold btn-close-modal">Batal</button>
                <button type="button" class="btn btn-danger font-weight-bold px-4" id="btnConfirmDelete">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelUserAjax').DataTable({
        "processing": true,
        "serverSide": true,
        "ordering": false,
        "autoWidth": false,
        "searchDelay": 650,
        "ajax": {
            "url": "pages/user/ajax-data-user.php",
            "type": "GET",
            "data": function (d) {
                d.role = $('#filter_role').val();
            }
        },
        "columns": [
            { "data": "no", "className": "text-center font-weight-bold" },
            { "data": "user_info" },
            { "data": "jabatan" },
            // MODIFIKASI KOLOM ROLE DI SINI
            { 
                "data": "role", 
                "className": "text-center",
                "render": function(data, type, row) {
                    data = (data || 'user').toString().toLowerCase();
                    if (data === 'kepala') {
                        return '<span class="badge-role badge-role-kepala">Kepala / Kabid</span>';
                    } else if (data === 'admin') {
                        return '<span class="badge-role badge-role-admin">Admin</span>';
                    } else if (data === 'superadmin') {
                        return '<span class="badge-role badge-role-superadmin">Super Admin</span>';
                    } else {
                        return '<span class="badge-role badge-role-user">User</span>';
                    }
                }
            },
            { "data": "status", "className": "text-center" },
            { "data": "aksi", "className": "text-center" }
        ],
        "language": {
            "search": "", "searchPlaceholder": "Cari User / Nama...",
            "zeroRecords": "Tidak ada data ditemukan",
            "info": "Menampilkan _START_ - _END_ dari _TOTAL_",
            "processing": "<div class='spinner-border text-primary spinner-border-sm'></div> Memuat...",
            "paginate": { "next": '<i class="fas fa-chevron-right"></i>', "previous": '<i class="fas fa-chevron-left"></i>' }
        },
        "dom": '<"dataTables-toolbar"lf>rt<"dataTables-footer"ip>'
    });

    var searchTimer = null;
    var $searchInput = $('#tabelUserAjax_filter input');
    $searchInput.off('.DT');
    $searchInput.on('input', function() {
        var keyword = this.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            if (table.search() !== keyword) {
                table.search(keyword).draw();
            }
        }, 650);
    });

    $('#filter_role').change(function(){ table.ajax.reload(); });

    // ... (Sisa script hapus sama persis seperti sebelumnya) ...
    $('body').on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#deleteId').val(id);
        $('#deleteReason').val('');
        $('#dataSummary').html('<i class="fas fa-spinner fa-spin text-primary"></i> Sedang mengambil info data...');
        $('#modalHapus').modal('show');

        $.ajax({
            url: 'pages/user/process_soft_delete_user.php',
            type: 'POST',
            data: { action: 'get_info', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status == 'success') {
                    $('#dataSummary').html('<strong>' + res.data.nama_user + '</strong><br><small>ID: ' + res.data.id_user + '</small>');
                } else {
                    $('#dataSummary').html('<span class="text-danger">' + res.message + '</span>');
                }
            }
        });
    });

    $('body').on('click', '.btn-close-modal', function() { $('#modalHapus').modal('hide'); });

    $('#btnConfirmDelete').click(function() {
        var id = $('#deleteId').val();
        var reason = $.trim($('#deleteReason').val());
        if (reason == '') { Swal.fire({ title: 'Wajib Diisi', text: 'Mohon isi alasan!', icon: 'warning' }); return; }

        var btn = $(this); btn.prop('disabled', true).text('Menghapus...');

        $.ajax({
            url: 'pages/user/process_soft_delete_user.php',
            type: 'POST',
            data: { action: 'delete', id: id, reason: reason },
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).text('Ya, Hapus');
                $('#modalHapus').modal('hide');
                $('.modal-backdrop').remove();
                if (res.status == 'success') {
                    Swal.fire({ icon: 'success', title: 'Terhapus', text: 'User dipindahkan ke Recycle Bin', timer: 1500, showConfirmButton: false });
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            },
            error: function() { btn.prop('disabled', false).text('Ya, Hapus'); Swal.fire('Error', 'Server Error', 'error'); }
        });
    });
});
</script>
