<?php
if (session_id() === '') session_start();
include 'dist/koneksi.php';
@include_once 'dist/functions.php';

if (!function_exists('od_e')) {
    function od_e($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('od_label')) {
    function od_label($key) {
        $labels = array(
            'nip' => 'NIK / NIP',
            'nama' => 'Nama Lengkap',
            'tempat_lhr' => 'Tempat Lahir',
            'tgl_lhr' => 'Tanggal Lahir',
            'jk' => 'Jenis Kelamin',
            'agama' => 'Agama',
            'gol_darah' => 'Golongan Darah',
            'status_nikah' => 'Status Nikah',
            'alamat' => 'Alamat',
            'telp' => 'Telepon',
            'email' => 'Email',
            'nik' => 'NIK',
            'tmp_lhr' => 'Tempat Lahir',
            'pendidikan' => 'Pendidikan',
            'id_pekerjaan' => 'ID Pekerjaan',
            'pekerjaan' => 'Pekerjaan',
            'status_hub' => 'Status Hubungan',
            'hp' => 'No HP',
            'bpjs_pasangan' => 'BPJS Pasangan',
            'anak_ke' => 'Anak Ke',
            'bpjs_anak' => 'BPJS Anak'
        );

        return isset($labels[$key]) ? $labels[$key] : ucwords(str_replace('_', ' ', $key));
    }
}

if (!function_exists('od_format_value')) {
    function od_format_value($key, $value) {
        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value === null || $value === '') {
            return '-';
        }

        if (in_array($key, array('tgl_lhr', 'tanggal_pengajuan', 'tanggal_otorisasi'), true)) {
            $ts = strtotime($value);
            return $ts ? date('d-m-Y', $ts) : $value;
        }

        return (string) $value;
    }
}

if (!function_exists('od_prepare_detail_data')) {
    function od_prepare_detail_data($jenis, $data) {
        if (!is_array($data)) {
            return array();
        }

        if ($jenis === 'biodata_update') {
            return $data;
        }

        if (strpos($jenis, 'keluarga_') === 0 && isset($data['payload']) && is_array($data['payload'])) {
            return $data['payload'];
        }

        return $data;
    }
}

$hak_akses = isset($_SESSION['hak_akses']) ? strtolower($_SESSION['hak_akses']) : '';
$can_approve = function_exists('userBisaApprovalOtorisasi') ? userBisaApprovalOtorisasi() : ($hak_akses === 'kepala' || $hak_akses === 'admin');
if (!$can_approve) {
    echo '<div class="alert alert-danger m-3">Anda tidak memiliki akses approval.</div>';
    exit;
}

$id_edit = isset($_GET['id_edit']) ? (int)$_GET['id_edit'] : 0;
if ($id_edit <= 0) {
    echo '<div class="alert alert-danger m-3">ID pengajuan tidak valid.</div>';
    exit;
}

