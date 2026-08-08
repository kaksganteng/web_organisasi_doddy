-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2026 at 09:16 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_organisasi`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `id_user`, `aktivitas`, `ip_address`, `created_at`) VALUES
(1, 1, 'User login ke dalam sistem', '::1', '2026-08-07 17:43:34'),
(2, 1, 'User logout dari sistem', '::1', '2026-08-07 17:44:12'),
(3, 1, 'User login ke dalam sistem', '::1', '2026-08-07 17:44:17'),
(4, 1, 'User logout dari sistem', '::1', '2026-08-07 17:47:42'),
(5, 1, 'User login ke dalam sistem', '::1', '2026-08-07 17:47:47'),
(6, 1, 'User logout dari sistem', '::1', '2026-08-07 17:56:00'),
(9, 1, 'User login ke dalam sistem', '::1', '2026-08-07 17:56:23'),
(10, 1, 'Menambahkan transaksi kas (PEMASUKAN): Rp 10.001 - Konsumsi', '::1', '2026-08-07 18:49:06'),
(11, 1, 'User logout dari sistem', '::1', '2026-08-07 19:07:16'),
(14, 1, 'User login ke dalam sistem', '::1', '2026-08-07 19:08:33'),
(15, 1, 'User logout dari sistem', '::1', '2026-08-07 19:16:30'),
(18, 1, 'User login ke dalam sistem', '::1', '2026-08-07 19:39:07'),
(19, 1, 'Mengubah data anggota ID: 1', '::1', '2026-08-07 19:40:51'),
(20, 1, 'User logout dari sistem', '::1', '2026-08-07 19:43:33'),
(23, 1, 'User login ke dalam sistem', '::1', '2026-08-07 19:46:06'),
(24, 1, 'Menambahkan transaksi kas (PEMASUKAN): Rp 1.001 - Konsumsi', '::1', '2026-08-07 19:51:35'),
(25, 1, 'Menghapus transaksi kas ID: 2 (PEMASUKAN): Rp 1.001', '::1', '2026-08-07 19:51:39'),
(26, 1, 'Mengubah transaksi kas ID: 1 (PEMASUKAN): Rp 10.000', '::1', '2026-08-07 20:30:19'),
(27, 1, 'Menambahkan transaksi kas (PEMASUKAN): Rp 15.000 - Konsumsi11212', '::1', '2026-08-07 20:30:32'),
(28, 1, 'Menghapus transaksi kas ID: 3 (PEMASUKAN): Rp 15.000', '::1', '2026-08-07 20:30:39'),
(29, 1, 'User logout dari sistem', '::1', '2026-08-07 20:30:44'),
(32, 1, 'User login ke dalam sistem', '::1', '2026-08-07 20:33:55'),
(33, 1, 'User logout dari sistem', '::1', '2026-08-07 20:34:18'),
(34, 1, 'User login ke dalam sistem', '::1', '2026-08-07 20:34:38'),
(35, 1, 'Menambahkan anggota baru: dodi', '::1', '2026-08-07 20:36:17'),
(36, 1, 'Menambahkan anggota baru: Rafael Alexander', '::1', '2026-08-07 20:40:24'),
(37, 1, 'User logout dari sistem', '::1', '2026-08-07 20:40:55'),
(38, 1, 'User login ke dalam sistem', '::1', '2026-08-07 20:41:35'),
(39, 1, 'User logout dari sistem', '::1', '2026-08-07 20:41:38'),
(40, 1, 'User login ke dalam sistem', '::1', '2026-08-07 20:41:59'),
(41, 1, 'Menambahkan anggota baru: Rafael Alexander', '::1', '2026-08-07 20:43:10'),
(42, 1, 'Menambahkan transaksi kas (PEMASUKAN): Rp 50.000 - beli barang', '::1', '2026-08-07 20:48:29'),
(43, 1, 'Menambahkan transaksi kas (PENGELUARAN): Rp 30.000 - Konsumsi', '::1', '2026-08-07 20:49:02'),
(44, 1, 'User logout dari sistem', '::1', '2026-08-07 20:50:03'),
(45, 1, 'User login ke dalam sistem', '::1', '2026-08-07 20:52:04'),
(46, 1, 'Menambahkan anggota baru: Rafael Alexander', '::1', '2026-08-07 20:52:32'),
(47, 1, 'User logout dari sistem', '::1', '2026-08-07 20:52:44'),
(48, 6, 'User login ke dalam sistem', '::1', '2026-08-07 20:52:58'),
(49, 6, 'User logout dari sistem', '::1', '2026-08-07 20:54:09'),
(50, 1, 'User login ke dalam sistem', '::1', '2026-08-08 01:44:34'),
(51, 1, 'User logout dari sistem', '::1', '2026-08-08 01:51:55'),
(52, 6, 'User login ke dalam sistem', '::1', '2026-08-08 01:52:06'),
(53, 1, 'User login ke dalam sistem', '::1', '2026-08-08 01:54:58'),
(54, 1, 'Menambahkan anggota baru: Doddy', '::1', '2026-08-08 02:51:06'),
(55, 7, 'User login ke dalam sistem', '::1', '2026-08-08 02:51:42'),
(56, 1, 'User login ke dalam sistem', '::1', '2026-08-08 06:21:57'),
(57, 1, 'Mengubah data anggota ID: 6', '::1', '2026-08-08 06:35:06'),
(58, 1, 'Mengubah data anggota ID: 5', '::1', '2026-08-08 06:35:21'),
(59, 1, 'User logout dari sistem', '::1', '2026-08-08 06:37:52'),
(60, 6, 'User login ke dalam sistem', '::1', '2026-08-08 06:38:05'),
(61, 6, 'User logout dari sistem', '::1', '2026-08-08 06:39:48');

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tempat_lahir` varchar(50) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `golongan_darah` enum('A','B','AB','O') NOT NULL,
  `rayon` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `tanggal_bergabung` date NOT NULL,
  `status_aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `anggota`
--

INSERT INTO `anggota` (`id`, `nama_lengkap`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `golongan_darah`, `rayon`, `no_hp`, `alamat`, `email`, `tanggal_bergabung`, `status_aktif`, `created_at`, `updated_at`) VALUES
(5, 'Rafael Alexander', 'Biak', '2026-08-08', 'Laki-laki', 'A', 'BIAK TIMUR', '081240107457', 'Jalan Jendral Sudirman, Borobudur\r\nNO.50 Kelurahan Padarni', 'rafaelalexander402@gmail.com', '2026-08-07', 1, '2026-08-07 20:52:32', '2026-08-08 06:35:21'),
(6, 'Doddy', 'surabaya', '2026-06-10', 'Laki-laki', 'A', 'BIAK UTARA', '081240107477', 'biak utara', 'Doddy@gmail.com', '2026-08-08', 1, '2026-08-08 02:51:06', '2026-08-08 06:35:06');

-- --------------------------------------------------------

--
-- Table structure for table `chat`
--

CREATE TABLE `chat` (
  `id` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `pesan` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat`
