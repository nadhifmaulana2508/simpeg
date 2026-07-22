<?php
// dashboard.php
// Pastikan tidak ada session_start() disini jika di file induk (index.php) sudah ada.
?>
<?php include_once 'dist/functions.php'; ?>

<?php include 'komponen/alert-welcome.php'; ?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2 align-items-center">
      <div class="col-lg-7 col-md-12">
        <h1 class="m-0 fw-bold text-dark" style="font-size: 1.8rem;">Dashboard</h1>
        <p class="text-muted small mb-0">Overview Data Kepegawaian & Statistik</p>
      </div>
      <?php if (strtolower(isset($_SESSION['hak_akses']) ? $_SESSION['hak_akses'] : '') !== 'kepala'): ?>
      <div class="col-lg-5 col-md-12 mt-3 mt-lg-0">
        <div class="dashboard-filter-toolbar">
          <label class="dashboard-filter-label mb-0" for="filter_unit_dashboard">Filter Wilayah</label>
          <select id="filter_unit_dashboard" class="form-control select2">
            <?php echo simpeg_dashboard_filter_options($conn); ?>
          </select>
        </div>
      </div>
      <?php endif; ?>
      </div>
  </div>
</div>

<section class="content">
  <div class="container-fluid">

    <div id="dashboard-content">
      
      <?php include 'komponen/statistik-box.php'; ?>
      
      <?php include 'komponen/chart-masakerja.php'; ?>
      
      <div class="row match-height">
        <div class="col-lg-5 col-md-12 mb-4">
          <?php include 'komponen/chart-pie-jk.php'; ?>
        </div>
        <div class="col-lg-7 col-md-12 mb-4">
          <?php include 'komponen/chart-bar-pendidikan.php'; ?>
        </div>
      </div>

      <div class="row match-height">
        <div class="col-md-6 mb-4">
          <?php include 'komponen/chart-bar-status.php'; ?>
        </div>
        <div class="col-md-6 mb-4">
          <?php include 'komponen/chart-line-pelanggaran.php'; ?>
        </div>
      </div>
      
      <?php include 'komponen/chart-bar-jabatan.php'; ?>

      <?php include 'komponen/tabel-pensiun.php'; ?>
      
      <div class="row match-height">
        <div class="col-md-6 mb-4">
          <?php include 'komponen/tabel-keterisian-eksekutif.php'; ?>
        </div>
        <div class="col-md-6 mb-4">
          <?php include 'komponen/tabel-keterisian-struktural.php'; ?>
        </div>
      </div>
      
    </div> </div>
</section>

<style>
/* CSS Dashboard */
.content-header h1 {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #333;
}
.dashboard-filter-toolbar {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 18px;
  background: #ffffff;
  border: 1px solid #dfe8e3;
  box-shadow: 0 10px 24px rgba(15, 49, 42, 0.05);
}
.dashboard-filter-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #6f7e79;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.dashboard-filter-toolbar .select2-container--default .select2-selection--single {
  height: 42px;
  border-radius: 12px;
  border: 1px solid #d7e4de;
  padding: 6px 10px;
}
.dashboard-filter-toolbar .select2-container--default .select2-selection--single .select2-selection__rendered {
  line-height: 28px;
  color: #16302b;
}
.dashboard-filter-toolbar .select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 40px;
}
.row.match-height {
  display: flex;
  flex-wrap: wrap;
}
.row.match-height > [class*='col-'] {
  display: flex;
  flex-direction: column;
}
.row.match-height > [class*='col-'] > .card {
  flex: 1;
  width: 100%;
}

@media (max-width: 991.98px) {
  .content-header .row.mb-2 {
    row-gap: 14px;
  }
  .dashboard-filter-toolbar {
    padding: 12px 14px;
    border-radius: 16px;
  }
}

@media (max-width: 767.98px) {
  .content-header {
    padding-bottom: 0.2rem;
  }
  .content-header h1 {
    font-size: 1.4rem !important;
    margin-bottom: 0.1rem;
  }
  .content-header p {
    font-size: 0.76rem;
  }
  .dashboard-filter-toolbar {
    gap: 4px;
    padding: 10px;
    border-radius: 14px;
  }
  .dashboard-filter-label {
    font-size: 0.64rem;
  }
  .dashboard-filter-toolbar .select2-container--default .select2-selection--single {
    height: 38px;
  }
  .dashboard-filter-toolbar .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
  }
  .dashboard-filter-toolbar .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 24px;
    font-size: 0.82rem;
  }
  #dashboard-content .mb-4 {
    margin-bottom: 0.8rem !important;
  }
}
</style>

<link href="plugins/select2/css/select2.min.css" rel="stylesheet" />

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $ === 'undefined') {
        console.error("jQuery belum diload! Pastikan script jQuery ada di footer.");
        return;
    }

    function initPlugins() {
        if ($('.select2').length > 0 && $.fn.select2) {
            $('.select2').select2({
                placeholder: "Pilih Filter Wilayah",
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 5
            });
        }
    }

    function notifyDashboardRefresh() {
        document.dispatchEvent(new CustomEvent('simpeg:dashboard-refresh'));
    }

    initPlugins();

    if (document.readyState === 'complete') {
        setTimeout(notifyDashboardRefresh, 0);
    } else {
        window.addEventListener('load', notifyDashboardRefresh, { once: true });
    }

    $(document).on('change', '#filter_unit_dashboard', function () {
        const dashboardFilter = $(this).val();

        $('#dashboard-content').css('opacity', '0.5');

        $.ajax({
            url: 'dashboard-filter.php',
            method: 'GET',
            data: { dashboard_filter: dashboardFilter },
            global: false,
            success: function (data) {
                $('#dashboard-content').html(data).css('opacity', '1');
                initPlugins();
                notifyDashboardRefresh();
                if (window.SimpegUI && typeof window.SimpegUI.hideLoader === 'function') {
                    window.SimpegUI.hideLoader();
                }
            },
            error: function () {
                alert('Gagal memuat data filter. Cek koneksi atau log error.');
                $('#dashboard-content').css('opacity', '1');
                if (window.SimpegUI && typeof window.SimpegUI.hideLoader === 'function') {
                    window.SimpegUI.hideLoader();
                }
            },
            complete: function () {
                $('#dashboard-content').css('opacity', '1');
                if (window.SimpegUI && typeof window.SimpegUI.hideLoader === 'function') {
                    window.SimpegUI.hideLoader();
                }
            }
        });
    });
});
</script>
