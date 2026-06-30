<?php
// ===========================================================
// FILE: pages/ref-keluarga/form-import-data-pasangan.php
// MODULE: Form Import Data Pasangan (Modern UI)
// ===========================================================

// Jika header belum diload di home-admin.php, uncomment baris bawah ini
// include "komponen/header.php"; 
?>

<style>
    /* Styling khusus halaman upload */
    .upload-container { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px; }
    .drop-zone { border: 2px dashed #cbd5e0; border-radius: 15px; padding: 40px; text-align: center; background-color: #f8fafc; transition: all 0.3s ease; cursor: pointer; position: relative; }
    .drop-zone:hover, .drop-zone.dragover { border-color: #007bff; background-color: #e3f2fd; transform: scale(1.01); }
    .drop-zone i { color: #a0aec0; transition: color 0.3s; }
    .drop-zone:hover i, .drop-zone.dragover i { color: #007bff; }
    .file-input-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
    .file-preview { display: none; margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .step-badge { background: #007bff; color: white; width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-weight: bold; margin-right: 10px; }
</style>

<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Import Data Pasangan</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="home-admin.php">Home</a></li>
          <li class="breadcrumb-item active">Import Pasangan</li>
        </ol>
      </div>
    </div>
  </div>
</section>

<section class="content">
  <div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="upload-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="m-0 font-weight-bold text-dark"><i class="fas fa-file-import text-primary mr-2"></i> Import Suami/Istri</h3>
                    <a href="home-admin.php?page=form-view-data-suami-istri" class="btn btn-light btn-sm rounded-pill px-3"><i class="fas fa-times"></i> Tutup</a>
                </div>

                <div class="alert alert-secondary bg-white border shadow-sm rounded-lg mb-4">
                    <div class="d-flex align-items-center">
                        <span class="step-badge">1</span>
                        <div class="flex-grow-1">
                            <h6 class="m-0 text-dark font-weight-bold">Persiapan Data</h6>
                            <small class="text-muted">Unduh template Excel Data Pasangan.</small>
                        </div>
                        <a href="pages/ref-keluarga/download-template-pasangan.php" target="_blank" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm">
                            <i class="fas fa-download mr-1"></i> Download Template
                        </a>
                    </div>
                </div>

                <form id="uploadForm" enctype="multipart/form-data" data-no-loading="true">
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-badge">2</span>
                            <h6 class="m-0 text-dark font-weight-bold">Upload File Excel</h6>
                        </div>
                        
                        <div class="drop-zone" id="dropZone">
                            <div class="content-wrap">
                                <i class="fas fa-cloud-upload-alt fa-4x mb-3 text-secondary"></i>
                                <h5 class="font-weight-bold text-dark">Klik atau Tarik File ke Sini</h5>
                                <p class="text-muted mb-0 small">Support: .xlsx, .xls (Maks 5MB)</p>
                            </div>
                            <input type="file" name="file_excel" id="file_excel" class="file-input-overlay" accept=".xlsx, .xls" required>
                        </div>

                        <div id="filePreview" class="file-preview">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-excel text-success fa-2x mr-3"></i>
                                <div>
                                    <h6 class="m-0 font-weight-bold text-dark" id="fileName">file.xlsx</h6>
                                    <small class="text-muted" id="fileSize">0 KB</small>
                                </div>
                                <div class="ml-auto"><span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Siap Upload</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">
                            <i class="fas fa-eye mr-2"></i> Preview Data
                        </button>
                    </div>
                </form>

                <div id="preview-area" class="mt-5"></div>
            </div>
        </div>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function hideGlobalSimpegLoader() {
    if (window.SimpegUI && typeof window.SimpegUI.hideLoader === 'function') {
        window.SimpegUI.hideLoader();
    }
}

// UI Interactions
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('file_excel');
const filePreview = document.getElementById('filePreview');
const fileNameTxt = document.getElementById('fileName');
const fileSizeTxt = document.getElementById('fileSize');

// Drag & Drop Effects
['dragenter', 'dragover'].forEach(evt => dropZone.addEventListener(evt, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); }));

// File Selection Handler
fileInput.addEventListener('change', function() {
    if (this.files.length) {
        filePreview.style.display = 'block';
        fileNameTxt.textContent = this.files[0].name;
        fileSizeTxt.textContent = (this.files[0].size / 1024).toFixed(2) + ' KB';
    }
});

// 1. PROSES PREVIEW
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!fileInput.files.length) { Swal.fire('Warning', 'Pilih file terlebih dahulu!', 'warning'); return; }

    const formData = new FormData();
    formData.append('file_excel', fileInput.files[0]);
    formData.append('action', 'preview');

    Swal.fire({title: 'Memproses Preview...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});

    fetch('pages/ref-keluarga/upload-data-pasangan.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        hideGlobalSimpegLoader();
        Swal.close();
        if (res.status === 'success') {
            document.getElementById('preview-area').innerHTML = res.html;
            document.getElementById('preview-area').scrollIntoView({ behavior: 'smooth' });
            Swal.fire({icon: 'success', title: 'Preview Berhasil', timer: 1500, showConfirmButton: false});
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    }).catch(err => {
        hideGlobalSimpegLoader();
        Swal.close();
        Swal.fire('Error', 'Terjadi kesalahan pada server.', 'error');
        console.error(err);
    });
});

// 2. PROSES SIMPAN (Event Delegation)
document.body.addEventListener('click', function(e) {
    if (e.target && (e.target.id == 'btnSimpanPasangan' || e.target.closest('#btnSimpanPasangan'))) {
        e.preventDefault();
        const textArea = document.getElementById('json_data_pasangan');
        const tokenInput = document.getElementById('import_pasangan_preview_token');
        
        if(!textArea && !tokenInput) { Swal.fire('Error', 'Data preview tidak ditemukan.', 'error'); return; }

        Swal.fire({
            title: 'Simpan Data Pasangan?',
            text: "Data akan disimpan ke database (Update jika ID Pegawai + Nama sama).",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Proses!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                simpanKeDatabase(textArea ? textArea.value : '', tokenInput ? tokenInput.value : '');
            }
        });
    }
});

function simpanKeDatabase(jsonData, previewToken) {
    const formData = new FormData();
    formData.append('action', 'save');
    if (previewToken) {
        formData.append('preview_token', previewToken);
    } else {
        formData.append('data_pasangan', jsonData);
    }

    Swal.fire({title: 'Menyimpan Data...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});

    fetch('pages/ref-keluarga/upload-data-pasangan.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        hideGlobalSimpegLoader();
        Swal.close();
        if (res.status === 'success') {
            Swal.fire({
                title: 'Selesai!',
                html: res.message,
                icon: 'success'
            }).then(() => {
                // Redirect ke halaman view data pasangan
                window.location.href = "home-admin.php?page=form-view-data-suami-istri"; 
            });
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    }).catch(err => {
        hideGlobalSimpegLoader();
        Swal.close();
        Swal.fire('Error', 'Koneksi ke server gagal.', 'error');
        console.error(err);
    });
}
</script>
