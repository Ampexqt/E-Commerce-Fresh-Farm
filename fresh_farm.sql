-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 20, 2025 at 04:43 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fresh_farm`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_type` varchar(50) NOT NULL,
  `recipient_name` varchar(100) NOT NULL,
  `street` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `permission_level` enum('super_admin','admin','editor') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `profile_image`, `permission_level`, `created_at`, `last_login`, `status`) VALUES
(1, 'admin', 'admin@freshfarm.com', '$2y$10$D9JgzEwOiJwBdtpk9Dq15.CXD6WXrX11/ybpjpkQ0ncdpb.mZdz2W', 'Admin', 'User', NULL, NULL, 'super_admin', '2025-05-20 14:33:02', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `quantity`, `created_at`) VALUES
(5, 14, 19, 5, '2025-05-20 06:12:36'),
(20, 20, 13, 1, '2025-05-20 11:29:08'),
(24, 21, 21, 5, '2025-05-20 14:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_details` varchar(100) DEFAULT NULL,
  `status` enum('preparing','in_transit','delivered','failed') DEFAULT 'preparing',
  `delivery_date` date DEFAULT NULL,
  `tracking_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_addresses`
--

CREATE TABLE `delivery_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_type` varchar(50) NOT NULL,
  `recipient_name` varchar(100) NOT NULL,
  `street_address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `region` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_addresses`
--

INSERT INTO `delivery_addresses` (`id`, `user_id`, `address_type`, `recipient_name`, `street_address`, `city`, `barangay`, `region`, `postal_code`, `phone_number`, `is_default`, `created_at`) VALUES
(31, 20, 'Home', 'Bunsai Saporno', 'Climaco', 'R9C1', 'ZAM9', 'Region9', '7000', '+63 917 271 9341', 0, '2025-05-20 10:52:39'),
(33, 21, 'Home', 'John christian Saporno', 'asdasda', 'NCR1', 'B102', 'NCR', '1001', '+63 912 313 1312', 1, '2025-05-20 14:18:16'),
(34, 21, 'Work', 'John christian Saporno', 'asdasd', 'R3C1', 'SF1', 'Region3', '2000', '+63 912 313 1312', 0, '2025-05-20 14:18:35');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`favorite_id`, `user_id`, `product_id`, `created_at`) VALUES
(84, 14, 12, '2025-05-19 20:06:34'),
(85, 14, 24, '2025-05-19 20:06:35'),
(87, 14, 23, '2025-05-19 20:06:38'),
(93, 21, 22, '2025-05-20 14:14:10'),
(94, 21, 21, '2025-05-20 14:14:11'),
(95, 21, 12, '2025-05-20 14:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_address` text NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `address_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `total_amount`, `status`, `shipping_address`, `payment_method`, `address_id`, `payment_method_id`) VALUES
(14, 14, '2025-05-19 20:17:27', '40.00', 'pending', 'Jecelle Eudilla\nSouthcom\nR9C1, Region9\nPhilippines 7000\nPhone: +63 954 312 3131', 'cod', 28, 33),
(15, 14, '2025-05-19 20:41:50', '102.00', 'pending', 'Jecelle Eudilla\nSouthcom\nR9C1, Region9\nPhilippines 7000\nPhone: +63 954 312 3131', 'cod', 28, 33),
(16, 14, '2025-05-19 21:05:20', '242.00', 'cancelled', 'Jecelle Eudilla\nSouthcom\nR9C1, Region9\nPhilippines 7000\nPhone: +63 954 312 3131', 'cod', 28, 33),
(17, 14, '2025-05-19 21:10:25', '570.00', 'cancelled', 'Jecelle Eudilla\nSouthcom\nR9C1, Region9\nPhilippines 7000\nPhone: +63 954 312 3131', 'cod', 28, 33),
(18, NULL, '2025-05-20 05:50:50', '121.00', 'pending', 'John Christian  Saporno\nsea breeze\nNCR1, NCR\nPhilippines 1000\nPhone: +63 959 012 4134', 'cod', 29, 34),
(19, NULL, '2025-05-20 08:33:25', '70.00', 'pending', 'John Christian  Saporno\nsea breeze\nNCR1, NCR\nPhilippines 1000\nPhone: +63 959 012 4134', 'cod', 29, 34),
(20, NULL, '2025-05-20 08:48:34', '900.00', 'pending', 'John Christian  Saporno\nsea breeze\nNCR1, NCR\nPhilippines 1000\nPhone: +63 959 012 4134', 'gcash', 29, 40),
(21, NULL, '2025-05-20 08:50:12', '242.00', 'pending', 'John Christian  Saporno\nsea breeze\nNCR1, NCR\nPhilippines 1000\nPhone: +63 959 012 4134', 'gcash', 29, 40),
(22, NULL, '2025-05-20 08:52:01', '306.00', 'pending', 'John Christian  Saporno\nsea breeze\nNCR1, NCR\nPhilippines 1000\nPhone: +63 959 012 4134', 'gcash', 29, 40),
(23, 20, '2025-05-20 10:53:02', '280.00', 'pending', 'Bunsai Saporno\nClimaco\nR9C1, Region9\nPhilippines 7000\nPhone: +63 917 271 9341', 'cod', 31, 41),
(24, 21, '2025-05-20 14:14:04', '40.00', 'pending', 'John christian Saporno\nSEA BREEZE\nR9C1, Region9\nPhilippines 7000\nPhone: +63 912 313 1312', 'cod', 32, 42),
(25, 21, '2025-05-20 14:26:04', '482.00', 'pending', 'John christian Saporno\nasdasda\nNCR1, NCR\nPhilippines 1001\nPhone: +63 912 313 1312', 'maya', 33, 46);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(14, 14, 21, 2, '20.00'),
(15, 15, 12, 1, '102.00'),
(16, 16, 20, 2, '121.00'),
(17, 17, 24, 3, '20.00'),
(18, 17, 12, 5, '102.00'),
(19, 18, 20, 1, '121.00'),
(20, 19, 23, 1, '70.00'),
(21, 20, 24, 2, '20.00'),
(22, 20, 21, 4, '20.00'),
(23, 20, 22, 2, '180.00'),
(24, 20, 23, 2, '70.00'),
(25, 20, 19, 2, '140.00'),
(26, 21, 18, 2, '121.00'),
(27, 22, 12, 3, '102.00'),
(28, 23, 19, 2, '140.00'),
(29, 24, 21, 2, '20.00'),
(30, 25, 18, 2, '121.00'),
(31, 25, 21, 3, '20.00'),
(32, 25, 22, 1, '180.00');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `masked_number` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `additional_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_number` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `user_id`, `type`, `masked_number`, `is_default`, `additional_data`, `created_at`, `updated_at`, `account_number`) VALUES
(33, 14, 'cod', '+63 ********3131', 1, NULL, '2025-05-19 20:14:31', '2025-05-19 20:14:31', '+63 954 312 3131'),
(41, 20, 'cod', '+63 ********9341', 1, NULL, '2025-05-20 10:52:52', '2025-05-20 10:52:52', '+63 917 271 9341'),
(42, 21, 'cod', '+63 ********1312', 0, NULL, '2025-05-20 14:13:56', '2025-05-20 14:21:39', '+63 912 313 1312'),
(46, 21, 'maya', '09*****1839', 1, NULL, '2025-05-20 14:21:36', '2025-05-20 14:21:39', '09762761839');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(10) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` enum('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock',
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `price`, `unit`, `stock`, `status`, `description`, `image_path`, `created_at`, `updated_at`) VALUES
(12, 'Eggs', 'Dairy', '102.00', 'box', 5, '', 'Farm-fresh eggs in a box, great for breakfast or baking.', 'assets/products/682382db58fa1_eggs.jpg', '2025-05-06 04:37:06', '2025-05-20 11:37:12'),
(13, 'apple', 'Dairy', '25.00', 'pcs', 116, '', 'Sweet pastry filled with rich purple star apple filling.', 'assets/products/68238310e9495_apple.jpg', '2025-05-06 04:37:50', '2025-05-19 17:02:59'),
(18, 'kale', 'Vegetables', '121.00', 'pcs', 13, '', 'Nutritious and crisp kale, excellent for salads, smoothies, or sautés.', 'assets/products/682368fece339_kale.jpg', '2025-05-13 15:45:02', '2025-05-20 14:26:04'),
(19, 'tomatoes', 'Fruits', '140.00', 'kg', 11, '', 'Juicy, ripe tomatoes ideal for salads, sauces, and everyday cooking.', 'assets/products/682371411fd0b_tomatoes.jpg', '2025-05-13 16:01:13', '2025-05-20 10:53:02'),
(20, 'squash', 'Vegetables', '121.00', 'pcs', 11, '', 'Fresh and firm squash, perfect for soups, stews, or roasting.', 'assets/products/68237135663e5_squash.jpg', '2025-05-13 16:20:05', '2025-05-20 11:37:28'),
(21, 'Eggpie', 'Bakery', '20.00', 'pcs', 37, '', 'A classic Filipino dessert with a rich, creamy custard filling and a perfectly golden crust—sweet, smooth, and satisfying in every bite.', 'assets/products/682384be2cfc9_eggpie.jpg', '2025-05-13 17:43:26', '2025-05-20 14:26:04'),
(22, 'Avocado', 'Fruits', '180.00', 'kg', 16, '', 'Creamy and nutritious', 'assets/products/682459ec49a3d_avocado.jpg', '2025-05-14 08:53:00', '2025-05-20 14:26:04'),
(23, 'Eggplant', 'Vegetables', '70.00', 'kg', 11, 'Low Stock', 'Soft and rich when cooked', 'assets/products/68258f850879c_eggplant.jpg', '2025-05-15 06:53:57', '2025-05-20 08:48:34'),
(24, 'Okra', 'Vegetables', '20.00', 'box', 7, 'Low Stock', 'Masarap to', 'assets/products/68273b299f4d0_okra.jpg', '2025-05-16 13:18:33', '2025-05-20 11:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `reference_code` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('admin','customer','staff') NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `first_name`, `last_name`, `email`, `phone_number`, `password`, `profile_image`, `role`, `created_at`, `last_login`, `active`, `phone`, `birth_date`) VALUES
(14, 'jecelle9de0c', 'JECELLE', 'EUDILA', 'jecelle@gmail.com', '+63 213 131 1111', '$2y$10$kUgHjz.Mdx3P87kbqUO9SOeFjC88DOlv9zn/xWZgj9jJISOnPvqXi', 'uploads/profile_images/profile_14_1747669631.png', 'customer', '2025-05-19 10:05:15', NULL, 1, '+63 213 131 1111', '2025-05-08'),
(20, 'jecelle10339', 'Bunsai', 'Saporno', 'kitkat@gmail.com', '+63 917 271 9341', '$2y$10$LjCOz8BP2uf1IY.v/FH8F.F2c.1/SpvOsZw7inKcAhryzl4uZeRXG', 'uploads/profile_images/profile_20_1747738166.jpg', 'customer', '2025-05-20 10:47:57', NULL, 1, '+63 917 271 9341', '2025-05-09'),
(21, 'johnchristian5b928', 'John Christian', 'Saporno', 'bunsai@gmail.com', '+63 912 313 1312', '$2y$10$QOOcRJwZvH7qakIUd.Efx.JMUQZAfkntsFPK8UaYtYKy/cyqAA0cO', 'uploads/profile_images/profile_21_1747750410.jpg', 'customer', '2025-05-20 14:13:13', NULL, 1, '+63 912 313 1312', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `delivery_addresses`
--
ALTER TABLE `delivery_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `orders_address_fk` (`address_id`),
  ADD KEY `orders_payment_method_fk` (`payment_method_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_addresses`
--
ALTER TABLE `delivery_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `delivery_addresses`
--
ALTER TABLE `delivery_addresses`
  ADD CONSTRAINT `delivery_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
