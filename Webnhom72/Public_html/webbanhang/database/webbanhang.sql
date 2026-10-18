-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 24, 2026 at 05:29 PM
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
-- Database: `webbanhang`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_login_attempts`
--

CREATE TABLE `auth_login_attempts` (
  `email_key` char(64) NOT NULL,
  `ip_key` char(64) NOT NULL,
  `failed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_login_attempts`
--

INSERT INTO `auth_login_attempts` (`email_key`, `ip_key`, `failed_count`, `locked_until`, `updated_at`) VALUES
('25d76f564f18b9f262b495b82597fad1095d87a9df0dd354d24397f68776bddd', 'eff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3', 1, NULL, '2026-09-23 05:24:38'),
('973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b', 'eff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3', 1, NULL, '2026-09-23 05:32:56');

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `user_id`, `token_hash`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(5, 0, 'ee4be79383b058f0f99f54fbceeab0ec9fa7d22c0a90a11afb98c87fd7e5b03a', NULL, NULL, '2026-10-08 15:29:41', '2026-09-08 15:29:41'),
(6, 0, '7772cf9da113e9156ba42d50fe015828cbe93cbaa99b19495ecaacac5c119891', NULL, NULL, '2026-10-08 15:30:40', '2026-09-08 15:30:40'),
(7, 0, '72eddb6ae5f92aecb53e6ab0efb6f17c0ee581649f0936dcef1c24d3bf1b7f47', NULL, NULL, '2026-10-08 15:31:44', '2026-09-08 15:31:44'),
(8, 0, '2390adfa6b5aca52ac7e26a4a2f8c66912878a5b4d83df1c84d40ba8cc30150e', NULL, NULL, '2026-10-08 15:32:18', '2026-09-08 15:32:18'),
(10, 0, 'c0c60d444f5b8e2cb5c3a1874a3639675e47769e898ed492dbb71f6fc7f998e1', NULL, NULL, '2026-10-09 18:04:46', '2026-09-09 18:04:46'),
(11, 0, '83a4e0d1f3939b3adfd53e160ac419a99d3e3f941475736c248621741c577ed7', NULL, NULL, '2026-10-09 19:22:33', '2026-09-09 19:22:33'),
(12, 1, '9c8ddbe9d5d50dd483c51ea0effd07e8271e10bdb30993fc5e508f056acef463', NULL, NULL, '2026-10-09 19:57:58', '2026-09-09 19:57:58'),
(16, 0, '9880753012e682b8dcb210c75066f1466c5720c2e95eb56cf63b8eca7093e923', NULL, NULL, '2026-10-09 20:05:13', '2026-09-09 20:05:13'),
(17, 1, '43532ccc20d165718d8c98df6e2424c8dc06fb015418a10aa9ea16559b3fa46e', NULL, NULL, '2026-10-09 20:09:08', '2026-09-09 20:09:08'),
(19, 1, '7aac87bcd0c169a8604f26c59eba9e91b587b469df4b9287837dd0f908b4dbf4', NULL, NULL, '2026-10-09 20:57:07', '2026-09-09 20:57:07'),
(21, 1, 'b46c42fb3370f24ea62d27939b908e6c8a2e6425715d64a5611059ec8af88910', NULL, NULL, '2026-10-09 21:02:25', '2026-09-09 21:02:25'),
(24, 1, 'bbfe29342996077404e77ed6ec7bd7595e55d0e4ecfa015dea5ed3165c9938a0', NULL, NULL, '2026-10-09 21:13:33', '2026-09-09 21:13:33'),
(25, 0, 'd55cd47a9579a50a3200d14ebec2526b91e370791c384a08705530a3ce93d307', NULL, NULL, '2026-10-09 21:15:01', '2026-09-09 21:15:01'),
(26, 1, '86c939922109068fb5ae68c48412f0367572f87bb8e110404e0bb983e8950246', NULL, NULL, '2026-10-09 21:43:14', '2026-09-09 21:43:14'),
(27, 1, '052a2270be170552dd37c2d508023b65cc51e52db44d6a283c31e0adc70e1990', NULL, NULL, '2026-10-09 21:49:15', '2026-09-09 21:49:15'),
(28, 1, 'f5cc4d10e093e84356ffdd2a6148fb35e803569866e088132e7acd6874c26c80', NULL, NULL, '2026-10-10 18:57:23', '2026-09-10 18:57:23'),
(29, 0, '10aa2dfc2b0660d1cbe35bcd72752c19f82692f7e73e6d9980edd284489c55e9', NULL, NULL, '2026-10-10 18:57:58', '2026-09-10 18:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`) VALUES
(1, 0),
(0, 1),
(3, 2),
(2, 5);

-- --------------------------------------------------------

--
-- Table structure for table `cart_item`
--

