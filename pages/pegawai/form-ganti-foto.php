<?php
// pages/pegawai/form-ganti-foto.php

// Matikan error display agar tidak merusak output JSON/Redirect
ini_set('display_errors', 0);
error_reporting(0);

include __DIR__ . '/../../dist/koneksi.php';
@include_once __DIR__ . '/../../dist/functions.php';

if (!function_exists('fgf_page_url')) {
    function fgf_page_url($page, $params = array()) {
        if (function_exists('page_url')) {
            return page_url($page, $params);
        }

        $url = 'home-admin.php?page=' . urlencode($page);
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return $url;
    }
}

// Validasi ID Pegawai
if (!isset($_GET['id_peg'])) die("Error. No Kode Selected!");
$id_peg = mysqli_real_escape_string($conn, $_GET['id_peg']);

// --- HANDLE POST REQUEST (PROSES SIMPAN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil URL Redirect
    $redirect_url = isset($_POST['redirect_back']) && !empty($_POST['redirect_back']) 
                    ? $_POST['redirect_back'] 
                    : fgf_page_url('profil-pegawai');

    // Validasi Data Gambar
    if (!isset($_POST['cropped_image']) || empty($_POST['cropped_image'])) {
        echo_swal('Gagal!', 'Data gambar tidak ditemukan.', 'error');
        exit;
    }

    $dataURL = $_POST['cropped_image'];

    // 1. Validasi Ukuran (Max 3MB)
    // Hitung ukuran base64 string secara kasar
    $sizeInBytes = (strlen($dataURL) * 3 / 4) - substr_count(substr($dataURL, -2), '=');
    $maxSizeMB = 3;
    $maxSizeBytes = $maxSizeMB * 1024 * 1024;

    if ($sizeInBytes > $maxSizeBytes) {
        echo_swal('File Terlalu Besar', 'Maksimal 3MB.', 'error');
        exit;
    }

    // 2. Proses Decode Base64
    $parts = explode(',', $dataURL);
    if (count($parts) !== 2) {
        echo_swal('Error!', 'Format data gambar salah.', 'error');
        exit;
    }

    $data = base64_decode($parts[1]);
    if ($data === false) {
        echo_swal('Error!', 'Gagal decode gambar.', 'error');
        exit;
    }

    // 3. Persiapan File
    $nama_file_baru = 'foto_' . $id_peg . '_' . time() . '.jpg';
    $folder_tujuan  = __DIR__ . '/../../pages/assets/foto/'; // Path absolut folder
    $path_tujuan    = $folder_tujuan . $nama_file_baru;

    // Cek apakah folder ada dan bisa ditulisi
    if (!is_dir($folder_tujuan)) {
        if (!mkdir($folder_tujuan, 0755, true)) {
            echo_swal('Error Server', 'Gagal membuat folder upload.', 'error');
            exit;
        }
    }

    if (!is_writable($folder_tujuan)) {
        echo_swal('Error Permission', 'Folder upload tidak bisa ditulisi (Permission Denied). Hubungi Admin.', 'error');
        exit;
    }
    
    // 4. Simpan File Baru
    if (file_put_contents($path_tujuan, $data)) {
        
        // Hapus foto lama jika ada
        $qLama = mysqli_query($conn, "SELECT foto FROM tb_pegawai WHERE id_peg='$id_peg'");
        if ($qLama && mysqli_num_rows($qLama) > 0) {
            $rLama = mysqli_fetch_assoc($qLama);
            $fileLama = function_exists('simpeg_resolve_photo_path')
                ? simpeg_resolve_photo_path($rLama['foto'], '', false)
                : ($folder_tujuan . $rLama['foto']);
            if (!empty($rLama['foto']) && strpos($fileLama, 'pages/assets/foto/') === 0 && file_exists($fileLama) && is_file($fileLama)) {
                @unlink($fileLama);
            }
        }

        // Update Database
        $update = mysqli_query($conn, "UPDATE tb_pegawai SET foto='$nama_file_baru' WHERE id_peg='$id_peg'");
        
        if ($update) {
            echo_swal('Berhasil!', 'Foto pegawai telah diperbarui.', 'success', $redirect_url);
            exit;
        } else {
            // Jika DB gagal update, hapus file yg baru diupload biar ga nyampah
            @unlink($path_tujuan); 
            echo_swal('Gagal DB!', 'Gagal update database: ' . mysqli_error($conn), 'error');
            exit;
        }

    } else {
        echo_swal('Gagal Simpan!', 'Gagal menulis file ke server.', 'error');
        exit;
    }
}

