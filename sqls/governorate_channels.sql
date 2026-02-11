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
-- Table structure for table `governorate_channels`
--

CREATE TABLE `governorate_channels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipper_id` bigint(20) UNSIGNED NOT NULL,
  `internal_governorate_id` bigint(20) UNSIGNED DEFAULT NULL,
  `internal_governorate_name` varchar(255) DEFAULT NULL,
  `external_governorate_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_governorate_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `governorate_channels`
--

INSERT INTO `governorate_channels` (`id`, `shipper_id`, `internal_governorate_id`, `internal_governorate_name`, `external_governorate_id`, `external_governorate_name`, `created_at`, `updated_at`) VALUES
(1, 2, 5, 'Al Batinah North', 3, 'Al Batinah North', '2025-03-18 09:42:48', '2025-03-18 09:42:48'),
(2, 2, 11, 'Al-Buraimi', 5, 'Al Buraimi', '2025-03-18 09:42:59', '2025-03-18 09:42:59'),
(3, 2, 1, 'Muscat', 1, 'Muscat', '2025-04-23 15:25:14', '2025-04-23 15:25:14'),
(4, 2, 5, 'Al Batinah North', 2, 'Al Batinah North', '2025-04-23 15:25:22', '2025-04-23 15:25:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `governorate_channels`
--
ALTER TABLE `governorate_channels`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `governorate_channels`
--
ALTER TABLE `governorate_channels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
