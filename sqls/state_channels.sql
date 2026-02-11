-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2025 at 06:38 PM
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
-- Database: `parcel_express`
--

-- --------------------------------------------------------

--
-- Table structure for table `state_channels`
--

CREATE TABLE `state_channels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipper_id` bigint(20) UNSIGNED NOT NULL,
  `internal_state_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_state_id` bigint(20) UNSIGNED DEFAULT NULL,
  `internal_state_name` varchar(255) DEFAULT NULL,
  `external_state_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `state_channels`
--

INSERT INTO `state_channels` (`id`, `shipper_id`, `internal_state_id`, `external_state_id`, `internal_state_name`, `external_state_name`, `created_at`, `updated_at`) VALUES
(1, 2, 30, 1, 'As-Suwaiq', 'Al Suwaiq', '2025-03-18 09:43:27', '2025-03-18 09:43:27'),
(2, 2, 33, 2, 'Saham', 'Saham', '2025-03-18 09:44:40', '2025-03-18 09:44:40'),
(3, 2, 29, 2, 'Al-Khaburah', 'Tawi Bedi Guish', '2025-03-18 09:44:51', '2025-03-18 09:44:51'),
(4, 2, 32, 2, 'Sahar', 'Sohar', '2025-03-18 09:44:56', '2025-03-18 09:44:56'),
(5, 2, 31, 2, 'Shinas', 'Shinas', '2025-03-18 09:45:02', '2025-03-18 09:45:02'),
(6, 2, 32, 2, 'Sahar', 'Qasabiyat az Za\'ab', '2025-03-18 09:45:09', '2025-03-18 09:45:09'),
(7, 2, 62, 2, 'Al-Buraimi', 'Al Buraimi', '2025-03-18 09:45:18', '2025-03-18 09:45:18'),
(8, 2, 33, 2, 'Saham', 'Ṣaḥam {Saham}', '2025-03-18 09:45:30', '2025-03-18 09:45:30'),
(9, 2, 30, 2, 'As-Suwaiq', 'As-Suwayq', '2025-03-18 09:45:37', '2025-03-18 09:45:37'),
(10, 2, 29, 2, 'Al-Khaburah', 'Al-Khābūrah {Al-Khaburah}', '2025-03-18 09:45:43', '2025-03-18 09:45:43'),
(11, 2, 29, 2, 'Al-Khaburah', 'Al Uwaynat', '2025-03-18 09:45:49', '2025-03-18 09:45:49'),
(12, 2, 32, 2, 'Sahar', 'Ṣuḥār [Sohar] {Suhar}', '2025-03-18 09:45:54', '2025-03-18 09:45:54'),
(13, 2, 34, 2, 'Luwa', 'Liwā {Liwa}', '2025-03-18 09:45:59', '2025-03-18 09:45:59'),
(14, 2, 29, 2, 'Al-Khaburah', 'Falaj Al Qabail', '2025-03-18 09:46:05', '2025-03-18 09:46:05'),
(15, 2, 32, 2, 'Sahar', 'Sohar Industrial Area', '2025-03-18 09:46:09', '2025-03-18 09:46:09'),
(16, 2, 31, 2, 'Shinas', 'Shināṣ {Shinas}', '2025-03-18 09:46:15', '2025-03-18 09:46:15'),
(17, 2, 30, 2, 'As-Suwaiq', 'Ghayl ash shabul', '2025-03-18 09:46:19', '2025-03-18 09:46:19'),
(18, 2, 34, 2, 'Luwa', 'Bu Baqarah', '2025-03-18 09:46:25', '2025-03-18 09:46:25'),
(19, 2, 29, 2, 'Al-Khaburah', 'Al shushbah', '2025-03-18 09:46:30', '2025-03-18 09:46:30'),
(20, 1, 33, 12, 'Saham', 'Saham', '2025-04-23 15:25:44', '2025-04-23 15:25:44'),
(21, 1, 1, 12, 'Muscat', 'Muscat', '2025-04-23 15:25:53', '2025-04-23 15:25:53'),
(22, 1, 2, 12, 'Muttrah', 'Al Maabela', '2025-04-23 15:26:34', '2025-04-23 15:26:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `state_channels`
--
ALTER TABLE `state_channels`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `state_channels`
--
ALTER TABLE `state_channels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