// Helper Function untuk Output SweetAlert
function echo_swal($title, $text, $icon, $redirect = null) {
    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body style="background:#f4f6f9">';
    echo "<script>
        Swal.fire({
            title: '$title',
            text: '$text',
            icon: '$icon',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            " . ($redirect ? "window.location.href = '$redirect';" : "history.back();") . "
        });
    </script>";
    echo '</body></html>';
}

// --- TAMPILAN FORM (METHOD GET) ---

$redirect_back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : fgf_page_url('profil-pegawai');

$q = mysqli_query($conn, "SELECT nama, foto, jk FROM tb_pegawai WHERE id_peg = '$id_peg'");
if (!$q || mysqli_num_rows($q) == 0) {
    echo "<div class='alert alert-warning'>Data pegawai tidak ditemukan.</div>";
    exit;
}
$peg = mysqli_fetch_assoc($q);
$nama = htmlspecialchars($peg['nama'], ENT_QUOTES, 'UTF-8');
$foto_file = trim($peg['foto']);
$gender = isset($peg['jk']) ? $peg['jk'] : 'L';

// Path Foto Display
$foto_display = function_exists('simpeg_resolve_photo_path')
    ? simpeg_resolve_photo_path($foto_file, $gender)
    : 'dist/img/avatar5.png';
?>

