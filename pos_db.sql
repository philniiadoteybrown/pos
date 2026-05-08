-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 04:53 PM
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
-- Database: `pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `page` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `catid` int(11) NOT NULL,
  `catname` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`catid`, `catname`) VALUES
(1, 'Beverages'),
(2, 'Cereals'),
(3, 'Household chemicals'),
(4, 'Personal Care'),
(5, 'Shoe Care'),
(6, 'Toiletries'),
(8, 'Food'),
(9, 'Food');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_units`
--

CREATE TABLE `item_units` (
  `id` int(11) NOT NULL,
  `unitname` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_units`
--

INSERT INTO `item_units` (`id`, `unitname`) VALUES
(1, 'Box'),
(2, 'Rolls'),
(3, 'Pieces'),
(4, 'Dozen'),
(5, 'Pack'),
(6, 'Sack'),
(7, 'Piece'),
(8, 'Pair'),
(9, 'Dozen'),
(10, 'Pack'),
(11, 'Box'),
(12, 'Carton'),
(13, 'Bag'),
(14, 'Crate'),
(15, 'Bottle'),
(16, 'Can'),
(17, 'Sachet'),
(18, 'Roll'),
(19, 'Bundle'),
(20, 'Set'),
(21, 'Tray'),
(22, 'Tin'),
(23, 'Kg'),
(24, 'Gram'),
(25, 'Litre'),
(26, 'ML'),
(27, 'Gallon'),
(28, '2 pcs'),
(29, '3 pcs'),
(30, '4 pcs'),
(31, '6 pcs'),
(32, '12 pcs'),
(33, '24 pcs'),
(34, '15 pcs');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `productid` varchar(20) NOT NULL,
  `pname` varchar(255) DEFAULT NULL,
  `pdesc` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `unitprice` decimal(10,2) DEFAULT NULL,
  `sellingprice` decimal(10,2) DEFAULT NULL,
  `qtyalert` decimal(10,2) DEFAULT NULL,
  `category` varchar(25) NOT NULL,
  `qtyperunit` int(11) NOT NULL,
  `costperunit` decimal(10,2) NOT NULL,
  `totalstock` decimal(10,2) NOT NULL,
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`productid`, `pname`, `pdesc`, `unit`, `qty`, `unitprice`, `sellingprice`, `qtyalert`, `category`, `qtyperunit`, `costperunit`, `totalstock`, `created_at`) VALUES
('PRD0002', 'Cowbell Strawberry', '35g', 'Rolls', 7.00, 39.00, 5.00, 10.00, 'Beverages', 10, 3.90, 71.00, '2026-05-03'),
('PRD0003', 'Cerelac Maize Sachet', '50g', 'Rolls', 1.00, 54.00, 6.00, 10.00, 'Cereals', 10, 5.40, 76.00, '2026-05-03'),
('PRD0004', 'Nescafe 3 in 1', '30g', 'Rolls', 1.00, 36.00, 5.00, 10.00, 'Beverages', 10, 3.60, 0.00, '2026-05-03'),
('PRD0005', 'Cowbell Café', '35g', 'Rolls', 2.00, 39.00, 5.00, 10.00, 'Beverages', 10, 3.90, 0.00, '2026-05-03'),
('PRD0006', 'Good Start Oats', '500g', 'Pieces', 4.00, 15.00, 20.00, 2.00, 'Cereals', 1, 15.00, 0.00, '2026-05-03'),
('PRD0007', 'Fatala Inserticide Spray', '400ml', 'Pieces', 3.00, 172.50, 39.00, 3.00, 'Household chemicals', 6, 28.75, 0.00, '2026-05-03'),
('PRD0008', 'Heaven Insecticide', '400ml', 'Pieces', 6.00, 187.40, 40.00, 3.00, 'Household chemicals', 6, 31.23, 0.00, '2026-05-03'),
('PRD0009', 'Mikki Brown', '75ml', 'Dozen', 0.50, 45.00, 10.00, 3.00, 'Shoe Care', 6, 7.50, 0.00, '2026-05-03'),
('PRD0010', 'Lude Shoe Polish', '40ml', 'Dozen', 1.00, 65.00, 7.00, 3.00, 'Shoe Care', 12, 5.42, 0.00, '2026-05-03'),
('PRD0011', 'Pepsodent', '175g', 'Pack', 1.00, 100.00, 20.00, 3.00, 'Personal Care', 6, 16.67, 0.00, '2026-05-03'),
('PRD0012', 'Pepsodent', '65g', 'Pack', 1.00, 45.00, 10.00, 3.00, 'Personal Care', 6, 7.50, 0.00, '2026-05-03'),
('PRD0013', 'Pepsodent Strawberry', '45g', 'Pack', 1.00, 45.00, 10.00, 3.00, 'Personal Care', 6, 7.50, 0.00, '2026-05-03'),
('PRD0014', 'Pepsodent Charcoal', '130g', 'Pack', 1.00, 92.00, 22.00, 3.00, 'Personal Care', 6, 15.33, 0.00, '2026-05-03'),
('PRD0015', 'CloseUp', '140g', 'Pack', 1.00, 105.00, 17.00, 3.00, 'Personal Care', 6, 17.50, 0.00, '2026-05-03'),
('PRD0016', 'KelKids', '75g', 'Pack', 1.00, 60.00, 12.00, 3.00, 'Personal Care', 6, 10.00, 0.00, '2026-05-03'),
('PRD0017', 'Yazz Toothbrush', '', 'Dozen', 1.00, 35.00, 4.00, 3.00, 'Personal Care', 12, 2.92, 0.00, '2026-05-03'),
('PRD0018', 'Yazz Kids', '', 'Dozen', 1.00, 30.00, 3.00, 3.00, 'Personal Care', 12, 2.50, 0.00, '2026-05-03'),
('PRD0019', 'Pepsodent Brush', '', 'Dozen', 1.00, 32.00, 3.00, 3.00, 'Personal Care', 12, 2.67, 0.00, '2026-05-03'),
('PRD0020', 'Softcare Diaper pack', 'medium 12', 'Sack', 0.50, 230.00, 2.50, 3.00, 'Personal Care', 10, 23.00, 0.00, '2026-05-03'),
('PRD0021', 'Softcare Diaper pieces', 'medium 12', 'Pack', 10.00, 23.00, 2.50, 3.00, 'Personal Care', 12, 1.92, 0.00, '2026-05-03'),
('PRD0022', 'Cutie Diaper', 'medium 12', 'Pack', 1.00, 20.00, 2.00, 3.00, 'Personal Care', 12, 1.67, 0.00, '2026-05-03'),
('PRD0023', 'Yazz sanitary Pad', '', 'Pack', 1.00, 78.00, 15.00, 3.00, 'Personal Care', 6, 13.00, 0.00, '2026-05-03'),
('PRD0024', 'Softcare Sanitary Pad', '', 'Pack', 1.00, 70.00, 13.00, 3.00, 'Personal Care', 6, 11.67, 0.00, '2026-05-03'),
('PRD0025', 'Softcare zip pad', '', 'Pack', 1.00, 80.00, 16.00, 3.00, 'Personal Care', 6, 13.33, 0.00, '2026-05-03'),
('PRD0026', 'Milo', '400g', 'Pack', 0.25, 441.00, 42.00, 3.00, 'Beverages', 12, 36.75, 0.00, '2026-05-03'),
('PRD0027', 'Milo', '20g', 'Rolls', 1.00, 20.00, 2.50, 3.00, 'Beverages', 10, 2.00, 0.00, '2026-05-03'),
('PRD0028', 'Milo', '37g', 'Rolls', 1.00, 32.00, 5.00, 3.00, 'Beverages', 10, 3.20, 0.00, '2026-05-03'),
('PRD0029', 'Santex', '180g', 'Pack', 1.00, 55.00, 10.00, 3.00, 'Toiletries', 6, 9.17, 0.00, '2026-05-03'),
('PRD0030', 'MediSoft soap', '90g', 'Pack', 1.00, 35.00, 7.00, 3.00, 'Toiletries', 6, 5.83, 0.00, '2026-05-03'),
('PRD0031', 'Imperial Leather', '200g', 'Pack', 1.00, 73.00, 15.00, 3.00, 'Toiletries', 6, 12.17, 0.00, '2026-05-03'),
('PRD0032', 'Madar Soap', 'Bathing soap', 'Box', 1.00, 140.00, 5.00, 10.00, 'Toiletries', 48, 2.92, 0.00, '2026-05-03'),
('PRD0033', 'Mabel', 'Bathing soap', 'Box', 1.00, 95.00, 5.00, 3.00, 'Toiletries', 36, 2.64, 0.00, '2026-05-03'),
('PRD0034', 'Juliet', '225g', 'Box', 0.50, 260.00, 8.00, 10.00, 'Toiletries', 6, 43.33, 0.00, '2026-05-03'),
('PRD0035', 'Milo Tin', '400g', 'Piece(s)', 3.00, 48.00, 53.00, 3.00, 'Beverages', 6, 8.00, 0.00, '2026-05-03'),
('PRD0036', 'Eggs', 'medium size', 'Crate', 6.00, 50.00, 2.50, 60.00, 'Food', 30, 1.67, 0.00, '2026-05-03');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `productid` varchar(50) DEFAULT NULL,
  `pname` varchar(255) DEFAULT NULL,
  `pdesc` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT 0.00,
  `unitprice` decimal(10,2) DEFAULT 0.00,
  `sellingprice` decimal(10,2) DEFAULT 0.00,
  `qtyalert` int(11) DEFAULT 0,
  `type` enum('initial','restock') DEFAULT 'initial',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `totalpurchase` decimal(10,2) NOT NULL,
  `totalqty` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `productid`, `pname`, `pdesc`, `unit`, `qty`, `unitprice`, `sellingprice`, `qtyalert`, `type`, `created_at`, `totalpurchase`, `totalqty`) VALUES
(1, 'PRD0001', 'Bon Tea', '180g', 'Box', 1.00, 30.00, 0.50, 20, 'initial', '0000-00-00 00:00:00', 30.00, 100.00),
(2, 'PRD0002', 'Cowbell Strawberry', '35g', 'Rolls', 1.00, 39.00, 5.00, 10, 'initial', '0000-00-00 00:00:00', 39.00, 10.00),
(3, 'PRD0003', 'Cerelac Maize Sachet', '50g', 'Rolls', 1.00, 54.00, 6.00, 10, 'initial', '0000-00-00 00:00:00', 54.00, 10.00),
(4, 'PRD0004', 'Nescafe 3 in 1', '30g', 'Rolls', 1.00, 36.00, 5.00, 10, 'initial', '0000-00-00 00:00:00', 36.00, 10.00),
(5, 'PRD0005', 'Cowbell Cafe', '35g', 'Rolls', 2.00, 39.00, 5.00, 10, 'initial', '0000-00-00 00:00:00', 78.00, 20.00),
(6, 'PRD0006', 'Good Start Oats', '500g', 'Pieces', 4.00, 15.00, 20.00, 2, 'initial', '0000-00-00 00:00:00', 60.00, 4.00),
(7, 'PRD0007', 'Fatala Inserticide Spray', '400ml', 'Pieces', 3.00, 172.50, 39.00, 3, 'initial', '0000-00-00 00:00:00', 517.50, 18.00),
(8, 'PRD0008', 'Heaven Insecticide', '400ml', 'Pieces', 6.00, 187.40, 40.00, 3, 'initial', '0000-00-00 00:00:00', 1124.40, 36.00),
(9, 'PRD0009', 'Mikki Brown ', '75ml', 'Dozen', 0.50, 45.00, 10.00, 3, 'initial', '0000-00-00 00:00:00', 22.50, 3.00),
(10, 'PRD0010', 'Lude Shoe Polish', '40ml', 'Dozen', 1.00, 65.00, 7.00, 3, 'initial', '0000-00-00 00:00:00', 65.00, 12.00),
(11, 'PRD0011', 'Pepsodent', '175g', 'Pack', 1.00, 100.00, 20.00, 3, 'initial', '0000-00-00 00:00:00', 100.00, 6.00),
(12, 'PRD0012', 'Pepsodent', '65g', 'Pack', 1.00, 45.00, 10.00, 3, 'initial', '0000-00-00 00:00:00', 45.00, 6.00),
(13, 'PRD0013', 'Pepsodent Strawberry', '45g', 'Pack', 1.00, 45.00, 10.00, 3, 'initial', '0000-00-00 00:00:00', 45.00, 6.00),
(14, 'PRD0014', 'Pepsodent Charcoal', '130g', 'Pack', 1.00, 92.00, 22.00, 3, 'initial', '0000-00-00 00:00:00', 92.00, 6.00),
(15, 'PRD0015', 'CloseUp', '140g', 'Pack', 1.00, 105.00, 17.00, 3, 'initial', '0000-00-00 00:00:00', 105.00, 6.00),
(16, 'PRD0016', 'KelKids', '75g', 'Pack', 1.00, 60.00, 12.00, 3, 'initial', '0000-00-00 00:00:00', 60.00, 6.00),
(17, 'PRD0017', 'Yazz Toothbrush', '', 'Dozen', 1.00, 35.00, 4.00, 3, 'initial', '0000-00-00 00:00:00', 35.00, 12.00),
(18, 'PRD0018', 'Yazz Kids', '', 'Dozen', 1.00, 30.00, 3.00, 3, 'initial', '0000-00-00 00:00:00', 30.00, 12.00),
(19, 'PRD0019', 'Pepsodent Brush', '', 'Dozen', 1.00, 32.00, 3.00, 3, 'initial', '0000-00-00 00:00:00', 32.00, 12.00),
(20, 'PRD0020', 'Softcare Diaper pack', 'medium 12', 'Sack', 0.50, 230.00, 2.50, 3, 'initial', '0000-00-00 00:00:00', 115.00, 5.00),
(21, 'PRD0021', 'Softcare Diaper pieces', 'medium 12', 'Pack', 10.00, 23.00, 2.50, 3, 'initial', '0000-00-00 00:00:00', 230.00, 120.00),
(22, 'PRD0022', 'Cutie Diaper', 'medium 12', 'Pack', 1.00, 20.00, 2.00, 3, 'initial', '0000-00-00 00:00:00', 20.00, 12.00),
(23, 'PRD0023', 'Yazz sanitary Pad', '', 'Pack', 1.00, 78.00, 15.00, 3, 'initial', '0000-00-00 00:00:00', 78.00, 6.00),
(24, 'PRD0024', 'Softcare Sanitary Pad', '', 'Pack', 1.00, 70.00, 13.00, 3, 'initial', '0000-00-00 00:00:00', 70.00, 6.00),
(25, 'PRD0025', 'Softcare zip pad', '', 'Pack', 1.00, 80.00, 16.00, 3, 'initial', '0000-00-00 00:00:00', 80.00, 6.00),
(26, 'PRD0026', 'Milo', '400g', 'Pack', 0.25, 441.00, 42.00, 1, 'initial', '0000-00-00 00:00:00', 110.25, 3.00),
(27, 'PRD0027', 'Milo', '20g', 'Rolls', 1.00, 20.00, 2.50, 3, 'initial', '0000-00-00 00:00:00', 20.00, 10.00),
(28, 'PRD0028', 'Milo', '37g', 'Rolls', 1.00, 32.00, 5.00, 3, 'initial', '0000-00-00 00:00:00', 32.00, 10.00),
(29, 'PRD0029', 'Santex', '180g', 'Pack', 1.00, 55.00, 10.00, 3, 'initial', '0000-00-00 00:00:00', 55.00, 6.00),
(30, 'PRD0030', 'MediSoft soap', '90g', 'Pack', 1.00, 35.00, 7.00, 3, 'initial', '0000-00-00 00:00:00', 35.00, 6.00),
(31, 'PRD0031', 'Imperial Leather', '200g', 'Pack', 1.00, 73.00, 15.00, 3, 'initial', '0000-00-00 00:00:00', 73.00, 6.00),
(32, 'PRD0032', 'Madar Soap', '', 'Box', 1.00, 140.00, 5.00, 10, 'initial', '0000-00-00 00:00:00', 140.00, 48.00),
(33, 'PRD0033', 'Mabel', '', 'Box', 1.00, 95.00, 5.00, 3, 'initial', '0000-00-00 00:00:00', 95.00, 36.00),
(34, 'PRD0034', 'Juliet', '225g', 'Box', 0.50, 260.00, 8.00, 10, 'initial', '0000-00-00 00:00:00', 130.00, 3.00),
(35, 'PRD0035', 'Milo Tin', '400g', 'Piece(s)', 3.00, 48.00, 53.00, 3, 'initial', '0000-00-00 00:00:00', 144.00, 18.00),
(36, 'PRD0036', 'Eggs', 'medium size', 'Crate', 10.00, 50.00, 2.50, 60, 'initial', '2026-05-03 20:32:37', 500.00, 300.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `paid` decimal(10,2) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT NULL,
  `product_codes` text DEFAULT NULL,
  `product_names` text DEFAULT NULL,
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `payment_method`, `customer_id`, `total`, `paid`, `balance`, `product_codes`, `product_names`, `created_at`) VALUES
(2, 'cash', 0, 180.00, 200.00, 20.00, 'PRD0036', 'Eggs', '2026-05-03'),
(3, 'cash', 0, 10.00, 10.00, 0.00, 'PRD0036', 'Eggs', '2026-05-03'),
(4, 'cash', 0, 20.00, 20.00, 0.00, 'PRD0036', 'Eggs', '2026-05-03'),
(5, 'cash', 0, 195.00, 200.00, 5.00, 'PRD0002', 'Cowbell Strawberry', '2026-05-08');

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` varchar(20) DEFAULT NULL,
  `pname` varchar(255) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sale_type` varchar(20) DEFAULT NULL,
  `unit_qty` decimal(10,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_items`
--

INSERT INTO `sales_items` (`id`, `sale_id`, `product_id`, `pname`, `qty`, `price`, `subtotal`, `created_at`, `sale_type`, `unit_qty`) VALUES
(1, 2, 'PRD0036', 'Eggs', 3.00, 60.00, 180.00, '2026-05-03 20:54:52', NULL, 30.00),
(2, 3, 'PRD0036', 'Eggs', 1.00, 10.00, 10.00, '2026-05-03 20:57:25', NULL, 5.00),
(3, 4, 'PRD0036', 'Eggs', 2.00, 20.00, 20.00, '2026-05-03 22:20:52', NULL, 5.00),
(4, 5, 'PRD0002', 'Cowbell Strawberry', 5.00, 195.00, 195.00, '2026-05-08 14:15:00', NULL, 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `product_id` varchar(25) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `system_qty` decimal(10,2) NOT NULL,
  `physical_qty` decimal(10,2) NOT NULL,
  `difference` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `product_id`, `product_name`, `system_qty`, `physical_qty`, `difference`, `reason`, `created_at`) VALUES
(1, 'PRD0003', 'Cerelac Maize Sachet', 70.00, 76.00, 6.00, 'Smart Closing', '2026-05-08 12:23:57'),
(2, 'PRD0002', 'Cowbell Strawberry', 72.00, 76.00, 4.00, 'Smart Closing', '2026-05-08 12:23:57');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `product_id` varchar(25) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `unit_qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `product_id`, `unit_name`, `unit_qty`, `price`) VALUES
(2, 'PRD0002', 'Rolls', 1.00, 39.00),
(3, 'PRD0003', 'Rolls', 1.00, 54.00),
(4, 'PRD0004', 'Rolls', 1.00, 36.00),
(5, 'PRD0005', 'Rolls', 2.00, 39.00),
(6, 'PRD0006', 'Pieces', 4.00, 15.00),
(7, 'PRD0007', 'Pieces', 3.00, 172.50),
(8, 'PRD0008', 'Pieces', 6.00, 187.40),
(9, 'PRD0009', 'Dozen', 0.50, 45.00),
(10, 'PRD0010', 'Dozen', 1.00, 65.00),
(11, 'PRD0011', 'Pack', 1.00, 100.00),
(12, 'PRD0012', 'Pack', 1.00, 45.00),
(13, 'PRD0013', 'Pack', 1.00, 45.00),
(14, 'PRD0014', 'Pack', 1.00, 92.00),
(15, 'PRD0015', 'Pack', 1.00, 105.00),
(16, 'PRD0016', 'Pack', 1.00, 60.00),
(17, 'PRD0017', 'Dozen', 1.00, 35.00),
(18, 'PRD0018', 'Dozen', 1.00, 30.00),
(19, 'PRD0019', 'Dozen', 1.00, 32.00),
(20, 'PRD0020', 'Sack', 0.50, 230.00),
(21, 'PRD0021', 'Pack', 10.00, 23.00),
(22, 'PRD0022', 'Pack', 1.00, 20.00),
(23, 'PRD0023', 'Pack', 1.00, 78.00),
(24, 'PRD0024', 'Pack', 1.00, 70.00),
(25, 'PRD0025', 'Pack', 1.00, 80.00),
(26, 'PRD0026', 'Pack', 0.25, 441.00),
(27, 'PRD0027', 'Rolls', 1.00, 20.00),
(28, 'PRD0028', 'Rolls', 1.00, 32.00),
(29, 'PRD0029', 'Pack', 1.00, 55.00),
(30, 'PRD0030', 'Pack', 1.00, 35.00),
(31, 'PRD0031', 'Pack', 1.00, 73.00),
(32, 'PRD0032', 'Box', 1.00, 140.00),
(33, 'PRD0033', 'Box', 1.00, 95.00),
(34, 'PRD0034', 'Box', 0.50, 260.00),
(35, 'PRD0035', 'Box', 3.00, 48.00),
(37, 'PRD0001', 'Pack', 100.00, 40.00),
(38, 'PRD0005', 'Roll', 10.00, 50.00),
(41, 'PRD0036', '1 Piece', 1.00, 2.50),
(42, 'PRD0036', '5 Pieces', 5.00, 10.00),
(43, 'PRD0036', 'Crate', 30.00, 60.00),
(44, 'PRD0036', 'Half Crate', 15.00, 30.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','cashier') DEFAULT NULL,
  `name` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'admin', 'Nii'),
(2, 'cashier', 'dbb8c54ee649f8af049357a5f99cede6', 'cashier', 'Naa Aku');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`catid`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `item_units`
--
ALTER TABLE `item_units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`productid`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `catid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_units`
--
ALTER TABLE `item_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
