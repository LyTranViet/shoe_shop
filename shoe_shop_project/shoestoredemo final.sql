-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 06:53 PM
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
-- Database: `shoestoredemo`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `address`, `city`, `district`, `ward`, `postal_code`, `phone`, `is_default`, `created_at`, `updated_at`) VALUES
(15, 14, '123', 'Hồ Chí Minh', 'Quận 7', 'Phường Tân Hưng', '111111111111', '0888609783', 1, '2025-11-10 11:51:48', '2025-11-10 12:08:04'),
(18, 22, '23', 'Lào Cai', 'Huyện Bảo Thắng', 'Thị trấn N.T Phong Hải', '123', '123456', 0, '2025-11-11 23:49:25', '2025-11-12 00:56:08'),
(19, 22, '123', 'Lào Cai', 'Huyện Si Ma Cai', 'Xã Bản Mế', '123456', '123456789', 0, '2025-11-11 23:50:34', '2025-11-11 23:50:34'),
(20, 22, '123', 'Hồ Chí Minh', 'Quận Tân Bình', 'Phường 2', '123', '123456', 1, '2025-11-11 23:52:19', '2025-11-12 00:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `address_codes`
--

CREATE TABLE `address_codes` (
  `id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `ghn_province_id` int(11) NOT NULL COMMENT 'ID Tỉnh/Thành phố theo GHN',
  `ghn_district_id` int(11) NOT NULL COMMENT 'ID Quận/Huyện theo GHN',
  `ghn_ward_code` varchar(10) NOT NULL COMMENT 'Mã Phường/Xã theo GHN',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu trữ các mã ID địa lý số cho tính phí vận chuyển (GHN/GHTK)';

--
-- Dumping data for table `address_codes`
--

INSERT INTO `address_codes` (`id`, `address_id`, `ghn_province_id`, `ghn_district_id`, `ghn_ward_code`, `created_at`, `updated_at`) VALUES
(9, 15, 202, 1449, '20704', '2025-11-10 04:51:48', '2025-11-10 04:51:48'),
(14, 18, 269, 2073, '80401', '2025-11-11 16:49:25', '2025-11-11 16:49:25'),
(16, 19, 269, 2264, '80201', '2025-11-11 16:50:34', '2025-11-11 16:50:34'),
(17, 20, 202, 1455, '21402', '2025-11-11 16:52:19', '2025-11-11 16:52:19');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `image_url`, `link`, `is_active`) VALUES
(1, 'Khuyến mãi Hè', 'assets/images/banner/banner1.jpg', '', 1),
(2, 'khuyến mãi tết trung thu', 'assets/images/banner/banner1.jpg', NULL, 1),
(3, 'khuyến mãi khai trương\r\n', 'assets/images/banner/banner1.jpg', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `description`) VALUES
(1, 'Nike', 'Thương hiệu Nike'),
(2, 'Adidas', 'Thương hiệu Adidas'),
(3, 'Puma', 'Thương hiệu Puma');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_id`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(2, 3, NULL, '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(3, 4, NULL, '2025-10-11 11:01:24', '2025-10-11 11:01:24'),
(4, 10, NULL, '2025-10-16 14:04:24', '2025-10-16 14:04:24'),
(5, 6, NULL, '2025-10-23 00:17:24', '2025-10-23 00:17:24'),
(6, 14, NULL, '2025-10-30 10:29:06', '2025-10-30 10:29:06'),
(7, 16, NULL, '2025-10-31 23:20:15', '2025-10-31 23:20:15'),
(8, 20, NULL, '2025-11-05 18:32:46', '2025-11-05 18:32:46'),
(9, 21, NULL, '2025-11-05 20:09:02', '2025-11-05 20:09:02'),
(10, 22, NULL, '2025-11-08 15:02:47', '2025-11-08 15:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `size`, `quantity`, `price`) VALUES
(1, 1, 1, '40', 1, 2500000.00),
(14, 3, 8, NULL, 1, 2200000.00),
(27, 5, 3, '30', 1, 1200000.00),
(165, 7, 5, NULL, 1, 3000000.00),
(225, 10, 5, '38', 1, 3000000.00),
(245, 6, 7, '41', 1, 3500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Nam', 'Giày dành cho nam'),
(2, 'Nữ', 'Giày dành cho nữ'),
(3, 'Trẻ em', 'Giày trẻ em'),
(4, 'Sneakers', 'Giày Sneakers'),
(5, 'Boots', 'Giày Boots');

-- --------------------------------------------------------

--
<<<<<<< HEAD
-- Cấu trúc bảng cho bảng `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `is_read_by_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `is_read_by_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- Cấu trúc bảng cho bảng `coupons`
=======
-- Table structure for table `coupons`
>>>>>>> 306bbc44839a04fe76846ec25e9f86c6b30a488c
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `discount_percent` int(11) DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_to` datetime DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `discount_type` text NOT NULL DEFAULT 'product' CHECK (`discount_type` in ('product','shipping'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_percent`, `valid_from`, `valid_to`, `usage_limit`, `created_at`, `discount_type`) VALUES
(3, 'TEST10', 10, '2025-01-01 00:00:00', '2025-12-31 00:00:00', NULL, '2025-10-30 13:38:35', 'product'),
(4, 'DISC20', 20, '2025-10-30 00:00:00', '2025-12-31 00:00:00', NULL, '2025-10-30 13:48:44', 'product'),
(5, 'DISC30', 30, '2025-10-30 00:00:00', '2025-12-31 00:00:00', NULL, '2025-10-30 13:48:44', 'product'),
(6, 'DISC50', 50, '2025-10-30 00:00:00', '2025-12-31 00:00:00', NULL, '2025-10-30 13:48:44', 'product');

-- --------------------------------------------------------

--
-- Table structure for table `export_receipt`
--

CREATE TABLE `export_receipt` (
  `id` int(11) NOT NULL,
  `receipt_code` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `export_date` datetime DEFAULT current_timestamp(),
  `export_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT 'Bán hàng',
  `employee_id` int(11) DEFAULT NULL,
  `total_amount` decimal(18,2) DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `status` enum('Đang xử lý','Đã xuất kho','Đã hủy','Hoàn kho') NOT NULL DEFAULT 'Đang xử lý',
  `order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `export_receipt`
--

INSERT INTO `export_receipt` (`id`, `receipt_code`, `export_date`, `export_type`, `employee_id`, `total_amount`, `note`, `status`, `order_id`) VALUES
(1, 'PX20251022094404', '2025-10-22 14:44:04', 'Xuất hủy', 3, 0.00, '', 'Đã xuất kho', NULL),
(2, 'PX20251022102051', '2025-10-22 15:20:51', 'Điều chuyển', 10, 0.00, 'sấ', 'Đã hủy', NULL),
(3, 'PX20251022102503', '2025-10-22 15:25:03', 'Điều chuyển', 10, 500000.00, '', 'Đã xuất kho', NULL),
(4, 'PX-ORD10', '2025-10-23 00:20:49', 'Bán hàng', 6, 3600030.00, 'Tự động tạo cho đơn hàng #10', 'Hoàn kho', 10),
(5, 'PX-ORD11', '2025-10-23 00:24:46', 'Bán hàng', 6, 2800030.00, 'Tự động tạo cho đơn hàng #11', 'Hoàn kho', 11),
(6, 'PX-ORD12', '2025-10-23 00:31:02', 'Bán hàng', 6, 2600030.00, 'Tự động tạo cho đơn hàng #12', 'Hoàn kho', 12),
(7, 'PX-ORD13', '2025-10-23 00:31:57', 'Bán hàng', 6, 1600030.00, 'Tự động tạo cho đơn hàng #13', 'Hoàn kho', 13),
(8, 'PX-ORD14', '2025-10-23 08:41:31', 'Bán hàng', 6, 1200030.00, 'Tự động tạo cho đơn hàng #14', 'Hoàn kho', 14),
(9, 'PX20251027082120', '2025-10-27 14:21:20', 'Xuất hủy', 3, 1800000.00, '', 'Đang xử lý', NULL),
(10, 'PX20251027082650', '2025-10-27 14:26:50', 'Xuất hủy', 3, 1800000.00, 'hédasad', 'Đang xử lý', NULL),
(11, 'PX20251027082853', '2025-10-27 14:28:53', 'Xuất hủy', 3, 900000.00, 'ádasda', 'Đang xử lý', NULL),
(12, 'PX20251027083402', '2025-10-27 14:34:02', 'Điều chuyển', 3, 1800000.00, 'sadd', 'Đang xử lý', NULL),
(13, 'PX-ORD20', '2025-10-30 11:36:04', 'Bán hàng', 14, 7330000.00, 'Tự động tạo cho đơn hàng #20', 'Đang xử lý', 20),
(14, 'PX-ORD21', '2025-10-30 11:49:25', 'Bán hàng', 10, 6030000.00, 'Tự động tạo cho đơn hàng #21', 'Đang xử lý', 21),
(15, 'PX-ORD22', '2025-10-30 11:51:33', 'Bán hàng', 10, 18036501.00, 'Tự động tạo cho đơn hàng #22', 'Đang xử lý', 22),
(16, 'PX-ORD23', '2025-10-30 11:53:09', 'Bán hàng', 14, 2736501.00, 'Tự động tạo cho đơn hàng #23', 'Đang xử lý', 23),
(17, 'PX-ORD24', '2025-10-30 13:39:29', 'Bán hàng', 14, 2466501.00, 'Tự động tạo cho đơn hàng #24', 'Đang xử lý', 24),
(18, 'PX-ORD25', '2025-10-30 13:44:51', 'Bán hàng', 14, 2730000.00, 'Tự động tạo cho đơn hàng #25', 'Đang xử lý', 25),
(19, 'PX-ORD26', '2025-10-30 13:49:23', 'Bán hàng', 14, 1730000.00, 'Tự động tạo cho đơn hàng #26', 'Đang xử lý', 26),
(20, 'PX-ORD27', '2025-10-30 14:16:01', 'Bán hàng', 14, 2736501.00, 'Tự động tạo cho đơn hàng #27', 'Đang xử lý', 27),
(21, 'PX-ORD28', '2025-10-30 14:23:11', 'Bán hàng', 14, 2736501.00, 'Tự động tạo cho đơn hàng #28', 'Đang xử lý', 28),
(22, 'PX-ORD29', '2025-10-30 14:23:51', 'Bán hàng', 14, 3036501.00, 'Tự động tạo cho đơn hàng #29', 'Đang xử lý', 29),
(23, 'PX-ORD30', '2025-10-30 14:56:48', 'Bán hàng', 14, 2736501.00, 'Tự động tạo cho đơn hàng #30', 'Đang xử lý', 30),
(24, 'PX-ORD37', '2025-10-30 16:18:49', 'Bán hàng', 14, 3041001.00, 'Tự động tạo cho đơn hàng #37', 'Đang xử lý', 37),
(25, 'PX-ORD38', '2025-10-30 16:35:19', 'Bán hàng', 14, 4901001.00, 'Tự động tạo cho đơn hàng #38', 'Đang xử lý', 38),
(26, 'PX-ORD39', '2025-10-30 16:41:41', 'Bán hàng', 14, 2841001.00, 'Tự động tạo cho đơn hàng #39', 'Đang xử lý', 39),
(27, 'PX-ORD40', '2025-10-30 16:47:34', 'Bán hàng', 14, 3441001.00, 'Tự động tạo cho đơn hàng #40', 'Đang xử lý', 40),
(28, 'PX-ORD41', '2025-10-30 16:53:37', 'Bán hàng', 14, 2742000.00, 'Tự động tạo cho đơn hàng #41', 'Đang xử lý', 41),
(29, 'PX-ORD42', '2025-10-30 16:56:01', 'Bán hàng', 14, 2741001.00, 'Tự động tạo cho đơn hàng #42', 'Đang xử lý', 42),
(30, 'PX-ORD43', '2025-10-30 16:58:12', 'Bán hàng', 14, 2700030.00, 'Tự động tạo cho đơn hàng #43', 'Đang xử lý', 43),
(31, 'PX-ORD44', '2025-11-01 00:12:09', 'Bán hàng', 16, 2736501.00, 'Tự động tạo cho đơn hàng #44', 'Đang xử lý', 44),
(32, 'PX-ORD45', '2025-11-01 00:42:21', 'Bán hàng', 16, 1736501.00, 'Tự động tạo cho đơn hàng #45', 'Đang xử lý', 45),
(33, 'PX-ORD46', '2025-11-01 00:53:40', 'Bán hàng', 16, 3821001.00, 'Tự động tạo cho đơn hàng #46', 'Đang xử lý', 46),
(34, 'PX-ORD47', '2025-11-01 00:58:26', 'Bán hàng', 16, 2136501.00, 'Tự động tạo cho đơn hàng #47', 'Đang xử lý', 47),
(35, 'PX-ORD48', '2025-11-01 01:26:55', 'Bán hàng', 16, 6841001.00, 'Tự động tạo cho đơn hàng #48', 'Đang xử lý', 48),
(36, 'PX-ORD49', '2025-11-01 01:33:45', 'Bán hàng', 16, 11795000.00, 'Tự động tạo cho đơn hàng #49', 'Đang xử lý', 49),
(37, 'PX-ORD50', '2025-11-01 01:38:46', 'Bán hàng', 16, 6036501.00, 'Tự động tạo cho đơn hàng #50', 'Đang xử lý', 50),
(38, 'PX-ORD51', '2025-11-01 01:43:55', 'Bán hàng', 16, 10845000.00, 'Tự động tạo cho đơn hàng #51', 'Đang xử lý', 51),
(39, 'PX-ORD52', '2025-11-01 01:49:53', 'Bán hàng', 16, 1386501.00, 'Tự động tạo cho đơn hàng #52', 'Đang xử lý', 52),
(40, 'PX-ORD53', '2025-11-01 02:05:37', 'Bán hàng', 16, 1393801.00, 'Tự động tạo cho đơn hàng #53', 'Đang xử lý', 53),
(41, 'PX-ORD54', '2025-11-01 02:12:11', 'Bán hàng', 14, 6209201.00, 'Tự động tạo cho đơn hàng #54', 'Đang xử lý', 54),
(42, 'PX-ORD55', '2025-11-01 02:24:35', 'Bán hàng', 14, 1932000.00, 'Tự động tạo cho đơn hàng #55', 'Đang xử lý', 55),
(43, 'PX-ORD61', '2025-11-01 09:52:00', 'Bán hàng', 16, 1799201.00, 'Tự động tạo cho đơn hàng #61', 'Đang xử lý', 61),
(44, 'PX-ORD62', '2025-11-01 09:54:33', 'Bán hàng', 16, 3041001.00, 'Tự động tạo cho đơn hàng #62', 'Đang xử lý', 62),
(45, 'PX-ORD63', '2025-11-01 09:58:04', 'Bán hàng', 16, 2741001.00, 'Tự động tạo cho đơn hàng #63', 'Đang xử lý', 63),
(46, 'PX-ORD64', '2025-11-01 10:02:30', 'Bán hàng', 14, 41001.00, 'Tự động tạo cho đơn hàng #64', 'Đang xử lý', 64),
(47, 'PX-ORD65', '2025-11-01 11:22:39', 'Bán hàng', 14, 6001640.04, 'Tự động tạo cho đơn hàng #65', 'Đang xử lý', 65),
(48, 'PX-ORD66', '2025-11-01 11:27:36', 'Bán hàng', 14, 3001360.00, 'Tự động tạo cho đơn hàng #66', 'Đang xử lý', 66),
(49, 'PX-ORD67', '2025-11-05 18:33:28', 'Bán hàng', 20, 3006000.00, 'Tự động tạo cho đơn hàng #67', 'Đang xử lý', 67),
(50, 'PX-ORD68', '2025-11-05 19:09:09', 'Bán hàng', 20, 9506000.00, 'Tự động tạo cho đơn hàng #68', 'Đang xử lý', 68),
(51, 'PX-ORD69', '2025-11-05 19:24:52', 'Bán hàng', 20, 3005000.00, 'Tự động tạo cho đơn hàng #69', 'Đang xử lý', 69),
(52, 'PX-ORD72', '2025-11-05 19:30:36', 'Bán hàng', 20, 3005000.00, 'Tự động tạo cho đơn hàng #72', 'Đang xử lý', 72),
(53, 'PX-ORD74', '2025-11-05 19:38:09', 'Bán hàng', 20, 3505000.00, 'Tự động tạo cho đơn hàng #74', 'Đang xử lý', 74),
(54, 'PX-ORD81', '2025-11-05 19:59:08', 'Bán hàng', 20, 3006000.00, 'Tự động tạo cho đơn hàng #81', 'Đang xử lý', 81),
(55, 'PX-ORD83', '2025-11-05 20:10:28', 'Bán hàng', 21, 3505000.00, 'Tự động tạo cho đơn hàng #83', 'Đang xử lý', 83),
(56, 'PX-ORD85', '2025-11-05 20:16:10', 'Bán hàng', 21, 3006000.00, 'Tự động tạo cho đơn hàng #85', 'Đang xử lý', 85),
(57, 'PX-ORD86', '2025-11-05 20:16:33', 'Bán hàng', 21, 2805000.00, 'Tự động tạo cho đơn hàng #86', 'Đang xử lý', 86),
(58, 'PX-ORD98', '2025-11-05 21:30:24', 'Bán hàng', 21, 1605000.00, 'Tự động tạo cho đơn hàng #98', 'Đang xử lý', 98),
(59, 'PX-ORD107', '2025-11-05 22:07:30', 'Bán hàng', 21, 4505000.00, 'Tự động tạo cho đơn hàng #107', 'Đang xử lý', 107),
(60, 'PX-ORD108', '2025-11-05 22:13:10', 'Bán hàng', 21, 1205000.00, 'Tự động tạo cho đơn hàng #108', 'Đang xử lý', 108),
(61, 'PX-ORD111', '2025-11-05 22:30:07', 'Bán hàng', 21, 4105000.00, 'Tự động tạo cho đơn hàng #111', 'Đang xử lý', 111),
(62, 'PX-ORD113', '2025-11-05 22:39:44', 'Bán hàng', 21, 5605000.00, 'Tự động tạo cho đơn hàng #113', 'Đang xử lý', 113),
(63, 'PX-ORD116', '2025-11-06 10:18:46', 'Bán hàng', 21, 8405000.00, 'Tự động tạo cho đơn hàng #116', 'Đang xử lý', 116),
(64, 'PX-ORD119', '2025-11-06 10:27:42', 'Bán hàng', 21, 1205000.00, 'Tự động tạo cho đơn hàng #119', 'Đang xử lý', 119),
(65, 'PX-ORD121', '2025-11-06 10:31:09', 'Bán hàng', 21, 3605000.00, 'Tự động tạo cho đơn hàng #121', 'Đang xử lý', 121),
(66, 'PX-ORD124', '2025-11-06 10:36:59', 'Bán hàng', 21, 1405000.00, 'Tự động tạo cho đơn hàng #124', 'Đang xử lý', 124),
(67, 'PX-ORD127', '2025-11-06 10:52:38', 'Bán hàng', 21, 4405000.00, 'Tự động tạo cho đơn hàng #127', 'Đang xử lý', 127),
(68, 'PX-ORD129', '2025-11-06 10:54:18', 'Bán hàng', 21, 3505000.00, 'Tự động tạo cho đơn hàng #129', 'Đang xử lý', 129),
(69, 'PX-ORD131', '2025-11-06 11:01:52', 'Bán hàng', 21, 5605000.00, 'Tự động tạo cho đơn hàng #131', 'Đang xử lý', 131),
(70, 'PX-ORD142', '2025-11-06 12:02:24', 'Bán hàng', 21, 2805000.00, 'Tự động tạo cho đơn hàng #142', 'Đang xử lý', 142),
(71, 'PX-ORD158', '2025-11-06 12:58:41', 'Bán hàng', 21, 3505000.00, 'Tự động tạo cho đơn hàng #158', 'Đang xử lý', 158),
(72, 'PX-ORD161', '2025-11-06 22:48:46', 'Bán hàng', 16, 6006000.00, 'Tự động tạo cho đơn hàng #161', 'Đang xử lý', 161),
(73, 'PX-ORD163', '2025-11-07 00:15:16', 'Bán hàng', 16, 2805000.00, 'Tự động tạo cho đơn hàng #163', 'Đang xử lý', 163),
(74, 'PX-ORD168', '2025-11-07 01:38:32', 'Bán hàng', 16, 1505000.00, 'Tự động tạo cho đơn hàng #168', 'Đang xử lý', 168),
(75, 'PX-ORD169', '2025-11-07 19:02:27', 'Bán hàng', 16, 6005000.00, 'Tự động tạo cho đơn hàng #169', 'Đang xử lý', 169),
(76, 'PX-ORD173', '2025-11-07 20:11:38', 'Bán hàng', 16, 3005000.00, 'Tự động tạo cho đơn hàng #173', 'Đang xử lý', 173),
(77, 'PX-ORD174', '2025-11-07 21:47:54', 'Bán hàng', 16, 3005000.00, 'Tự động tạo cho đơn hàng #174', 'Đang xử lý', 174),
(78, 'PX-ORD178', '2025-11-07 23:49:39', 'Bán hàng', 16, 6005000.00, 'Tự động tạo cho đơn hàng #178', 'Đang xử lý', 178),
(79, 'PX-ORD181', '2025-11-08 00:04:33', 'Bán hàng', 16, 3005000.00, 'Tự động tạo cho đơn hàng #181', 'Đang xử lý', 181),
(80, 'PX-ORD185', '2025-11-08 00:08:20', 'Bán hàng', 16, 3005000.00, 'Tự động tạo cho đơn hàng #185', 'Đang xử lý', 185),
(81, 'PX-ORD186', '2025-11-08 15:03:32', 'Bán hàng', 22, 985000.00, 'Tự động tạo cho đơn hàng #186', 'Đang xử lý', 186),
(82, 'PX-ORD188', '2025-11-10 13:09:52', 'Bán hàng', 14, 6025000.00, 'Tự động tạo cho đơn hàng #188', 'Đang xử lý', 188),
(83, 'PX-ORD189', '2025-11-12 13:23:14', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #189', 'Đang xử lý', 189),
(84, 'PX-ORD190', '2025-11-12 14:19:13', 'Bán hàng', 22, 4500000.00, 'Tự động tạo cho đơn hàng #190', 'Đang xử lý', 190),
(85, 'PX-ORD191', '2025-11-12 15:48:33', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #191', 'Đang xử lý', 191),
(86, 'PX-ORD193', '2025-11-12 16:02:39', 'Bán hàng', 22, 6000000.00, 'Tự động tạo cho đơn hàng #193', 'Đang xử lý', 193),
(87, 'PX-ORD194', '2025-11-12 17:13:13', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #194', 'Đang xử lý', 194),
(88, 'PX-ORD195', '2025-11-12 17:13:59', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #195', 'Đang xử lý', 195),
(89, 'PX-ORD196', '2025-11-12 17:15:30', 'Bán hàng', 22, 1600000.00, 'Tự động tạo cho đơn hàng #196', 'Đang xử lý', 196),
(90, 'PX-ORD197', '2025-11-12 17:17:27', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #197', 'Đang xử lý', 197),
(91, 'PX-ORD198', '2025-11-13 09:42:59', 'Bán hàng', 22, 7600000.00, 'Tự động tạo cho đơn hàng #198', 'Đang xử lý', 198),
(92, 'PX-ORD199', '2025-11-13 11:21:10', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #199', 'Đang xử lý', 199),
(93, 'PX-ORD200', '2025-11-13 11:27:35', 'Bán hàng', 22, 3000000.00, 'Tự động tạo cho đơn hàng #200', 'Đang xử lý', 200),
(94, 'PX-ORD201', '2025-11-13 11:28:43', 'Bán hàng', 22, 7000000.00, 'Tự động tạo cho đơn hàng #201', 'Đang xử lý', 201),
(95, 'PX-ORD202', '2025-11-13 11:29:22', 'Bán hàng', 22, 7000000.00, 'Tự động tạo cho đơn hàng #202', 'Đang xử lý', 202),
(96, 'PX-ORD203', '2025-11-13 11:30:31', 'Bán hàng', 22, 6000000.00, 'Tự động tạo cho đơn hàng #203', 'Đang xử lý', 203),
(97, 'PX-ORD204', '2025-11-13 11:31:08', 'Bán hàng', 22, 30000000.00, 'Tự động tạo cho đơn hàng #204', 'Đang xử lý', 204),
(98, 'PX-ORD205', '2025-11-13 11:32:09', 'Bán hàng', 22, 24000000.00, 'Tự động tạo cho đơn hàng #205', 'Đang xử lý', 205),
(99, 'PX-ORD211', '2025-11-14 00:49:54', 'Bán hàng', 14, 3006920.00, 'Tự động tạo cho đơn hàng #211', 'Đang xử lý', 211);

-- --------------------------------------------------------

--
-- Table structure for table `export_receipt_detail`
--

CREATE TABLE `export_receipt_detail` (
  `id` int(11) NOT NULL,
  `export_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `productsize_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `export_receipt_detail`
--

INSERT INTO `export_receipt_detail` (`id`, `export_id`, `batch_id`, `productsize_id`, `quantity`, `price`) VALUES
(1, 1, 6, 5, 1, 0.00),
(2, 2, 4, 37, 1, 0.00),
(3, 3, 6, 5, 1, 500000.00),
(4, 4, 3, 5, 3, 1200000.00),
(5, 6, 5, 64, 2, 1300000.00),
(6, 7, 2, 37, 1, 1600000.00),
(7, 8, 3, 5, 1, 1200000.00),
(8, 9, 3, 5, 2, 900000.00),
(9, 10, 3, 5, 2, 900000.00),
(10, 11, 3, 5, 1, 900000.00),
(11, 12, 3, 5, 2, 900000.00),
(12, 17, 7, 66, 1, 2700000.00),
(13, 19, 16, 48, 1, 3400000.00),
(14, 20, 7, 66, 1, 2700000.00),
(15, 21, 7, 66, 1, 2700000.00),
(16, 23, 7, 66, 1, 2700000.00),
(17, 31, 7, 66, 1, 2700000.00),
(18, 33, 11, 66, 1, 2700000.00),
(19, 35, 16, 48, 1, 3400000.00),
(20, 36, 14, 84, 4, 2800000.00),
(21, 36, 15, 84, 2, 2800000.00),
(22, 41, 11, 66, 1, 2700000.00),
(23, 45, 11, 66, 1, 2700000.00),
(24, 46, 11, 66, 2, 2700000.00),
(25, 89, 2, 37, 1, 1600000.00),
(26, 91, 2, 37, 1, 1600000.00);

-- --------------------------------------------------------

--
-- Table structure for table `import_receipt`
--

CREATE TABLE `import_receipt` (
  `id` int(11) NOT NULL,
  `receipt_code` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `import_date` datetime DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `total_amount` decimal(18,2) DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `status` enum('Đang chờ xác nhận','Xác nhận','Hủy') NOT NULL DEFAULT 'Đang chờ xác nhận'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `import_receipt`
--

INSERT INTO `import_receipt` (`id`, `receipt_code`, `import_date`, `supplier_id`, `employee_id`, `total_amount`, `note`, `status`) VALUES
(2, 'PN20251020203027', '2025-10-21 01:30:27', 1, 10, 24000000.00, '123Puma Ignite', 'Đang chờ xác nhận'),
(3, 'PN20251020204847', '2025-10-21 01:48:47', 1, 10, 1200000.00, '123Puma Ignite', 'Đang chờ xác nhận'),
(4, 'PN20251020205152', '2025-10-21 01:51:52', 1, 10, 2700000.00, '123456', 'Đang chờ xác nhận'),
(5, 'PN-HOAN-10', '2025-10-23 00:23:52', NULL, 3, 0.00, 'Tự động tạo do hoàn hàng từ đơn hàng #10', 'Đang chờ xác nhận'),
(6, 'PN-HOAN-11', '2025-10-23 00:25:50', NULL, 3, 0.00, 'Tự động tạo do hoàn hàng từ đơn hàng #11', 'Đang chờ xác nhận'),
(7, 'PN-HOAN-12', '2025-10-23 00:31:25', NULL, 3, 0.00, 'Tự động tạo do hoàn hàng từ đơn hàng #12', 'Đang chờ xác nhận'),
(8, 'PN-HOAN-13', '2025-10-23 00:32:32', NULL, 3, 0.00, 'Tự động tạo do hoàn hàng từ đơn hàng #13', 'Đang chờ xác nhận'),
(9, 'PN-HOAN-14', '2025-10-23 08:43:02', NULL, 3, 0.00, 'Tự động tạo do hoàn hàng từ đơn hàng #14', 'Đang chờ xác nhận'),
(10, 'PN20251027082928', '2025-10-27 14:29:28', 2, 3, 2500000.00, 'addsads', 'Đang chờ xác nhận'),
(11, 'PN20251027083316', '2025-10-27 14:33:16', 2, 3, 85000000.00, 'adas', 'Đang chờ xác nhận'),
(12, 'PN20251027083344', '2025-10-27 14:33:44', 2, 3, 85000000.00, 'adas', 'Đang chờ xác nhận'),
(13, 'PN20251027083703', '2025-10-27 14:37:03', 2, 3, 150000000.00, '', 'Đang chờ xác nhận'),
(14, 'PN20251027083935', '2025-10-27 14:39:35', 2, 3, 2938892875.00, '', 'Đang chờ xác nhận'),
(15, 'PN20251027084858', '2025-10-27 14:48:58', 2, 3, 25445245.00, '', 'Đang chờ xác nhận'),
(16, 'PN20251027085506', '2025-10-27 14:55:06', 1, 3, 16.00, '', 'Đang chờ xác nhận'),
(17, 'PN20251027085857', '2025-10-27 14:58:57', 1, 3, 221016.00, '', 'Đang chờ xác nhận'),
(18, 'PN20251027085907', '2025-10-27 14:59:07', 1, 3, 221016.00, 'sdffsf', 'Đang chờ xác nhận'),
(19, 'PN20251027090233', '2025-10-27 15:02:33', 2, 3, 5055454.00, 'sdadasd', 'Đang chờ xác nhận'),
(20, 'PN20251027091423', '2025-10-27 15:14:23', 1, 3, 45610500.00, '', 'Đang chờ xác nhận'),
(21, 'PN20251027180218', '2025-10-28 00:02:18', 2, 3, 16363692.00, '', 'Đang chờ xác nhận');

-- --------------------------------------------------------

--
-- Table structure for table `import_receipt_detail`
--

CREATE TABLE `import_receipt_detail` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `productsize_id` int(11) NOT NULL,
  `batch_code` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `batch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `import_receipt_detail`
--

INSERT INTO `import_receipt_detail` (`id`, `import_id`, `productsize_id`, `batch_code`, `quantity`, `price`, `batch_id`) VALUES
(2, 2, 37, 'L20251020-22', 12, 500000.00, 2),
(3, 2, 5, 'L20251020-3', 20, 900000.00, 3),
(4, 3, 37, 'L20251020-22-37', 2, 600000.00, 4),
(5, 4, 64, 'L20251020-12-64', 2, 600000.00, 5),
(6, 4, 5, 'L20251020-3-5', 3, 500000.00, 6),
(7, 7, 64, 'L20251020-12-64', 2, 1300000.00, 5),
(8, 8, 37, 'L20251020-22', 1, 1600000.00, 2),
(9, 9, 5, 'L20251020-3', 1, 1200000.00, 3),
(10, 10, 66, 'L20251027-11-66', 5, 500000.00, 7),
(11, 11, 37, 'L20251027-22-37', 17, 5000000.00, 8),
(12, 12, 37, 'L20251027-22-37', 17, 5000000.00, 9),
(13, 13, 43, 'L20251027-19-43', 3, 50000000.00, 10),
(14, 14, 66, 'L20251027-11-66', 5, 99999999.99, 11),
(15, 15, 5, 'L20251027-3-5', 1, 25445245.00, 12),
(16, 16, 37, 'L20251027-22-37', 4, 4.00, 13),
(17, 17, 84, 'L20251027-4-84', 4, 55254.00, 14),
(18, 18, 84, 'L20251027-4-84', 4, 55254.00, 15),
(19, 19, 48, 'L20251027-17-48', 2, 2527727.00, 16),
(20, 20, 43, 'L20251027-19-43', 5, 9122100.00, 17),
(21, 21, 84, 'L20251027-4-84', 3, 5454564.00, 18);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `content`, `is_read`, `created_at`) VALUES
(1, 2, 'Giảm giá 10%', 'Coupon SUMMER2025 áp dụng cho đơn hàng đầu tiên', 0, '2025-10-11 08:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `shipping_fee` int(11) DEFAULT 0,
  `shipping_carrier` varchar(50) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `shipping_discount_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_coupon_code` varchar(50) DEFAULT NULL,
  `vnpay_trans_no` varchar(50) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `paypal_order_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `discount_amount`, `coupon_code`, `shipping_fee`, `shipping_carrier`, `shipping_address`, `status_id`, `coupon_id`, `payment_method`, `phone`, `created_at`, `updated_at`, `shipping_discount_amount`, `shipping_coupon_code`, `vnpay_trans_no`, `paid_at`, `paypal_order_id`) VALUES
(1, 2, 2500000.00, 0.00, NULL, 0, NULL, '123 Đường ABC, HCM', 4, NULL, 'COD', NULL, '2025-10-11 08:50:12', '2025-10-12 08:49:05', 0.00, NULL, NULL, NULL, NULL),
(2, 4, 15400000.00, 0.00, NULL, 0, NULL, '26', 3, NULL, 'CARD', NULL, '2025-10-12 08:42:59', '2025-10-12 08:56:47', 0.00, NULL, NULL, NULL, NULL),
(3, 4, 4600000.00, 0.00, NULL, 0, NULL, 'ho chi minh', 1, NULL, 'COD', NULL, '2025-10-12 12:07:16', '2025-10-12 12:07:16', 0.00, NULL, NULL, NULL, NULL),
(4, 4, 2300000.00, 0.00, NULL, 0, NULL, 'ho chi minh', 1, NULL, 'CARD', NULL, '2025-10-12 12:35:28', '2025-10-12 12:35:28', 0.00, NULL, NULL, NULL, NULL),
(5, 4, 2300000.00, 0.00, NULL, 0, NULL, 'hồ chí mình', 2, NULL, 'COD', NULL, '2025-10-12 13:55:06', '2025-10-12 14:04:16', 0.00, NULL, NULL, NULL, NULL),
(6, 4, 2380000.00, 0.00, NULL, 0, NULL, 'hồ chí minh', 1, 2, 'COD', NULL, '2025-10-12 15:46:09', '2025-10-12 15:46:09', 0.00, NULL, NULL, NULL, NULL),
(7, 14, 5300030.00, 0.00, NULL, 0, NULL, '223a/9 ấp 1, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-22 22:53:23', '2025-10-22 22:53:23', 0.00, NULL, NULL, NULL, NULL),
(8, 14, 2100030.00, 0.00, NULL, 0, NULL, '2231 , Xã Sốp Cộp, huyện Sốp Cộp, Sơn La', 1, NULL, 'COD', NULL, '2025-10-22 23:01:08', '2025-10-22 23:01:08', 0.00, NULL, NULL, NULL, NULL),
(9, 14, 18800030.00, 0.00, NULL, 0, NULL, 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-23 09:42:46', '2025-10-23 09:42:46', 0.00, NULL, NULL, NULL, NULL),
(10, 14, 2100030.00, 0.00, NULL, 0, NULL, 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-23 09:44:26', '2025-10-23 09:44:26', 0.00, NULL, NULL, NULL, NULL),
(11, 14, 6000030.00, 0.00, NULL, 0, NULL, '223A/9 ấp 1, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-29 15:59:44', '2025-10-29 15:59:44', 0.00, NULL, NULL, NULL, NULL),
(12, 14, 4600001.20, 0.00, NULL, 0, NULL, 'Địa chỉ chi tiết, Xã Bình Chánh, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-29 16:51:35', '2025-10-29 16:51:35', 0.00, NULL, NULL, NULL, NULL),
(13, 14, 2600030.00, 0.00, NULL, 30, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-29 23:35:16', '2025-10-29 23:35:16', 0.00, NULL, NULL, NULL, NULL),
(14, 14, 2600030.00, 0.00, NULL, 30, 'GHN', '223A/9 tổ 9 ấp 1, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-29 23:54:46', '2025-10-29 23:54:46', 0.00, NULL, NULL, NULL, NULL),
(15, 14, 2300001.20, 0.00, NULL, 1, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-30 00:18:41', '2025-10-30 00:18:41', 0.00, NULL, NULL, NULL, NULL),
(16, 14, 1600001.20, 0.00, NULL, 1, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-30 00:45:37', '2025-10-30 00:45:37', 0.00, NULL, NULL, NULL, NULL),
(17, 14, 1600030.00, 0.00, NULL, 30, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-30 00:49:13', '2025-10-30 00:49:13', 0.00, NULL, NULL, NULL, NULL),
(19, 14, 4200030.00, 0.00, NULL, 30, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', '0961759523', '2025-10-30 09:09:30', '2025-10-30 09:09:30', 0.00, NULL, NULL, NULL, NULL),
(20, 14, 7330000.00, 0.00, NULL, 30000, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 11:36:04', '2025-10-30 11:36:04', 0.00, NULL, NULL, NULL, NULL),
(21, 10, 6030000.00, 0.00, NULL, 30000, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 11:49:25', '2025-10-30 11:49:25', 0.00, NULL, NULL, NULL, NULL),
(22, 10, 18036501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 11:51:33', '2025-10-30 11:51:33', 0.00, NULL, NULL, NULL, NULL),
(23, 14, 2736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 11:53:09', '2025-10-30 11:53:09', 0.00, NULL, NULL, NULL, NULL),
(24, 14, 2466501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, 3, 'COD', NULL, '2025-10-30 13:39:29', '2025-10-30 13:39:29', 0.00, NULL, NULL, NULL, NULL),
(25, 14, 2730000.00, 0.00, NULL, 30000, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, 3, 'COD', NULL, '2025-10-30 13:44:51', '2025-10-30 13:44:51', 0.00, NULL, NULL, NULL, NULL),
(26, 14, 1730000.00, 0.00, NULL, 30000, 'GHTK', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, 6, 'COD', NULL, '2025-10-30 13:49:23', '2025-10-30 13:49:23', 0.00, NULL, NULL, NULL, NULL),
(27, 14, 2736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 14:16:01', '2025-10-30 14:16:01', 0.00, NULL, NULL, NULL, NULL),
(28, 14, 2736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã Bình Chánh, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 14:23:11', '2025-10-30 14:23:11', 0.00, NULL, NULL, NULL, NULL),
(29, 14, 3036501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 14:23:51', '2025-10-30 14:23:51', 0.00, NULL, NULL, NULL, NULL),
(30, 14, 2736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Phú Tây, Huyện Bình Chánh, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-10-30 14:56:48', '2025-10-30 14:56:48', 0.00, NULL, NULL, NULL, NULL),
(31, 14, 1391001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Phú Nghĩa, Huyện Lạc Thủy, Hòa Bình', 1, 6, 'COD', NULL, '2025-10-30 15:54:21', '2025-10-30 15:54:21', 0.00, NULL, NULL, NULL, NULL),
(32, 14, 1931001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Xuân Giao, Huyện Bảo Thắng, Lào Cai', 1, 5, 'COD', NULL, '2025-10-30 15:56:06', '2025-10-30 15:56:06', 0.00, NULL, NULL, NULL, NULL),
(33, 14, 1745000.00, 0.00, NULL, 45000, 'GHTK', 'Địa chỉ chi tiết, Xã Đồng Tân, Huyện Mai Châu, Hòa Bình', 1, 6, 'COD', NULL, '2025-10-30 15:57:54', '2025-10-30 15:57:54', 0.00, NULL, NULL, NULL, NULL),
(34, 14, 1741001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Thị trấn Trần Cao, Huyện Phù Cừ, Hưng Yên', 1, 6, 'COD', NULL, '2025-10-30 16:01:04', '2025-10-30 16:01:04', 0.00, NULL, NULL, NULL, NULL),
(35, 14, 2475000.00, 0.00, NULL, 45000, 'GHTK', 'Địa chỉ chi tiết, Xã Tả Thàng, Huyện Mường Khương, Lào Cai', 1, 3, 'COD', NULL, '2025-10-30 16:04:07', '2025-10-30 16:04:07', 0.00, NULL, NULL, NULL, NULL),
(36, 14, 1392000.00, 0.00, NULL, 42000, 'GHTK', 'Địa chỉ chi tiết, Xã Tiền Tiến, Huyện Phù Cừ, Hưng Yên', 1, 6, 'COD', NULL, '2025-10-30 16:05:54', '2025-10-30 16:05:54', 0.00, NULL, NULL, NULL, NULL),
(37, 14, 3041001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Yên Lạc, Huyện Yên Thủy, Hòa Bình', 1, NULL, 'COD', NULL, '2025-10-30 16:18:49', '2025-10-30 16:18:49', 0.00, NULL, NULL, NULL, NULL),
(38, 14, 4901001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, 3, 'COD', NULL, '2025-10-30 16:35:19', '2025-10-30 16:35:19', 0.00, NULL, NULL, NULL, NULL),
(39, 14, 2841001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Đồng Tân, Huyện Mai Châu, Hòa Bình', 1, NULL, 'COD', NULL, '2025-10-30 16:41:41', '2025-10-30 16:41:41', 0.00, NULL, NULL, NULL, NULL),
(40, 14, 3441001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Phú Nghĩa, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'COD', NULL, '2025-10-30 16:47:34', '2025-10-30 16:47:34', 0.00, NULL, NULL, NULL, NULL),
(41, 14, 2742000.00, 0.00, NULL, 42000, 'GHTK', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-10-30 16:53:37', '2025-10-30 16:53:37', 0.00, NULL, NULL, NULL, NULL),
(42, 14, 2741001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Yên Phú, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'COD', NULL, '2025-10-30 16:56:01', '2025-10-30 16:56:01', 0.00, NULL, NULL, NULL, NULL),
(43, 14, 2700030.00, 0.00, NULL, 0, NULL, 'ágsG', 3, NULL, 'COD', NULL, '2025-10-30 16:58:12', '2025-10-30 17:01:20', 0.00, NULL, NULL, NULL, NULL),
(44, 16, 2736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã Ngũ Phụng, Huyện đảo Phú Quý, Bình Thuận', 1, NULL, 'COD', NULL, '2025-11-01 00:12:09', '2025-11-01 00:12:09', 0.00, NULL, NULL, NULL, NULL),
(45, 16, 1736501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Xã An Hải, Huyện Tuy An, Phú Yên', 1, 6, 'COD', NULL, '2025-11-01 00:42:21', '2025-11-01 00:42:21', 0.00, NULL, NULL, NULL, NULL),
(46, 16, 3821001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, 5, 'COD', NULL, '2025-11-01 00:53:40', '2025-11-01 00:53:40', 0.00, NULL, NULL, NULL, NULL),
(47, 16, 2136501.00, 0.00, NULL, 36501, 'GHN', 'Địa chỉ chi tiết, Thị trấn Phú Thứ, Huyện Tây Hòa, Phú Yên', 1, 5, 'COD', NULL, '2025-11-01 00:58:26', '2025-11-01 00:58:26', 0.00, NULL, NULL, NULL, NULL),
(48, 16, 6841001.00, 0.00, NULL, 41001, 'GHN', 'Địa chỉ chi tiết, Xã Thành Sơn, Huyện Mai Châu, Hòa Bình', 1, 6, 'COD', NULL, '2025-11-01 01:26:55', '2025-11-01 01:26:55', 0.00, NULL, NULL, NULL, NULL),
(49, 16, 11795000.00, 5040000.00, 'DISC30', 35000, 'GHTK', 'Địa chỉ chi tiết, Phường Nhơn Hưng, Thị xã An Nhơn, Bình Định', 1, 5, 'COD', NULL, '2025-11-01 01:33:45', '2025-11-01 01:33:45', 0.00, NULL, NULL, NULL, NULL),
(50, 16, 6036501.00, 6000000.00, 'DISC50', 36501, 'GHN', 'Địa chỉ chi tiết, Thị trấn Sa Thầy, Huyện Sa Thầy, Kon Tum', 1, 6, 'COD', NULL, '2025-11-01 01:38:46', '2025-11-01 01:38:46', 0.00, NULL, NULL, NULL, NULL),
(51, 16, 10845000.00, 10800000.00, 'DISC50', 45000, 'GHTK', 'Địa chỉ chi tiết, Xã Thanh Bình, Huyện Mường Khương, Lào Cai', 1, 6, 'COD', NULL, '2025-11-01 01:43:55', '2025-11-01 01:43:55', 0.00, NULL, NULL, NULL, NULL),
(52, 16, 1386501.00, 1350000.00, 'DISC50', 36501, 'GHN', 'Địa chỉ chi tiết, Thị trấn Thuận Nam, Huyện Hàm Thuận Nam, Bình Thuận', 1, 6, 'COD', NULL, '2025-11-01 01:49:53', '2025-11-01 01:49:53', 0.00, NULL, NULL, NULL, NULL),
(53, 16, 1393801.00, 1350000.00, 'DISC50', 43801, 'ShoeShopShip', 'Địa chỉ chi tiết, Xã Ia Dom, Huyện Ia H Drai, Kon Tum', 1, 6, 'COD', NULL, '2025-11-01 02:05:37', '2025-11-01 02:05:37', 0.00, NULL, NULL, NULL, NULL),
(54, 14, 6209201.00, 2640000.00, 'DISC30', 49201, 'ShoeShopShip', 'Địa chỉ chi tiết, Xã Tống Trân, Huyện Phù Cừ, Hưng Yên', 1, 5, 'COD', NULL, '2025-11-01 02:12:11', '2025-11-01 02:12:11', 0.00, NULL, NULL, NULL, NULL),
(55, 14, 1932000.00, 810000.00, 'DISC30', 42000, 'GHTK', 'Địa chỉ chi tiết, Thị trấn Sa Thầy, Huyện Sa Thầy, Kon Tum', 1, 5, 'COD', NULL, '2025-11-01 02:24:35', '2025-11-01 02:24:35', 0.00, NULL, NULL, NULL, NULL),
(61, 16, 1799201.00, 1750000.00, 'DISC50', 49201, 'ShoeShopShip', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, 6, 'COD', NULL, '2025-11-01 09:52:00', '2025-11-01 09:52:00', 0.00, NULL, NULL, NULL, NULL),
(62, 16, 3041001.00, 3000000.00, 'DISC50', 41001, 'GHN', 'Địa chỉ chi tiết, Xã Phiêng Khoài, Huyện Yên Châu, Sơn La', 1, 6, 'COD', NULL, '2025-11-01 09:54:33', '2025-11-01 09:54:33', 0.00, NULL, NULL, NULL, NULL),
(63, 16, 2741001.00, 2700000.00, 'DISC50', 41001, 'GHN', 'Địa chỉ chi tiết, Xã Phụng Công, Huyện Văn Giang, Hưng Yên', 1, 6, 'COD', NULL, '2025-11-01 09:58:04', '2025-11-01 09:58:04', 0.00, NULL, NULL, NULL, NULL),
(64, 14, 41001.00, 5400000.00, 'SHIP100', 41001, 'GHN', 'Địa chỉ chi tiết, Xã Yên Nghiệp, Huyện Lạc Sơn, Hòa Bình', 1, 8, 'COD', NULL, '2025-11-01 10:02:30', '2025-11-01 10:02:30', 0.00, NULL, NULL, NULL, NULL),
(65, 14, 6001640.04, 6000000.00, 'DISC50', 1640, 'GHN', 'Địa chỉ chi tiết, Xã Kim Lập, Huyện Kim Bôi, Hòa Bình', 1, 6, 'COD', NULL, '2025-11-01 11:22:39', '2025-11-01 11:22:39', 6560.16, 'SHIP80', NULL, NULL, NULL),
(66, 14, 3001360.00, 3000000.00, 'DISC50', 1360, 'GHN', 'Địa chỉ chi tiết, Xã Thụy Lôi, Huyện Tiên Lữ, Hưng Yên', 1, 6, 'COD', NULL, '2025-11-01 11:27:36', '2025-11-01 11:27:36', 5440.00, 'SHIP80', NULL, NULL, NULL),
(67, 20, 3006000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 18:33:28', '2025-11-05 18:33:28', 24000.00, 'SHIP80', NULL, NULL, NULL),
(68, 20, 9506000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 19:09:09', '2025-11-05 19:09:09', 24000.00, 'SHIP80', NULL, NULL, NULL),
(69, 20, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 19:24:52', '2025-11-05 19:24:52', 20000.00, 'SHIP80', NULL, NULL, NULL),
(70, 20, 3006000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:29:06', '2025-11-05 19:29:06', 24000.00, 'SHIP80', NULL, NULL, NULL),
(71, 20, 3006000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:29:22', '2025-11-05 19:29:22', 24000.00, 'SHIP80', NULL, NULL, NULL),
(72, 20, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 19:30:36', '2025-11-05 19:30:36', 20000.00, 'SHIP80', NULL, NULL, NULL),
(73, 20, 3506000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:30:55', '2025-11-05 19:30:55', 24000.00, 'SHIP80', NULL, NULL, NULL),
(74, 20, 3505000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 19:38:09', '2025-11-05 19:38:09', 20000.00, 'SHIP80', NULL, NULL, NULL),
(75, 20, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:38:25', '2025-11-05 19:38:25', 20000.00, 'SHIP80', NULL, NULL, NULL),
(76, 20, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, Xã Yên Bồng, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:39:43', '2025-11-05 19:39:43', 20000.00, 'SHIP80', NULL, NULL, NULL),
(77, 20, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:40:22', '2025-11-05 19:40:22', 20000.00, 'SHIP80', NULL, NULL, NULL),
(78, 20, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:45:45', '2025-11-05 19:45:45', 20000.00, 'SHIP80', NULL, NULL, NULL),
(79, 20, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:53:56', '2025-11-05 19:53:56', 20000.00, 'SHIP80', NULL, NULL, NULL),
(80, 20, 3006000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 19:57:56', '2025-11-05 19:57:56', 24000.00, 'SHIP80', NULL, NULL, NULL),
(81, 20, 3006000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-11-05 19:59:08', '2025-11-05 19:59:08', 24000.00, 'SHIP80', NULL, NULL, NULL),
(82, 21, 3506000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:09:54', '2025-11-05 20:09:54', 24000.00, 'SHIP80', NULL, NULL, NULL),
(83, 21, 3505000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 20:10:28', '2025-11-05 20:10:28', 20000.00, 'SHIP80', NULL, NULL, NULL),
(84, 21, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:11:56', '2025-11-05 20:11:56', 20000.00, 'SHIP80', NULL, NULL, NULL),
(85, 21, 3006000.00, 0.00, '', 6000, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-05 20:16:10', '2025-11-05 20:16:10', 24000.00, 'SHIP80', NULL, NULL, NULL),
(86, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'COD', NULL, '2025-11-05 20:16:33', '2025-11-05 20:16:33', 20000.00, 'SHIP80', NULL, NULL, NULL),
(87, 21, 1405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:16:59', '2025-11-05 20:16:59', 20000.00, 'SHIP80', NULL, NULL, NULL),
(88, 21, 1405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tả Phìn, Huyện Tủa Chùa, Điện Biên', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:21:43', '2025-11-05 20:21:43', 20000.00, 'SHIP80', NULL, NULL, NULL),
(89, 21, 3000000.00, 0.00, '', 0, 'GHTK', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:24:09', '2025-11-05 20:24:09', 0.00, 'SHIP80', NULL, NULL, NULL),
(90, 21, 5805000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:24:52', '2025-11-05 20:24:52', 20000.00, 'SHIP80', NULL, NULL, NULL),
(91, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:31:16', '2025-11-05 20:31:16', 20000.00, 'SHIP80', NULL, NULL, NULL),
(92, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:35:39', '2025-11-05 20:35:39', 20000.00, 'SHIP80', NULL, NULL, NULL),
(93, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:44:46', '2025-11-05 20:44:46', 20000.00, 'SHIP80', NULL, NULL, NULL),
(94, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:46:57', '2025-11-05 20:46:57', 20000.00, 'SHIP80', NULL, NULL, NULL),
(95, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Sin Suối Hồ, Huyện Phong Thổ, Lai Châu', 1, NULL, 'VNPAY', NULL, '2025-11-05 20:50:52', '2025-11-05 20:50:52', 20000.00, 'SHIP80', NULL, NULL, NULL),
(96, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:22:50', '2025-11-05 21:22:50', 20000.00, 'SHIP80', NULL, NULL, NULL),
(97, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thanh Bình, Huyện Mường Khương, Lào Cai', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:28:03', '2025-11-05 21:28:03', 20000.00, 'SHIP80', NULL, NULL, NULL),
(98, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Phú Hựu, Huyện Châu Thành, Đồng Tháp', 1, NULL, 'COD', NULL, '2025-11-05 21:30:24', '2025-11-05 21:30:24', 20000.00, 'SHIP80', NULL, NULL, NULL),
(99, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Phú Thành, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:31:02', '2025-11-05 21:31:02', 20000.00, 'SHIP80', NULL, NULL, NULL),
(100, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:33:26', '2025-11-05 21:33:26', 20000.00, 'SHIP80', NULL, NULL, NULL),
(101, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:35:01', '2025-11-05 21:35:01', 20000.00, 'SHIP80', NULL, NULL, NULL),
(102, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tả Giàng Phình, Thị xã Sa Pa, Lào Cai', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:38:59', '2025-11-05 21:38:59', 20000.00, 'SHIP80', NULL, NULL, NULL),
(103, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tống Phan, Huyện Phù Cừ, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:40:35', '2025-11-05 21:40:35', 20000.00, 'SHIP80', NULL, NULL, NULL),
(104, 21, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thào Chư Phìn, Huyện Si Ma Cai, Lào Cai', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:52:16', '2025-11-05 21:52:16', 20000.00, 'SHIP80', NULL, NULL, NULL),
(105, 21, 4506000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Yên Hòa, Huyện Đà Bắc, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 21:56:26', '2025-11-05 21:56:26', 24000.00, 'SHIP80', NULL, NULL, NULL),
(106, 21, 4505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Tân Thuận Đông, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:04:20', '2025-11-05 22:04:20', 20000.00, 'SHIP80', NULL, NULL, NULL),
(107, 21, 4505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thái Niên, Huyện Bảo Thắng, Lào Cai', 1, NULL, 'COD', NULL, '2025-11-05 22:07:30', '2025-11-05 22:07:30', 20000.00, 'SHIP80', NULL, NULL, NULL),
(108, 21, 1205000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-05 22:13:10', '2025-11-05 22:13:10', 20000.00, 'SHIP80', NULL, NULL, NULL),
(109, 21, 1305000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tiền Tiến, Huyện Phù Cừ, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:13:32', '2025-11-05 22:13:32', 20000.00, 'SHIP80', NULL, NULL, NULL),
(110, 21, 4105000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Pi Toong, Huyện Mường La, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:22:18', '2025-11-05 22:22:18', 20000.00, 'SHIP80', NULL, NULL, NULL),
(111, 21, 4105000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Yên Bồng, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'COD', NULL, '2025-11-05 22:30:07', '2025-11-05 22:30:07', 20000.00, 'SHIP80', NULL, NULL, NULL),
(112, 21, 1505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Tiến, Huyện Văn Giang, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:32:00', '2025-11-05 22:32:00', 20000.00, 'SHIP80', NULL, NULL, NULL),
(113, 21, 5605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thanh Bình, Huyện Mường Khương, Lào Cai', 1, NULL, 'COD', NULL, '2025-11-05 22:39:44', '2025-11-05 22:39:44', 20000.00, 'SHIP80', NULL, NULL, NULL),
(114, 21, 8405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Quang Minh, Huyện Vân Hồ, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:40:17', '2025-11-05 22:40:17', 20000.00, 'SHIP80', NULL, NULL, NULL),
(115, 21, 8406000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Nhân Mỹ, Huyện Tân Lạc, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-05 22:49:57', '2025-11-05 22:49:57', 24000.00, 'SHIP80', NULL, NULL, NULL),
(116, 21, 8405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Sín Chéng, Huyện Si Ma Cai, Lào Cai', 1, NULL, 'COD', NULL, '2025-11-06 10:18:46', '2025-11-06 10:18:46', 20000.00, 'SHIP80', NULL, NULL, NULL),
(117, 21, 2405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tìa Dình, Huyện Điện Biên Đông, Điện Biên', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:19:16', '2025-11-06 10:19:16', 20000.00, 'SHIP80', NULL, NULL, NULL),
(118, 21, 2405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:27:06', '2025-11-06 10:27:06', 20000.00, 'SHIP80', NULL, NULL, NULL),
(119, 21, 1205000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thái Niên, Huyện Bảo Thắng, Lào Cai', 1, NULL, 'COD', NULL, '2025-11-06 10:27:42', '2025-11-06 10:27:42', 20000.00, 'SHIP80', NULL, NULL, NULL),
(120, 21, 3605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Trung Hòa, Huyện Tân Lạc, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:28:12', '2025-11-06 10:28:12', 20000.00, 'SHIP80', NULL, NULL, NULL),
(121, 21, 3605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-06 10:31:08', '2025-11-06 10:31:08', 20000.00, 'SHIP80', NULL, NULL, NULL),
(122, 21, 1405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:31:35', '2025-11-06 10:31:35', 20000.00, 'SHIP80', NULL, NULL, NULL),
(123, 21, 1405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Xuất Hóa, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:32:41', '2025-11-06 10:32:41', 20000.00, 'SHIP80', NULL, NULL, NULL),
(124, 21, 1405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tả Thàng, Huyện Mường Khương, Lào Cai', 1, NULL, 'COD', NULL, '2025-11-06 10:36:59', '2025-11-06 10:36:59', 20000.00, 'SHIP80', NULL, NULL, NULL),
(125, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Đồng Tân, Huyện Mai Châu, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:43:42', '2025-11-06 10:43:42', 20000.00, 'SHIP80', NULL, NULL, NULL),
(126, 21, 1605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tòng Đậu, Huyện Mai Châu, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:51:24', '2025-11-06 10:51:24', 20000.00, 'SHIP80', NULL, NULL, NULL),
(127, 21, 4405000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Yên Phú, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'COD', NULL, '2025-11-06 10:52:38', '2025-11-06 10:52:38', 20000.00, 'SHIP80', NULL, NULL, NULL),
(128, 21, 3505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Phường Na Lay, Thị xã Mường Lay, Điện Biên', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:53:08', '2025-11-06 10:53:08', 20000.00, 'SHIP80', NULL, NULL, NULL),
(129, 21, 3505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Trưng Trắc, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-06 10:54:18', '2025-11-06 10:54:18', 20000.00, 'SHIP80', NULL, NULL, NULL),
(130, 21, 5605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Yên Nghiệp, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 10:59:53', '2025-11-06 10:59:53', 20000.00, 'SHIP80', NULL, NULL, NULL),
(131, 21, 5605000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tiền Tiến, Huyện Phù Cừ, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-06 11:01:52', '2025-11-06 11:01:52', 20000.00, 'SHIP80', NULL, NULL, NULL),
(132, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Vĩnh Khúc, Huyện Văn Giang, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:12:59', '2025-11-06 11:12:59', 20000.00, 'SHIP80', NULL, NULL, NULL),
(133, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:19:15', '2025-11-06 11:19:15', 20000.00, 'SHIP80', NULL, NULL, NULL),
(134, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Sặp Vạt, Huyện Yên Châu, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:24:39', '2025-11-06 11:24:39', 20000.00, 'SHIP80', NULL, NULL, NULL),
(135, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:32:33', '2025-11-06 11:32:33', 20000.00, 'SHIP80', NULL, NULL, NULL),
(136, 21, 2806000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Tân Thành, Huyện Mai Châu, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:41:50', '2025-11-06 11:41:50', 24000.00, 'SHIP80', NULL, NULL, NULL),
(137, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tống Phan, Huyện Phù Cừ, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:48:06', '2025-11-06 11:48:06', 20000.00, 'SHIP80', NULL, NULL, NULL),
(138, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:54:56', '2025-11-06 11:54:56', 20000.00, 'SHIP80', NULL, NULL, NULL),
(139, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tả Thàng, Huyện Mường Khương, Lào Cai', 1, NULL, 'VNPAY', NULL, '2025-11-06 11:56:14', '2025-11-06 11:56:14', 20000.00, 'SHIP80', NULL, NULL, NULL),
(140, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:01:00', '2025-11-06 12:01:00', 20000.00, 'SHIP80', NULL, NULL, NULL),
(141, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:01:22', '2025-11-06 12:01:22', 20000.00, 'SHIP80', NULL, NULL, NULL),
(142, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-06 12:02:24', '2025-11-06 12:02:24', 20000.00, 'SHIP80', NULL, NULL, NULL),
(143, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Yên Nghiệp, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:05:49', '2025-11-06 12:05:49', 20000.00, 'SHIP80', NULL, NULL, NULL),
(144, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tam Đa, Huyện Phù Cừ, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:08:47', '2025-11-06 12:08:47', 20000.00, 'SHIP80', NULL, NULL, NULL),
(145, 21, 2806000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Suối Bàng, Huyện Vân Hồ, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:12:44', '2025-11-06 12:12:44', 24000.00, 'SHIP80', NULL, NULL, NULL),
(146, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thẩm Dương, Huyện Văn Bàn, Lào Cai', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:18:46', '2025-11-06 12:18:46', 20000.00, 'SHIP80', NULL, NULL, NULL),
(147, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:31:33', '2025-11-06 12:31:33', 20000.00, 'SHIP80', NULL, NULL, NULL),
(148, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Thắng Lợi, Huyện Văn Giang, Hưng Yên', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:33:58', '2025-11-06 12:33:58', 20000.00, 'SHIP80', NULL, NULL, NULL),
(149, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Phiêng Khoài, Huyện Yên Châu, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:35:11', '2025-11-06 12:35:11', 20000.00, 'SHIP80', NULL, NULL, NULL),
(150, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Xuất Hóa, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:36:13', '2025-11-06 12:36:13', 20000.00, 'SHIP80', NULL, NULL, NULL),
(151, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Yên Nghiệp, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:37:11', '2025-11-06 12:37:11', 20000.00, 'SHIP80', NULL, NULL, NULL),
(152, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Xuân, Huyện Vân Hồ, Sơn La', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:39:22', '2025-11-06 12:39:22', 20000.00, 'SHIP80', NULL, NULL, NULL),
(153, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:40:46', '2025-11-06 12:40:46', 20000.00, 'SHIP80', NULL, NULL, NULL),
(154, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-06 12:43:48', '2025-11-06 12:43:48', 20000.00, 'SHIP80', NULL, NULL, NULL),
(155, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tú Nang, Huyện Yên Châu, Sơn La', 2, NULL, 'VNPAY', NULL, '2025-11-06 12:47:54', '2025-11-06 12:48:32', 20000.00, 'SHIP80', NULL, '2025-11-06 12:48:32', NULL),
(156, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 2, NULL, 'VNPAY', NULL, '2025-11-06 12:52:42', '2025-11-06 12:53:18', 20000.00, 'SHIP80', NULL, '2025-11-06 12:53:18', NULL),
(157, 21, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Vũ Lâm, Huyện Lạc Sơn, Hòa Bình', 2, NULL, 'VNPAY', NULL, '2025-11-06 12:54:04', '2025-11-06 12:54:52', 20000.00, 'SHIP80', NULL, '2025-11-06 12:54:52', NULL),
(158, 21, 3505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'COD', NULL, '2025-11-06 12:58:41', '2025-11-06 12:58:41', 20000.00, 'SHIP80', NULL, NULL, NULL),
(159, 21, 3006000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 2, NULL, 'VNPAY', NULL, '2025-11-06 12:59:08', '2025-11-06 12:59:38', 24000.00, 'SHIP80', NULL, '2025-11-06 12:59:38', NULL),
(160, 21, 3906000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Tây Phong, Huyện Cao Phong, Hòa Bình', 2, NULL, 'VNPAY', NULL, '2025-11-06 13:45:22', '2025-11-06 13:46:18', 0.00, '', NULL, '2025-11-06 13:46:18', NULL),
(161, 16, 6006000.00, 0.00, '', 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Tô Múa, Huyện Vân Hồ, Sơn La', 1, NULL, 'COD', NULL, '2025-11-06 22:48:46', '2025-11-06 22:48:46', 24000.00, 'SHIP80', NULL, NULL, NULL),
(162, 16, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Xuất Hóa, Huyện Lạc Sơn, Hòa Bình', 2, NULL, 'VNPAY', NULL, '2025-11-07 00:13:38', '2025-11-07 00:14:36', 20000.00, 'SHIP80', NULL, '2025-11-07 00:14:36', NULL),
(163, 16, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-07 00:15:16', '2025-11-07 00:15:16', 20000.00, 'SHIP80', NULL, NULL, NULL),
(164, 16, 2805000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Minh Hải, Huyện Văn Lâm, Hưng Yên', 2, NULL, 'VNPAY', NULL, '2025-11-07 00:15:36', '2025-11-07 00:16:04', 20000.00, 'SHIP80', NULL, '2025-11-07 00:16:04', NULL),
(165, 16, 3006000.00, 0.00, NULL, 6000, 'GHTK', 'Địa chỉ chi tiết, Xã Thanh Nông, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'PAYPAL', '341343423', '2025-11-07 01:24:19', '2025-11-07 01:27:12', 0.00, NULL, NULL, '2025-11-07 01:24:19', '0HK410121G377874J'),
(166, 16, 1505000.00, 0.00, NULL, 5000, 'GHN', 'Địa chỉ chi tiết, Xã Vĩnh Khúc, Huyện Văn Giang, Hưng Yên', 1, NULL, 'PAYPAL', '4324', '2025-11-07 01:33:17', '2025-11-07 01:33:17', 0.00, NULL, NULL, '2025-11-07 01:33:17', '33N124077F570011R'),
(167, 16, 3505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'VNPAY', NULL, '2025-11-07 01:37:07', '2025-11-07 01:38:03', 20000.00, 'SHIP80', NULL, '2025-11-07 01:38:03', NULL),
(168, 16, 1505000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Tân Tiến, Huyện Văn Giang, Hưng Yên', 1, NULL, 'COD', NULL, '2025-11-07 01:38:32', '2025-11-07 01:38:32', 20000.00, 'SHIP80', NULL, NULL, NULL),
(169, 16, 6005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-07 19:02:27', '2025-11-07 19:02:27', 20000.00, 'SHIP80', NULL, NULL, NULL),
(170, 16, 2405000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-07 19:02:49', '2025-11-07 19:03:57', 20000.00, 'SHIP80', NULL, '2025-11-07 19:03:57', NULL),
(171, 16, 6005000.00, 0.00, NULL, 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'PAYPAL', '0768957454', '2025-11-07 19:05:29', '2025-11-07 19:05:29', 0.00, NULL, NULL, '2025-11-07 19:05:29', '2EM37191HT739980M'),
(172, 16, 12005000.00, 0.00, NULL, 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 5, NULL, 'PAYPAL', '0768957454', '2025-11-07 19:07:47', '2025-11-07 23:39:08', 0.00, NULL, NULL, '2025-11-07 19:07:47', '9KT6757052407745D'),
(173, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-07 20:11:38', '2025-11-07 20:11:38', 20000.00, 'SHIP80', NULL, NULL, NULL),
(174, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', NULL, '2025-11-07 21:47:54', '2025-11-07 21:47:54', 20000.00, 'SHIP80', NULL, NULL, NULL),
(175, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', NULL, '2025-11-07 21:48:10', '2025-11-07 21:48:42', 20000.00, 'SHIP80', NULL, '2025-11-07 21:48:42', NULL),
(176, 16, 3505000.00, 0.00, NULL, 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'PAYPAL', '0768957454', '2025-11-07 21:50:33', '2025-11-07 21:50:33', 0.00, NULL, NULL, '2025-11-07 21:50:33', '41Y50038FL328632L'),
(177, 16, 2805000.00, 0.00, NULL, 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'PAYPAL', '0768957454', '2025-11-07 21:52:44', '2025-11-07 21:52:44', 0.00, NULL, NULL, '2025-11-07 21:52:44', '4EH16487RH7468132'),
(178, 16, 6005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'COD', '0768957454', '2025-11-07 23:49:39', '2025-11-07 23:49:39', 20000.00, 'SHIP80', NULL, NULL, NULL),
(179, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', '0768957454', '2025-11-07 23:51:26', '2025-11-07 23:51:55', 20000.00, 'SHIP80', NULL, '2025-11-07 23:51:55', NULL),
(180, 16, 3005000.00, 0.00, NULL, 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'PAYPAL', '0768957454', '2025-11-07 23:52:41', '2025-11-07 23:52:41', 0.00, NULL, NULL, '2025-11-07 23:52:41', '86M35823S1223091M'),
(181, 16, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Thị Trấn Ba Hàng Đồi, Huyện Lạc Thủy, Hòa Bình', 1, NULL, 'COD', '1425345574', '2025-11-08 00:04:33', '2025-11-08 00:04:33', 20000.00, 'SHIP80', NULL, NULL, NULL),
(182, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', '0768957454', '2025-11-08 00:06:01', '2025-11-08 00:06:01', 20000.00, 'SHIP80', NULL, NULL, NULL),
(183, 16, 3000000.00, 0.00, '', 0, 'GHN', '391/36/6 HUYNH TAN PHAT, TAN THUAN DONG Q7 HCM', 1, NULL, 'VNPAY', '0768957454', '2025-11-08 00:06:06', '2025-11-08 00:06:06', 0.00, 'SHIP80', NULL, NULL, NULL),
(184, 16, 3005000.00, 0.00, '', 5000, 'GHN', '391/36/6 HUYNH TAN PHAT, Xã Yên Nghiệp, Huyện Lạc Sơn, Hòa Bình', 1, NULL, 'VNPAY', '0768957454', '2025-11-08 00:06:29', '2025-11-08 00:06:59', 20000.00, 'SHIP80', NULL, '2025-11-08 00:06:59', NULL),
(185, 16, 3005000.00, 0.00, '', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Chỉ Đạo, Huyện Văn Lâm, Hưng Yên', 1, NULL, 'COD', '12345678900000', '2025-11-08 00:08:20', '2025-11-08 00:08:20', 20000.00, 'SHIP80', NULL, NULL, NULL),
(186, 22, 985000.00, 420000.00, 'DISC30', 5000, 'GHN', 'Địa chỉ chi tiết, Xã Suối Bàng, Huyện Vân Hồ, Sơn La', 1, 5, 'COD', '0961804873', '2025-11-08 15:03:32', '2025-11-08 15:03:32', 20000.00, 'SHIP80', NULL, NULL, NULL),
(187, 14, 3000000.00, 0.00, '', 0, 'GHN', '124, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0961759523', '2025-11-10 10:04:21', '2025-11-10 10:04:21', 0.00, '', NULL, NULL, NULL),
(188, 14, 6025000.00, 0.00, '', 25000, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'COD', '0888609783', '2025-11-10 13:09:52', '2025-11-10 13:09:52', 0.00, '', NULL, NULL, NULL),
(189, 22, 3000000.00, 0.00, '', 0, 'GHN', '23, Thị trấn N.T Phong Hải, Huyện Bảo Thắng, Lào Cai', 1, NULL, 'COD', '123456', '2025-11-12 13:23:14', '2025-11-12 13:23:14', 0.00, 'SHIP80', NULL, NULL, NULL),
(190, 22, 4500000.00, 4500000.00, 'DISC50', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, 6, 'COD', '123456', '2025-11-12 14:19:13', '2025-11-12 14:19:13', 0.00, 'SHIP80', NULL, NULL, NULL),
(191, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 15:48:33', '2025-11-12 15:48:33', 0.00, '', NULL, NULL, NULL),
(192, 22, 6000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'VNPAY', '123456', '2025-11-12 16:02:31', '2025-11-12 16:02:31', 0.00, 'SHIP80', NULL, NULL, NULL),
(193, 22, 6000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 16:02:39', '2025-11-12 16:02:39', 0.00, 'SHIP80', NULL, NULL, NULL),
(194, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 17:13:13', '2025-11-12 17:13:13', 0.00, 'SHIP80', NULL, NULL, NULL),
(195, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 17:13:59', '2025-11-12 17:13:59', 0.00, '', NULL, NULL, NULL),
(196, 22, 1600000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 17:15:30', '2025-11-12 17:15:30', 0.00, '', NULL, NULL, NULL),
(197, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-12 17:17:27', '2025-11-12 17:17:27', 0.00, '', NULL, NULL, NULL),
(198, 22, 7600000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 09:42:59', '2025-11-13 09:42:59', 0.00, 'SHIP80', NULL, NULL, NULL),
(199, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:21:10', '2025-11-13 11:21:10', 0.00, 'SHIP80', NULL, NULL, NULL),
(200, 22, 3000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:27:35', '2025-11-13 11:27:35', 0.00, '', NULL, NULL, NULL),
(201, 22, 7000000.00, 0.00, '', 0, 'GHN', '23, Thị trấn N.T Phong Hải, Huyện Bảo Thắng, Lào Cai', 1, NULL, 'COD', '123456', '2025-11-13 11:28:43', '2025-11-13 11:28:43', 0.00, '', NULL, NULL, NULL),
(202, 22, 7000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:29:22', '2025-11-13 11:29:22', 0.00, '', NULL, NULL, NULL),
(203, 22, 6000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:30:31', '2025-11-13 11:30:31', 0.00, '', NULL, NULL, NULL),
(204, 22, 30000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:31:08', '2025-11-13 11:31:08', 0.00, '', NULL, NULL, NULL),
(205, 22, 24000000.00, 0.00, '', 0, 'GHN', '123, Phường 2, Quận Tân Bình, Hồ Chí Minh', 1, NULL, 'COD', '123456', '2025-11-13 11:32:09', '2025-11-13 11:32:09', 0.00, '', NULL, NULL, NULL),
(206, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:49:49', '2025-11-14 00:49:49', 80.00, 'SHIP80', NULL, NULL, NULL),
(207, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:49:51', '2025-11-14 00:49:51', 80.00, 'SHIP80', NULL, NULL, NULL),
(208, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:49:52', '2025-11-14 00:49:52', 80.00, 'SHIP80', NULL, NULL, NULL),
(209, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:49:52', '2025-11-14 00:49:52', 80.00, 'SHIP80', NULL, NULL, NULL),
(210, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:49:52', '2025-11-14 00:49:52', 80.00, 'SHIP80', NULL, NULL, NULL),
(211, 14, 3006920.00, 0.00, '', 6920, 'GHN', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'COD', '0888609783', '2025-11-14 00:49:54', '2025-11-14 00:49:54', 80.00, 'SHIP80', NULL, NULL, NULL),
(212, 14, 3508920.00, 0.00, '', 8920, 'ShoeShopShip', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:50:55', '2025-11-14 00:50:55', 80.00, 'SHIP80', NULL, NULL, NULL),
(213, 14, 3508920.00, 0.00, '', 8920, 'ShoeShopShip', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:50:55', '2025-11-14 00:50:55', 80.00, 'SHIP80', NULL, NULL, NULL),
(214, 14, 3508920.00, 0.00, '', 8920, 'ShoeShopShip', '123, Phường Tân Hưng, Quận 7, Hồ Chí Minh', 1, NULL, 'VNPAY', '0888609783', '2025-11-14 00:50:56', '2025-11-14 00:50:56', 80.00, 'SHIP80', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `size`, `quantity`, `price`) VALUES
(1, 1, 1, '40', 1, 2500000.00),
(2, 2, 2, NULL, 4, 2300000.00),
(3, 2, 1, '40', 2, 2500000.00),
(4, 2, 3, NULL, 1, 1200000.00),
(5, 3, 2, '38', 2, 2300000.00),
(6, 4, 2, '38', 1, 2300000.00),
(7, 5, 2, NULL, 1, 2300000.00),
(8, 6, 21, '39', 1, 2800000.00),
(12, 10, 3, '30', 3, 1200000.00),
(13, 11, 4, '42', 1, 2800000.00),
(14, 12, 12, '30', 2, 1300000.00),
(15, 13, 22, '31', 1, 1600000.00),
(16, 14, 3, '30', 1, 1200000.00),
(17, 20, 22, NULL, 1, 1600000.00),
(18, 20, 5, '38', 1, 3000000.00),
(19, 20, 11, NULL, 1, 2700000.00),
(20, 21, 5, NULL, 2, 3000000.00),
(21, 22, 5, NULL, 5, 3000000.00),
(22, 22, 5, '38', 1, 3000000.00),
(23, 23, 11, NULL, 1, 2700000.00),
(24, 24, 11, '38', 1, 2700000.00),
(25, 25, 5, '38', 1, 3000000.00),
(26, 26, 17, '42', 1, 3400000.00),
(27, 27, 11, '38', 1, 2700000.00),
(28, 28, 11, '38', 1, 2700000.00),
(29, 29, 5, NULL, 1, 3000000.00),
(30, 30, 11, '38', 1, 2700000.00),
(31, 37, 5, '38', 1, 3000000.00),
(32, 38, 11, NULL, 2, 2700000.00),
(33, 39, 4, NULL, 1, 2800000.00),
(34, 40, 17, NULL, 1, 3400000.00),
(35, 41, 11, NULL, 1, 2700000.00),
(36, 42, 11, NULL, 1, 2700000.00),
(37, 43, 11, NULL, 1, 2700000.00),
(38, 44, 11, '38', 1, 2700000.00),
(39, 45, 17, NULL, 1, 3400000.00),
(40, 46, 11, NULL, 1, 2700000.00),
(41, 46, 11, '38', 1, 2700000.00),
(42, 47, 5, '38', 1, 3000000.00),
(43, 48, 17, NULL, 3, 3400000.00),
(44, 48, 17, '42', 1, 3400000.00),
(45, 49, 4, '40', 6, 2800000.00),
(46, 50, 5, '39', 4, 3000000.00),
(47, 51, 11, NULL, 8, 2700000.00),
(48, 52, 11, NULL, 1, 2700000.00),
(49, 53, 11, NULL, 1, 2700000.00),
(50, 54, 17, NULL, 1, 3400000.00),
(51, 54, 11, NULL, 1, 2700000.00),
(52, 54, 11, '38', 1, 2700000.00),
(53, 55, 11, NULL, 1, 2700000.00),
(54, 61, 7, NULL, 1, 3500000.00),
(55, 62, 5, NULL, 1, 3000000.00),
(56, 62, 5, '38', 1, 3000000.00),
(57, 63, 11, NULL, 1, 2700000.00),
(58, 63, 11, '38', 1, 2700000.00),
(59, 64, 11, '38', 2, 2700000.00),
(60, 65, 5, '38', 3, 3000000.00),
(61, 65, 5, '39', 1, 3000000.00),
(62, 66, 5, NULL, 1, 3000000.00),
(63, 66, 5, '38', 1, 3000000.00),
(64, 67, 5, NULL, 1, 3000000.00),
(65, 68, 7, NULL, 1, 3500000.00),
(66, 68, 5, NULL, 2, 3000000.00),
(67, 69, 5, NULL, 1, 3000000.00),
(68, 70, 5, NULL, 1, 3000000.00),
(69, 71, 5, NULL, 1, 3000000.00),
(70, 72, 5, NULL, 1, 3000000.00),
(71, 73, 7, NULL, 1, 3500000.00),
(72, 74, 7, NULL, 1, 3500000.00),
(73, 75, 5, NULL, 1, 3000000.00),
(74, 76, 5, NULL, 1, 3000000.00),
(75, 77, 5, NULL, 1, 3000000.00),
(76, 78, 5, NULL, 1, 3000000.00),
(77, 79, 5, NULL, 1, 3000000.00),
(78, 80, 5, NULL, 1, 3000000.00),
(79, 81, 5, NULL, 1, 3000000.00),
(80, 82, 7, NULL, 1, 3500000.00),
(81, 83, 7, NULL, 1, 3500000.00),
(82, 84, 5, NULL, 1, 3000000.00),
(83, 85, 5, NULL, 1, 3000000.00),
(84, 86, 4, NULL, 1, 2800000.00),
(85, 87, 19, NULL, 1, 1400000.00),
(86, 88, 19, NULL, 1, 1400000.00),
(87, 89, 5, NULL, 1, 3000000.00),
(88, 90, 5, NULL, 1, 3000000.00),
(89, 90, 4, NULL, 1, 2800000.00),
(90, 91, 22, NULL, 1, 1600000.00),
(91, 92, 22, NULL, 1, 1600000.00),
(92, 93, 22, NULL, 1, 1600000.00),
(93, 94, 22, NULL, 1, 1600000.00),
(94, 95, 22, NULL, 1, 1600000.00),
(95, 96, 22, NULL, 1, 1600000.00),
(96, 97, 22, NULL, 1, 1600000.00),
(97, 98, 22, NULL, 1, 1600000.00),
(98, 99, 5, NULL, 1, 3000000.00),
(99, 100, 5, NULL, 1, 3000000.00),
(100, 101, 5, NULL, 1, 3000000.00),
(101, 102, 5, NULL, 1, 3000000.00),
(102, 103, 5, NULL, 1, 3000000.00),
(103, 104, 5, NULL, 1, 3000000.00),
(104, 105, 5, NULL, 1, 3000000.00),
(105, 105, 6, NULL, 1, 1500000.00),
(106, 106, 5, NULL, 1, 3000000.00),
(107, 106, 6, NULL, 1, 1500000.00),
(108, 107, 5, NULL, 1, 3000000.00),
(109, 107, 6, NULL, 1, 1500000.00),
(110, 108, 3, NULL, 1, 1200000.00),
(111, 109, 12, NULL, 1, 1300000.00),
(112, 110, 12, NULL, 1, 1300000.00),
(113, 110, 4, NULL, 1, 2800000.00),
(114, 111, 12, NULL, 1, 1300000.00),
(115, 111, 4, NULL, 1, 2800000.00),
(116, 112, 6, NULL, 1, 1500000.00),
(117, 113, 4, NULL, 2, 2800000.00),
(118, 114, 4, NULL, 3, 2800000.00),
(119, 115, 4, NULL, 3, 2800000.00),
(120, 116, 4, NULL, 3, 2800000.00),
(121, 117, 3, NULL, 2, 1200000.00),
(122, 118, 3, NULL, 2, 1200000.00),
(123, 119, 3, NULL, 1, 1200000.00),
(124, 120, 3, NULL, 3, 1200000.00),
(125, 121, 3, NULL, 3, 1200000.00),
(126, 122, 19, NULL, 1, 1400000.00),
(127, 123, 19, NULL, 1, 1400000.00),
(128, 124, 19, NULL, 1, 1400000.00),
(129, 125, 22, NULL, 1, 1600000.00),
(130, 126, 22, NULL, 1, 1600000.00),
(131, 127, 22, NULL, 1, 1600000.00),
(132, 127, 4, NULL, 1, 2800000.00),
(133, 128, 7, NULL, 1, 3500000.00),
(134, 129, 7, NULL, 1, 3500000.00),
(135, 130, 4, NULL, 2, 2800000.00),
(136, 131, 4, NULL, 2, 2800000.00),
(137, 132, 4, NULL, 1, 2800000.00),
(138, 133, 4, NULL, 1, 2800000.00),
(139, 134, 4, NULL, 1, 2800000.00),
(140, 135, 4, NULL, 1, 2800000.00),
(141, 136, 4, NULL, 1, 2800000.00),
(142, 137, 4, NULL, 1, 2800000.00),
(143, 138, 4, NULL, 1, 2800000.00),
(144, 139, 4, NULL, 1, 2800000.00),
(145, 140, 4, NULL, 1, 2800000.00),
(146, 141, 4, NULL, 1, 2800000.00),
(147, 142, 4, NULL, 1, 2800000.00),
(148, 143, 4, NULL, 1, 2800000.00),
(149, 144, 4, NULL, 1, 2800000.00),
(150, 145, 4, NULL, 1, 2800000.00),
(151, 146, 4, NULL, 1, 2800000.00),
(152, 147, 4, NULL, 1, 2800000.00),
(153, 148, 4, NULL, 1, 2800000.00),
(154, 149, 4, NULL, 1, 2800000.00),
(155, 150, 4, NULL, 1, 2800000.00),
(156, 151, 4, NULL, 1, 2800000.00),
(157, 152, 4, NULL, 1, 2800000.00),
(158, 153, 4, NULL, 1, 2800000.00),
(159, 154, 4, NULL, 1, 2800000.00),
(160, 155, 4, NULL, 1, 2800000.00),
(161, 156, 4, NULL, 1, 2800000.00),
(162, 157, 4, NULL, 1, 2800000.00),
(163, 158, 7, NULL, 1, 3500000.00),
(164, 159, 5, NULL, 1, 3000000.00),
(165, 160, 12, NULL, 3, 1300000.00),
(166, 161, 5, NULL, 2, 3000000.00),
(167, 162, 5, NULL, 1, 3000000.00),
(168, 163, 4, NULL, 1, 2800000.00),
(169, 164, 4, NULL, 1, 2800000.00),
(170, 165, 5, NULL, 1, 3000000.00),
(171, 166, 6, NULL, 1, 1500000.00),
(172, 167, 7, NULL, 1, 3500000.00),
(173, 168, 6, NULL, 1, 1500000.00),
(174, 169, 5, NULL, 2, 3000000.00),
(175, 170, 3, NULL, 2, 1200000.00),
(176, 171, 6, NULL, 4, 1500000.00),
(177, 172, 6, NULL, 8, 1500000.00),
(178, 173, 5, NULL, 1, 3000000.00),
(179, 174, 5, NULL, 1, 3000000.00),
(180, 175, 5, NULL, 1, 3000000.00),
(181, 176, 7, NULL, 1, 3500000.00),
(182, 177, 4, NULL, 1, 2800000.00),
(183, 178, 5, NULL, 2, 3000000.00),
(184, 179, 5, NULL, 1, 3000000.00),
(185, 180, 5, NULL, 1, 3000000.00),
(186, 181, 5, NULL, 1, 3000000.00),
(187, 182, 5, NULL, 1, 3000000.00),
(188, 183, 5, NULL, 1, 3000000.00),
(189, 184, 5, NULL, 1, 3000000.00),
(190, 185, 5, NULL, 1, 3000000.00),
(191, 186, 19, NULL, 1, 1400000.00),
(192, 187, 5, '38', 1, 3000000.00),
(193, 188, 5, '38', 2, 3000000.00),
(194, 189, 5, '38', 1, 3000000.00),
(195, 190, 5, '39', 3, 3000000.00),
(196, 191, 5, '38', 1, 3000000.00),
(197, 192, 5, '38', 2, 3000000.00),
(198, 193, 5, '38', 2, 3000000.00),
(199, 194, 5, '38', 1, 3000000.00),
(200, 195, 5, '38', 1, 3000000.00),
(201, 196, 22, '31', 1, 1600000.00),
(202, 197, 5, '38', 1, 3000000.00),
(203, 198, 5, '38', 1, 3000000.00),
(204, 198, 22, '31', 1, 1600000.00),
(205, 198, 5, '39', 1, 3000000.00),
(206, 199, 5, '38', 1, 3000000.00),
(207, 200, 5, '39', 1, 3000000.00),
(208, 201, 7, '41', 2, 3500000.00),
(209, 202, 7, '41', 2, 3500000.00),
(210, 203, 5, '38', 2, 3000000.00),
(211, 204, 5, '38', 10, 3000000.00),
(212, 205, 5, '39', 7, 3000000.00),
(213, 205, 5, '38', 1, 3000000.00),
(214, 206, 5, '38', 1, 3000000.00),
(215, 207, 5, '38', 1, 3000000.00),
(216, 208, 5, '38', 1, 3000000.00),
(217, 209, 5, '38', 1, 3000000.00),
(218, 210, 5, '38', 1, 3000000.00),
(219, 211, 5, '38', 1, 3000000.00),
(220, 212, 7, '41', 1, 3500000.00),
(221, 213, 7, '41', 1, 3500000.00),
(222, 214, 7, '41', 1, 3500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`id`, `name`) VALUES
(1, 'Chờ xử lý'),
(2, 'Đang giao'),
(3, 'Hoàn tất'),
(4, 'Hủy'),
(5, 'Hoàn hàng');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `code`, `description`, `price`, `category_id`, `brand_id`, `created_at`, `updated_at`) VALUES
(1, 'Nike Air Max', 'NIKE001', 'Giày thể thao nam', 2500000.00, 1, 1, '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(2, 'Adidas Runner', 'ADIDAS001', 'Giày chạy bộ nữ', 2300000.00, 2, 2, '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(3, 'Puma Kids', 'PUMA001', 'Giày trẻ em', 1200000.00, 3, 3, '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(4, 'Nike Zoom Pegasus', 'NIKE002', 'Giày chạy bộ nam hiệu năng cao', 2800000.00, 1, 1, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(5, 'Adidas Ultraboost', 'ADIDAS002', 'Giày thể thao nữ êm ái', 3000000.00, 2, 2, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(6, 'Puma Velocity', 'PUMA002', 'Giày trẻ em năng động', 1500000.00, 3, 3, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(7, 'Nike Air Jordan', 'NIKE003', 'Giày sneaker nam thời thượng', 3500000.00, 4, 1, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(8, 'Adidas Stan Smith', 'ADIDAS003', 'Giày sneaker nữ cổ điển', 2200000.00, 4, 2, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(9, 'Puma Suede Classic', 'PUMA003', 'Giày sneaker unisex phong cách', 2000000.00, 4, 3, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(10, 'Nike Combat Boots', 'NIKE004', 'Giày boots nam bền bỉ', 3200000.00, 5, 1, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(11, 'Adidas Winter Boots', 'ADIDAS004', 'Giày boots nữ giữ ấm', 2700000.00, 5, 2, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(12, 'Puma Kids Runner', 'PUMA004', 'Giày thể thao trẻ em nhẹ nhàng', 1300000.00, 3, 3, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(13, 'Nike Free Run', 'NIKE005', 'Giày chạy bộ nam siêu nhẹ', 2600000.00, 1, 1, '2025-10-12 12:15:18', '2025-10-12 12:15:18'),
(14, 'Nike React Infinity', 'NIKE006', 'Giày chạy bộ nam hỗ trợ tối đa', 2900000.00, 1, 1, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(15, 'Adidas NMD', 'ADIDAS005', 'Giày sneaker nữ phong cách hiện đại', 2600000.00, 4, 2, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(16, 'Puma RS-X', 'PUMA005', 'Giày sneaker unisex thời trang', 2400000.00, 4, 3, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(17, 'Nike Chelsea Boots', 'NIKE007', 'Giày boots nam cao cấp', 3400000.00, 5, 1, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(18, 'Adidas Gazelle', 'ADIDAS006', 'Giày sneaker nữ cổ điển', 2100000.00, 4, 2, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(19, 'Puma Future Rider', 'PUMA006', 'Giày trẻ em phong cách retro', 1400000.00, 3, 3, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(20, 'Nike Air Force', 'NIKE008', 'Giày sneaker nam huyền thoại', 2700000.00, 4, 1, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(21, 'Adidas Terrex', 'ADIDAS007', 'Giày boots nữ đi bộ đường dài', 2800000.00, 5, 2, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(22, 'Puma Ignite', 'PUMA007', 'Giày thể thao trẻ em năng động', 1600000.00, 3, 3, '2025-10-12 12:19:31', '2025-10-12 12:19:31'),
(23, 'Nike Epic React', 'NIKE009', 'Giày chạy bộ nam siêu nhẹ', 3000000.00, 1, 1, '2025-10-12 12:19:31', '2025-10-12 12:19:31');

-- --------------------------------------------------------

--
-- Table structure for table `product_batch`
--

CREATE TABLE `product_batch` (
  `id` int(11) NOT NULL,
  `productsize_id` int(11) NOT NULL,
  `batch_code` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `quantity_in` int(11) NOT NULL,
  `quantity_remaining` int(11) NOT NULL,
  `import_date` datetime DEFAULT current_timestamp(),
  `expiry_date` datetime DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_batch`
--

INSERT INTO `product_batch` (`id`, `productsize_id`, `batch_code`, `quantity_in`, `quantity_remaining`, `import_date`, `expiry_date`, `note`) VALUES
(2, 37, 'L20251020-22', 12, 10, '2025-10-21 01:30:27', NULL, NULL),
(3, 5, 'L20251020-3', 20, 13, '2025-10-21 01:30:27', NULL, NULL),
(4, 37, 'L20251020-22-37', 2, 2, '2025-10-21 01:48:47', NULL, NULL),
(5, 64, 'L20251020-12-64', 2, 2, '2025-10-21 01:51:52', NULL, NULL),
(6, 5, 'L20251020-3-5', 3, 0, '2025-10-21 01:51:52', NULL, NULL),
(7, 66, 'L20251027-11-66', 5, 0, '2025-10-27 14:29:28', NULL, NULL),
(8, 37, 'L20251027-22-37', 17, 17, '2025-10-27 14:33:16', NULL, NULL),
(9, 37, 'L20251027-22-37', 17, 17, '2025-10-27 14:33:44', NULL, NULL),
(10, 43, 'L20251027-19-43', 3, 3, '2025-10-27 14:37:03', NULL, NULL),
(11, 66, 'L20251027-11-66', 5, 0, '2025-10-27 14:39:35', NULL, NULL),
(12, 5, 'L20251027-3-5', 1, 1, '2025-10-27 14:48:58', NULL, NULL),
(13, 37, 'L20251027-22-37', 4, 4, '2025-10-27 14:55:06', NULL, NULL),
(14, 84, 'L20251027-4-84', 4, 0, '2025-10-27 14:58:57', NULL, NULL),
(15, 84, 'L20251027-4-84', 4, 2, '2025-10-27 14:59:07', NULL, NULL),
(16, 48, 'L20251027-17-48', 2, 0, '2025-10-27 15:02:33', NULL, NULL),
(17, 43, 'L20251027-19-43', 5, 5, '2025-10-27 15:14:23', NULL, NULL),
(18, 84, 'L20251027-4-84', 3, 3, '2025-10-28 00:02:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_main` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `url`, `is_main`) VALUES
(1, 1, 'assets/images/product/Nike Air Max.jpg', 1),
(2, 1, 'assets/images/product/nike_air.jpg', 0),
(3, 2, 'assets/images/product/adidas_runner1.jpg', 1),
(4, 3, 'assets/images/product/puma_kids1.jpg', 1),
(5, 2, 'assets/images/product/pepsi.jpg', 1),
(11, 8, 'assets/images/adidas_stansmith1.jpg', 0),
(28, 23, 'assets/images/products/68eb5bdae83ca-NikeEpicReact1.jpg', 1),
(29, 23, 'assets/images/products/68eb5be757ed3-NikeEpicReact.jpg', 0),
(30, 22, 'assets/images/products/68eb5c2624a6b-pumaignite.jpg', 1),
(31, 22, 'assets/images/products/68eb5c2624ccd-pumaignite1.jpg', 0),
(32, 21, 'assets/images/products/68eb5c718bcd1-AdidasTerrex.jpg', 1),
(33, 21, 'assets/images/products/68eb5c718bf9f-AdidasTerrex1.jpg', 0),
(34, 20, 'assets/images/products/68eb5ca817258-NikeAirForce.jpg', 1),
(35, 20, 'assets/images/products/68eb5ca8174b8-NikeAirForce1.jpg', 0),
(36, 19, 'assets/images/products/68eb5cdb6a422-PumaFutureRider.jpg', 1),
(37, 19, 'assets/images/products/68eb5cdb6a6ac-PumaFutureRider1.jpg', 0),
(38, 18, 'assets/images/products/68eb5d1f0490f-AdidasGazelle.jpg', 1),
(39, 18, 'assets/images/products/68eb5d1f04b32-AdidasGazelle1.jpg', 0),
(40, 17, 'assets/images/products/68eb5d5b86192-NikeChelseaBoots.jpg', 1),
(41, 17, 'assets/images/products/68eb5d5b864a0-NikeChelseaBoots1.jpg', 0),
(42, 16, 'assets/images/products/68eb5d97bd990-PumaRS-X.jpg', 1),
(43, 16, 'assets/images/products/68eb5d97bdbd8-PumaRS-X1.jpg', 0),
(44, 15, 'assets/images/products/68eb5dd50e80f-AdidasNMD.jpg', 1),
(45, 15, 'assets/images/products/68eb5dd50ea16-AdidasNMD1.jpg', 0),
(46, 14, 'assets/images/products/68eb5e215a073-NikeReactInfinity.jpg', 1),
(47, 14, 'assets/images/products/68eb5e215a331-NikeReactInfinity1.jpg', 0),
(48, 13, 'assets/images/products/68eb5e4b02cdf-NikeFreeRun.jpg', 1),
(49, 13, 'assets/images/products/68eb5e4b02f00-NikeFreeRun1.jpg', 0),
(50, 12, 'assets/images/products/68eb5e78bc28b-PumaKidsRunner.jpg', 1),
(51, 12, 'assets/images/products/68eb5e78bc4d1-PumaKidsRunner1.jpg', 0),
(52, 11, 'assets/images/products/68eb5eadcfb85-AdidasWinterBoots.jpg', 1),
(53, 11, 'assets/images/products/68eb5eadcfdaa-AdidasWinterBoots1.jpg', 0),
(54, 10, 'assets/images/products/68eb5ee66676a-NikeCombatBoots.jpg', 1),
(55, 10, 'assets/images/products/68eb5ee6669aa-NikeCombatBoots1.jpg', 0),
(56, 9, 'assets/images/products/68eb5f25ca751-PumaSuedeClassic.jpg', 1),
(57, 9, 'assets/images/products/68eb5f25ca95e-PumaSuedeClassic1.jpg', 0),
(58, 8, 'assets/images/products/68eb5f5eefa8e-AdidasStanSmith.jpg', 1),
(59, 8, 'assets/images/products/68eb5f5eefcdd-AdidasStanSmith1.jpg', 0),
(60, 7, 'assets/images/products/68eb5f921b677-NikeAirJordan.jpg', 1),
(61, 7, 'assets/images/products/68eb5f921b899-NikeAirJordan1.jpg', 0),
(62, 6, 'assets/images/products/68eb5fcdc0961-PumaVelocity.jpg', 1),
(63, 6, 'assets/images/products/68eb5fcdc0b76-PumaVelocity1.jpg', 0),
(64, 5, 'assets/images/products/68eb6004b1013-AdidasUltraboost.jpg', 1),
(65, 5, 'assets/images/products/68eb6004b122f-AdidasUltraboost1.jpg', 0),
(66, 4, 'assets/images/products/68eb6064c11d7-NikeZoomPegasus.jpg', 1),
(67, 4, 'assets/images/products/68eb6064c1433-NikeZoomPegasus1.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 2, 5, 'Giày rất đẹp và êm chân', '2025-10-11 08:50:12'),
(2, 2, 3, 4, 'Chạy bộ thoải mái, màu sắc đẹp', '2025-10-11 08:50:12'),
(3, 3, 4, 5, 'sản phẩm sử dụng tốt, bền', '2025-10-11 10:26:37'),
(4, 2, 4, 5, 'giày đẹp, đi thoải mái,', '2025-10-11 10:49:07'),
(5, 5, 14, 5, 'kkkk', '2025-10-30 13:43:54');

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`) VALUES
(1, 1, '40', 0),
(2, 1, '41', 0),
(3, 2, '38', 0),
(4, 2, '39', 0),
(5, 3, '30', 14),
(34, 23, '41', 0),
(35, 23, '42', 0),
(37, 22, '31', 50),
(39, 21, '39', 0),
(41, 20, '40', 0),
(43, 19, '32', 8),
(45, 18, '36', 0),
(48, 17, '42', 0),
(50, 16, '39', 0),
(53, 15, '37', 0),
(54, 15, '38', 0),
(57, 14, '41', 0),
(58, 14, '43', 0),
(61, 13, '40', 0),
(62, 13, '41', 0),
(64, 12, '30', 2),
(66, 11, '38', 0),
(68, 10, '42', 0),
(70, 9, '39', 0),
(72, 8, '37', 0),
(74, 7, '41', 9),
(77, 6, '31', 5),
(80, 5, '38', 10),
(81, 5, '39', 7),
(84, 4, '40', 13),
(85, 4, '42', 6),
(86, 23, '43', 0),
(87, 3, '35', 0),
(88, 23, '41', 0),
(89, 23, '41', 0),
(90, 23, '42', 0),
(91, 23, '43', 0),
(92, 23, '44', 0);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(4, 'Admin'),
(2, 'Customer'),
(1, 'Guest'),
(3, 'Staff'),
(5, 'SupperAdmin');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_coupons`
--

CREATE TABLE `shipping_coupons` (
  `id` int(11) NOT NULL,
  `CODE` varchar(50) DEFAULT NULL,
  `TYPE` enum('shipping') NOT NULL DEFAULT 'shipping',
  `VALUE` int(11) NOT NULL,
  `expire_date` date NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_coupons`
--

INSERT INTO `shipping_coupons` (`id`, `CODE`, `TYPE`, `VALUE`, `expire_date`, `active`) VALUES
(1, 'SHIP80', 'shipping', 80, '2025-12-31', 1),
(2, 'FREESHIP100', 'shipping', 100, '2025-12-31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `supplierName` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `Sdt` varchar(20) DEFAULT NULL,
  `Address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplierName`, `Sdt`, `Address`, `Email`) VALUES
(1, 'Lý Trần Việt', '012055555', '3,đường số 3', 'lyviettran0128@gmail.com'),
(2, 'Khánh', '0123135446', 'djashdhash', 'khanh@gmail.com'),
(3, 'gfsfds', '0123566789', 'vsdfsdsdf', 'Viet234@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `created_at`, `updated_at`) VALUES
(1, 'Admin Demo', 'admin@demo.com', '$2y$10$uchlkULJM/Dj/xH0mFXaw.wKvB1VxKHHRbxLnHE3K9a6ZB2WKyRD2', '0123456789', '2025-10-11 08:50:12', '2025-10-12 14:25:19'),
(2, 'John Doe', 'john@example.com', '123456', '0987654321', '2025-10-11 08:50:12', '2025-10-11 08:50:12'),
(3, 'Jane Smith', 'jane@example.com', '$2y$10$uchlkULJM/Dj/xH0mFXaw.wKvB1VxKHHRbxLnHE3K9a6ZB2WKyRD2', '0987123456', '2025-10-11 08:50:12', '2025-10-11 22:10:50'),
(4, 'Viet', 'viet123@gmail.com', '$2y$10$uchlkULJM/Dj/xH0mFXaw.wKvB1VxKHHRbxLnHE3K9a6ZB2WKyRD2', '0123566789', '2025-10-11 08:59:29', '2025-10-11 22:09:10'),
(5, 'ltv', 'ly@gmail.com', '$2y$10$naCPwgptEAV1DGIYXz70muMTjJknimSioNihQhuV8EAsGL88/vWPW', NULL, '2025-10-11 22:19:23', '2025-10-12 16:17:45'),
(6, 'Viet', 'Viet234@gmail.com', '$2y$10$.kSL/wKcpnhtQFpzI9WECen2Eyw2BQiCpdWaDYNJEDk1cdoQ8BvV2', NULL, '2025-10-12 15:05:13', '2025-10-12 15:05:13'),
(9, 'Viet', 'lytranviet23@gmail.com', '$2y$10$ZBbhcz/yCkIMOzkXtGG8euXgHQVx7o7oVLfk6ZvEXrTB.yhA81V8W', NULL, '2025-10-12 16:26:17', '2025-10-12 16:26:17'),
(10, 'Trần Khánh', 'khanh@gmail.com', '$2y$10$2bdfSKMpXG9kfd0B7E/.7OiL.8ZpOzho4v3myNVJR0qOpMiUlnnJ2', NULL, '2025-10-16 11:48:25', '2025-10-16 11:48:25'),
(13, 'Trần Văn Khang', 'nguyentranankhang10@gmail.com', '$2y$10$G7oLJcjrc8CpXWJ5T1uZzeldeP60Vgaubb4xapC2/fzVuDvLZ/b8G', NULL, '2025-10-18 08:57:21', '2025-10-18 08:57:21'),
(14, 'nhan', 'nhanle1219@gmail.com', '$2y$10$/zznUxp7/bvoPQmPedWfi.jLLXsY.08MRfaVRmVBdvvDA5xuVFkhu', NULL, '2025-10-30 10:28:57', '2025-10-30 10:28:57'),
(16, 'API', 'nhanle1210@gmail.com', '$2y$10$eI.dWRSrF6wPSiYQNQHFlerxjAiPzvJS9Cack87/HbYx8TNxnYvMi', '123456789', '2025-10-31 23:09:50', '2025-11-08 00:10:20'),
(17, 'minh', 'leminhphan1@gmail.com', '$2y$10$2CK8ivz.v0vovDVVAdhkeeH0dSZoS9J7DvV8hZ9VrC.8eIfJ9AoH2', NULL, '2025-11-03 22:19:51', '2025-11-03 22:19:51'),
(19, 'minh', 'leminhphan@gmail.com', '$2y$10$S6ypcMeK1s.MIRcYVkhFf.u8w10Ut5Phw1Ab9MOsbJiZ0vR53BvvO', NULL, '2025-11-05 18:27:31', '2025-11-05 18:27:31'),
(20, 'minh', 'minh@gmail.com', '$2y$10$CrNVBZNsyoz9L5NCVtwmMut5dZqAzDtp5gYW0n81cCeNkKxtQvPaq', NULL, '2025-11-05 18:32:31', '2025-11-05 18:32:31'),
(21, 'minhle', 'leminh@gmail.com', '$2y$10$ZG5GZNkaozOcZl1GtNfAk.rml30K6McfgASUTNFYU0uA0ohZUoUXa', NULL, '2025-11-05 20:09:02', '2025-11-05 20:09:02'),
(22, 'nhabnb', 'nhanle1211@gmail.com', '$2y$10$cBXJ71ar8bjJOTC1SnoHJ.nhYMa3g5F4B5aTQPMurcoW5SbV2qleq', NULL, '2025-11-08 15:02:42', '2025-11-08 15:02:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 3),
(2, 2),
(3, 4),
(5, 2),
(6, 3),
(9, 2),
(10, 4),
(13, 2),
(14, 2),
(16, 5),
(17, 2),
(19, 2),
(20, 2),
(21, 2),
(22, 2);

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(1, 2, 2, '2025-10-11 08:50:12'),
(2, 3, 3, '2025-10-11 08:50:12'),
(9, 4, 2, '2025-10-12 13:37:36'),
(10, 10, 18, '2025-10-16 14:04:19'),
(11, 10, 17, '2025-10-16 16:20:11'),
(12, 3, 18, '2025-10-23 08:51:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_address` (`user_id`);

--
-- Indexes for table `address_codes`
--
ALTER TABLE `address_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `address_id` (`address_id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `export_receipt`
--
ALTER TABLE `export_receipt`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_code` (`receipt_code`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `export_receipt_detail`
--
ALTER TABLE `export_receipt_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `export_id` (`export_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `fk_export_receipt_detail_product_sizes` (`productsize_id`);

--
-- Indexes for table `import_receipt`
--
ALTER TABLE `import_receipt`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_code` (`receipt_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `import_receipt_detail`
--
ALTER TABLE `import_receipt_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_receipt_detail_product_sizes` (`productsize_id`),
  ADD KEY `fk_import_receipt_detail_import_receipt` (`import_id`),
  ADD KEY `fk_import_receipt_detail_product_batch` (`batch_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `brand_id` (`brand_id`);

--
-- Indexes for table `product_batch`
--
ALTER TABLE `product_batch`
  ADD PRIMARY KEY (`id`),
  ADD KEY `productsize_id` (`productsize_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
<<<<<<< HEAD
-- Chỉ mục cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Chỉ mục cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Chỉ mục cho bảng `roles`
=======
-- Indexes for table `roles`
>>>>>>> 306bbc44839a04fe76846ec25e9f86c6b30a488c
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `shipping_coupons`
--
ALTER TABLE `shipping_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `CODE` (`CODE`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `address_codes`
--
ALTER TABLE `address_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=246;

--
<<<<<<< HEAD
-- AUTO_INCREMENT cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `categories`
=======
-- AUTO_INCREMENT for table `categories`
>>>>>>> 306bbc44839a04fe76846ec25e9f86c6b30a488c
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `export_receipt`
--
ALTER TABLE `export_receipt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `export_receipt_detail`
--
ALTER TABLE `export_receipt_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `import_receipt`
--
ALTER TABLE `import_receipt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `import_receipt_detail`
--
ALTER TABLE `import_receipt_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=215;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=223;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `product_batch`
--
ALTER TABLE `product_batch`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipping_coupons`
--
ALTER TABLE `shipping_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_user_address` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `address_codes`
--
ALTER TABLE `address_codes`
  ADD CONSTRAINT `address_codes_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
<<<<<<< HEAD
-- Các ràng buộc cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `export_receipt`
=======
-- Constraints for table `export_receipt`
>>>>>>> 306bbc44839a04fe76846ec25e9f86c6b30a488c
--
ALTER TABLE `export_receipt`
  ADD CONSTRAINT `export_receipt_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `export_receipt_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `export_receipt_detail`
--
ALTER TABLE `export_receipt_detail`
  ADD CONSTRAINT `export_receipt_detail_ibfk_1` FOREIGN KEY (`export_id`) REFERENCES `export_receipt` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `export_receipt_detail_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `product_batch` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_export_receipt_detail_product_sizes` FOREIGN KEY (`productsize_id`) REFERENCES `product_sizes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `import_receipt`
--
ALTER TABLE `import_receipt`
  ADD CONSTRAINT `import_receipt_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_receipt_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `import_receipt_detail`
--
ALTER TABLE `import_receipt_detail`
  ADD CONSTRAINT `fk_import_receipt_detail_import_receipt` FOREIGN KEY (`import_id`) REFERENCES `import_receipt` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_import_receipt_detail_product_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batch` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_import_receipt_detail_product_sizes` FOREIGN KEY (`productsize_id`) REFERENCES `product_sizes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `import_receipt_detail_ibfk_1` FOREIGN KEY (`import_id`) REFERENCES `import_receipt` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
