<?php
/*********************************************************
 * FILE    : pages/pegawai/view-detail-data-pegawai.php
 * MODULE  : Detail Pegawai (Versi Aman & Offline)
 * VERSION : v5.6
 *********************************************************/

if (session_id() === '') session_start();

// --- 1. CEK LOGIN ---
if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

include "dist/koneksi.php";

// --- 2. LOGIKA ID & HAK AKSES ---
if (!isset($_GET['id_peg']) || empty($_GET['id_peg'])) {
    echo '<div class="alert alert-danger m-4">Error: ID Pegawai tidak ditemukan di URL.</div>';
    exit;
}

// [SECURITY] Sanitasi input ID untuk mencegah SQL Injection
$id_peg = mysqli_real_escape_string($conn, $_GET['id_peg']);
$hak_akses_session = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : 'user';

// Admin DAN Kepala boleh edit Biodata & Keluarga
$can_edit = ($hak_akses_session === 'admin' || $hak_akses_session === 'kepala');

// Link Kembali (Sesuai Hak Akses)
$link_back = ($hak_akses_session == 'kepala')
    ? (function_exists('page_url') ? page_url('dashboard-cabang') : "home-admin.php?page=dashboard-cabang")
    : (function_exists('page_url') ? page_url('form-view-data-pegawai') : "home-admin.php?page=form-view-data-pegawai");

// --- 3. QUERY DATA UTAMA ---
$tampilPeg = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg = '$id_peg'");
if (mysqli_num_rows($tampilPeg) == 0) {
    echo '<div class="alert alert-warning m-4">Data pegawai tidak ditemukan.</div>';
    exit;
}
$peg = mysqli_fetch_array($tampilPeg);

// --- 4. ASSETS FOTO ---
// --- 4. ASSETS FOTO ---
$foto_db    = isset($peg['foto']) ? trim($peg['foto']) : '';
$jk         = isset($peg['jk']) ? strtolower(trim($peg['jk'])) : '';
$avatar_def = ($jk == 'laki-laki' || $jk == 'l') ? 'dist/img/avatar5.png' : 'dist/img/avatar3.png';

$src_foto   = $avatar_def; // Default awal ke avatar

if (!empty($foto_db)) {
    $folder_path = 'pages/assets/foto/';
    $extensions  = ['.jpg', '.jpeg', '.png', '.JPG', '.JPEG', '.PNG'];
    
    // Cek apakah di DB sudah ada ekstensinya (jaga-jaga data lama)
    if (file_exists($folder_path . $foto_db)) {
        $src_foto = $folder_path . $foto_db;
    } else {
        // Jika tidak ada ekstensi, looping cari filenya
        foreach ($extensions as $ext) {
            if (file_exists($folder_path . $foto_db . $ext)) {
                $src_foto = $folder_path . $foto_db . $ext;
                break; // Stop jika sudah ketemu
            }
        }
    }
}
?>

