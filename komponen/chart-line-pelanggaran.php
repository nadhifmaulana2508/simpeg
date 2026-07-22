<?php
// Pastikan koneksi database sudah di-include sebelumnya
// include "dist/koneksi.php"; 

$tahunList = [];
$tahunQuery = mysqli_query($conn, "SELECT DISTINCT YEAR(tgl_sk) AS tahun FROM tb_hukuman ORDER BY tahun DESC");
while ($row = mysqli_fetch_assoc($tahunQuery)) {
  $tahunList[] = $row['tahun'];
}
$tahun_sekarang = date('Y');
?>

<div class="card card-modern h-100 mb-4" style="min-height: 450px;">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <h6 class="fw-bold text-soft mb-0">
      <i class="fas fa-chart-line text-pastel-red me-2"></i> Tren Pelanggaran
    </h6>
    
    <div class="card-tools">
      <select id="filter_tahun_pelanggaran" class="form-control form-control-sm border-0 bg-light text-muted fw-bold" style="width: 100px;">
        <?php foreach ($tahunList as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>" <?= $t == $tahun_sekarang ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="card-body d-flex flex-column justify-content-center">
    <div style="height: 300px; width: 100%;">
      <canvas id="lineChartPelanggaran"></canvas>
    </div>
    <div class="text-center mt-3">
       <small class="text-muted fw-bold ls-1" style="font-size: 10px;">DATA PELANGGARAN PER BULAN</small>
    </div>
  </div>
</div>

<style>
  .text-pastel-red { color: #E57373 !important; }
</style>

<script>
(function () {
  function initChartPelanggaran() {
    const ctxElement = document.getElementById('lineChartPelanggaran');
    const currentDashboardFilter = document.getElementById('filter_unit_dashboard')
      ? document.getElementById('filter_unit_dashboard').value
      : <?= json_encode(simpeg_dashboard_filter_current($conn)); ?>;
    
    if (ctxElement && typeof Chart !== 'undefined') {
        if (ctxElement._chartInstance) {
          ctxElement._chartInstance.destroy();
        }

        const ctxLine = ctxElement.getContext('2d');
        const systemFont = "'-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif";
        
        let pelanggaranChart = new Chart(ctxLine, {
          type: 'line',
          data: {
            labels: [],
            datasets: [{
              label: 'Jumlah Pelanggaran',
              data: [],
              borderColor: '#E57373',
              backgroundColor: 'rgba(229, 115, 115, 0.1)',
              borderWidth: 2,
              pointBackgroundColor: '#fff',
              pointBorderColor: '#E57373',
              pointRadius: 4,
              pointHoverRadius: 6,
              fill: true,
              lineTension: 0.4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              onComplete: function() {
                var chart = this.chart;
                var ctx = chart.ctx;
                ctx.save();
                ctx.font = '600 10px ' + systemFont;
                ctx.fillStyle = '#7a7f87';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';

                this.data.datasets.forEach(function(dataset, datasetIndex) {
                  var meta = chart.controller.getDatasetMeta(datasetIndex);
                  meta.data.forEach(function(point, index) {
                    var value = dataset.data[index];
                    if (value === null || typeof value === 'undefined') {
                      return;
                    }
                    ctx.fillText(value, point._model.x, point._model.y - 8);
                  });
                });

                ctx.restore();
              }
            },
            legend: { display: false },
            tooltips: {
              backgroundColor: '#fff',
              titleFontColor: '#555',
              titleFontFamily: systemFont,
              bodyFontColor: '#666',
              bodyFontFamily: systemFont,
              borderColor: '#f0f0f0',
              borderWidth: 1,
              xPadding: 10,
              yPadding: 10,
              displayColors: false,
              callbacks: {
                  label: function(tooltipItem, data) {
                      return ' Total: ' + tooltipItem.yLabel + ' Kasus';
                  }
              }
            },
            scales: {
              yAxes: [{
                 ticks: {
                     beginAtZero: true,
                     stepSize: 1,
                     fontFamily: systemFont,
                     fontSize: 10
                 },
                 gridLines: {
                     borderDash: [5, 5],
                     drawBorder: false,
                     color: '#f2f2f2'
                 }
              }],
              xAxes: [{
                 gridLines: {
                     display: false
                 },
                 ticks: {
                     fontFamily: systemFont,
                     fontSize: 10
                 }
              }]
            }
          }
        });
        ctxElement._chartInstance = pelanggaranChart;

        function loadDataPelanggaran(tahun) {
          fetch(`komponen/get_data_pelanggaran.php?tahun=${tahun}&dashboard_filter=${encodeURIComponent(currentDashboardFilter)}`)
            .then(response => response.json())
            .then(result => {
              pelanggaranChart.data.labels = result.labels;
              pelanggaranChart.data.datasets[0].data = result.data;
              pelanggaranChart.update(); 
            })
            .catch(error => console.error('Error fetching data:', error));
        }

        const selectTahun = document.getElementById('filter_tahun_pelanggaran');
        if(selectTahun){
            loadDataPelanggaran(selectTahun.value);
            selectTahun.onchange = function() {
              loadDataPelanggaran(this.value);
            };
        }
    }
  }

  if (document.readyState === 'complete') {
    setTimeout(initChartPelanggaran, 0);
  } else {
    window.addEventListener('load', initChartPelanggaran, { once: true });
  }

  document.addEventListener('simpeg:dashboard-refresh', initChartPelanggaran);
})();
</script>
