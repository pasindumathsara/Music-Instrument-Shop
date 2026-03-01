-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 28, 2026 at 07:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `music_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Guitars'),
(2, 'Keyboards'),
(3, 'Drums'),
(4, 'Accessories'),
(5, 'Guitars'),
(6, 'Keyboards & Pianos'),
(7, 'Drums & Percussion'),
(8, 'Bass Guitars'),
(9, 'Wind Instruments'),
(10, 'String Instruments'),
(11, 'Audio Equipment'),
(12, 'Digital Products');

-- --------------------------------------------------------

--
-- Table structure for table `digital_products`
--

CREATE TABLE `digital_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT 'stored filename in uploads/digital/',
  `original_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'original uploaded filename',
  `file_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'bytes',
  `file_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'mime type',
  `file_ext` varchar(10) NOT NULL DEFAULT '',
  `download_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `version` varchar(20) NOT NULL DEFAULT '1.0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `digital_products`
--

INSERT INTO `digital_products` (`id`, `product_id`, `file_name`, `original_name`, `file_size`, `file_type`, `file_ext`, `download_count`, `version`, `created_at`, `updated_at`) VALUES
(1, 21, 'digi_699e16f13b6da.pdf', 'digi_699e16f13b6da.pdf', 0, '', 'pdf', 2, '1.0', '2026-02-24 21:39:20', '2026-02-28 04:14:07');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `download_logs`
--

