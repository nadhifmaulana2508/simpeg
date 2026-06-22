<?php
/*********************************************************
 * FILE     : pages/user/ajax-data-user.php
 * MODULE   : Backend JSON User (Direct Jabatan)
 *********************************************************/

if (session_id() == '') session_start();
ini_set('display_errors', 0);
while(ob_get_level()){ ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

@include_once __DIR__ . '/../../dist/koneksi.php';
if (!isset($conn)) { @include_once __DIR__ . '/../../config/koneksi.php'; $conn = isset($koneksi)?$koneksi:null; }
@include_once __DIR__ . '/../../dist/sso-auth.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc($s){ global $conn; return mysqli_real_escape_string($conn, trim($s)); }

function simpeg_sync_apk_from_active_pegawai($conn) {
    $default_pass = password_hash('123456', PASSWORD_BCRYPT);
    $safe_pass = mysqli_real_escape_string($conn, $default_pass);

    $missing = mysqli_query($conn, "
        SELECT p.id_peg
        FROM tb_pegawai p
        LEFT JOIN tb_apk a ON a.id_peg = p.id_peg
        WHERE p.status_aktif IN ('1','Y') AND a.id_peg IS NULL
    ");

    if ($missing) {
        while ($row = mysqli_fetch_assoc($missing)) {
            $id_peg = mysqli_real_escape_string($conn, $row['id_peg']);
            mysqli_query($conn, "
                INSERT INTO tb_apk
                    (id_peg, monbis, ims, rekrutmen, simstock, simpeg, `visitin-ao`, portal_bkk, sipatuh, pass, created_at, updated_at)
                VALUES
                    ('$id_peg', 1, 1, 1, 1, 1, 1, 1, 1, '$safe_pass', NOW(), NOW())
            ");
        }
    }

    mysqli_query($conn, "
        UPDATE tb_user u
        LEFT JOIN tb_pegawai p ON p.id_peg = u.id_pegawai
        SET u.status_aktif = 'N', u.updated_at = NOW(), u.updated_by = 'system-sync'
        WHERE u.id_pegawai IS NOT NULL
          AND u.status_aktif = 'Y'
          AND (p.id_peg IS NULL OR p.status_aktif NOT IN ('1','Y'))
    ");
}

simpeg_sync_apk_from_active_pegawai($conn);

$draw   = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$len    = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
$f_role = isset($_GET['role']) ? esc($_GET['role']) : '';

// --- 1. BASE WHERE ---
$base_where = " WHERE p.status_aktif IN ('1','Y') AND a.simpeg = 1 ";
$where = $base_where;
if($f_role !== '') {
    if ($f_role === 'kepala') {
        $where .= " AND (
            LOWER(COALESCE(u.hak_akses, 'User')) = 'kepala'
            OR EXISTS (
                SELECT 1 FROM tb_jabatan jf
                WHERE jf.id_peg = p.id_peg
                  AND LOWER(jf.status_jab) = 'aktif'
                  AND (
                    LOWER(jf.jabatan) LIKE '%kepala cabang%'
                    OR LOWER(jf.jabatan) LIKE '%kepala kantor%'
                    OR (LOWER(jf.jabatan) LIKE '%operasional%' AND (LOWER(jf.jabatan) LIKE '%kabid%' OR LOWER(jf.jabatan) LIKE '%kepala bidang%'))
                  )
            )
        ) ";
    } elseif ($f_role === 'superadmin') {
        $where .= " AND (
            LOWER(COALESCE(u.hak_akses, 'User')) = 'superadmin'
            OR EXISTS (
                SELECT 1 FROM tb_jabatan jf
                WHERE jf.id_peg = p.id_peg
                  AND LOWER(jf.status_jab) = 'aktif'
                  AND (LOWER(jf.jabatan) LIKE '%direktur%' OR LOWER(jf.jabatan) LIKE '%direksi%')
            )
        ) ";
    } else {
        $where .= " AND LOWER(COALESCE(u.hak_akses, 'User')) = LOWER('$f_role') ";
    }
}

if($search !== '') {
    $s = esc($search);
    $where .= " AND (
        p.nama LIKE '%$s%'
        OR p.id_peg LIKE '%$s%'
        OR COALESCE(u.id_user, a.id_peg) LIKE '%$s%'
        OR COALESCE(u.hak_akses, 'User') LIKE '%$s%'
        OR (
            SELECT j.jabatan
            FROM tb_jabatan j
            WHERE j.id_peg = p.id_peg AND j.status_jab = 'Aktif'
            ORDER BY j.tmt_jabatan DESC LIMIT 1
        ) LIKE '%$s%'
    ) ";
}

// --- 2. HITUNG TOTAL DATA ---
$base_from = "
    FROM tb_apk a
    INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
    LEFT JOIN tb_user u ON u.id_pegawai = a.id_peg
";
$qTotal = mysqli_query($conn, "SELECT COUNT(DISTINCT a.id_peg) AS c $base_from $base_where");
$recordsTotal = ($qTotal) ? (int)mysqli_fetch_assoc($qTotal)['c'] : 0;

$qCount = mysqli_query($conn, "SELECT COUNT(DISTINCT a.id_peg) AS c $base_from $where");
$recordsFiltered = ($qCount) ? (int)mysqli_fetch_assoc($qCount)['c'] : 0;

// --- 3. QUERY UTAMA (LANGSUNG KE TB_JABATAN) ---
// Perubahan: Tidak lagi join ke tb_master_jabatan.
// Langsung ambil kolom 'jabatan' dari 'tb_jabatan'.
$sql = "SELECT
            a.id_apk,
            a.id_peg AS apk_id_peg,
            a.simpeg,
            p.nama AS pegawai_nama,
            p.status_aktif AS pegawai_status_aktif,
            u.id_user,
            u.nama_user,
            u.jabatan,
            u.hak_akses,
            u.status_aktif,
            u.id_pegawai,
        (
            SELECT j.jabatan 
            FROM tb_jabatan j 
            WHERE j.id_peg = a.id_peg AND j.status_jab = 'Aktif'
            ORDER BY j.tmt_jabatan DESC LIMIT 1
        ) as jabatan_live
        $base_from
        $where 
        GROUP BY a.id_peg
        ORDER BY p.nama ASC
        LIMIT $start, $len";

$q = mysqli_query($conn, $sql);
$data = array();
$no = $start + 1;

if($q){
    while($r = mysqli_fetch_assoc($q)){
        
        // Info User
        $id_user_tampil = !empty($r['id_user']) ? $r['id_user'] : $r['apk_id_peg'];
        $nama_tampil = !empty($r['nama_user']) ? $r['nama_user'] : $r['pegawai_nama'];
        $user_info = '<div><b>'.h($nama_tampil).'</b></div><small class="text-muted">@'.h($id_user_tampil).'</small>';
        
        $role_raw = strtolower(trim(isset($r['hak_akses']) ? $r['hak_akses'] : 'user'));
        if ($role_raw === '') $role_raw = 'user';
        if (function_exists('simpeg_auto_role_for_employee')) {
            $auto_role = simpeg_auto_role_for_employee($conn, $r['apk_id_peg']);
            if (function_exists('simpeg_role_rank') && simpeg_role_rank($auto_role) > simpeg_role_rank($role_raw)) {
                $role_raw = strtolower($auto_role);
                if (!empty($r['id_user'])) {
                    $safe_role_update = esc($auto_role);
                    $safe_user_update = esc($r['id_user']);
                    mysqli_query($conn, "
                        UPDATE tb_user
                        SET hak_akses = '$safe_role_update', updated_at = NOW(), updated_by = 'system-role-sync'
                        WHERE id_user = '$safe_user_update'
                        LIMIT 1
                    ");
                }
            }
        }

        // Badge Status
        $status_user = (!empty($r['id_user']) && $r['status_aktif'] === 'N') ? 'N' : 'Y';
        $status_html = ($status_user == 'Y')
            ? '<span class="user-status-badge" style="background:#dff5f2;color:#0f766e;border-color:rgba(15,118,110,0.14);">Aktif</span>'
            : '<span class="user-status-badge" style="background:#fee2e2;color:#b91c1c;border-color:rgba(201,95,90,0.16);">Non-Aktif</span>';

        // --- LOGIC JABATAN ---
        // Prioritas 1: Jabatan Live dari Tabel Jabatan (Langsung kolom jabatan)
        // Prioritas 2: Jabatan Manual dari Tabel User (u.jabatan)
        // Default: "-"
        $jabatan_tampil = '-';
        if (!empty($r['jabatan_live'])) {
            $jabatan_tampil = h($r['jabatan_live']); // Ambil dari tb_jabatan
        } elseif (!empty($r['jabatan'])) {
            $jabatan_tampil = h($r['jabatan']);      // Ambil Manual (Backup)
        }

        // Tombol Aksi
        if (!empty($r['id_user'])) {
            $aksi = '<div class="user-action-group">
                        <a href="home-admin.php?page=form-master-data-user&mode=edit&id='.h($r['id_user']).'" class="user-action-btn user-action-edit" title="Edit Role"><i class="fas fa-pen"></i></a>
                        <button type="button" class="user-action-btn user-action-delete btn-delete" data-id="'.h($r['id_user']).'" title="Nonaktifkan"><i class="fas fa-trash"></i></button>
                     </div>';
        } else {
            $aksi = '<div class="user-action-group">
                        <a href="home-admin.php?page=form-master-data-user&mode=create&id_pegawai='.h($r['apk_id_peg']).'" class="user-action-btn user-action-edit" title="Buat Role"><i class="fas fa-user-plus"></i></a>
                     </div>';
        }

        $data[] = array(
            'no' => $no++,
            'user_info' => $user_info,
            'jabatan' => $jabatan_tampil,
            'role' => $role_raw,
            'status' => $status_html,
            'aksi' => $aksi
        );
    }
}

echo json_encode(array('draw'=>$draw, 'recordsTotal'=>$recordsTotal, 'recordsFiltered'=>$recordsFiltered, 'data'=>$data));
?>
