<?php
// =============================================================
// FILE: pages/report/print-biodata-pegawai.php
// MODULE: Cetak Biodata (TCPDF) - Fix Layout Foto
// =============================================================

ob_start(); // Buffer Output

// 1. LOAD LIBRARY TCPDF
// Pastikan path ini benar. Jika folder plugins ada di root, sesuaikan ../ nya
$path_tcpdf = '../../plugins/tcpdf/tcpdf.php';
if (!file_exists($path_tcpdf)) {
    die("Error: Library TCPDF tidak ditemukan di: " . $path_tcpdf);
}
require_once($path_tcpdf);

// 2. LOAD KONEKSI
include "../../dist/koneksi.php";
include "../../dist/library.php"; // Opsional

function cv_e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function cv_date($s, $format = 'd-m-Y') {
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '-';
    $time = strtotime($s);
    return $time ? date($format, $time) : cv_e($s);
}

function cv_section_title($no, $title) {
    return '<div style="font-weight:bold; color:#0f766e; font-size:10pt; margin-top:6px;">'.$no.'. '.cv_e($title).'</div>';
}

function cv_history_card($title, $meta, $desc = '') {
    $html = '
    <table border="0" cellspacing="0" cellpadding="5" width="100%" style="border:0.4px solid #cbd8d5; background-color:#fbfdfc;">
        <tr>
            <td width="72%" style="font-weight:bold; color:#10231d;">'.$title.'</td>
            <td width="28%" align="right" style="color:#52635c; font-size:8.5pt;">'.$meta.'</td>
        </tr>';
    if (trim((string)$desc) !== '') {
        $html .= '<tr><td colspan="2" style="color:#40544d; font-size:8.8pt;">'.$desc.'</td></tr>';
    }
    $html .= '</table><div style="height:4px;"></div>';
    return $html;
}

// 3. CEK ID
if (isset($_GET['id_peg'])) {
    $id_peg = mysqli_real_escape_string($conn, $_GET['id_peg']);
} else {
    die("Error: ID Pegawai tidak ditemukan.");
}

// 4. AMBIL DATA PEGAWAI
$qPeg = mysqli_query($conn, "SELECT * FROM tb_pegawai WHERE id_peg='$id_peg'");
$peg  = mysqli_fetch_array($qPeg);

if (!$peg) die("Error: Data pegawai tidak ditemukan.");

// 5. SETUP FOTO
$fotoDb   = trim((string)$peg['foto']);
$jk       = $peg['jk'];
$pathFoto = '../../pages/assets/foto/'; 
$fileFoto = '';

// Cek Ketersediaan File
if (!empty($fotoDb)) {
    $candidates = array($fotoDb, $fotoDb.'.jpg', $fotoDb.'.jpeg', $fotoDb.'.png', $fotoDb.'.webp', $fotoDb.'.JPG', $fotoDb.'.PNG');
    foreach ($candidates as $candidate) {
        if (file_exists($pathFoto . $candidate)) {
            $fileFoto = $pathFoto . $candidate;
            break;
        }
    }
}
if (empty($fileFoto)) {
    // Default Avatar
    if ($jk == 'Laki-laki' || $jk == 'L') {
        $fileFoto = '../../pages/assets/foto/no-foto-male.png'; // Pastikan file default ini ada
    } else {
        $fileFoto = '../../pages/assets/foto/no-foto-female.png';
    }
    
    // Fallback jika file default pun tidak ada (biar pdf ga error)
    if(!file_exists($fileFoto)) $fileFoto = ''; 
}


// --- KONFIGURASI PDF ---
class MYPDF extends TCPDF {
    public function Header() {
        // Kosongkan header default
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman '.$this->getAliasNumPage().'/'.$this->getAliasNbPages().' | Dicetak: '.date("d-m-Y H:i"), 0, false, 'R');
    }
}

$pdf = new MYPDF('P', 'mm', array(210, 330), true, 'UTF-8', false); // F4/Folio
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SIMPEG BKK');
$pdf->SetTitle('Biodata - ' . $peg['nama']);

// Margin (Kiri, Atas, Kanan)
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 10);

$pdf->AddPage();

// ==========================================================================
// ISI KONTEN PDF
// ==========================================================================

