<?php
include "dist/koneksi.php";
include_once "dist/functions.php";

$jabatan = [];
$jmlJabatan = [];

$where_unit = simpeg_dashboard_filter_clause($conn, 'j.unit_kerja');

// --- 1. AMAN DARI SQL INJECTION ---
// Query ini aman karena tidak menerima input user ($_POST/$_GET).
// Tapi kita pastikan error database tidak bocor ke user (Silent Error).
$sql = "SELECT j.jabatan, COUNT(DISTINCT p.id_peg) as total
        FROM tb_jabatan j
        JOIN tb_pegawai p ON p.id_peg = j.id_peg
        WHERE p.status_aktif = 1
        AND j.status_jab = 'Aktif'
        $where_unit
        GROUP BY j.jabatan
        ORDER BY j.jabatan ASC";
$hasil = mysqli_query($conn, $sql);

if (!$hasil) {
    // Jika query error, jangan die(mysqli_error), cukup log atau kosongkan data
    // error_log(mysqli_error($conn)); 
} else {
    while ($data = mysqli_fetch_array($hasil)) {
        // --- 2. AMAN DARI XSS (Sanitasi Output) ---
        // Bersihkan nama jabatan dari karakter HTML berbahaya
        $nama_raw = htmlspecialchars($data['jabatan'], ENT_QUOTES, 'UTF-8');
        
        // --- 3. LOGIC POTONG TEKS (Support UTF-8) ---
        // Gunakan mb_substr agar aman untuk karakter khusus
        if (mb_strlen($nama_raw) > 20) {
            $nama_raw = mb_substr($nama_raw, 0, 20) . '...';
        }
        
        $jabatan[] = $nama_raw;
        $jmlJabatan[] = (int)$data['total'];
    }
}
?>

<style>
.card-modern {
  border: none;
  border-radius: 16px; /* Sedikit dikurangi biar pas sama AdminLTE */
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05); /* Shadow lebih soft */
  margin-bottom: 2rem;
  overflow: hidden;
}

.card-modern .card-header {
  background: #ffffff;
  border-bottom: 1px solid #f0f2f5;
  padding: 20px 25px;
}

.title-group h3 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #343a40;
  margin-bottom: 5px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
.title-group p {
  font-size: 0.875rem;
  color: #6c757d;
  margin-bottom: 0;
}
</style>

<div class="card card-modern">
  <div class="card-header">
    <div class="title-group">
      <h3>Statistik Pegawai</h3>
      <p>Jumlah pegawai berdasarkan jabatan saat ini</p>
    </div>
  </div>
  
  <div class="card-body">
    <div style="position: relative; height: 350px; width: 100%;">
      <canvas id="barChartJabatan"></canvas>
    </div>
  </div>
</div>

<script>
(function () {
  function initBarJabatanChart() {
    var chartCanvas = document.getElementById('barChartJabatan');
    
    if (chartCanvas && typeof Chart !== 'undefined') {
        if (chartCanvas._chartInstance) {
            chartCanvas._chartInstance.destroy();
        }

        var ctx = chartCanvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(60, 141, 188, 0.9)');
        gradient.addColorStop(1, 'rgba(60, 141, 188, 0.2)');
        var systemFont = "'-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif";

        chartCanvas._chartInstance = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: <?= json_encode($jabatan) ?>,
            datasets: [{
              label: 'Jumlah Pegawai',
              data: <?= json_encode($jmlJabatan) ?>,
              backgroundColor: gradient,
              borderColor: '#3c8dbc',     
              borderWidth: 1,
              borderRadius: 4,          
              barPercentage: 0.6, 
              hoverBackgroundColor: '#307095' 
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
              mode: 'index',
              intersect: false,
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 13, family: systemFont },
                bodyFont: { size: 13, family: systemFont },
                padding: 12,
                cornerRadius: 6,
                displayColors: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                grid: {
                  color: '#f4f6f9',     
                  borderDash: [3, 3],   
                  drawBorder: false
                },
                ticks: {
                  color: '#6c757d',
                  font: { size: 11, family: systemFont },
                  precision: 0
                }
              },
              x: {
                grid: {
                  display: false,
                  drawBorder: false
                },
                ticks: {
                  color: '#6c757d',
                  font: { size: 11, family: systemFont },
                  maxRotation: 45,
                  minRotation: 0,
                  autoSkip: true,
                  maxTicksLimit: 15
                }
              }
            },
            animation: {
              duration: 1500,
              easing: 'easeOutQuart'
            }
          }
        });
    }
  }

  if (document.readyState === 'complete') {
    setTimeout(initBarJabatanChart, 0);
  } else {
    window.addEventListener('load', initBarJabatanChart, { once: true });
  }

  document.addEventListener('simpeg:dashboard-refresh', initBarJabatanChart);
})();
</script>
