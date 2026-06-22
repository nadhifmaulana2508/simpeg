<?php
if (session_id() == '') session_start();
include "dist/koneksi.php";
include_once "dist/sso-auth.php";

// Ambil input (bisa berupa id_user atau id_pegawai)
$login_input = isset($_POST['id_user']) ? trim($_POST['id_user']) : '';
$password_raw = isset($_POST['password']) ? $_POST['password'] : '';
$password    = md5($password_raw);
$op          = isset($_GET['op']) ? $_GET['op'] : 'in';

function simpeg_finish_login($conn, $row) {
    if ($row['status_aktif'] == "N") {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Akses Ditolak',
                text: 'Akun Anda dinonaktifkan. Hubungi Admin.',
                confirmButtonText: 'Kembali',
                confirmButtonColor: '#d33'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>";
        return;
    }

    session_regenerate_id(true);

    $_SESSION['id_user']    = $row['id_user'];
    $_SESSION['nama_user']  = $row['nama_user'];
    $_SESSION['hak_akses']  = strtolower($row['hak_akses']);
    $_SESSION['id_pegawai'] = $row['id_pegawai'];

    if ($_SESSION['hak_akses'] == 'kepala') {
        $id_peg = $row['id_pegawai'];

        $stmt2 = mysqli_prepare($conn, "SELECT unit_kerja FROM tb_jabatan WHERE id_peg=? AND status_jab='Aktif' LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "s", $id_peg);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);

        if ($dKantor = mysqli_fetch_assoc($res2)) {
            $_SESSION['kode_kantor'] = $dKantor['unit_kerja'];
        } else {
            $_SESSION['kode_kantor'] = '-';
        }
        mysqli_stmt_close($stmt2);
    }

    switch ($_SESSION['hak_akses']) {
        case 'superadmin':
        case 'admin':  $redirectPage = 'home-admin.php'; break;
        case 'kepala': $redirectPage = 'home-admin.php?page=dashboard-cabang'; break;
        case 'user':   $redirectPage = 'home-admin.php?page=profil-pegawai'; break;
        default:       $redirectPage = 'index.php'; break;
    }

    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil',
            text: 'Selamat datang, " . htmlspecialchars($row['nama_user']) . "!',
            showConfirmButton: false,
            timer: 2000
        }).then(() => {
            window.location.href = '$redirectPage';
        });
    </script>";
}

function simpeg_finish_sso_login($conn, $token, $whoami) {
    if (!simpeg_fill_session_from_sso($conn, $token, $whoami)) {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Akses Ditolak',
                text: 'Akun SIMPEG Anda belum aktif atau tidak memiliki akses.',
                confirmButtonText: 'Kembali',
                confirmButtonColor: '#d33'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>";
        return;
    }

    simpeg_set_sso_cookie($token);

    switch ($_SESSION['hak_akses']) {
        case 'superadmin':
        case 'admin':  $redirectPage = 'home-admin.php'; break;
        case 'kepala': $redirectPage = 'home-admin.php?page=dashboard-cabang'; break;
        case 'user':   $redirectPage = 'home-admin.php?page=profil-pegawai'; break;
        default:       $redirectPage = 'index.php'; break;
    }

    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil',
            text: 'Selamat datang, " . htmlspecialchars($_SESSION['nama_user']) . "!',
            showConfirmButton: false,
            timer: 1600
        }).then(() => {
            window.location.href = '$redirectPage';
        });
    </script>";
}

?>
<!DOCTYPE html>
<html>
<head>
  <script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
  <style> body { font-family: sans-serif; background: #f4f6f9; } </style>
</head>
<body>

<?php
if ($op == "in") {
    $logged_in = false;

    // Login utama: SSO BKK. Role tetap dibaca dari tb_user.
    $ssoLogin = simpeg_sso_login($login_input, $password_raw);
    if ($ssoLogin['ok'] && !empty($ssoLogin['json']['data']['token'])) {
        $token = $ssoLogin['json']['data']['token'];
        $who = simpeg_sso_whoami($token);
        if ($who['ok'] && !empty($who['json']['data'])) {
            simpeg_finish_sso_login($conn, $token, $who['json']['data']);
            $logged_in = true;
        }
    }

    // Fallback lokal untuk dev/admin saat SSO tidak tersedia.
    if (!$logged_in) {
    $stmtApk = mysqli_prepare($conn, "
        SELECT
            COALESCE(u.id_user, a.id_peg) AS id_user,
            COALESCE(u.nama_user, p.nama) AS nama_user,
            COALESCE(u.hak_akses, 'User') AS hak_akses,
            a.id_peg AS id_pegawai,
            CASE
                WHEN p.status_aktif NOT IN ('1','Y') THEN 'N'
                WHEN u.status_aktif = 'N' THEN 'N'
                ELSE 'Y'
            END AS status_aktif,
            a.pass AS apk_pass
        FROM tb_apk a
        INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
        LEFT JOIN tb_user u ON u.id_pegawai = a.id_peg
        WHERE (a.id_peg = ? OR u.id_user = ?)
          AND a.simpeg = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtApk, "ss", $login_input, $login_input);
    mysqli_stmt_execute($stmtApk);
    $resultApk = mysqli_stmt_get_result($stmtApk);

    if ($rowApk = mysqli_fetch_assoc($resultApk)) {
        $hash = isset($rowApk['apk_pass']) ? $rowApk['apk_pass'] : '';
        $password_ok = password_verify($password_raw, $hash) || $hash === $password;

        if ($password_ok) {
            if (function_exists('simpeg_auto_role_for_employee')) {
                $autoRole = simpeg_auto_role_for_employee($conn, $rowApk['id_pegawai']);
                if (simpeg_role_rank($autoRole) > simpeg_role_rank($rowApk['hak_akses'])) {
                    $rowApk['hak_akses'] = $autoRole;
                }
            }
            simpeg_finish_login($conn, $rowApk);
            $logged_in = true;
        }
    }
    mysqli_stmt_close($stmtApk);
    }

    if (!$logged_in) {
    // Fallback login lama: admin/manual account yang hanya ada di tb_user.
    $stmt = mysqli_prepare($conn, "SELECT * FROM tb_user WHERE (id_user=? OR id_pegawai=?) AND password=?");
    
    // "sss" artinya 3 string: param1 (id_user), param2 (id_pegawai), param3 (password)
    // Kita masukkan $login_input dua kali karena dia mengecek ke dua kolom berbeda
    mysqli_stmt_bind_param($stmt, "sss", $login_input, $login_input, $password);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Cek apakah user ditemukan
    if ($row = mysqli_fetch_assoc($result)) {
        if (function_exists('simpeg_auto_role_for_employee')) {
            $autoRole = simpeg_auto_role_for_employee($conn, $row['id_pegawai']);
            if (simpeg_role_rank($autoRole) > simpeg_role_rank($row['hak_akses'])) {
                $row['hak_akses'] = $autoRole;
            }
        }
        simpeg_finish_login($conn, $row);
    } else {
        // Login Gagal
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Login Gagal',
                text: 'Username/ID Pegawai atau Password salah!',
                confirmButtonText: 'Coba Lagi',
                confirmButtonColor: '#3085d6'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>";
    }
    
    mysqli_stmt_close($stmt);
    }

} elseif ($op == "out") {
    // Logout Logic
    simpeg_clear_sso_cookie();
    session_unset();
    session_destroy();
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Logout Berhasil',
            text: 'Sampai jumpa lagi!',
            showConfirmButton: false,
            timer: 2000
        }).then(() => {
            window.location.href = 'index.php';
        });
    </script>";
}
?>

</body>
</html>