<style>
    .photo-page { padding-top: 18px; padding-bottom: 32px; }
    .photo-shell { background: linear-gradient(180deg, #f4fbfa 0%, #ffffff 100%); border-radius: 22px; padding: 22px; }
    .photo-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
    .photo-title { font-size: 1.65rem; font-weight: 800; color: #173534; margin: 0; }
    .photo-subtitle { color: #607270; margin: 6px 0 0; }
    .crop-container { display: flex; flex-wrap: wrap; gap: 30px; justify-content: center; padding: 24px; background: #fff; border-radius: 20px; border: 1px solid #e2efed; box-shadow: 0 20px 40px rgba(15,118,110,0.08); }
    .editor-area { flex: 1; min-width: 300px; max-width: 500px; display: flex; flex-direction: column; align-items: center; }
    .crop-frame { width: 320px; height: 320px; border-radius: 50%; border: 8px solid #fff; box-shadow: 0 12px 30px rgba(15,118,110,0.18); overflow: hidden; position: relative; background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAACpJREFUeNpiVk6xcgYDCgAjIyMp+k0YGBgY/v///x8Hxk+00Q9G4w8ABBgAVj0E0/2/j/QAAAAASUVORK5CYII='); cursor: grab; }
    .crop-frame:active { cursor: grabbing; }
    .crop-image { position: absolute; top: 0; left: 0; max-width: none; user-select: none; -webkit-user-drag: none; transform-origin: center center; }
    .preview-area { flex: 1; min-width: 250px; max-width: 400px; text-align: center; border-left: 1px solid #e7efee; padding-left: 30px; display: flex; flex-direction: column; justify-content: center; }
    .preview-circle { width: 160px; height: 160px; border-radius: 50%; overflow: hidden; border: 4px solid #e9ecef; margin: 0 auto 20px; background: #f8f9fa; box-shadow: 0 10px 24px rgba(0,0,0,0.1); }
    .control-group { width: 100%; margin-top: 20px; }
    .range-slider { width: 100%; margin: 15px 0; cursor: pointer; }
    .helper-text { font-size: 13px; color: #888; margin-top: 5px; text-align: center; }
    .photo-panel-title { color: #173534; font-size: 1rem; font-weight: 800; }
    .photo-note { border: 1px solid #d7e9e6; background: #f3fbfa; color: #446260; border-radius: 14px; padding: 12px 14px; font-size: 0.9rem; margin-bottom: 20px; }
    .btn-block { width: 100%; display: block; }
    @media(max-width: 768px) {
        .preview-area { border-left: none; padding-left: 0; border-top: 1px solid #eee; padding-top: 20px; }
        .crop-container { gap: 15px; }
        .photo-header { flex-direction: column; }
    }
</style>

<section class="content photo-page">
    <div class="container-fluid">
        <div class="photo-shell">
            <div class="photo-header">
                <div>
                    <h1 class="photo-title">Ganti Foto Pegawai</h1>
                    <p class="photo-subtitle">Perbarui foto profil untuk <strong><?= $nama ?></strong> dengan alur crop yang konsisten.</p>
                </div>
                <a href="<?= htmlspecialchars($redirect_back) ?>" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left mr-1"></i> Kembali
                </a>
            </div>

            <div class="photo-note">
                Pastikan wajah berada di tengah lingkaran. Hasil foto akan langsung dipakai di profil pegawai.
            </div>

                <div class="crop-container">
                    
                    <div class="editor-area">
                        <div class="crop-frame" id="cropBox">
                            <img id="cropImage" class="crop-image" src="<?= htmlspecialchars($foto_display) ?>" alt="Editor">
                        </div>
                        
                        <div class="control-group">
                            <div class="custom-file mb-3">
                                <input type="file" class="custom-file-input" id="fileInput" accept="image/*">
                                <label class="custom-file-label" for="fileInput">Pilih Foto Baru...</label>
                            </div>
                            <label class="text-muted small"><i class="fas fa-search-minus"></i> Zoom <i class="fas fa-search-plus"></i></label>
                            <input type="range" class="custom-range range-slider" id="zoomRange" min="0.5" max="3" step="0.01" value="1">
                            <div class="helper-text"><i class="fas fa-arrows-alt"></i> Geser gambar untuk memposisikan wajah di tengah lingkaran.</div>
                        </div>
                    </div>

                    <div class="preview-area">
                        <h5 class="mb-3 photo-panel-title">Preview Hasil</h5>
                        <canvas id="previewCanvas" class="preview-circle" width="300" height="300"></canvas>
                        
                        <div class="mt-4" style="width: 100%;">
                            <button id="btnSave" class="btn btn-primary btn-block btn-lg shadow-sm mb-2">
                                <i class="fas fa-save mr-1"></i> Simpan Foto
                            </button>
                            <a href="<?= htmlspecialchars($redirect_back) ?>" class="btn btn-light btn-block">
                                <i class="fas fa-times mr-1"></i> Batal
                            </a>
                        </div>
                        <div class="helper-text mt-2">Ukuran file maksimal: <b>3 MB</b></div>
                    </div>

                </div>
        </div>
    </div>
</section>

<form id="postForm" method="POST" style="display:none">
    <input type="hidden" name="cropped_image" id="cropped_image_input" value="">
    <input type="hidden" name="redirect_back" value="<?= htmlspecialchars($redirect_back) ?>">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// JAVASCRIPT LOGIC (SAMA SEPERTI YANG KAMU PUNYA, CUMA SAYA RAPIKAN DIKIT)
document.addEventListener('DOMContentLoaded', function() {
    const cropBox   = document.getElementById('cropBox');
    const img       = document.getElementById('cropImage');
    const fileInput = document.getElementById('fileInput');
    const zoomRange = document.getElementById('zoomRange');
    const canvas    = document.getElementById('previewCanvas');
    const ctx       = canvas.getContext('2d');
    const btnSave   = document.getElementById('btnSave');
    const postForm  = document.getElementById('postForm');
    const hiddenInput = document.getElementById('cropped_image_input');

    let state = { imgWidth: 0, imgHeight: 0, scale: 1, posX: 0, posY: 0, isDragging: false, startX: 0, startY: 0, boxSize: 320 };

    img.onload = function() {
        state.imgWidth  = img.naturalWidth;
        state.imgHeight = img.naturalHeight;
        fitImageToBox();
        render(); 
    };

    function fitImageToBox() {
        const ratioW = state.boxSize / state.imgWidth;
        const ratioH = state.boxSize / state.imgHeight;
        state.scale = Math.max(ratioW, ratioH); 
        state.posX = (state.boxSize - (state.imgWidth * state.scale)) / 2;
        state.posY = (state.boxSize - (state.imgHeight * state.scale)) / 2;
        zoomRange.value = state.scale;
        zoomRange.min = state.scale * 0.5; 
        zoomRange.max = state.scale * 3;   
    }

    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if(file.size > 5 * 1024 * 1024) { 
             Swal.fire('File Terlalu Besar', 'Mohon pilih foto di bawah 5MB.', 'warning');
             this.value = ''; return;
        }
        e.target.nextElementSibling.innerText = file.name;
        const reader = new FileReader();
        reader.onload = function(ev) { img.src = ev.target.result; }
        reader.readAsDataURL(file);
    });

    function render() {
        img.style.width     = (state.imgWidth * state.scale) + 'px';
        img.style.height    = (state.imgHeight * state.scale) + 'px';
        img.style.transform = `translate(${state.posX}px, ${state.posY}px)`;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        const ratioCanvas = canvas.width / state.boxSize; 
        const drawX = state.posX * ratioCanvas;
        const drawY = state.posY * ratioCanvas;
        const drawW = (state.imgWidth * state.scale) * ratioCanvas;
        const drawH = (state.imgHeight * state.scale) * ratioCanvas;
        ctx.drawImage(img, 0, 0, state.imgWidth, state.imgHeight, drawX, drawY, drawW, drawH);
    }

    zoomRange.addEventListener('input', function() {
        const oldScale = state.scale;
        const newScale = parseFloat(this.value);
        const boxCenter = state.boxSize / 2;
        const imgRelCenterX = (boxCenter - state.posX) / oldScale;
        const imgRelCenterY = (boxCenter - state.posY) / oldScale;
        state.scale = newScale;
        state.posX = boxCenter - (imgRelCenterX * newScale);
        state.posY = boxCenter - (imgRelCenterY * newScale);
        render();
    });

    const startDrag = (e) => { e.preventDefault(); state.isDragging = true; state.startX = getX(e); state.startY = getY(e); cropBox.style.cursor = 'grabbing'; };
    const doDrag = (e) => {
        if (!state.isDragging) return;
        e.preventDefault(); 
        const curX = getX(e); const curY = getY(e);
        state.posX += curX - state.startX;
        state.posY += curY - state.startY;
        state.startX = curX; state.startY = curY;
        render();
    };
    const stopDrag = () => { state.isDragging = false; cropBox.style.cursor = 'grab'; };
    const getX = (e) => e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
    const getY = (e) => e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;

    cropBox.addEventListener('mousedown', startDrag); window.addEventListener('mousemove', doDrag); window.addEventListener('mouseup', stopDrag);
    cropBox.addEventListener('touchstart', startDrag, {passive:false}); window.addEventListener('touchmove', doDrag, {passive:false}); window.addEventListener('touchend', stopDrag);

    btnSave.addEventListener('click', function() {
        Swal.fire({title: 'Memproses...', text: 'Sedang menyimpan gambar', allowOutsideClick: false, didOpen: () => { Swal.showLoading() }});
        setTimeout(() => {
            const outSize = 600; 
            const outCanvas = document.createElement('canvas');
            outCanvas.width = outSize; outCanvas.height = outSize;
            const outCtx = outCanvas.getContext('2d');
            outCtx.fillStyle = "#ffffff";
            outCtx.fillRect(0, 0, outSize, outSize);
            const ratio = outSize / state.boxSize;
            const dX = state.posX * ratio;
            const dY = state.posY * ratio;
            const dW = (state.imgWidth * state.scale) * ratio;
            const dH = (state.imgHeight * state.scale) * ratio;
            outCtx.drawImage(img, 0, 0, state.imgWidth, state.imgHeight, dX, dY, dW, dH);
            const dataURL = outCanvas.toDataURL('image/jpeg', 0.9); 
            
            hiddenInput.value = dataURL;
            postForm.submit();
        }, 300);
    });

    if (img.complete) img.onload();
});
</script>
