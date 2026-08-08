<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

// 1. Load Composer Autoload jika ada
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 2. Deklarasi 'use' WAJIB di paling atas file (Top-Level Scope)
use Dompdf\Dompdf;
use Dompdf\Options;

// Cek apakah library Dompdf terdeteksi di sistem
$use_dompdf = class_exists('Dompdf\Dompdf');

$type = $_GET['type'] ?? 'kas'; // Parameter: 'kas' atau 'iuran'

// ------------------------------------------------------------------
// 1. OLAH DATA LAPORAN KAS
// ------------------------------------------------------------------
if ($type === 'kas') {
    $tgl_mulai   = $_GET['tgl_mulai'] ?? '';
    $tgl_selesai = $_GET['tgl_selesai'] ?? '';
    $jenis       = $_GET['jenis'] ?? 'all';

    $sql = "SELECT k.*, u.username FROM kas k LEFT JOIN users u ON k.created_by = u.id WHERE 1=1";
    $params = [];

    if (!empty($tgl_mulai)) {
        $sql .= " AND DATE(k.tanggal) >= ?";
        $params[] = $tgl_mulai;
    }
    if (!empty($tgl_selesai)) {
        $sql .= " AND DATE(k.tanggal) <= ?";
        $params[] = $tgl_selesai;
    }
    if ($jenis !== 'all') {
        $sql .= " AND k.jenis = ?";
        $params[] = $jenis;
    }

    $sql .= " ORDER BY k.tanggal ASC, k.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data_kas = $stmt->fetchAll();

    // Hitung Subtotal
    $total_in = 0;
    $total_out = 0;
    foreach ($data_kas as $r) {
        if ($r['jenis'] === 'pemasukan') $total_in += $r['jumlah'];
        else $total_out += $r['jumlah'];
    }
    $saldo_bersih = $total_in - $total_out;

    $periode_str = (!empty($tgl_mulai) ? date('d/m/Y', strtotime($tgl_mulai)) : 'Awal') . 
                   ' s/d ' . 
                   (!empty($tgl_selesai) ? date('d/m/Y', strtotime($tgl_selesai)) : date('d/m/Y'));
    $title_report = "LAPORAN TRANSAKSI KAS ORGANISASI";
} 