CREATE TABLE `cart_item` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cart_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_item`
--

INSERT INTO `cart_item` (`id`, `product_id`, `cart_id`) VALUES
(0, 2, 0),
(1, 3, 2),
(2, 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`id`, `name`) VALUES
(1, 'Điện thoại'),
(2, 'Laptop'),
(3, 'Phụ kiện');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_code` varchar(30) DEFAULT NULL,
  `delivery_pin` varchar(10) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `order_date` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `total_money` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`id`, `user_id`, `fullname`, `email`, `phone_number`, `address`, `note`, `order_date`, `status`, `total_money`) VALUES
(0, 0, 'Nguyễn Đăng Khôi', 'khoai101205@gmail.com', '0779224535', 'ngõ 87 yên xá\r\nSố nhà 6 ngách 27', '', '2026-09-11 00:00:03', 0, 2000000),
(2, 2, 'Khoaibeti', 'khooigarena@gmail.com', '0779224535', 'Địa chỉ', '', '2026-09-22 16:07:16', 3, 2000000000);

-- --------------------------------------------------------

--
-- Table structure for table `order_detail`
--

CREATE TABLE `order_detail` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `num` int(11) DEFAULT NULL,
  `total_money` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_detail`
--

INSERT INTO `order_detail` (`id`, `order_id`, `product_id`, `price`, `num`, `total_money`) VALUES
(0, 0, 3, 2000000, 1, 2000000),
(2, 2, 2, 2000000000, 1, 2000000000);

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `discount` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `category_id`, `title`, `price`, `discount`, `thumbnail`, `description`, `created_at`, `updated_at`, `deleted`) VALUES
(1, 1, 'Iphone XXX', '100000000', NULL, 'https://i.ytimg.com/vi/PfjRa7-NviE/maxresdefault.jpg', 'Đẳng cấp', '2026-09-10 01:13:28', '2026-09-21 19:28:18', 0),
(2, 2, 'ACER A715', '2000000000', NULL, 'https://tse3.mm.bing.net/th/id/OIP.CbmJ6a3q1fDQzTkeYe1oJAAAAA?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 'Đẳng cấp', '2026-09-10 02:15:39', '2026-09-21 19:28:13', 0),
(3, 3, 'Màn hình edra egm27f240s', '2000000', NULL, 'https://tse1.mm.bing.net/th/id/OIP.H0wl2L7PuC9FkEqQgPFNfgHaHX?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 'MÀN 240FPS', '2026-09-10 23:59:40', '2026-09-10 23:59:40', 0),
(4, 1, 'Concac', '2132144141', NULL, 'https://vstatic.vietnam.vn/vietnam/resource/IMAGE/2026/09/13/1789268913021_minh-tuan-mobile-mo-dat-hang-iphone-18-pro-02-6602-1616-jpg.webp', 'Nguyễn Đăng Khôi', '2026-09-15 00:02:50', '2026-09-21 09:53:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_comments`
--

CREATE TABLE `product_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_comments`
--

