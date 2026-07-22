<?php
include "dist/koneksi.php";
include_once "dist/functions.php";

$where_unit = simpeg_dashboard_filter_clause($conn, 'j.unit_kerja');

// --- 2. QUERY ---
$sql = "SELECT p.jk, COUNT(DISTINCT p.id_peg) as total 
        FROM tb_pegawai p
        JOIN tb_jabatan j ON p.id_peg = j.id_peg
        WHERE p.status_aktif=1 
        AND j.status_jab = 'Aktif' 
        $where_unit 
        GROUP BY p.jk";

$hasil = mysqli_query($conn, $sql);

// Inisialisasi Hitungan
$total_l = 0;
$total_p = 0;

while ($data = mysqli_fetch_array($hasil)) {
    // Normalisasi data (hapus spasi, uppercase)
    $jk_db = strtoupper(trim($data['jk']));
    $jml   = (int)$data['total'];

    // LOGIKA LEBIH KETAT (Mencegah data kosong masuk ke Perempuan)
    // Cek Laki-laki
    if ($jk_db == 'L' || $jk_db == 'LAKI-LAKI' || $jk_db == 'PRIA') {
        $total_l += $jml;
    } 
    // Cek Perempuan (Eksplisit)
    elseif ($jk_db == 'P' || $jk_db == 'PEREMPUAN' || $jk_db == 'WANITA') {
        $total_p += $jml;
    }
    // Jika NULL/Kosong, tidak dihitung agar statistik akurat
}

// Hitung Total Valid
$total_seluruh = $total_l + $total_p;

// Data Chart
$data_chart = "$total_l, $total_p"; 
?>

<div class="card border-0 shadow-sm rounded-lg" style="min-height: 380px;">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="card-title fw-bold text-dark mb-0">
            <i class="fas fa-venus-mars text-pastel-blue me-2"></i> Komposisi Gender
        </h5>
    </div>

    <div class="card-body d-flex flex-column align-items-center justify-content-center pt-0">
        <div class="chart-container position-relative" style="height: 220px; width: 220px;">
            <canvas id="doughnutChartJK"></canvas>
            
            <div class="center-text">
                <h2 class="fw-bold mb-0 text-dark counter-value" data-target="<?= $total_seluruh ?>">0</h2>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Total</small>
            </div>
        </div>

        <div class="mt-4 w-100 px-3">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                <span class="d-flex align-items-center text-muted small fw-bold">
                    <span class="dot bg-pastel-blue me-2"></span> Laki-laki
                </span>
                <span class="fw-bold text-dark small">
                    <span class="counter-value" data-target="<?= $total_l ?>">0</span> Pegawai
                </span> 
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="d-flex align-items-center text-muted small fw-bold">
                    <span class="dot bg-pastel-pink me-2"></span> Perempuan
                </span>
                <span class="fw-bold text-dark small">
                    <span class="counter-value" data-target="<?= $total_p ?>">0</span> Pegawai
                </span>
            </div>
        </div>
    </div>
</div>

<style>
    .rounded-lg { border-radius: 15px; }
    
    /* Center Text Absolute Position */
    .center-text {
        position: absolute; top: 50%; left: 50%;
        transform: translate(-50%, -50%); text-align: center;
        pointer-events: none; z-index: 0;
        margin-top: 10px; /* Sedikit adjustment agar pas di tengah donut */
    }
    
    /* Warna Pastel */
    .bg-pastel-blue { background-color: #90CAF9 !important; } 
    .text-pastel-blue { color: #90CAF9 !important; }
    
    .bg-pastel-pink { background-color: #F48FB1 !important; } 
    
    .dot { height: 12px; width: 12px; border-radius: 4px; display: inline-block; }
</style>

<script>
(function () {
    function initGenderCounters() {
        const statCounters = document.querySelectorAll('#dashboard-content .counter-value');
        statCounters.forEach(function (counter) {
            const target = parseInt(counter.getAttribute('data-target') || '0', 10);
            if (!target || target <= 0) {
                counter.innerText = '0';
                return;
            }

            const duration = 1000;
            const increment = target / (duration / 16 || 1);
            let current = 0;

            const updateStat = function () {
                current += increment;
                if (current < target) {
                    counter.innerText = Math.ceil(current).toLocaleString('id-ID');
                    requestAnimationFrame(updateStat);
                } else {
                    counter.innerText = target.toLocaleString('id-ID');
                }
            };

            updateStat();
        });
    }

    function initGenderChart() {
        var canvas = document.getElementById('doughnutChartJK');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var ctx = canvas.getContext('2d');
        if (canvas._chartInstance) {
            canvas._chartInstance.destroy();
        }

        canvas._chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Laki-laki', 'Perempuan'], 
                datasets: [{
                    data: [<?= $data_chart; ?>], 
                    backgroundColor: ['#90CAF9', '#F48FB1'],
                    hoverBackgroundColor: ['#64B5F6', '#F06292'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%', 
                plugins: {
                    legend: { display: false }, 
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#555',
                        bodyColor: '#666',
                        borderColor: '#eee',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                var value = context.raw;
                                var total = <?= ($total_seluruh > 0) ? $total_seluruh : 1 ?>; 
                                var persen = ((value / total) * 100).toFixed(1) + '%';
                                return ' ' + context.label + ': ' + value + ' (' + persen + ')';
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }

    function initGenderSection() {
        initGenderCounters();
        initGenderChart();
    }

    if (document.readyState === 'complete') {
        setTimeout(initGenderSection, 0);
    } else {
        window.addEventListener('load', initGenderSection, { once: true });
    }

    document.addEventListener('simpeg:dashboard-refresh', initGenderSection);
})();
</script>