// ------------------------------------------------------------------
// 2. OLAH DATA LAPORAN IURAN
// ------------------------------------------------------------------
else {
    $bulan  = (int)($_GET['bulan'] ?? date('m'));
    $tahun  = (int)($_GET['tahun'] ?? date('Y'));
    $status = $_GET['status'] ?? 'all';

    $nama_bulan_arr = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $sql = "
        SELECT i.*, a.nama_lengkap, a.nik, u.username 
        FROM iuran i 
        JOIN anggota a ON i.id_anggota = a.id 
        LEFT JOIN users u ON a.id_user = u.id 
        WHERE i.bulan = ? AND i.tahun = ?
    ";
    $params = [$bulan, $tahun];

    if ($status !== 'all') {
        $sql .= " AND i.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY a.nama_lengkap ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data_iuran = $stmt->fetchAll();

    $total_terkumpul = 0;
    $lunas_count = 0;
    $pending_count = 0;

    foreach ($data_iuran as $r) {
        if ($r['status'] === 'lunas') {
            $total_terkumpul += $r['nominal'];
            $lunas_count++;
        } else {
            $pending_count++;
        }
    }

    $periode_str = ($nama_bulan_arr[$bulan] ?? $bulan) . " " . $tahun;
    $title_report = "LAPORAN REKAPITULASI IURAN ANGGOTA";
}

// ------------------------------------------------------------------
// 3. RENDER TEMPLATE HTML UNTUK PDF
// ------------------------------------------------------------------
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $title_report ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #333; line-height: 1.4; margin: 0; padding: 15px; }
        
        .header-table { width: 100%; border-collapse: collapse; border-bottom: 3px double #1e3a8a; padding-bottom: 8px; margin-bottom: 15px; }
        .org-title { font-size: 15pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .org-subtitle { font-size: 10pt; font-weight: bold; color: #4b5563; }
        .org-address { font-size: 8pt; color: #6b7280; }

        .report-box { text-align: center; background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px; border-radius: 4px; margin-bottom: 15px; }
        .report-title { font-size: 11pt; font-weight: bold; color: #0f172a; margin: 0; }
        .report-period { font-size: 8.5pt; color: #475569; margin-top: 2px; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .summary-table td { padding: 8px; background-color: #f8fafc; border: 1px solid #e2e8f0; width: 33.33%; }
        .summary-label { font-size: 7.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; }
        .summary-val { font-size: 11pt; font-weight: bold; margin-top: 2px; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt; }
        .data-table th { background-color: #1e3a8a; color: #fff; font-weight: bold; text-align: left; padding: 6px; border: 1px solid #1e3a8a; font-size: 8.5pt; }
        .data-table td { padding: 5px 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-green { color: #16a34a; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .text-blue { color: #2563eb; font-weight: bold; }

        .badge { padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 7.5pt; display: inline-block; }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; }
        .badge-warning { background-color: #fef3c7; color: #b45309; }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; }
        .signature-table td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 50px; }
    </style>
</head>
<body>

    <!-- KOP SURAT -->
    <table class="header-table">
        <tr>
            <td>
                <div class="org-title">Sistem Keuangan Organisasi</div>
                <div class="org-subtitle">Pengurus Pusat Pemberdayaan Masyarakat</div>
                <div class="org-address">Jl. Pemuda No. 123, Surabaya | Telp: (031) 555-0192 | Email: info@organisasi.or.id</div>
            </td>
        </tr>
    </table>

    <!-- JUDUL LAPORAN -->
    <div class="report-box">
        <div class="report-title"><?= $title_report ?></div>
        <div class="report-period">Periode: <?= $periode_str ?></div>
    </div>

    <?php if ($type === 'kas'): ?>
        <!-- RINGKASAN KAS -->
        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-label">Total Pemasukan</div>
                    <div class="summary-val text-green">Rp <?= number_format($total_in, 0, ',', '.') ?></div>
                </td>
                <td>
                    <div class="summary-label">Total Pengeluaran</div>
                    <div class="summary-val text-red">Rp <?= number_format($total_out, 0, ',', '.') ?></div>
                </td>
                <td>
                    <div class="summary-label">Saldo Net Periode</div>
                    <div class="summary-val text-blue">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></div>
                </td>
            </tr>
        </table>

        <!-- TABEL DATA KAS -->
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 12%;">Jenis</th>
                    <th>Keterangan</th>
                    <th style="width: 15%;">Pencatat</th>
                    <th style="width: 18%;" class="text-end">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_kas)): ?>
                    <tr><td colspan="6" class="text-center">Tidak ada transaksi pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($data_kas as $row): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td>
                                <span class="badge <?= $row['jenis'] === 'pemasukan' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($row['jenis']) ?>
                                </span>
                            </td>
                            <td><?= e($row['keterangan']) ?></td>
                            <td><?= e($row['username'] ?? 'Sistem') ?></td>
                            <td class="text-end <?= $row['jenis'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                                <?= $row['jenis'] === 'pemasukan' ? '+' : '-' ?> Rp <?= number_format($row['jumlah'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>
        <!-- RINGKASAN IURAN -->
        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-label">Total Terkumpul</div>
                    <div class="summary-val text-green">Rp <?= number_format($total_terkumpul, 0, ',', '.') ?></div>
                </td>
                <td>
                    <div class="summary-label">Anggota Lunas</div>
                    <div class="summary-val text-blue"><?= $lunas_count ?> Anggota</div>
                </td>
                <td>
                    <div class="summary-label">Belum Bayar / Pending</div>
                    <div class="summary-val text-red"><?= $pending_count ?> Anggota</div>
                </td>
            </tr>
        </table>

        <!-- TABEL DATA IURAN -->
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 18%;">NIK</th>
                    <th>Nama Anggota</th>
                    <th style="width: 15%;">Nominal</th>
                    <th style="width: 15%;" class="text-center">Status</th>
                    <th style="width: 15%;">Tgl Bayar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_iuran)): ?>
                    <tr><td colspan="6" class="text-center">Tidak ada catatan iuran pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($data_iuran as $row): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= e($row['nik'] ?: '-') ?></td>
                            <td><strong><?= e($row['nama_lengkap']) ?></strong></td>
                            <td class="text-end">Rp <?= number_format($row['nominal'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge <?= $row['status'] === 'lunas' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td><?= $row['tgl_bayar'] ? date('d/m/Y', strtotime($row['tgl_bayar'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- TANDA TANGAN -->
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br><strong>Ketua Umum</strong>
                <div class="signature-space"></div>
                <strong>( ________________________ )</strong>
            </td>
            <td>
                Surabaya, <?= date('d F Y') ?><br><strong>Bendahara Umum</strong>
                <div class="signature-space"></div>
                <strong>( ________________________ )</strong>
            </td>
        </tr>
    </table>

</body>
</html>
<?php
$html_output = ob_get_clean();

// ------------------------------------------------------------------
// 4. EKSEKUSI STREAM PDF ATAU HTML PRINT
// ------------------------------------------------------------------
if ($use_dompdf) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html_output);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "Laporan_" . ucfirst($type) . "_" . date('Ymd_His') . ".pdf";
    $dompdf->stream($filename, ["Attachment" => false]);
    exit;
} else {
    // Tampilkan versi HTML siap cetak jika Dompdf belum terpasang
    echo $html_output;
}