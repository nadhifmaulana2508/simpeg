<?php
include "dist/koneksi.php";
include_once "dist/functions.php";

$where_unit = simpeg_dashboard_position_clause($conn, 'j.unit_kerja');
$where_lingkup = simpeg_dashboard_master_lingkup_clause($conn, 'm.lingkup');

// QUERY KHUSUS JABATAN STRUKTURAL (PS)
// Logika query tetap sama, hanya beda di WHERE group_jabatan = 'PS'
$queryPS = mysqli_query($conn, "
  SELECT 
    m.kode_jabatan,
    m.nama_jabatan,
    m.kuota,
    COUNT(DISTINCT p.id_peg) AS jml
  FROM tb_master_jabatan m
  LEFT JOIN tb_jabatan j 
    ON m.nama_jabatan = j.jabatan
    AND j.status_jab = 'Aktif'
    $where_unit
  LEFT JOIN tb_pegawai p 
    ON j.id_peg = p.id_peg AND p.status_aktif = 1
  WHERE m.group_jabatan = 'PS'
    $where_lingkup
  GROUP BY m.kode_jabatan, m.nama_jabatan, m.kuota
  ORDER BY m.kode_jabatan ASC
");
?>

<style>
/* ================= STYLE MODERN (KONSISTENSI DESAIN) ================= */
.font-primary { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }

/* CARD STYLE */
.card-modern {
  border: none;
  border-radius: 20px;
  background: #ffffff;
  box-shadow: 0 12px 40px rgba(0,0,0,0.04);
  margin-bottom: 2rem;
  overflow: hidden;
}

/* HEADER STYLE - SEARCH MEPET KANAN */
.card-modern .card-header {
  background: #ffffff;
  border-bottom: 1px solid #f2f4f8;
  padding: 25px 20px; /* Padding kanan kiri 20px biar mepet */
  display: flex;
  justify-content: space-between; /* Pisahkan Judul & Search */
  align-items: center;
  flex-wrap: wrap; 
  gap: 15px;
}

.title-group h3 {
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 6px;
  letter-spacing: -0.5px;
}
.title-group p {
  font-size: 0.9rem;
  color: #94a3b8;
  margin-bottom: 0;
  font-weight: 500;
}

/* SEARCH BAR */
.header-search { 
    position: relative; 
    width: 280px; 
    margin-left: auto; /* Paksa ke kanan */
}
.header-search input {
  width: 100%;
  border-radius: 50px;
  border: 2px solid #f1f5f9;
  background: #f8fafc;
  padding: 10px 20px 10px 45px;
  font-size: 0.95rem;
  color: #334155;
  transition: all 0.3s ease;
  height: 45px;
}
.header-search input:focus {
  background: #ffffff;
  border-color: #38bdf8;
  box-shadow: 0 4px 12px rgba(56, 189, 248, 0.15);
  outline: none;
}
.header-search i {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #cbd5e1;
  font-size: 1.1rem;
}

/* TABLE STYLE */
.table-modern thead th {
  background-color: #ffffff;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
  border-bottom: 2px solid #f1f5f9;
  padding: 20px 20px;
  letter-spacing: 0.8px;
}
.table-modern tbody td {
  padding: 20px 20px;
  vertical-align: middle;
  border-bottom: 1px solid #f8fafc;
  font-size: 1rem;
  color: #334155;
}
.table-modern tbody tr:hover { background-color: #f8fafc; }
.table-modern tbody tr:last-child td { border-bottom: none; }

/* BADGES SOFT */
.badge-soft { 
    padding: 8px 16px; 
    border-radius: 30px; 
    font-size: 0.85rem; 
    font-weight: 700; 
    letter-spacing: 0.5px; 
    display: inline-block;
}
.badge-soft-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; } 
.badge-soft-success { background: #f0fdf4; color: #22c55e; border: 1px solid #bbf7d0; }

/* PAGINATION */
.dataTables_wrapper .row:last-child {
  padding: 25px 20px 35px 20px !important;
  margin: 0;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  border-top: 1px solid #f1f5f9;
}
.dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0 !important; margin: 0 !important; border: none !important; background: transparent !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { border: none !important; background: transparent !important; }

.page-item .page-link { 
  border: none; width: 42px; height: 42px; 
  margin-left: 8px; border-radius: 12px !important; 
  display: flex; align-items: center; justify-content: center; 
  font-weight: 600; font-size: 0.9rem; 
  color: #64748b !important; background: #f1f5f9 !important; 
  transition: all 0.2s;
}
.page-item:not(.active) .page-link:hover { background-color: #e0f2fe !important; color: #0ea5e9 !important; transform: translateY(-2px); }
.page-item.active .page-link { background: #0ea5e9 !important; color: #fff !important; box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3) !important; }

@media (max-width: 991.98px) {
  .card-modern .card-header {
    padding: 20px 16px;
    align-items: stretch;
  }
  .title-group h3 {
    font-size: 1.15rem;
  }
  .title-group p {
    font-size: 0.82rem;
  }
  .header-search {
    width: 100%;
    margin-left: 0;
  }
  .header-search input {
    height: 42px;
    font-size: 0.9rem;
  }
  .table-modern thead th,
  .table-modern tbody td {
    padding: 16px 14px;
  }
}

@media (max-width: 575.98px) {
  .table-modern thead th {
    font-size: 0.68rem;
    letter-spacing: 0.04em;
  }
  .table-modern tbody td {
    font-size: 0.9rem;
    padding: 14px 12px;
  }
  .badge-soft {
    padding: 6px 12px;
    font-size: 0.75rem;
  }
  .page-item .page-link {
    width: 36px;
    height: 36px;
    margin-left: 6px;
    font-size: 0.82rem;
  }
}
</style>

<div class="card card-modern">
  <div class="card-header">
    <div class="title-group">
      <h3>Jabatan Struktural</h3>
      <p>Monitoring ketersediaan posisi dan kuota jabatan (PS)</p>
    </div>

    <div class="header-search">
      <input type="text" id="searchStruktural" placeholder="Cari Jabatan...">
      <i class="fas fa-search"></i>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="tabelStruktural" class="table table-modern w-100">
        <thead>
          <tr>
            <th style="width: 75%;">Deskripsi Jabatan</th>
            <th class="text-center" style="width: 15%; white-space: nowrap;">Terisi</th>
            <th class="text-center" style="width: 10%; white-space: nowrap;">Kuota</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = mysqli_fetch_assoc($queryPS)) {
            $db_kuota = (int)$row['kuota'];
            $terisi   = (int)$row['jml'];
            
            // Logic Fix: Anggap kuota 0 sebagai 1 (Handling data kotor)
            $kuota_tampil = ($db_kuota == 0) ? 1 : $db_kuota;

            // Logic Vacant vs Number
            if ($terisi == 0) {
                // Tampilkan Badge Vacant
                $display_terisi = "<span class='badge badge-soft-danger'>Vacant</span>";
            } else {
                // Tampilkan Angka Besar
                $display_terisi = "<span style='font-weight:800; color:#334155; font-size:1.1rem;'>$terisi</span>";
            }
          ?>
            <tr>
              <td>
                <span style="font-weight:600; color:#334155; font-size:1rem;"><?= $row['nama_jabatan'] ?></span>
              </td>
              <td class="text-center">
                <?= $display_terisi ?>
              </td>
              <td class="text-center">
                <span style="font-weight:700; color:#94a3b8;"><?= $kuota_tampil ?></span>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function () {
    function initStrukturalTable() {
      if (typeof $ === 'undefined' || !$.fn.DataTable) {
        return;
      }

      if ($.fn.DataTable.isDataTable('#tabelStruktural')) {
        $('#tabelStruktural').DataTable().destroy();
      }

      var table = $('#tabelStruktural').DataTable({
        dom: 'tp',
        paging: true,
        pageLength: 5,
        responsive: true,
        autoWidth: false,
        ordering: true,
        lengthChange: false,
        info: false,
        language: {
          zeroRecords: 'Tidak ada data jabatan ditemukan',
          paginate: { next: '<i class="fas fa-chevron-right"></i>', previous: '<i class="fas fa-chevron-left"></i>' }
        },
        drawCallback: function() {
          $('.dataTables_paginate > .pagination').addClass('justify-content-end');
        }
      });

      $('#searchStruktural').off('keyup.simpeg').on('keyup.simpeg', function() {
        table.search(this.value).draw();
      });
    }

    if (document.readyState === 'complete') {
      setTimeout(initStrukturalTable, 0);
    } else {
      window.addEventListener('load', initStrukturalTable, { once: true });
    }

    document.addEventListener('simpeg:dashboard-refresh', initStrukturalTable);
  })();
</script>