$q = mysqli_query($conn, "
    SELECT ep.*, u.nama_user, p.nama AS nama_pegawai, p.nip,
           COALESCE(NULLIF(ep.kode_kantor, ''), LEFT(target_jab.unit_kerja, 3)) AS unit_target
    FROM tb_edit_pending ep
    LEFT JOIN tb_user u ON ep.id_user = u.id_user
    LEFT JOIN tb_pegawai p ON ep.id_peg = p.id_peg
    LEFT JOIN tb_jabatan target_jab ON ep.id_peg = target_jab.id_peg AND LOWER(target_jab.status_jab) = 'aktif'
    WHERE ep.id_edit = $id_edit
    LIMIT 1
");

if (!$q || mysqli_num_rows($q) === 0) {
    echo '<div class="alert alert-warning m-3">Pengajuan tidak ditemukan.</div>';
    exit;
}

$row = mysqli_fetch_assoc($q);
$unit_approval = function_exists('unitKerjaApprovalUser') ? unitKerjaApprovalUser() : (isset($_SESSION['kode_kantor']) ? $_SESSION['kode_kantor'] : '');
$allowed_units = function_exists('simpegScopeKantorApproval') ? simpegScopeKantorApproval($unit_approval) : array(substr($unit_approval, 0, 3));
if (!in_array($row['unit_target'], $allowed_units)) {
    echo '<div class="alert alert-danger m-3">Pengajuan ini bukan wilayah approval Anda.</div>';
    exit;
}

$status_display = $row['status_otorisasi'];
if ($status_display === 'pending') $status_display = 'Menunggu';
if ($status_display === 'approved') $status_display = 'Disetujui';
if ($status_display === 'rejected') $status_display = 'Ditolak';

$data_lama = json_decode($row['data_lama'], true);
$data_baru = json_decode($row['data_baru'], true);
$detail_lama = od_prepare_detail_data($row['jenis_data'], $data_lama);
$detail_baru = od_prepare_detail_data($row['jenis_data'], $data_baru);
?>

<style>
  .approval-detail-box {
    background: #f8fbfb;
    border: 1px solid #dfeceb;
    border-radius: 16px;
    padding: 14px;
    min-height: 260px;
  }
  .approval-detail-box table {
    margin-bottom: 0;
  }
  .approval-detail-box td,
  .approval-detail-box th {
    vertical-align: top;
    border-top: 1px solid #e8f0ef;
  }
  .approval-detail-box tr:first-child td,
  .approval-detail-box tr:first-child th {
    border-top: 0;
  }
  .approval-empty {
    color: #708281;
    font-style: italic;
  }
</style>

<section class="content-header pt-4 pb-2">
  <div class="container-fluid">
    <h3 class="font-weight-bold mb-1">Detail Approval Perubahan</h3>
    <p class="text-muted mb-0">Periksa pengajuan sebelum menyetujui atau menolak.</p>
  </div>
</section>

<section class="content">
  <div class="container-fluid">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6 class="font-weight-bold text-primary">Pegawai</h6>
            <table class="table table-sm table-bordered">
              <tr><th style="width:35%">ID Pegawai</th><td><?php echo od_e($row['id_peg']); ?></td></tr>
              <tr><th>Nama</th><td><?php echo od_e($row['nama_pegawai']); ?></td></tr>
              <tr><th>NIK/NIP</th><td><?php echo od_e($row['nip']); ?></td></tr>
              <tr><th>Unit Kerja</th><td><?php echo od_e($row['unit_target']); ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="font-weight-bold text-primary">Pengajuan</h6>
            <table class="table table-sm table-bordered">
              <tr><th style="width:35%">Jenis</th><td><?php echo od_e($row['jenis_data']); ?></td></tr>
              <tr><th>Status</th><td><?php echo od_e($status_display); ?></td></tr>
              <tr><th>Diajukan Oleh</th><td><?php echo od_e($row['nama_user']); ?></td></tr>
              <tr><th>Tanggal</th><td><?php echo $row['tanggal_pengajuan'] ? date('d-m-Y H:i', strtotime($row['tanggal_pengajuan'])) : '-'; ?></td></tr>
            </table>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <h6 class="font-weight-bold">Data Lama</h6>
            <div class="approval-detail-box">
              <?php if (!empty($detail_lama)): ?>
                <table class="table table-sm">
                  <tbody>
                    <?php foreach ($detail_lama as $key => $value): ?>
                      <tr>
                        <th style="width:38%"><?php echo od_e(od_label($key)); ?></th>
                        <td><?php echo nl2br(od_e(od_format_value($key, $value))); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="approval-empty">Data lama tidak tersedia.</div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <h6 class="font-weight-bold">Data Baru</h6>
            <div class="approval-detail-box">
              <?php if (!empty($detail_baru)): ?>
                <table class="table table-sm">
                  <tbody>
                    <?php foreach ($detail_baru as $key => $value): ?>
                      <tr>
                        <th style="width:38%"><?php echo od_e(od_label($key)); ?></th>
                        <td><?php echo nl2br(od_e(od_format_value($key, $value))); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="approval-empty"><?php echo $row['data_baru'] !== '' ? od_e($row['data_baru']) : 'Data baru tidak tersedia.'; ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($status_display === 'Menunggu'): ?>
        <form action="pages/otorisasi/proses-otorisasi.php" method="POST" class="mt-3">
          <input type="hidden" name="id_edit[]" value="<?php echo (int)$row['id_edit']; ?>">
          <div class="form-group">
            <label>Komentar</label>
            <input type="text" name="komentar[<?php echo (int)$row['id_edit']; ?>]" class="form-control" placeholder="Opsional">
          </div>
          <div class="d-flex justify-content-end" style="gap:8px">
            <a href="home-admin.php?page=otorisasi-approval" class="btn btn-light">Kembali</a>
            <button type="submit" name="aksi[<?php echo (int)$row['id_edit']; ?>]" value="reject" class="btn btn-danger">Tolak</button>
            <button type="submit" name="aksi[<?php echo (int)$row['id_edit']; ?>]" value="approve" class="btn btn-success">Setujui</button>
          </div>
        </form>
        <?php else: ?>
          <div class="text-right">
            <a href="home-admin.php?page=otorisasi-approval" class="btn btn-light">Kembali</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
