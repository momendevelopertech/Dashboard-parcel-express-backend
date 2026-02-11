-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2025 at 11:14 AM
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
-- Table structure for table `country_channels`
--

CREATE TABLE `country_channels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipper_id` bigint(20) UNSIGNED NOT NULL,
  `internal_country_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_country_id` bigint(20) UNSIGNED DEFAULT NULL,
  `internal_country_name` varchar(255) DEFAULT NULL,
  `external_country_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `country_channels`
--

INSERT INTO `country_channels` (`id`, `shipper_id`, `internal_country_id`, `external_country_id`, `internal_country_name`, `external_country_name`, `created_at`, `updated_at`) VALUES
(1, 2, 165, 1, 'Oman', 'Oman', '2025-03-18 09:26:52', '2025-03-18 09:26:52'),
(2, 2, 165, 1, 'Oman', 'Oman', '2025-03-18 09:42:31', '2025-03-18 09:42:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `country_channels`
--
ALTER TABLE `country_channels`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `country_channels`
--
ALTER TABLE `country_channels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
