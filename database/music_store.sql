-- ============================================================
-- Melody Masters – Music Store Database Schema + Sample Data
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `music_store`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `music_store`;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: categories
-- ----------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: products
-- ----------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT(11)        NOT NULL AUTO_INCREMENT,
  `category_id` INT(11)        DEFAULT NULL,
  `name`        VARCHAR(200)   NOT NULL,
  `description` TEXT,
  `price`       DECIMAL(10,2)  NOT NULL,
  `stock`       INT(11)        NOT NULL DEFAULT 0,
  `image`       VARCHAR(255)   DEFAULT NULL,
  `created_at`  DATETIME       DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_product_cat`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: orders
-- ----------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `user_id`          INT(11)        NOT NULL,
  `total_amount`     DECIMAL(10,2)  NOT NULL,
  `shipping_cost`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `status`           ENUM('pending','paid','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_name`    VARCHAR(150)   DEFAULT NULL,
  `shipping_address` TEXT           DEFAULT NULL,
  `shipping_city`    VARCHAR(100)   DEFAULT NULL,
  `shipping_zip`     VARCHAR(20)    DEFAULT NULL,
  `created_at`       DATETIME       DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_order_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: order_items
-- ----------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`   INT(11)       NOT NULL,
  `product_id` INT(11)       DEFAULT NULL,
  `quantity`   INT(11)       NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_item_order`
    FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: reviews
-- ----------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT(11)  NOT NULL AUTO_INCREMENT,
  `product_id` INT(11)  NOT NULL,
  `user_id`    INT(11)  NOT NULL,
  `rating`     TINYINT(1) NOT NULL,
  `comment`    TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_review_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: payments
-- ----------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`    INT(11)       NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `card_last4`  VARCHAR(4)    NOT NULL,
  `card_holder` VARCHAR(150)  NOT NULL,
  `card_type`   VARCHAR(20)   DEFAULT 'Unknown',
  `status`      ENUM('success','failed') NOT NULL DEFAULT 'success',
  `created_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_payment_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Sample Categories
-- ----------------------------
INSERT INTO `categories` (`name`) VALUES
  ('Guitars'),
  ('Keyboards & Pianos'),
  ('Drums & Percussion'),
  ('Bass Guitars'),
  ('Wind Instruments'),
  ('String Instruments'),
  ('Audio Equipment');

-- ----------------------------
-- Sample Products
-- ----------------------------
INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `stock`, `image`) VALUES
(1, 'Yamaha FG800 Acoustic Guitar',       'The FG800 features a solid Sitka spruce top with scalloped bracing and nato back and sides, delivering a full-bodied, resonant sound. Perfect for beginners and intermediate players alike.', 199.99, 25, NULL),
(1, 'Fender Stratocaster Electric Guitar','The iconic Stratocaster with solid alder body, maple neck, and 3 single-coil pickups. Versatile tone suitable for blues, rock, and everything in between.', 849.99, 10, NULL),
(1, 'Gibson Les Paul Standard',           'Mahogany body, maple top, and two humbucking pickups deliver the legendary Les Paul tone. A timeless masterpiece for serious players.', 2499.99, 5,  NULL),
(2, 'Casio CT-S300 Portable Keyboard',    'Lightweight 61-key keyboard with 400 AiX tones and 77 built-in rhythms. Great for students and on-the-go musicians.', 79.99, 30, NULL),
(2, 'Roland FP-30X Digital Piano',        'Premium 88-key weighted piano with PHA-4 Standard keyboard action and powerful speaker system. Studio-grade sound in a portable form.', 699.99, 8,  NULL),
(3, 'Pearl Export EXX 5-Piece Drum Kit',  'The world\'s best-selling drum kit: 22" bass drum, 10"/12" rack toms, 16" floor tom, and 14" snare. Built for durability and great tone.', 799.99, 6,  NULL),
(3, 'Meinl Classics Custom Cymbal Set',   'Professional 3-piece cymbal set: 14" hi-hats, 16" crash, 20" ride. Brilliant finish with a bright, cutting sound ideal for rock and pop.', 249.99, 15, NULL),
(4, 'Fender Player Jazz Bass',            'Classic Jazz Bass feel and sound with alder body, maple neck, and 2 Pure Vintage single-coil pickups. Smooth playability for every style.', 749.99, 7,  NULL),
(5, 'Yamaha YAS-280 Alto Saxophone',      'Designed for beginners to develop proper technique. Durable brass body with smooth key action and a warm, resonant tone.', 649.99, 10, NULL),
(6, 'Stentor Student II Violin 4/4',      'Quality solid tonewood construction with superior playability and warm tone. Perfect for beginners advancing to intermediate level.', 199.99, 20, NULL),
(7, 'Focusrite Scarlett 2i2 Interface',   'Industry-leading USB audio interface with 2 combo inputs and best-in-class preamps. Get studio-quality recordings at home.', 159.99, 18, NULL),
(7, 'Sony MDR-7506 Studio Headphones',    'The industry-standard studio headphone used by professionals worldwide. 40mm neodymium drivers deliver clear, detailed sound reproduction.', 99.99, 25, NULL);

-- ----------------------------
-- Default Admin Account
-- Password: admin123
-- ----------------------------
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Admin User', 'admin@melodymasters.com',
 '$2y$10$cvYCNO/ovoEMuM857Z6Dd.hGhcTyCV9hSV2XSBI1U1.Yp6vUseSNbG',
 'admin');

-- NOTE: The above hash is for "admin123". Change this after first login.
-- To regenerate: echo password_hash('admin123', PASSWORD_DEFAULT);