// 1. JUDUL HEADER
$htmlHeader = '
<div style="text-align:center;">
    <span style="font-size:13pt; font-weight:bold;">PT BPR BKK JATENG (PERSERODA)</span><br>
    <span style="font-size:10pt; text-decoration:underline; font-weight:bold;">BIODATA PEGAWAI</span>
</div><br>';
$pdf->writeHTML($htmlHeader, true, false, false, false, '');

// 2. DATA PRIBADI (LAYOUT FOTO DIPERBAIKI)
// Kita gunakan tabel HTML dengan lebar kolom terkunci agar foto punya ruang pas
// Kolom Kiri: Label & Data (80%)
// Kolom Kanan: Foto (20%)

$tgl_lahir = cv_date($peg['tgl_lhr']);

$tblPribadi = '
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td width="75%">
            <table border="0" cellpadding="3">
                <tr><td colspan="3" style="font-weight:bold; font-size:10pt; color:#0f766e;">I. DATA PRIBADI</td></tr>
                <tr>
                    <td width="30">1.</td>
                    <td width="130">NIP</td>
                    <td width="300">: ' . cv_e($peg['nip']) . '</td>
                </tr>
                <tr>
                    <td>2.</td>
                    <td>Nama Lengkap</td>
                    <td>: <b>' . strtoupper(cv_e($peg['nama'])) . '</b></td>
                </tr>
                <tr>
                    <td>3.</td>
                    <td>Tempat, Tgl Lahir</td>
                    <td>: ' . cv_e($peg['tempat_lhr']) . ', ' . $tgl_lahir . '</td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td>Jenis Kelamin</td>
                    <td>: ' . cv_e($peg['jk']) . '</td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td>Agama</td>
                    <td>: ' . cv_e($peg['agama']) . '</td>
                </tr>
                <tr>
                    <td>6.</td>
                    <td>Status Nikah</td>
                    <td>: ' . cv_e($peg['status_nikah']) . '</td>
                </tr>
                <tr>
                    <td>7.</td>
                    <td>Status Kepegawaian</td>
                    <td>: ' . cv_e($peg['status_kepeg']) . '</td>
                </tr>
                <tr>
                    <td>8.</td>
                    <td>Alamat</td>
                    <td>: ' . cv_e($peg['alamat']) . '</td>
                </tr>
                <tr>
                    <td>9.</td>
                    <td>No. Telepon / HP</td>
                    <td>: ' . cv_e($peg['telp']) . '</td>
                </tr>
                <tr>
                    <td>10.</td>
                    <td>Email</td>
                    <td>: ' . cv_e($peg['email']) . '</td>
                </tr>
            </table>
        </td>
        
        <td width="25%" align="center" valign="top" style="padding-top:10px;">
            <br>
            '. ($fileFoto ? '<img src="'.$fileFoto.'" width="105" height="132" border="0.5" style="object-fit:cover;">' : '<div style="border:1px solid #000; width:105px; height:132px; line-height:132px;">No Photo</div>') .'
            <br><span style="font-size:8pt;">' . cv_e($peg['id_peg']) . '</span>
        </td>
    </tr>
</table><br>';

$pdf->writeHTML($tblPribadi, true, false, false, false, '');


$sectionNo = 2;

