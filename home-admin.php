<?php
session_start();
include "config.php";
ob_start("simpeg_normalize_markup");
include "dist/koneksi.php";
include "dist/functions.php";
include "dist/sso-auth.php";
// include "cek.php";

simpeg_restore_session_from_sso_cookie($conn);

if (!isset($_SESSION['id_user'])) {
  header("Location: " . (function_exists('base_url') ? base_url('index.php') : 'index.php'));
  exit;
}

//cekAkses(['Admin']);
aturSessionTimeout(1800, function_exists('base_url') ? base_url('index.php') : "index.php");

$App = mysqli_query($conn, "SELECT * FROM tb_config WHERE id_app='1'");
$set = mysqli_fetch_array($App);

include "templates/layout-header.php";
include "templates/layout-sidebar.php";
?>

<div class="content-wrapper">
  <section class="content text-sm">
    <?php
    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    $file = getPage($page); // pastikan variabel $file didefinisikan
    include $file;
    ?>
  </section>
</div>



<?php include "templates/layout-footer.php"; ?>
