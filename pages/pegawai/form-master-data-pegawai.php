<?php
/*********************************************************
 * FILE    : pages/pegawai/form-master-data-pegawai.php
 * MODULE  : Form Input Pegawai (Clean Layout & Fixed UI)
 * VERSION : v3.0 (No Header, Vanilla JS)
 *********************************************************/

// --- BAGIAN INI SAYA HAPUS AGAR HEADER/BREADCRUMB HILANG ---
// include "komponen/header.php"; 

include 'dist/koneksi.php';

// --- SECURITY: SANITASI INPUT ---
$mode       = isset($_GET['mode']) ? $_GET['mode'] : 'tambah';
$id_peg_raw = isset($_GET['id']) ? $_GET['id'] : null;
$id_peg     = $id_peg_raw ? mysqli_real_escape_string($conn, $id_peg_raw) : null;

// Inisialisasi Data Kosong
$data = array(
    'id_peg' => '', 'nip' => '', 'nama' => '', 'tempat_lhr' => '', 'tgl_lhr' => '',
    'agama' => '', 'jk' => '', 'gol_darah' => '', 'status_nikah' => '', 'status_kepeg' => '',
    'alamat' => '', 'telp' => '', 'email' => '', 'bpjstk' => '', 'bpjskes' => '', 'foto' => ''
);

$dataUser = array('hak_akses' => 'User', 'status_aktif' => 'Y');

// --- LOGIKA EDIT ---
if ($mode == 'edit' && $id_peg) {
    $q = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg='".$id_peg."'");
    $data = mysqli_fetch_assoc($q);
    
    if (!$data) {
        echo "<script>alert('Data pegawai tidak ditemukan'); window.location='home-admin.php?page=form-view-data-pegawai';</script>";
        exit;
    }

    $qUser = mysqli_query($conn, "SELECT * FROM tb_user WHERE id_pegawai='".$id_peg."'");
    if(mysqli_num_rows($qUser) > 0){
        $dataUser = mysqli_fetch_assoc($qUser);
    }
}

// Redirect Logic
$redirect_back = function_exists('page_url') ? page_url('form-view-data-pegawai') : "home-admin.php?page=form-view-data-pegawai";
if(isset($_SESSION['id_pegawai']) && $_SESSION['id_pegawai'] == $id_peg){
    $redirect_back = function_exists('page_url') ? page_url('profil-pegawai') : "home-admin.php?page=profil-pegawai";
} elseif($mode == 'edit') {
    $redirect_back = function_exists('page_url') ? page_url('view-detail-data-pegawai', array('id_peg' => $id_peg)) : "home-admin.php?page=view-detail-data-pegawai&id_peg=" . urlencode($id_peg);
}
?>

<style>
    .pegawai-form-page .custom-file-label {
        border-radius: 12px;
        min-height: 44px;
        padding-top: 0.6rem;
        border-color: var(--simpeg-border);
    }
    .pegawai-form-page .custom-file-label::after {
        border-radius: 0 12px 12px 0;
        background: var(--simpeg-surface-muted);
        color: var(--simpeg-text);
        font-weight: 700;
    }
    .pegawai-form-page .img-thumbnail {
        border: 3px solid rgba(255,255,255,0.95);
        box-shadow: var(--simpeg-shadow-soft);
        background: #fff;
    }
</style>

