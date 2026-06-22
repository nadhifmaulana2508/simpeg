<?php
session_start();
include_once "../../dist/sso-auth.php";
simpeg_clear_sso_cookie();
session_unset();
session_destroy();
header("Location: ../../index.php");
exit;
?>
