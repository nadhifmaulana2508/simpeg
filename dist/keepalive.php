<?php
session_start();
$_SESSION['start_session'] = time(); // perpanjang waktu aktif
session_write_close();
http_response_code(204); // tidak kirim konten
exit;