<style>
    .pegawai-detail-page .profile-header-cover { background: linear-gradient(135deg, #0f766e 0%, #3f83d5 100%); height: 138px; border-radius: 18px 18px 0 0; }
    .pegawai-detail-page .profile-user-img { width: 128px; height: 128px; margin-top: -64px; border: 5px solid #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.12); background: #fff; object-fit: cover; }
    .pegawai-detail-page .nav-pills-custom { border-bottom: 1px solid rgba(223, 230, 215, 0.95); margin-bottom: 0; padding: 0 1rem; }
    .pegawai-detail-page .nav-pills-custom .nav-link { color: #6c757d; font-weight: 700; padding: 14px 18px; border-radius: 0; border-bottom: 3px solid transparent; transition: all 0.2s; }
    .pegawai-detail-page .nav-pills-custom .nav-link:hover { color: var(--simpeg-primary); background: transparent; }
    .pegawai-detail-page .nav-pills-custom .nav-link.active { background-color: #0f766e !important; color: #fff !important; border-bottom: 3px solid #14b8a6; }
    .pegawai-detail-page .table-detail tr td { padding: 12px 15px; border-bottom: 1px solid #f1f5f3; }
    .pegawai-detail-page + .modal .modal-content,
    .modal .modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 26px 70px rgba(15, 35, 26, 0.22); }
    .modal .modal-header { background: #0f766e !important; color: #fff !important; border: 0; padding: 1rem 1.25rem; }
    .modal .modal-title { font-weight: 800; }
    .modal .close { opacity: .8; text-shadow: none; }
    .modal .modal-body { padding: 1rem 1.25rem; }
    .modal .table { margin-bottom: 0; border-radius: 14px; overflow: hidden; }
    .modal .table thead th { background: #f6faf7; color: #51645d; font-size: .78rem; text-transform: uppercase; border-bottom: 1px solid #dbe8df; }
    .modal .table tbody td { vertical-align: middle; }
    @media (max-width: 576px) {
        .pegawai-detail-page .nav-pills-custom { display: flex; flex-wrap: nowrap; overflow-x: auto; padding: .35rem; }
        .pegawai-detail-page .nav-pills-custom .nav-link { white-space: nowrap; padding: 11px 14px; border-radius: 12px; }
    }
</style>

<section class="content simpeg-page pegawai-detail-page">
    <div class="container-fluid">
        <div class="simpeg-page-header">
            <div>
                <h1 class="simpeg-page-title">Detail Pegawai</h1>
                <p class="simpeg-page-subtitle">Melihat biodata, keluarga, riwayat, dan aksi lanjutan pegawai dalam satu halaman.</p>
            </div>
            <div>
                <a href="<?= $link_back ?>" class="btn btn-light border shadow-sm"><i class="fa fa-arrow-left mr-1"></i> Kembali</a>
            </div>
        </div>
        <div class="row">
            
            <div class="col-md-4 col-lg-3 mb-4">
                
                <div class="card shadow-sm border-0">
                    <div class="profile-header-cover"></div>
                    <div class="card-body text-center pt-0">
                        <?php if($can_edit): ?>
                        <a class="d-inline-block" href="home-admin.php?page=form-ganti-foto&id_peg=<?= urlencode($peg['id_peg']); ?>&source=detail" title="Klik foto untuk ganti foto">
                            <img class="profile-user-img img-fluid img-circle"
                                 src="<?php echo $src_foto; ?>?time=<?php echo time(); ?>"
                                 onerror="this.src='<?php echo $avatar_def; ?>';">
                        </a>
                        <?php else: ?>
                        <div class="fancybox-trigger">
                            <img class="profile-user-img img-fluid img-circle"
                                 src="<?php echo $src_foto; ?>?time=<?php echo time(); ?>"
                                 onerror="this.src='<?php echo $avatar_def; ?>';">
                        </div>
                        <?php endif; ?>
                        
                        <h4 class="mt-3 mb-1 font-weight-bold"><?php echo htmlspecialchars($peg['nama']); ?></h4>
                        <p class="text-muted mb-2 small"><?php echo htmlspecialchars($peg['id_peg']); ?></p>
                        <span class="badge badge-primary px-3 py-1 rounded-pill mb-4"><?php echo htmlspecialchars($peg['status_kepeg']); ?></span>
                        
                        <div class="text-left border-top pt-3">
                            <p class="text-muted small mb-1"><i class="fas fa-phone mr-2"></i> Telepon</p>
                            <h6 class="mb-3 ml-4"><?php echo $peg['telp'] ? htmlspecialchars($peg['telp']) : '-'; ?></h6>
                            <p class="text-muted small mb-1"><i class="fas fa-envelope mr-2"></i> Email</p>
                            <h6 class="mb-0 ml-4 small text-truncate"><?php echo $peg['email'] ? htmlspecialchars($peg['email']) : '-'; ?></h6>
                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white font-weight-bold border-bottom-0">
                        <i class="fas fa-th mr-2 text-primary"></i> Menu Cepat
                    </div>
                    <div class="card-body p-2">
                        <div class="simpeg-quick-actions">
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#pensiun"><i class="fa fa-user-clock"></i><span>Pensiun</span></button>
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#naikpkt"><i class="fa fa-layer-group"></i><span>Pangkat</span></button>
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#naikgj"><i class="fa fa-money-bill"></i><span>Gaji</span></button>
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#dp3"><i class="fa fa-chart-line"></i><span>SKP</span></button>
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#bahasa"><i class="fa fa-language"></i><span>Bahasa</span></button>
                            <button type="button" class="simpeg-quick-btn" data-toggle="modal" data-target="#pendidikan"><i class="fa fa-graduation-cap"></i><span>Sekolah</span></button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-md-8 col-lg-9">
                <div class="card shadow-sm border-0" style="min-height: 600px;">
                    <div class="card-header p-0 border-bottom-0 bg-white rounded-top">
                        <ul class="nav nav-pills nav-pills-custom" id="custom-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" id="tab-bio" data-toggle="pill" href="#bio" role="tab">Biodata</a></li>
                            <li class="nav-item"><a class="nav-link" id="tab-keluarga" data-toggle="pill" href="#keluarga" role="tab">Keluarga</a></li>
                            <li class="nav-item"><a class="nav-link" id="tab-riwayat" data-toggle="pill" href="#riwayat" role="tab">Riwayat</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            
                            <div class="tab-pane fade show active" id="bio" role="tabpanel">
                                <table class="table-detail simpeg-info-table w-100">
                                    <tr><td>NIK</td><td>: <?php echo htmlspecialchars($peg['nip']); ?></td></tr>
                                    <tr><td>Nama Lengkap</td><td>: <?php echo htmlspecialchars($peg['nama']); ?></td></tr>
                                    <tr><td>TTL</td><td>: <?php echo htmlspecialchars($peg['tempat_lhr']) . ', ' . date('d-m-Y', strtotime($peg['tgl_lhr'])); ?></td></tr>
                                    <tr><td>Jenis Kelamin</td><td>: <?php echo htmlspecialchars($peg['jk']); ?></td></tr>
                                    <tr><td>Agama</td><td>: <?php echo htmlspecialchars($peg['agama']); ?></td></tr>
                                    <tr><td>Golongan Darah</td><td>: <?php echo htmlspecialchars($peg['gol_darah']); ?></td></tr>
                                    <tr><td>Status Nikah</td><td>: <?php echo htmlspecialchars($peg['status_nikah']); ?></td></tr>
                                    <tr><td>Alamat</td><td>: <?php echo htmlspecialchars($peg['alamat']); ?></td></tr>
                                    <tr><td>No BPJS TK</td><td>: <?php echo htmlspecialchars($peg['bpjstk']); ?></td></tr>
                                    <tr><td>Tgl Masuk Kerja</td><td>: <?php echo date('d-m-Y', strtotime($peg['tmt_kerja'])); ?></td></tr>
                                </table>
                                
                                <?php if($can_edit): ?>
                                <div class="mt-4 text-right">
                                    <a href="home-admin.php?page=form-master-data-pegawai&mode=edit&id=<?= urlencode($peg['id_peg']); ?>" class="btn btn-warning shadow-sm"><i class="fa fa-edit"></i> Edit Biodata</a>
                                    <a href="./pages/report/print-biodata-pegawai.php?id_peg=<?= urlencode($id_peg); ?>" target="_blank" class="btn btn-primary shadow-sm ml-2"><i class="fas fa-print"></i> Cetak CV</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="keluarga" role="tabpanel">
                                
                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-3">Pasangan (Suami/Istri)</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Pekerjaan</th><th>Status</th><?php if($can_edit) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php 
                                            // QUERY AMAN
                                            $qSi = mysqli_query($conn,"SELECT a.*, (SELECT desc_pekerjaan FROM tb_master_pekerjaan WHERE id_pekerjaan=a.id_pekerjaan) as nm_kerja FROM tb_suamiistri a WHERE id_peg='$id_peg'");
                                            if(mysqli_num_rows($qSi)>0) {
                                                while($si=mysqli_fetch_array($qSi)){ 
                                                    $id_si = isset($si['id_si']) ? $si['id_si'] : 0; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($si['nama']) ?></td>
                                                    <td><?= htmlspecialchars($si['tmp_lhr']) ?>, <?= htmlspecialchars($si['tgl_lhr']) ?></td>
                                                    <td><?= htmlspecialchars($si['nm_kerja']) ?></td>
                                                    <td><?= htmlspecialchars($si['status_hub']) ?></td>
                                                    <?php if($can_edit): ?>
                                                        <td class="text-center">
                                                            <a href="home-admin.php?page=form-master-data-suami-istri&mode=edit&id_si=<?=$id_si?>&uid=<?=urlencode($id_peg)?>" class="btn btn-xs btn-info"><i class="fa fa-edit"></i></a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='5' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-3">Anak</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Pendidikan</th><th>Anak Ke</th><?php if($can_edit) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php $qAnak = mysqli_query($conn,"SELECT * FROM tb_anak WHERE id_peg='$id_peg' ORDER BY anak_ke");
                                            if(mysqli_num_rows($qAnak)>0) {
                                                while($ak=mysqli_fetch_array($qAnak)){ 
                                                    $id_ak = isset($ak['id_anak']) ? $ak['id_anak'] : 0; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ak['nama']) ?></td>
                                                    <td><?= htmlspecialchars($ak['tmp_lhr']) ?>, <?= htmlspecialchars($ak['tgl_lhr']) ?></td>
                                                    <td><?= htmlspecialchars($ak['pendidikan']) ?></td>
                                                    <td><?= htmlspecialchars($ak['anak_ke']) ?></td>
                                                    <?php if($can_edit): ?>
                                                        <td class="text-center">
                                                            <a href="home-admin.php?page=form-master-data-anak&mode=edit&id_anak=<?=$id_ak?>&uid=<?=urlencode($id_peg)?>" class="btn btn-xs btn-info"><i class="fa fa-edit"></i></a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='5' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-3">Orang Tua</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="bg-light"><tr><th>Nama</th><th>TTL</th><th>Hubungan</th><?php if($can_edit) echo '<th>Aksi</th>'; ?></tr></thead>
                                        <tbody>
                                            <?php $qOrtu = mysqli_query($conn,"SELECT * FROM tb_ortu WHERE id_peg='$id_peg'");
                                            if(mysqli_num_rows($qOrtu)>0) {
                                                while($or=mysqli_fetch_array($qOrtu)){ 
                                                    $id_or = isset($or['id_ortu']) ? $or['id_ortu'] : 0; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($or['nama']) ?></td>
                                                    <td><?= htmlspecialchars($or['tmp_lhr']) ?>, <?= htmlspecialchars($or['tgl_lhr']) ?></td>
                                                    <td><?= htmlspecialchars($or['status_hub']) ?></td>
                                                    <?php if($can_edit): ?>
                                                        <td class="text-center">
                                                            <a href="home-admin.php?page=form-master-data-ortu&mode=edit&id_ortu=<?=$id_or?>&uid=<?=urlencode($id_peg)?>" class="btn btn-xs btn-info"><i class="fa fa-edit"></i></a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php } } else { echo "<tr><td colspan='4' class='text-center text-muted small'>Tidak ada data</td></tr>"; } ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="riwayat" role="tabpanel">
                                <div class="row">
                                    <div class="col-12"><h6 class="font-weight-bold mb-3">Data Riwayat</h6></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#pengangkatan"><i class="fa fa-file-contract mr-2"></i> Pengangkatan</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#mutasi"><i class="fa fa-exchange-alt mr-2"></i> Mutasi</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#diklat"><i class="fa fa-chalkboard-teacher mr-2"></i> Diklat</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#sertifikasi"><i class="fa fa-certificate mr-2"></i> Sertifikasi</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#hukum"><i class="fa fa-gavel mr-2"></i> Pelanggaran</button></div>
                                    <div class="col-md-4 mb-2"><button class="btn btn-outline-secondary btn-block text-left" data-toggle="modal" data-target="#jabatan"><i class="fa fa-briefcase mr-2"></i> Jabatan</button></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div id="pensiun" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Info Pensiun</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><table class="table"><tr><td>Tgl Lahir</td><td class="font-weight-bold"><?= htmlspecialchars($peg['tgl_lhr']) ?></td></tr><tr><td>Jatuh Tempo</td><td class="font-weight-bold text-danger"><?= htmlspecialchars($peg['tgl_pensiun']) ?></td></tr></table></div>
        </div>
    </div>
</div>

<div id="naikgj" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Proyeksi Kenaikan Gaji</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Periode</th><th>Estimasi Tanggal</th></tr></thead>
                    <tbody>
                        <?php if($peg['tgl_naikgaji'] && $peg['tgl_pensiun']){
                            $begin = new DateTime($peg['tgl_naikgaji']); $end = new DateTime($peg['tgl_pensiun']); $no=0;
                            for($i = $begin; $begin <= $end; $i->modify('+2 year')){ $no++; if($no > 5) break; echo "<tr><td>Ke-$no</td><td>".$i->format("d-m-Y")."</td></tr>"; }
                        } else { echo "<tr><td colspan='2'>Data tanggal tidak lengkap.</td></tr>"; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="naikpkt" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pangkat</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2"><strong>Estimasi Naik:</strong> <?php if(!empty($peg['tgl_naikpangkat'])){ $next = new DateTime($peg['tgl_naikpangkat']); $next->modify('+4 year'); echo $next->format('d-m-Y'); } else { echo "Data belum tersedia"; } ?></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light"><tr><th>Pangkat</th><th>Gol</th><th>TMT</th><th>SK</th></tr></thead>
                        <tbody>
                            <?php $qPan = mysqli_query($conn,"SELECT * FROM tb_pangkat WHERE id_peg='$id_peg' ORDER BY tgl_sk DESC"); 
                            while($p=mysqli_fetch_array($qPan)){ ?>
                            <tr>
                                <td><?= htmlspecialchars($p['pangkat']) ?></td>
                                <td><?= htmlspecialchars($p['gol']) ?></td>
                                <td><?= htmlspecialchars($p['tmt_pangkat']) ?></td>
                                <td><?= htmlspecialchars($p['no_sk']) ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="bahasa" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Bahasa</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <table class="table table-bordered"><thead><tr><th>Bahasa</th><th>Kemampuan</th></tr></thead><tbody>
                    <?php $qBhs = mysqli_query($conn,"SELECT * FROM tb_bahasa WHERE id_peg='$id_peg'"); while($b=mysqli_fetch_array($qBhs)){ ?>
                    <tr><td><?= htmlspecialchars($b['bahasa']) ?></td><td><?= htmlspecialchars($b['kemampuan']) ?></td></tr>
                    <?php } ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div id="pendidikan" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pendidikan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body table-responsive">
                <table class="table table-bordered table-hover"><thead><tr><th>Jenjang</th><th>Nama Sekolah</th><th>Jurusan</th><th>Lulus</th></tr></thead><tbody>
                    <?php $qSek = mysqli_query($conn,"SELECT * FROM tb_pendidikan WHERE id_peg='$id_peg' ORDER BY tgl_ijazah DESC"); while($s=mysqli_fetch_array($qSek)){ ?>
                    <tr>
                        <td><?= htmlspecialchars($s['jenjang']) ?></td>
                        <td><?= htmlspecialchars($s['nama_sekolah']) ?></td>
                        <td><?= htmlspecialchars($s['jurusan']) ?></td>
                        <td><?= htmlspecialchars($s['tgl_ijazah']) ?></td>
                    </tr>
                    <?php } ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div id="jabatan" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Jabatan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Jabatan</th><th>TMT</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php 
                        $qJab = mysqli_query($conn,"SELECT j.tmt_jabatan, j.status_jab, m.nama_jabatan 
                                                    FROM tb_jabatan j 
                                                    LEFT JOIN tb_master_jabatan m ON j.jabatan = m.nama_jabatan 
                                                    WHERE j.id_peg='$id_peg' 
                                                    ORDER BY j.tmt_jabatan DESC"); 
                        while($j=mysqli_fetch_array($qJab)){ ?>
                        <tr>
                            <td><?= !empty($j['nama_jabatan']) ? htmlspecialchars($j['nama_jabatan']) : '<i>(Kode Tidak Dikenal)</i>' ?></td>
                            <td><?= htmlspecialchars($j['tmt_jabatan']) ?></td>
                            <td><?= htmlspecialchars($j['status_jab']) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="dp3" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white"><h5 class="modal-title">Sasaran Kerja (SKP)</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body table-responsive">
                <table class="table table-bordered table-hover"><thead><tr><th>Periode</th><th>Nilai</th><th>Mutu</th></tr></thead><tbody>
                <?php $qDp3 = mysqli_query($conn,"SELECT * FROM tb_dp3 WHERE id_peg='$id_peg' ORDER BY periode_akhir DESC"); while($d=mysqli_fetch_array($qDp3)){ $jml = $d['nilai_kesetiaan']+$d['nilai_prestasi']+$d['nilai_tgjwb']+$d['nilai_ketaatan']+$d['nilai_kejujuran']+$d['nilai_kerjasama']+$d['nilai_prakarsa']+$d['nilai_kepemimpinan']; ?>
                <tr>
                    <td><?= htmlspecialchars($d['periode_akhir']) ?></td>
                    <td><?= htmlspecialchars($jml) ?></td>
                    <td><?= htmlspecialchars($d['hasil_penilaian']) ?></td>
                </tr>
                <?php } ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div id="pengangkatan" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pengangkatan</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Status</th><th>Tgl</th><th>No SK</th><th>File</th></tr></thead><tbody><?php $qAng = mysqli_query($conn,"SELECT * FROM tb_angkat WHERE id_peg_baru='$id_peg'"); while($a=mysqli_fetch_array($qAng)){ ?><tr><td><?= htmlspecialchars($a['jns_mutasi']) ?></td><td><?= htmlspecialchars($a['tgl_mutasi']) ?></td><td><?= htmlspecialchars($a['no_mutasi']) ?></td><td><a href="home-admin.php?page=view-pengangkatan&id_angkat=<?=urlencode($a['id_angkat'])?>" target="_blank"><i class="fa fa-file-pdf"></i></a></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="mutasi" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Mutasi</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Jenis</th><th>Tgl</th><th>No SK</th></tr></thead><tbody><?php $qMut = mysqli_query($conn,"SELECT * FROM tb_mutasi WHERE id_peg='$id_peg'"); while($m=mysqli_fetch_array($qMut)){ ?><tr><td><?= htmlspecialchars($m['jns_mutasi']) ?></td><td><?= htmlspecialchars($m['tgl_mutasi']) ?></td><td><?= htmlspecialchars($m['no_mutasi']) ?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="diklat" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Diklat</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Nama</th><th>Penyelenggara</th><th>Tahun</th></tr></thead><tbody><?php $qDik = mysqli_query($conn,"SELECT * FROM tb_diklat WHERE id_peg='$id_peg'"); while($d=mysqli_fetch_array($qDik)){ ?><tr><td><?= htmlspecialchars($d['diklat']) ?></td><td><?= htmlspecialchars($d['penyelenggara']) ?></td><td><?= htmlspecialchars($d['tahun']) ?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="sertifikasi" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Sertifikasi</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Sertifikasi</th><th>Exp</th><th>Status</th></tr></thead><tbody><?php $qSer = mysqli_query($conn,"SELECT *, DATEDIFF(tgl_expired, CURDATE()) AS selisih FROM tb_sertifikasi WHERE id_peg='$id_peg'"); while($s=mysqli_fetch_array($qSer)){ ?><tr><td><?= htmlspecialchars($s['sertifikasi']) ?></td><td><?= htmlspecialchars($s['tgl_expired']) ?></td><td><?= ($s['selisih'] < 0) ? 'Exp' : 'Aktif' ?></td></tr><?php } ?></tbody></table></div></div></div></div>
<div id="hukum" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Riwayat Pelanggaran</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Hukuman</th><th>Tgl SK</th></tr></thead><tbody><?php $qHuk = mysqli_query($conn,"SELECT * FROM tb_hukuman WHERE id_peg='$id_peg'"); while($h=mysqli_fetch_array($qHuk)){ ?><tr><td><?= htmlspecialchars($h['hukuman']) ?></td><td><?= htmlspecialchars($h['tgl_sk']) ?></td></tr><?php } ?></tbody></table></div></div></div></div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