<section class="content simpeg-page pegawai-form-page">
<div class="container-fluid">
    <div class="simpeg-form-shell">
    <div class="simpeg-page-header">
        <div>
            <h1 class="simpeg-page-title"><?php echo $mode === 'edit' ? 'Ubah Biodata Pegawai' : 'Tambah Pegawai Baru'; ?></h1>
            <p class="simpeg-page-subtitle">Lengkapi identitas, data pribadi, kontak, dan foto pegawai dalam satu template yang konsisten.</p>
        </div>
        <a href="<?php echo htmlspecialchars($redirect_back); ?>" class="btn btn-light border"><i class="fas fa-arrow-left mr-2"></i>Kembali</a>
    </div>
    <form action="pages/pegawai/simpan-data-pegawai.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        
        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_back); ?>">
        
        <?php if ($mode == 'edit'): ?>
            <input type="hidden" name="id_peg" value="<?php echo htmlspecialchars($data['id_peg']); ?>">
            <input type="hidden" name="hak_akses_lama" value="<?php echo htmlspecialchars($dataUser['hak_akses']); ?>">
            <input type="hidden" name="status_aktif_lama" value="<?php echo htmlspecialchars($dataUser['status_aktif']); ?>">
            <input type="hidden" name="hak_akses" value="<?php echo htmlspecialchars($dataUser['hak_akses']); ?>">
            <input type="hidden" name="status_aktif" value="<?php echo htmlspecialchars($dataUser['status_aktif']); ?>">
        <?php endif; ?>

        <div class="card simpeg-form-card">
                    <div class="card-header">
                        <h4 class="card-title mb-1"><i class="fas fa-user-edit mr-2 text-primary"></i>Form <?php echo ucfirst(htmlspecialchars($mode)); ?> Biodata</h4>
                        <p class="small mb-0">Pastikan data inti pegawai valid sebelum disimpan.</p>
                    </div>
                    <div class="card-body simpeg-form-grid">

                        <div class="simpeg-section-title"><i class="fas fa-id-card text-primary"></i> Identitas Utama</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>ID Pegawai <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-badge"></i></span></div>
                                    <input type="text" name="id_peg" class="form-control" value="<?php echo htmlspecialchars($data['id_peg']); ?>" 
                                           <?php echo $mode == 'edit' ? 'disabled' : 'required'; ?> placeholder="Masukkan ID Pegawai">
                                </div>
                                <?php if($mode == 'edit'): ?><small class="text-muted">ID Pegawai tidak dapat diubah.</small><?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                    <input type="text" name="nama" class="form-control" value="<?php echo htmlspecialchars($data['nama']); ?>" required placeholder="Nama lengkap beserta gelar">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>NIK <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-fingerprint"></i></span></div>
                                    <input type="number" name="nip" class="form-control" value="<?php echo htmlspecialchars($data['nip']); ?>" required placeholder="16 digit NIK">
                                </div>
                            </div>
                        </div>

                        <div class="simpeg-section-title mt-4"><i class="fas fa-user-tag text-primary"></i> Data Pribadi</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tempat Lahir</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span></div>
                                    <input type="text" name="tempat_lhr" class="form-control" value="<?php echo htmlspecialchars($data['tempat_lhr']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Tanggal Lahir</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                    <input type="date" name="tgl_lhr" class="form-control" value="<?php echo htmlspecialchars($data['tgl_lhr']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Agama</label>
                                <select name="agama" class="form-control custom-select" required>
                                    <option value="">-- Pilih Agama --</option>
                                    <?php foreach(array('Islam','Protestan','Katolik','Hindu','Budha','KongHuCu') as $a) {
                                        $sel = ($data['agama'] == $a) ? "selected" : "";
                                        echo "<option value='$a' $sel>$a</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Jenis Kelamin</label>
                                <select name="jk" class="form-control custom-select" required>
                                    <option value="">-- Pilih JK --</option>
                                    <option value="Laki-laki" <?php echo $data['jk'] == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="Perempuan" <?php echo $data['jk'] == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Gol. Darah</label>
                                <select name="gol_darah" class="form-control custom-select">
                                    <option value="-">-</option>
                                    <?php foreach (array('A','B','AB','O') as $gol) {
                                        $sel = ($data['gol_darah'] == $gol) ? "selected" : "";
                                        echo "<option value='$gol' $sel>$gol</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Status Pernikahan</label>
                                <select name="status_nikah" class="form-control custom-select">
                                    <?php foreach (array('Menikah','Belum Menikah','Janda','Duda') as $s) {
                                        $sel = ($data['status_nikah'] == $s) ? "selected" : "";
                                        echo "<option value='$s' $sel>$s</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Status Kepegawaian</label>
                                <select name="status_kepeg" class="form-control custom-select" required>
                                    <?php foreach (array('Tetap','Kontrak','Outsource') as $s) {
                                        $sel = ($data['status_kepeg'] == $s) ? "selected" : "";
                                        echo "<option value='$s' $sel>$s</option>";
                                    } ?>
                                </select>
                            </div>
                        </div>

                        <div class="simpeg-section-title mt-4"><i class="fas fa-address-book text-primary"></i> Kontak dan Administrasi</div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label>Alamat Domisili</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-home"></i></span></div>
                                    <textarea name="alamat" class="form-control" rows="2" required><?php echo htmlspecialchars($data['alamat']); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>No. Telepon / WA</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                    <input type="text" name="telp" class="form-control" value="<?php echo htmlspecialchars($data['telp']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($data['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>No. BPJS Ketenagakerjaan</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-briefcase-medical"></i></span></div>
                                    <input type="text" name="bpjstk" class="form-control" value="<?php echo htmlspecialchars($data['bpjstk']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>No. BPJS Kesehatan</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-heartbeat"></i></span></div>
                                    <input type="text" name="bpjskes" class="form-control" value="<?php echo htmlspecialchars($data['bpjskes']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="simpeg-section-title mt-4"><i class="fas fa-camera text-primary"></i> Foto Profil</div>
                        <div class="form-group">
                            <div class="row align-items-center">
                                <div class="col-md-9">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="foto" name="foto">
                                        
                                        <?php 
                                            $label_foto = "Pilih file foto...";
                                            if ($mode == 'edit' && !empty($data['foto'])) {
                                                $label_foto = $data['foto'];
                                            }
                                        ?>
                                        <label class="custom-file-label text-truncate" for="foto">
                                            <?php echo htmlspecialchars($label_foto); ?>
                                        </label>
                                    </div>
                                    <small class="text-muted mt-2 d-block">* Kosongkan jika tidak ingin mengubah foto.</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <?php 
                                        $fotoShow = 'dist/img/avatar5.png'; // Default
                                        if ($mode == 'edit' && !empty($data['foto'])) {
                                            $path = 'pages/assets/foto/' . $data['foto'];
                                            if (file_exists($path)) {
                                                $fotoShow = $path;
                                            }
                                        }
                                    ?>
                                    <img src="<?php echo $fotoShow; ?>?t=<?php echo time(); ?>" 
                                         class="img-thumbnail rounded-circle shadow-sm" 
                                         style="width: 80px; height: 80px; object-fit: cover;" 
                                         alt="Preview Foto">
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white">
                        <div class="simpeg-form-actions">
                            <a href="<?php echo htmlspecialchars($redirect_back); ?>" class="btn btn-light border">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-2"></i>Simpan Data</button>
                        </div>
                    </div>
                </div>
    </form>
</div>
</div>
</section>

<script>
  // 1. Script Ganti Label File (Vanilla JS - Aman tanpa jQuery)
  var fotoInput = document.querySelector('.custom-file-input');
  if (fotoInput) {
    fotoInput.addEventListener('change', function(e) {
      if (!document.getElementById("foto").files.length) return;
      var fileName = document.getElementById("foto").files[0].name;
      var nextSibling = e.target.nextElementSibling;
      nextSibling.innerText = fileName;
    });
  }

  // 2. Validasi Form
  (function() {
    'use strict';
    window.addEventListener('load', function() {
      var forms = document.getElementsByClassName('needs-validation');
      var validation = Array.prototype.filter.call(forms, function(form) {
        form.addEventListener('submit', function(event) {
          if (form.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    }, false);
  })();
</script>