--

INSERT INTO `chat` (`id`, `id_admin`, `pesan`, `created_at`) VALUES
(1, 1, 'tolong hadir pada rapat 1 agustus', '2026-08-07 19:06:22'),
(2, 1, 'tes guys', '2026-08-07 20:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `iuran`
--

CREATE TABLE `iuran` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_jenis_iuran` int(11) DEFAULT NULL,
  `bulan` tinyint(4) NOT NULL,
  `tahun` year(4) NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `metode` enum('tunai','transfer') NOT NULL,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `tanggal_bayar` date NOT NULL,
  `status` enum('pending','diterima','ditolak') DEFAULT 'pending',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `iuran`
--

INSERT INTO `iuran` (`id`, `id_anggota`, `id_jenis_iuran`, `bulan`, `tahun`, `nominal`, `metode`, `bukti_transfer`, `tanggal_bayar`, `status`, `keterangan`, `created_at`) VALUES
(2, 5, 1, 0, '2026', 100000.00, '', 'bukti_1786154788_8280.jpg', '2026-08-08', 'diterima', 'tes', '2026-08-08 02:06:28'),
(3, 6, 1, 0, '2026', 100000.00, '', 'bukti_1786157727_3180.jpg', '2026-08-08', 'diterima', '', '2026-08-08 02:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `jenis_iuran`
--

CREATE TABLE `jenis_iuran` (
  `id` int(11) NOT NULL,
  `nama_iuran` varchar(100) NOT NULL,
  `tipe_periode` enum('harian','mingguan','bulanan','tahunan','insidental') NOT NULL DEFAULT 'bulanan',
  `nominal_default` decimal(12,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jenis_iuran`
--

INSERT INTO `jenis_iuran` (`id`, `nama_iuran`, `tipe_periode`, `nominal_default`, `keterangan`, `created_at`) VALUES
(1, 'Kas Rutin', '', 100000.00, 'KAS rutin untuk anggota', '2026-08-08 01:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `kas`
--

CREATE TABLE `kas` (
  `id` int(11) NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `tanggal` date NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `sumber_keperluan` varchar(255) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `id_admin` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kas`
--

INSERT INTO `kas` (`id`, `jenis`, `tanggal`, `nominal`, `sumber_keperluan`, `keterangan`, `id_admin`, `created_at`) VALUES
(1, 'pemasukan', '2026-08-07', 10000.00, 'Konsumsi', 'tes', 1, '2026-08-07 18:49:06'),
(4, 'pemasukan', '2026-08-07', 50000.00, 'beli barang', 'barang penting', 1, '2026-08-07 20:48:29'),
(5, 'pengeluaran', '2026-08-07', 30000.00, 'Konsumsi', 'tes', 1, '2026-08-07 20:49:02');

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
  `id_admin` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `isi`, `lampiran`, `id_admin`, `created_at`) VALUES
(1, 'RAPAT', 'Rapat tanggal 1 agustus', NULL, 1, '2026-08-07 19:04:21'),
(2, 'RAPAT pertmuan', 'yfxycycyctycuyyctctycyctyg', NULL, 1, '2026-08-07 20:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','anggota') NOT NULL DEFAULT 'anggota',
  `id_anggota` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `id_anggota`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin123', 'admin', NULL, '2026-08-07 16:46:57', '2026-08-07 17:43:24'),
(6, 'rafael', 'Rafael123', 'anggota', 5, '2026-08-07 20:52:32', '2026-08-07 20:52:32'),
(7, 'doddy', 'Doddy123', 'anggota', 6, '2026-08-08 02:51:06', '2026-08-08 02:51:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indexes for table `iuran`
--
ALTER TABLE `iuran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`);

--
-- Indexes for table `jenis_iuran`
--
ALTER TABLE `jenis_iuran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kas`
--
ALTER TABLE `kas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indexes for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_anggota` (`id_anggota`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat`
--
ALTER TABLE `chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `iuran`
--
ALTER TABLE `iuran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jenis_iuran`
--
ALTER TABLE `jenis_iuran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kas`
--
ALTER TABLE `kas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `chat_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `iuran`
--
ALTER TABLE `iuran`
  ADD CONSTRAINT `iuran_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kas`
--
ALTER TABLE `kas`
  ADD CONSTRAINT `kas_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