// 3. DATA KELUARGA
$rowsKeluarga = '';
$no = 1;
// Pasangan
$qPas = mysqli_query($conn, "SELECT * FROM tb_suamiistri WHERE id_peg='$id_peg'");
while ($r = mysqli_fetch_array($qPas)) {
    $tgl = cv_date($r['tgl_lhr']);
    $rowsKeluarga .= '<tr>
        <td align="center">' . $no++ . '</td>
        <td>' . cv_e($r['nama']) . '</td>
        <td>' . cv_e($r['tmp_lhr']) . ', ' . $tgl . '</td>
        <td align="center">' . cv_e($r['status_hub']) . '</td>
    </tr>';
}
// Anak
$qAnak = mysqli_query($conn, "SELECT * FROM tb_anak WHERE id_peg='$id_peg' ORDER BY tgl_lhr ASC");
while ($r = mysqli_fetch_array($qAnak)) {
    $tgl = cv_date($r['tgl_lhr']);
    $rowsKeluarga .= '<tr>
        <td align="center">' . $no++ . '</td>
        <td>' . cv_e($r['nama']) . '</td>
        <td>' . cv_e($r['tmp_lhr']) . ', ' . $tgl . '</td>
        <td align="center">Anak ke-' . cv_e($r['anak_ke']) . '</td>
    </tr>';
}
// Orang Tua
$qOrtu = mysqli_query($conn, "SELECT * FROM tb_ortu WHERE id_peg='$id_peg'");
while ($r = mysqli_fetch_array($qOrtu)) {
    $tgl = cv_date($r['tgl_lhr']);
    $rowsKeluarga .= '<tr>
        <td align="center">' . $no++ . '</td>
        <td>' . cv_e($r['nama']) . '</td>
        <td>' . cv_e($r['tmp_lhr']) . ', ' . $tgl . '</td>
        <td align="center">' . cv_e($r['status_hub']) . '</td>
    </tr>';
}
if ($no > 1) {
    $tblKeluarga = '
<span style="font-weight:bold; color:#0f766e;">'.($sectionNo++).'. DATA KELUARGA</span><br>
<table border="1" cellspacing="0" cellpadding="4" width="100%">
    <tr style="background-color:#EEF7F4; font-weight:bold; text-align:center;">
        <th width="7%">No</th>
        <th width="36%">Nama</th>
        <th width="40%">Tempat, Tgl Lahir</th>
        <th width="17%">Status</th>
    </tr>'.$rowsKeluarga.'</table><br>';
    $pdf->writeHTML($tblKeluarga, true, false, false, false, '');
}


// 4. RIWAYAT PENDIDIKAN
$rowsPendidikan = '';
$no = 1;
$qSek = mysqli_query($conn, "SELECT * FROM tb_pendidikan WHERE id_peg='$id_peg' ORDER BY tgl_ijazah DESC");
while ($r = mysqli_fetch_array($qSek)) {
    $thn = cv_date($r['tgl_ijazah'], 'Y');
    $title = cv_e($r['jenjang']) . ' - ' . cv_e($r['nama_sekolah']);
    $meta = 'Lulus ' . $thn;
    $desc = '<b>Jurusan:</b> ' . cv_e($r['jurusan']);
    $rowsPendidikan .= cv_history_card($title, $meta, $desc);
    $no++;
}
if ($no > 1) {
    $tblPendidikan = cv_section_title($sectionNo++, 'RIWAYAT PENDIDIKAN') . $rowsPendidikan . '<br>';
    $pdf->writeHTML($tblPendidikan, true, false, false, false, '');
}


