-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 23, 2025 at 06:20 AM
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
-- Table structure for table `governorates`
--

CREATE TABLE `governorates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `country_id` bigint(20) UNSIGNED NOT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `ar_name` varchar(255) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `polygon` geometry DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `governorates`
--

INSERT INTO `governorates` (`id`, `country_id`, `en_name`, `ar_name`, `lat`, `lng`, `created_at`, `updated_at`) VALUES
(1, 165, 'Muscat', 'مسقط', 23.58803070, 58.38287170, '2025-02-23 00:15:46', '2025-02-23 00:19:38'),
(2, 165, 'Dhofar', 'ظفار', 17.03221210, 54.14252140, '2025-02-23 00:15:47', '2025-02-23 00:19:39'),
(3, 165, 'Musandam', 'مسندم', 26.19861440, 56.24609490, '2025-02-23 00:15:50', '2025-02-23 00:19:40'),
(4, 165, 'Al Sharqiyah North', 'شمال الشرقية', 22.71411960, 58.53080640, '2025-02-23 00:15:53', '2025-02-23 00:19:41'),
(5, 165, 'Al Batinah North', 'شمال الباطنة', 24.34198460, 56.72989040, '2025-02-23 00:15:55', '2025-02-23 00:19:42'),
(6, 165, 'Al Sharqiyah South', 'جنوب الشرقية', 22.01582490, 59.32519220, '2025-02-23 00:15:58', '2025-02-23 00:19:43'),
(7, 165, 'Al Batinah South', 'جنوب الباطنة', 23.43149030, 57.42397960, '2025-02-23 00:15:59', '2025-02-23 00:19:43'),
(8, 165, 'Al Wusta', 'الوسطي', 19.95710780, 56.27568460, '2025-02-23 00:16:02', '2025-02-23 00:19:44'),
(9, 165, 'Al Dhahira', 'الظاهرة', 23.21616740, 56.49074440, '2025-02-23 00:16:02', '2025-02-23 00:19:45'),
(10, 165, 'Al Dakhiliyah', 'الداخلية', 22.85887580, 57.53943560, '2025-02-23 00:16:04', '2025-02-23 00:19:46'),
(11, 165, 'Al-Buraimi', 'البريمي', 24.16714130, 56.11422530, '2025-02-23 00:16:07', '2025-02-23 00:19:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `governorates`
--
ALTER TABLE `governorates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `governorates_country_id_foreign` (`country_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `governorates`
--
ALTER TABLE `governorates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `governorates`
--
ALTER TABLE `governorates`
  ADD CONSTRAINT `governorates_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