INSERT INTO `product_comments` (`id`, `product_id`, `user_id`, `parent_id`, `content`, `is_approved`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 3, 5, NULL, 'ảnh', 1, '2026-09-23 01:23:56', '2026-09-23 01:46:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_comment_media`
--

CREATE TABLE `product_comment_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `comment_id` bigint(20) UNSIGNED NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_comment_media`
--

INSERT INTO `product_comment_media` (`id`, `comment_id`, `media_type`, `file_path`, `created_at`) VALUES
(1, 1, 'image', 'uploads/comments/6f38ba0c84fce99134a83aec36d22dfc.jpg', '2026-09-23 01:23:56');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`id`, `name`) VALUES
(1, 'Admin'),
(2, 'User');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `avatar_url` text DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `deleted` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `fullname`, `email`, `avatar_url`, `phone_number`, `address`, `password`, `role_id`, `status`, `created_at`, `updated_at`, `last_login_at`, `deleted`) VALUES
(0, 'Nguyễn Đăng Khôi', 'khoai101205@gmail.com', NULL, NULL, NULL, '$2y$10$MvDTOjmZtmFxs6C6Giumy.i//arvveeZqr9b3aZOYB7znMUGyjcMG', 1, 'active', '2026-09-07 23:30:58', '2026-09-11 23:15:28', NULL, 1),
(1, 'Nguyễn Khôi', 'khoi123jkl@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$DnR9ec5a5gRzWbS39smf5u/u7Osiv1XDKUB.IrTnKD5q9a8D8ZY4O', 2, 'active', '2026-09-09 23:23:57', '2026-09-11 23:15:42', NULL, 1),
(2, 'Khoaibeti', 'khooigarena@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$pN0nOs3TcCZZRg4fqcn/3eUp8ImTiE2oYZl1ajzrvxqTrfK8CzT3m', 1, 'active', '2026-09-11 23:11:10', '2026-09-11 23:15:08', NULL, 0),
(3, 'Nguyễn Đăng Khôi', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$xYCVAmnH7VIhuEIyg.69KuodlYm3SJPLfP3eSjnyRlJALYPwjE8Xy', 2, 'active', '2026-09-11 23:15:56', '2026-09-23 05:27:00', NULL, 1),
(4, 'Nguyễn Đăng Khôi', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$i2ujXxBMHJaG.F4R9k4B0.lmudpXJKpWHLe3EvcD53qmEtxxIzU8G', 2, 'active', '2026-09-11 23:16:47', '2026-09-11 23:16:59', NULL, 1),
(5, 'Nguyễn Đăng Khôi', 'khoi123jkl@gmail.com', NULL, NULL, NULL, '$2y$10$CasXN0t60E09s0TNyBVR3.uF.pE7lg3lEbGtFqTSMspSs7oZU7wEe', 2, 'active', '2026-09-11 18:18:53', '2026-09-11 18:18:53', NULL, 0),
(6, 'Nguyễn Văn A', 'khoi@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$3uxP2O.jnOcnVm1sBpW/MOKaypiGN8gxNzF3z8Ma6XALxVlLcVw3m', 2, 'active', '2026-09-12 22:30:42', '2026-09-23 05:27:05', NULL, 1),
(7, 'Nguyễn Văn A', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$7o65t/NchtRRS.XJI5z47OWn/vew2auaURHhECIzl77F4KROL0sGu', 2, 'active', '2026-09-14 03:41:04', '2026-09-14 03:44:14', NULL, 1),
(8, 'Nguyễn Văn A', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$L4nlrQB0mbHVyy7m5roS6ub8cR.duEdv1dx1fl34mSRCSZ9RJzDrG', 2, 'active', '2026-09-14 03:42:00', '2026-09-14 03:43:48', NULL, 1),
(9, 'Nguyễn Văn A', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$M.oZ5T1YfGKyRZ/75FzvDeBeCp26zNlLaCLf9Xq7MzurGXhl8uvFi', 2, 'active', '2026-09-14 03:42:34', '2026-09-14 03:43:42', NULL, 1),
(10, 'Nguyễn Văn A', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$Kku8.DUVnm82w0wk4/2Q2OTd.vc34Z.bRM/kqlJuu0uwjshyVAtsy', 2, 'active', '2026-09-14 03:43:36', '2026-09-14 03:43:40', NULL, 1),
(11, 'Nguyễn Văn A', 'khoai101205@gmail.com', NULL, '0779224535', 'ngõ 87 yên xá', '$2y$10$0cfTp4BzCoxWWCKaUI15bOW7ePcoaex8uS5Q01X0ELNVVu/G25rky', 2, 'active', '2026-09-14 03:45:47', '2026-09-23 05:27:03', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_mfa`
--

CREATE TABLE `user_mfa` (
  `user_id` int(11) NOT NULL,
  `secret_ciphertext` text NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_mfa`
--

INSERT INTO `user_mfa` (`user_id`, `secret_ciphertext`, `enabled`, `created_at`, `updated_at`) VALUES
(2, 'UyKOFuoBF/+3y9PJyAa9Mtrusr/JqEwuZ8D6reFw+30u3pFJmivWojrRKJilFC9I8hCDq25dTeZgFtfd', 1, '2026-09-23 05:22:25', '2026-09-23 05:22:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_login_attempts`
--
ALTER TABLE `auth_login_attempts`
  ADD PRIMARY KEY (`email_key`,`ip_key`),
  ADD KEY `locked_until` (`locked_until`),
  ADD KEY `idx_login_attempts_ip` (`ip_key`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_item`
--
ALTER TABLE `cart_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_comments`
--
ALTER TABLE `product_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_comments_product` (`product_id`),
  ADD KEY `idx_product_comments_user` (`user_id`),
  ADD KEY `fk_product_comments_parent` (`parent_id`);

--
-- Indexes for table `product_comment_media`
--
ALTER TABLE `product_comment_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_comment_media_comment` (`comment_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_user_email` (`email`);

--
-- Indexes for table `user_mfa`
--
ALTER TABLE `user_mfa`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_comments`
--
ALTER TABLE `product_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_comment_media`
--
ALTER TABLE `product_comment_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

--
-- Constraints for table `cart_item`
--
ALTER TABLE `cart_item`
  ADD CONSTRAINT `cart_item_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`id`),
  ADD CONSTRAINT `cart_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Constraints for table `gallery`
--
ALTER TABLE `gallery`
  ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

--
-- Constraints for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD CONSTRAINT `order_detail_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`),
  ADD CONSTRAINT `order_detail_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`);

--
-- Constraints for table `product_comments`
--
ALTER TABLE `product_comments`
  ADD CONSTRAINT `fk_product_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_comments_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_comments_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_comment_media`
--
ALTER TABLE `product_comment_media`
  ADD CONSTRAINT `fk_product_comment_media_comment` FOREIGN KEY (`comment_id`) REFERENCES `product_comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`);

--
-- Constraints for table `user_mfa`
--
ALTER TABLE `user_mfa`
  ADD CONSTRAINT `fk_user_mfa_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