// 5. RIWAYAT JABATAN
$rowsJabatan = '';
$no = 1;
$qJab = mysqli_query($conn, "SELECT j.*, m.nama_jabatan, k.nama_kantor 
                             FROM tb_jabatan j
                             LEFT JOIN tb_master_jabatan m ON j.kode_jabatan = m.kode_jabatan
                             LEFT JOIN tb_kantor k ON j.unit_kerja = k.kode_kantor_detail
                             WHERE j.id_peg='$id_peg' 
                             ORDER BY j.tmt_jabatan DESC");

while ($r = mysqli_fetch_array($qJab)) {
    $namaJab = !empty($r['nama_jabatan']) ? $r['nama_jabatan'] : $r['jabatan'];
    $tmt = cv_date($r['tmt_jabatan']);
    $title = cv_e($namaJab);
    $meta = cv_e($r['status_jab']);
    $desc = '<b>Unit:</b> ' . cv_e($r['nama_kantor']) . ' (' . cv_e($r['unit_kerja']) . ') &nbsp; <b>TMT:</b> ' . $tmt;
    $rowsJabatan .= cv_history_card($title, $meta, $desc);
    $no++;
}
if ($no > 1) {
    $tblJabatan = cv_section_title($sectionNo++, 'RIWAYAT JABATAN') . $rowsJabatan . '<br>';
    $pdf->writeHTML($tblJabatan, true, false, false, false, '');
}

// 6. RIWAYAT DIKLAT
$rowsDiklat = '';
$no = 1;
$qDiklat = mysqli_query($conn, "SELECT * FROM tb_diklat WHERE id_peg='$id_peg' ORDER BY tahun DESC, diklat ASC");
while ($qDiklat && ($r = mysqli_fetch_array($qDiklat))) {
    $title = cv_e($r['diklat']);
    $meta = cv_e($r['tahun']);
    $descParts = array();
    if (!empty($r['penyelenggara'])) $descParts[] = '<b>Penyelenggara:</b> ' . cv_e($r['penyelenggara']);
    if (!empty($r['tempat'])) $descParts[] = '<b>Tempat:</b> ' . cv_e($r['tempat']);
    $rowsDiklat .= cv_history_card($title, $meta, implode(' &nbsp; ', $descParts));
    $no++;
}
if ($no > 1) {
    $tblDiklat = cv_section_title($sectionNo++, 'RIWAYAT DIKLAT') . $rowsDiklat . '<br>';
    $pdf->writeHTML($tblDiklat, true, false, false, false, '');
}

// 7. RIWAYAT SERTIFIKASI
$rowsSertifikasi = '';
$no = 1;
$qSert = mysqli_query($conn, "SELECT *, DATEDIFF(tgl_expired, CURDATE()) AS selisih FROM tb_sertifikasi WHERE id_peg='$id_peg' ORDER BY tgl_sertifikat DESC");
while ($qSert && ($r = mysqli_fetch_array($qSert))) {
    $statusSert = (isset($r['selisih']) && $r['selisih'] < 0) ? 'Expired' : 'Aktif';
    $title = cv_e($r['sertifikasi']);
    $meta = $statusSert;
    $descParts = array();
    if (!empty($r['penyelenggara'])) $descParts[] = '<b>Penyelenggara:</b> ' . cv_e($r['penyelenggara']);
    $descParts[] = '<b>Tanggal:</b> ' . cv_date(isset($r['tgl_sertifikat']) ? $r['tgl_sertifikat'] : '');
    $descParts[] = '<b>Expired:</b> ' . cv_date(isset($r['tgl_expired']) ? $r['tgl_expired'] : '');
    $rowsSertifikasi .= cv_history_card($title, $meta, implode(' &nbsp; ', $descParts));
    $no++;
}
if ($no > 1) {
    $tblSertifikasi = cv_section_title($sectionNo++, 'RIWAYAT SERTIFIKASI') . $rowsSertifikasi . '<br>';
    $pdf->writeHTML($tblSertifikasi, true, false, false, false, '');
}

// 8. RIWAYAT PELANGGARAN / HUKUMAN
$rowsPelanggaran = '';
$no = 1;
$qHuk = mysqli_query($conn, "SELECT * FROM tb_hukuman WHERE id_peg='$id_peg' ORDER BY tgl_sk DESC");
while ($qHuk && ($r = mysqli_fetch_array($qHuk))) {
    $title = cv_e($r['hukuman']);
    $meta = cv_date(isset($r['tgl_sk']) ? $r['tgl_sk'] : '');
    $descParts = array();
    if (!empty($r['no_sk'])) $descParts[] = '<b>No SK:</b> ' . cv_e($r['no_sk']);
    if (!empty($r['keterangan'])) $descParts[] = '<b>Keterangan:</b> ' . cv_e($r['keterangan']);
    $rowsPelanggaran .= cv_history_card($title, $meta, implode(' &nbsp; ', $descParts));
    $no++;
}
if ($no > 1) {
    $tblPelanggaran = cv_section_title($sectionNo++, 'RIWAYAT PELANGGARAN') . $rowsPelanggaran . '<br><br>';
    $pdf->writeHTML($tblPelanggaran, true, false, false, false, '');
}


// 9. TANDA TANGAN
$tglCetak = date("d F Y");
$tblTtd = '
<table border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td width="350"></td>
        <td width="200" align="center">
            Semarang, ' . $tglCetak . '<br>
            Pegawai Yang Bersangkutan,
            <br><br><br><br><br>
            <b><u>' . strtoupper($peg['nama']) . '</u></b><br>
            NIP. ' . $peg['id_peg'] . '
        </td>
    </tr>
</table>';
$pdf->writeHTML($tblTtd, true, false, false, false, '');


// OUTPUT
ob_end_clean(); 
$pdf->Output('Biodata_' . $peg['nama'] . '.pdf', 'I');
?>
