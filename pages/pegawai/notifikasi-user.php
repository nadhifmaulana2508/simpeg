<?php
include 'dist/koneksi.php';

// 1. SECURITY: Sanitize the session ID before using it in SQL
// Even though it's a session, it's safer to escape it.
$id_user = mysqli_real_escape_string($conn, $_SESSION['id_user']);

// 2. OPTIMIZATION: Combine Query logic if possible, but keeping it simple here.
$qNotif = mysqli_query($conn, "
  SELECT *
  FROM tb_notifikasi
  WHERE id_user = '$id_user'
  ORDER BY waktu_notif DESC
");

// 3. SECURITY: Use the escaped ID for the update query as well
mysqli_query($conn, "UPDATE tb_notifikasi SET status_baca = 'read' WHERE id_user = '$id_user'");
?>

<style>
  .notif-page { padding-top: 1rem; }
  .notif-shell {
    border: 1px solid #dbe8df;
    border-radius: 18px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 14px 34px rgba(15,35,26,0.06);
    overflow: hidden;
  }
  .notif-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid #e3eee7;
    background: linear-gradient(180deg, #fbfdfb, #f6faf7);
  }
  .notif-title { margin: 0; font-size: 1.12rem; font-weight: 800; color: #10231d; }
  .notif-subtitle { margin: 0.12rem 0 0; color: #66756e; font-size: 0.86rem; }
  .notif-list { padding: 0.6rem; }
  .notif-item {
    display: grid;
    grid-template-columns: 42px 1fr auto;
    gap: 0.85rem;
    align-items: start;
    padding: 0.9rem;
    border: 1px solid transparent;
    border-radius: 14px;
  }
  .notif-item + .notif-item { border-top-color: #edf3ef; border-radius: 0; }
  .notif-icon {
    width: 42px; height: 42px; border-radius: 14px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #dff5f2; color: #0f766e;
  }
  .notif-name { font-weight: 800; color: #10231d; margin-bottom: 0.2rem; }
  .notif-message { color: #52635c; margin: 0; line-height: 1.45; }
  .notif-meta { color: #7a8a83; font-size: 0.8rem; white-space: nowrap; }
  .notif-badge {
    display: inline-flex; align-items: center;
    border-radius: 999px; padding: 0.24rem 0.58rem;
    font-size: 0.72rem; font-weight: 800;
    background: #eef5f0; color: #64746d;
    margin-top: 0.45rem;
  }
  .notif-badge.unread { background: #fff3cd; color: #996100; }
  .btn-notif-back {
    border-radius: 12px; border: 1px solid #d9e5dc;
    background: #fff; color: #0f766e; font-weight: 800;
  }
  @media (max-width: 767.98px) {
    .notif-page { padding-top: .5rem; }
    .notif-shell { border-radius: 16px; }
    .notif-head { align-items: stretch; flex-direction: column; padding: .85rem; }
    .notif-title { font-size: 1.05rem; }
    .notif-subtitle { font-size: .8rem; }
    .btn-notif-back { width: 100%; }
    .notif-list { padding: .45rem; }
    .notif-item { grid-template-columns: 36px 1fr; gap: .65rem; padding: .75rem; }
    .notif-icon { width: 36px; height: 36px; border-radius: 12px; }
    .notif-meta { grid-column: 2; white-space: normal; }
    .notif-message { font-size: .9rem; }
    .notif-badge { font-size: .68rem; }
  }
</style>

<section class="content notif-page">
  <div class="container-fluid">
    <div class="notif-shell">
      <div class="notif-head">
        <div>
          <h3 class="notif-title">Notifikasi</h3>
          <p class="notif-subtitle">Riwayat status pengajuan dan informasi aplikasi.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-notif-back">
          <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
      </div>
      <div class="notif-list">
        <?php if ($qNotif && mysqli_num_rows($qNotif) > 0): ?>
          <?php while ($row = mysqli_fetch_assoc($qNotif)): ?>
            <?php
              $status_baca = strtolower(isset($row['status_baca']) ? $row['status_baca'] : '');
              $is_unread = in_array($status_baca, array('unread', 'belum'));
              $waktu_raw = isset($row['waktu_notif']) ? $row['waktu_notif'] : '';
              $waktu = $waktu_raw ? date('d M Y H:i', strtotime($waktu_raw)) : '-';
            ?>
            <div class="notif-item">
              <span class="notif-icon"><i class="fas fa-bell"></i></span>
              <div>
                <div class="notif-name"><?= htmlspecialchars($row['judul']) ?></div>
                <p class="notif-message"><?= htmlspecialchars($row['pesan']) ?></p>
                <span class="notif-badge <?= $is_unread ? 'unread' : '' ?>"><?= $is_unread ? 'Belum dibaca' : 'Dibaca' ?></span>
                <?php if (!empty($row['link_aksi'])): ?>
                  <a href="<?= htmlspecialchars($row['link_aksi']) ?>" class="btn btn-sm btn-link font-weight-bold text-success ml-2">Lihat</a>
                <?php endif; ?>
              </div>
              <div class="notif-meta"><i class="far fa-clock mr-1"></i><?= $waktu ?></div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="text-center text-muted py-5">
            <i class="far fa-bell mb-3" style="font-size:2rem;opacity:.5"></i>
            <div>Belum ada notifikasi.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
