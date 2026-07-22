<?php
include 'dist/koneksi.php';
include_once 'dist/functions.php';
?>

<?php include 'komponen/statistik-box.php'; ?>

<?php include 'komponen/chart-masakerja.php'; ?>

<div class="row match-height">
  <div class="col-lg-5 col-md-12 mb-4">
    <?php include 'komponen/chart-pie-jk.php'; ?>
  </div>
  <div class="col-lg-7 col-md-12 mb-4">
    <?php include 'komponen/chart-bar-pendidikan.php'; ?>
  </div>
</div>

<div class="row match-height">
  <div class="col-md-6 mb-4">
    <?php include 'komponen/chart-bar-status.php'; ?>
  </div>
  <div class="col-md-6 mb-4">
    <?php include 'komponen/chart-line-pelanggaran.php'; ?>
  </div>
</div>

<?php include 'komponen/chart-bar-jabatan.php'; ?>

<?php include 'komponen/tabel-pensiun.php'; ?>

<div class="row match-height">
  <div class="col-md-6 mb-4">
    <?php include 'komponen/tabel-keterisian-eksekutif.php'; ?>
  </div>
  <div class="col-md-6 mb-4">
    <?php include 'komponen/tabel-keterisian-struktural.php'; ?>
  </div>
</div>
