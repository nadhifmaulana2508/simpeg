<?php
/*********************************************************
 * FILE    : pages/report/laporan-nominatif-pegawai.php
 * MODULE  : View Laporan Nominatif Pegawai
 *********************************************************/

include "dist/koneksi.php";

$hak_akses = isset($_SESSION['hak_akses']) ? $_SESSION['hak_akses'] : '';
$kode_kantor_user = isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '';
$is_kepala = ($hak_akses == 'kepala');

$opt_kantor = "";
if ($is_kepala) {
    $qKantor = mysqli_query($conn, "SELECT kode_kantor_detail, nama_kantor FROM tb_kantor WHERE kode_kantor_detail = '$kode_kantor_user'");
} else {
    $qKantor = mysqli_query($conn, "SELECT kode_kantor_detail, nama_kantor FROM tb_kantor WHERE level IN ('KP', 'KANWIL', 'KC') ORDER BY kode_kantor_detail ASC");
}
while ($k = mysqli_fetch_assoc($qKantor)) {
    $sel = ($is_kepala) ? 'selected' : '';
    $opt_kantor .= "<option value='" . $k['kode_kantor_detail'] . "' $sel>" . $k['nama_kantor'] . "</option>";
}

$opt_status = "";
$qStatus = mysqli_query($conn, "SELECT DISTINCT status_kepeg FROM tb_pegawai WHERE status_aktif=1 ORDER BY status_kepeg ASC");
while ($s = mysqli_fetch_assoc($qStatus)) {
    $opt_status .= "<option value='" . $s['status_kepeg'] . "'>" . $s['status_kepeg'] . "</option>";
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f7f2;
        color: #16302b;
    }

    .report-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 10px;
    }

    .report-title-wrap {
        flex: 1 1 auto;
        min-width: 0;
    }

    .page-title {
        font-weight: 800;
        color: #16302b;
        font-size: 2rem;
        line-height: 1.15;
        margin-bottom: 0.35rem;
    }

    .page-subtitle {
        color: #60756f;
        font-size: 0.97rem;
        margin-bottom: 0;
    }

    .header-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex: 0 0 auto;
        padding: 10px 12px;
        border: 1px solid #dfe9e4;
        border-radius: 18px;
        background: rgba(255,255,255,0.88);
        box-shadow: 0 10px 24px rgba(22, 48, 43, 0.05);
    }

    .header-control-host {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 8px 10px;
    }

    .header-control-host .dataTables_length,
    .header-control-host .dataTables_filter {
        margin: 0;
    }

    .header-control-host .dataTables_length label,
    .header-control-host .dataTables_filter label {
        margin: 0;
        font-size: 0.86rem;
        color: #60756f;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
    }

    .header-control-host .dataTables_length select {
        min-width: 58px;
        height: 38px;
        border-radius: 11px;
        border: 1px solid #d7e4de;
        background: #fbfdfc;
        padding: 4px 8px;
        color: #17312a;
        box-shadow: none;
    }

    .header-control-host .dataTables_filter input {
        width: 260px !important;
        height: 40px;
        border-radius: 12px;
        border: 1px solid #d7e4de;
        padding: 9px 14px;
        background: #fbfdfc;
        color: #17312a;
        margin-left: 0;
        box-shadow: none;
    }

    .header-control-host .dataTables_filter input:focus,
    .header-control-host .dataTables_length select:focus {
        outline: none;
        border-color: #0f8f73;
        box-shadow: 0 0 0 3px rgba(15, 143, 115, 0.12);
    }

    .report-icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        border: 1px solid #d8e7e0;
        background: #ffffff;
        color: #1f5c4f;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: all 0.2s ease;
        box-shadow: 0 10px 18px rgba(22, 48, 43, 0.06);
    }

    .report-icon-btn:hover {
        transform: translateY(-1px);
        color: #ffffff;
        background: linear-gradient(135deg, #0f8f73 0%, #18b886 100%);
        border-color: transparent;
    }

    .report-icon-btn.pdf:hover {
        background: linear-gradient(135deg, #ca3c3c 0%, #ef4444 100%);
    }

    .card-modern {
        border: 1px solid rgba(16, 109, 91, 0.08);
        border-radius: 22px;
        box-shadow: 0 18px 42px rgba(22, 48, 43, 0.08);
        background: #fff;
        overflow: hidden;
    }

    .filter-panel {
        padding: 14px 18px !important;
        background: linear-gradient(180deg, rgba(245, 250, 247, 0.92) 0%, #ffffff 100%);
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        align-items: stretch;
    }

    .filter-col {
        min-width: 0;
        max-width: none;
    }

    .filter-group {
        background: #ffffff;
        border: 1px solid #dbe7e2;
        border-radius: 16px;
        padding: 12px;
        height: 100%;
        box-shadow: 0 8px 18px rgba(22, 48, 43, 0.03);
    }

    .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #6f7e79;
        margin-bottom: 6px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .form-control,
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 44px;
        border-radius: 12px !important;
        border: 1px solid #cddbd5 !important;
        background: #fbfdfc !important;
        box-shadow: none !important;
        font-size: 0.92rem;
        color: #1f352f;
    }

    .select2-container--bootstrap-5 .select2-selection {
        padding: 6px 12px 0 !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #1f352f;
        line-height: 1.7;
        padding-left: 0 !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        right: 12px;
        top: 11px;
    }

    .table-shell {
        padding: 0 14px 0;
        background: #fff;
    }

    .table-responsive {
        padding: 0;
    }

    .table-scroll-wrap {
        max-height: calc(100vh - 248px);
        overflow: auto;
        border-top: 1px solid #e5ece8;
        position: relative;
        scrollbar-width: thin;
        border-radius: 16px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
    }

    #nominatifTableAjax {
        border-collapse: separate !important;
        border-spacing: 0;
        width: 100% !important;
    }

    table.dataTable thead th {
        background: linear-gradient(135deg, #1d6357 0%, #245d8a 100%);
        color: #ffffff !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.76rem;
        letter-spacing: 0.04em;
        border: none;
        padding: 12px 14px !important;
        white-space: nowrap;
        vertical-align: middle !important;
        position: sticky;
        top: 0;
        z-index: 30;
        box-shadow: 0 2px 0 rgba(255,255,255,0.06), 0 6px 18px rgba(15, 49, 42, 0.12);
    }

    table.dataTable thead th:first-child {
        border-top-left-radius: 16px;
    }

    table.dataTable thead th:last-child {
        border-top-right-radius: 16px;
    }

    table.dataTable tbody td {
        padding: 14px !important;
        vertical-align: middle;
        color: #28413b;
        border-bottom: 1px solid #edf3f0;
        font-size: 0.9rem;
        background: #fff;
    }

    table.dataTable tbody tr:hover td {
        background-color: #f5fbf8;
    }

    .table-footer {
        padding: 10px 14px 14px;
    }

    .dataTables_info,
    .dataTables_paginate {
        font-size: 0.9rem;
        color: #60756f;
        margin-bottom: 0;
    }

    .dataTables_paginate .paginate_button {
        border-radius: 12px !important;
        margin-left: 6px;
        border: 1px solid #d7e4de !important;
        background: #fff !important;
        color: #21433a !important;
    }

    .dataTables_paginate .paginate_button.current,
    .dataTables_paginate .paginate_button.current:hover {
        background: linear-gradient(135deg, #0f8f73 0%, #18b886 100%) !important;
        border-color: transparent !important;
        color: #fff !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f1f8f5 !important;
        color: #17312a !important;
    }

    .table-empty {
        padding: 56px 18px;
        color: #7a8a85;
    }

    .table-empty i {
        font-size: 3rem;
        opacity: 0.25;
        margin-bottom: 14px;
    }

    @media (max-width: 992px) {
        .report-header {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions {
            justify-content: flex-start;
        }

        .header-control-host {
            justify-content: flex-start;
        }

        .filter-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .page-title {
            font-size: 1.55rem;
        }

        .page-subtitle {
            font-size: 0.88rem;
        }

        .card-modern {
            border-radius: 18px;
        }

        .filter-panel {
            padding: 12px !important;
        }

        .filter-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .filter-col {
            flex: 1 1 100%;
            max-width: none;
        }

        .filter-group {
            padding: 11px;
            border-radius: 16px;
        }

        .table-shell {
            padding: 0 10px 0;
        }

        .table-scroll-wrap {
            max-height: calc(100vh - 330px);
        }

        .header-control-host {
            width: 100%;
            gap: 10px;
        }

        .header-control-host .dataTables_filter,
        .header-control-host .dataTables_filter label,
        .header-control-host .dataTables_filter input {
            width: 100% !important;
        }

        table.dataTable thead th,
        table.dataTable tbody td {
            padding: 10px !important;
            font-size: 0.82rem;
        }

        .table-footer {
            padding: 12px;
        }

        .dataTables_info,
        .dataTables_paginate {
            text-align: left !important;
        }
    }
</style>

<section class="content-header pt-4 pb-3">
    <div class="container-fluid">
        <div class="report-header">
            <div class="report-title-wrap">
                <h1 class="page-title">Laporan Nominatif Pegawai</h1>
                <p class="page-subtitle">Rekapitulasi data pegawai aktif per unit kerja.</p>
            </div>
            <div class="header-actions">
                <div id="nominatifHeaderControls" class="header-control-host"></div>
                <button type="button" id="exportExcelBtn" class="report-icon-btn" title="Export Excel" aria-label="Export Excel">
                    <i class="fas fa-file-excel"></i>
                </button>
                <button type="button" id="exportPdfBtn" class="report-icon-btn pdf" title="Export PDF" aria-label="Export PDF">
                    <i class="fas fa-file-pdf"></i>
                </button>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card card-modern">
            <div class="card-body filter-panel border-bottom">
                <div class="filter-row">
                    <div class="filter-col">
                        <div class="filter-group">
                            <span class="filter-label">Status Pegawai</span>
                            <select id="filter_status" class="form-control select2-status">
                                <option value="">-- Semua Status --</option>
                                <?= $opt_status ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-col">
                        <div class="filter-group">
                            <span class="filter-label">Kantor Cabang</span>
                            <select id="filter_unit" class="form-control select2-status" <?= $is_kepala ? 'disabled' : '' ?>>
                                <option value="">-- Semua Kantor --</option>
                                <?= $opt_kantor ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-col">
                        <div class="filter-group">
                            <span class="filter-label">Jabatan</span>
                            <select id="filter_jabatan" class="form-control select2-jabatan" disabled>
                                <option value="">-- Pilih Jabatan --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-shell">
                    <div class="table-responsive table-scroll-wrap">
                        <table id="nominatifTableAjax" class="table mb-0">
                            <thead>
                                <tr>
                                    <th width="5%" class="text-center">No</th>
                                    <th>Nama Pegawai</th>
                                    <th>ID Pegawai</th>
                                    <th>Jabatan</th>
                                    <th>Unit Kerja</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">TMT Jabatan</th>
                                    <th>Pendidikan</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="table-footer">
                    <div class="row align-items-center">
                        <div class="col-sm-12 col-md-5" id="nominatifInfoWrap"></div>
                        <div class="col-sm-12 col-md-7" id="nominatifPagingWrap"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {
    $('.select2-status').select2({ theme: 'bootstrap-5', width: '100%' });
    $('.select2-jabatan').select2({ theme: 'bootstrap-5', width: '100%', placeholder: "-- Pilih Jabatan --" });

    function buildExportUrl(baseUrl) {
        var params = {
            status_kepeg: $('#filter_status').val() || '',
            unit_kerja: $('#filter_unit').val() || '',
            jabatan: $('#filter_jabatan').val() || '',
            search: $('#nominatifTableAjax_filter input').val() || ''
        };

        return baseUrl + '?' + $.param(params);
    }

    var table = $('#nominatifTableAjax').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "pages/report/ajax-nominatif-pegawai.php",
            type: "GET",
            data: function(d) {
                d.status_kepeg = $('#filter_status').val();
                d.unit_kerja   = $('#filter_unit').val();
                d.jabatan      = $('#filter_jabatan').val();
            }
        },
        columns: [
            { data: "no", className: "text-center" },
            { data: "nama" },
            { data: "nip" },
            { data: "jabatan" },
            { data: "unit_kerja" },
            { data: "status", className: "text-center" },
            { data: "tmt", className: "text-center" },
            { data: "pendidikan" }
        ],
        language: {
            search: "",
            searchPlaceholder: "Cari Nama / ID Pegawai...",
            lengthMenu: "Tampil _MENU_",
            processing: '<div class="spinner-border text-primary text-sm" role="status"><span class="sr-only">Loading...</span></div> Memuat data...',
            zeroRecords: "<div class='table-empty text-center'><i class='fas fa-search d-block'></i>Data tidak ditemukan</div>",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            paginate: { first: "<<", last: ">>", next: ">", previous: "<" }
        },
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        dom: "<'nominatif-dt-head'l f>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        order: [],
        initComplete: function() {
            var wrapper = $('#nominatifTableAjax_wrapper');
            $('#nominatifHeaderControls').empty()
                .append(wrapper.find('.dataTables_length'))
                .append(wrapper.find('.dataTables_filter'));
            $('#nominatifInfoWrap').append(wrapper.find('.dataTables_info'));
            $('#nominatifPagingWrap').append(wrapper.find('.dataTables_paginate'));
            wrapper.find('.nominatif-dt-head').remove();
        }
    });

    $('#filter_unit').on('change', function() {
        var kodeKantor = $(this).val();

        $('#filter_jabatan').html('<option value="">-- Memuat... --</option>').prop('disabled', true).trigger('change');
        table.ajax.reload();

        if (kodeKantor) {
            $.ajax({
                url: 'pages/report/ajax-get-options.php',
                type: 'POST',
                data: { type: 'get_jabatan', kode_kantor: kodeKantor },
                success: function(resp) {
                    $('#filter_jabatan').html(resp).prop('disabled', false).trigger('change');
                },
                error: function() {
                    $('#filter_jabatan').html('<option value="">Gagal memuat jabatan</option>');
                }
            });
        } else {
            $('#filter_jabatan').html('<option value="">-- Pilih Jabatan --</option>').prop('disabled', true).trigger('change');
        }
    });

    if ($('#filter_unit').val()) {
        $('#filter_unit').trigger('change');
    }

    $('#filter_status, #filter_jabatan').on('change', function() {
        table.ajax.reload();
    });

    $('#exportExcelBtn').on('click', function() {
        window.open(buildExportUrl('pages/report/export-nominatif-excel.php'), '_blank');
    });

    $('#exportPdfBtn').on('click', function() {
        window.open(buildExportUrl('pages/report/print-nominatif-pegawai.php'), '_blank');
    });
});
</script>
