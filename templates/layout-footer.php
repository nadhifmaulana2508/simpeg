<style>
/* ... (SEMUA KODE CSS KUSTOM ANDA SEBELUMNYA DI SINI) ... */

/* ------------------------------------------------------------- */
/* 3. KUSTOMISASI FOOTER (PERBAIKAN FINAL BATIK FULL) */
/* ------------------------------------------------------------- */
.main-footer {
    background-color: #f8f9fa !important; 
    border-top: none !important; 
    padding: 10px 15px !important;
    position: relative; 
    z-index: 100;
    overflow: hidden; 
}

/* Mengatur warna teks copyright agar terlihat jelas */
.main-footer strong,
.main-footer a,
.main-footer {
    color: #343a40 !important; /* Warna teks hitam/gelap */
}

/* Mengatur warna teks Version di kanan */
.main-footer .float-right {
    color: #495057 !important; /* Warna abu-abu gelap */
}

/* Motif Batik PENUH DI FOOTER */
.main-footer::after {
    content: '';
    position: absolute;
    bottom: 0; 
    left: 0;
    width: 100%;
    height: 100%; /* FULL FOOTER */
    
    /* GRADASI DIHILANGKAN, LANGSUNG BACKGROUND BATIK */
    background-image: url('dist/img/batik.png') !important; 
    
    background-repeat: repeat;
    background-position: 0 0;
    background-size: 100px 100px; 
    opacity: 0.4; /* Opacity DITINGKATKAN sedikit agar batik terlihat JELAS */
    z-index: -1; 
}

/* ... (AKHIR SEMUA KODE CSS KUSTOM ANDA SEBELUMNYA) ... */
</style>

<style>
/* KODE CSS KUSTOM SEBELUMNYA DIMASUKKAN DI SINI */

/* ------------------------------------------------------------- */
/* 3. KUSTOMISASI FOOTER (PERBAIKAN FINAL BATIK FULL) */
/* ------------------------------------------------------------- */
.main-footer {
    background-color: #f8f9fa !important; 
    border-top: none !important; 
    padding: 10px 15px !important;
    position: relative; 
    z-index: 100;
    overflow: hidden; 
}

/* Mengatur warna teks copyright agar terlihat jelas */
.main-footer strong,
.main-footer a,
.main-footer {
    color: #343a40 !important; /* Warna teks hitam/gelap */
}

/* Mengatur warna teks Version di kanan */
.main-footer .float-right {
    color: #495057 !important; /* Warna abu-abu gelap */
}

/* Motif Batik PENUH DI FOOTER */
.main-footer::after {
    content: '';
    position: absolute;
    bottom: 0; 
    left: 0;
    width: 100%;
    height: 100%; /* FULL FOOTER */
    
    /* GRADASI DIHILANGKAN, LANGSUNG BACKGROUND BATIK */
    background-image: url('dist/img/batik.png') !important; 
    
    background-repeat: repeat;
    background-position: 0 0;
    background-size: 100px 100px; 
    opacity: 0.4; /* Opacity DITINGKATKAN sedikit agar batik terlihat JELAS */
    z-index: -1; 
}
</style>
<footer class="main-footer text-sm">
  <strong>
    &copy; <?php echo date('Y'); ?> 
    <?php echo isset($set['nama_app']) ? $set['nama_app'] : 'SIMPEG'; ?>
  </strong>
  &nbsp;
  All rights reserved.
  
  <div class="float-right d-none d-sm-inline-block">
    <b>Version</b> 2.0
  </div>
</footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="plugins/bootstrap5/js/bootstrap.bundle.min.js"></script>

<script src="dist/js/adminlte.min.js"></script>
<script src="plugins/chart.js/Chart.min.js"></script>
<script src="plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="plugins/jszip/jszip.min.js"></script>
<script src="plugins/pdfmake/pdfmake.min.js"></script>
<script src="plugins/pdfmake/vfs_fonts.js"></script>
<script src="plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="plugins/datatables-buttons/js/buttons.print.min.js"></script>

<script src="plugins/select2/js/select2.full.min.js"></script>
<?php $simpeg_app_js_ver = file_exists('dist/js/simpeg-app.js') ? filemtime('dist/js/simpeg-app.js') : time(); ?>
<script src="dist/js/simpeg-app.js?v=<?php echo $simpeg_app_js_ver; ?>"></script>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const inputs = Array.from(document.querySelectorAll('input, select, textarea'))
      .filter(el => !el.disabled && el.type !== 'hidden');

    inputs.forEach((input, index) => {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          const next = inputs[index + 1];
          if (next) next.focus();
        }
      });
    });
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let timer;
let sessionTimeout = 15 * 60 * 1000; // 15 menit
let keepAliveInterval = 5 * 60 * 1000; // ping tiap 5 menit

function resetSession() {
  clearTimeout(timer);
  timer = setTimeout(() => {
    Swal.fire({
      title: 'Sesi Habis',
      text: 'Sesi Anda telah habis karena tidak ada aktivitas.',
      icon: 'warning',
      confirmButtonText: 'OK',
      allowOutsideClick: false
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = 'pages/login/act-logout.php';
      }
    });
  }, sessionTimeout);
}

function sendKeepAlive() {
  fetch('dist/keepalive.php');
}

['click', 'mousemove', 'keydown', 'scroll'].forEach(evt => {
  document.addEventListener(evt, () => {
    resetSession();
    sendKeepAlive();
  });
});

resetSession();
setInterval(sendKeepAlive, keepAliveInterval);
</script>



</body>
</html>