INSERT INTO `download_logs` (`id`, `user_id`, `product_id`, `file_name`, `ip_address`, `downloaded_at`) VALUES
(1, 6, 21, 'digi_699e16f13b6da.pdf', '::1', '2026-02-28 04:06:42'),
(2, 6, 21, 'digi_699e16f13b6da.pdf', '::1', '2026-02-28 04:14:07');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_name` varchar(150) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_zip` varchar(20) DEFAULT NULL,
  `payment_method` enum('card','bank_transfer','cash_on_delivery') NOT NULL DEFAULT 'card',
  `tracking_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `shipping_cost`, `status`, `shipping_name`, `shipping_address`, `shipping_city`, `shipping_zip`, `payment_method`, `tracking_note`, `created_at`) VALUES
(10, 5, 20.00, 0.00, 'paid', 'Digital Delivery', 'Digital Delivery', 'Digital', '000000', 'card', NULL, '2026-02-24 21:25:45'),
(13, 6, 849.99, 0.00, 'pending', 'pasindu', '3738 Huntz Lane', 'AURORA', '47001', 'cash_on_delivery', NULL, '2026-02-24 22:07:14'),
(14, 6, 849.99, 0.00, 'paid', 'pasindu', '3738 Huntz Lane', 'AURORA', '47001', 'card', NULL, '2026-02-24 22:08:26'),
(15, 6, 89.98, 9.99, 'paid', 'pasindu', '3738 Huntz Lane', 'AURORA', '47001', 'bank_transfer', NULL, '2026-02-24 22:09:36'),
(16, 6, 20.00, 0.00, 'paid', 'Digital Delivery', 'Digital Delivery', 'Digital', '000000', 'card', NULL, '2026-02-28 04:06:09'),
(17, 6, 20.00, 0.00, 'paid', 'Digital Delivery', 'Digital Delivery', 'Digital', '000000', 'card', NULL, '2026-02-28 04:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(10, 10, 21, 1, 20.00),
(13, 13, 5, 1, 849.99),
(14, 14, 5, 1, 849.99),
(15, 15, 7, 1, 79.99),
(16, 16, 21, 1, 20.00),
(17, 17, 21, 1, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `note`, `created_by`, `created_at`) VALUES
(6, 10, 'paid', 'Initial status', 'system', '2026-02-24 21:53:57'),
(10, 13, 'pending', 'Order placed via cash on delivery', 'pasindu', '2026-02-24 22:07:14'),
(11, 14, 'pending', 'Order placed via card', 'pasindu', '2026-02-24 22:08:26'),
(12, 14, 'paid', 'Payment successful (Card ending in 2222)', 'pasindu', '2026-02-24 22:08:39'),
(13, 15, 'pending', 'Order placed via bank transfer', 'pasindu', '2026-02-24 22:09:36'),
(14, 15, 'paid', 'Status updated by administrator', 'Admin', '2026-02-24 22:12:12'),
(15, 16, 'pending', 'Order placed via card', 'pasindu', '2026-02-28 04:06:09'),
(16, 16, 'paid', 'Payment successful (Card ending in 7575)', 'pasindu', '2026-02-28 04:06:29'),
(17, 17, 'pending', 'Order placed via card', 'pasindu', '2026-02-28 04:12:37'),
(18, 17, 'paid', 'Payment successful (Card ending in 3222)', 'pasindu', '2026-02-28 04:13:51');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `card_last4` varchar(4) NOT NULL,
  `card_holder` varchar(150) NOT NULL,
  `card_type` varchar(20) DEFAULT 'Unknown',
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `card_last4`, `card_holder`, `card_type`, `status`, `created_at`) VALUES
(8, 10, 20.00, '4444', 'Admin', 'Visa', 'success', '2026-02-25 02:56:25'),
(10, 14, 849.99, '2222', 'pasindu', 'Visa', 'success', '2026-02-25 03:38:39'),
(11, 16, 20.00, '7575', 'pasindu', 'Unknown', 'success', '2026-02-28 09:36:29'),
(12, 17, 20.00, '3222', 'pasindu', 'Visa', 'success', '2026-02-28 09:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `stock` int(11) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_type` enum('physical','digital') NOT NULL DEFAULT 'physical',
  `digital_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `shipping_cost`, `stock`, `image`, `created_at`, `product_type`, `digital_file`) VALUES
(2, 2, 'Digital Piano', '88-key professional digital piano', 499.00, 0.00, 5, 'product_699e020b1835d.jpg', '2026-02-24 13:00:54', 'physical', NULL),
(4, 1, 'Yamaha FG800 Acoustic Guitar', 'The FG800 features a solid Sitka spruce top with scalloped bracing and nato back and sides, delivering a full-bodied, resonant sound. Perfect for beginners and intermediate players alike.', 199.99, 0.00, 24, 'product_699e011e1bebb.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(5, 1, 'Fender Stratocaster Electric Guitar', 'The iconic Stratocaster with solid alder body, maple neck, and 3 single-coil pickups. Versatile tone suitable for blues, rock, and everything in between.', 849.99, 0.00, 4, 'product_699e00ca5a4bc.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(7, 2, 'Casio CT-S300 Portable Keyboard', 'Lightweight 61-key keyboard with 400 AiX tones and 77 built-in rhythms. Great for students and on-the-go musicians.', 79.99, 30.00, 26, 'product_699dfec26da34.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(8, 2, 'Roland FP-30X Digital Piano', 'Premium 88-key weighted piano with PHA-4 Standard keyboard action and powerful speaker system. Studio-grade sound in a portable form.', 699.99, 0.00, 8, 'product_699dff4e17011.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(9, 3, 'Pearl Export EXX 5-Piece Drum Kit', 'The world\'s best-selling drum kit: 22\" bass drum, 10\"/12\" rack toms, 16\" floor tom, and 14\" snare. Built for durability and great tone.', 799.99, 0.00, 6, 'product_699dff223ac99.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(10, 3, 'Meinl Classics Custom Cymbal Set', 'Professional 3-piece cymbal set: 14\" hi-hats, 16\" crash, 20\" ride. Brilliant finish with a bright, cutting sound ideal for rock and pop.', 249.99, 0.00, 15, 'product_699dff80c7ffc.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(11, 4, 'Fender Player Jazz Bass', 'Classic Jazz Bass feel and sound with alder body, maple neck, and 2 Pure Vintage single-coil pickups. Smooth playability for every style.', 749.99, 0.00, 7, 'product_699e004be4e41.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(13, 6, 'Stentor Student II Violin 4/4', 'Quality solid tonewood construction with superior playability and warm tone. Perfect for beginners advancing to intermediate level.', 199.99, 0.00, 20, 'product_699e017232f4e.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(14, 7, 'Focusrite Scarlett 2i2 Interface', 'Industry-leading USB audio interface with 2 combo inputs and best-in-class preamps. Get studio-quality recordings at home.', 159.99, 0.00, 18, 'product_699e019ee7437.jpg', '2026-02-24 14:40:12', 'physical', NULL),
(15, 7, 'Sony MDR-7506 Studio Headphones', 'The industry-standard studio headphone used by professionals worldwide. 40mm neodymium drivers deliver clear, detailed sound reproduction.', 99.99, 0.00, 25, 'product_699e01d75f44e.webp', '2026-02-24 14:40:12', 'physical', NULL),
(21, 12, 'music lyrics pdf', 'ggg', 20.00, 0.00, 6, 'product_699e16f13b091.jpg', '2026-02-24 21:24:01', 'digital', 'digi_699e16f13b6da.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(2, 21, 6, 5, 'good product', '2026-02-28 04:07:18'),
(3, 5, 6, 5, 'good', '2026-02-28 04:23:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(5, 'Admin', 'admin@gmail.com', '$2y$10$IzjUVZWoLCrqTgF2T6gOfe9nV8K1TuqCXTayKpOE.Rb3.vJ/e/Dve', 'admin', '2026-02-24 15:35:38'),
(6, 'pasindu', 'pasindumathsara@gmail.com', '$2y$10$WfpSLoJ6b3XIUVUCEheJx.aGwUZYhbjhda0BODebB/1mvxG9PZ5c2', 'customer', '2026-02-24 17:19:07'),
(8, 'mathsara', 'mathsara@gmail.com', '$2y$10$kYFLK1shwaWx3hboczR3huQlK/rvDzGUaPAppvYSFSmAUjEvDMku.', 'staff', '2026-02-28 05:25:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `digital_products`
--
ALTER TABLE `digital_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_items_ibfk_2` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `digital_products`
--
ALTER TABLE `digital_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `digital_products`
--
ALTER TABLE `digital_products`
  ADD CONSTRAINT `digital_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `download_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `download_logs_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
