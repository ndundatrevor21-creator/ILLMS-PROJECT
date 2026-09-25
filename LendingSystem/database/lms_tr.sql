-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 02:46 PM
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
-- Database: `lms_tr`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `profile` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `profile`, `created_at`) VALUES
(3, NULL, '2026-08-28 12:32:51'),
(5, NULL, '2026-08-28 12:33:52'),
(6, NULL, '2026-08-28 12:57:54'),
(7, NULL, '2026-08-28 14:16:16'),
(8, NULL, '2026-09-04 09:02:56'),
(9, NULL, '2026-09-04 09:51:53'),
(10, NULL, '2026-09-05 12:51:23'),
(11, NULL, '2026-09-08 14:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `lenders`
--

CREATE TABLE `lenders` (
  `id` int(11) NOT NULL,
  `organization` varchar(160) DEFAULT NULL,
  `profile` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lenders`
--

INSERT INTO `lenders` (`id`, `organization`, `profile`, `created_at`) VALUES
(2, NULL, NULL, '2026-08-28 12:33:52'),
(4, NULL, NULL, '2026-08-28 12:32:51');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `lender_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `term_months` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `application_id` int(11) NOT NULL DEFAULT 0,
  `offer_id` int(11) NOT NULL DEFAULT 0,
  `disbursed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `customer_id`, `lender_id`, `amount`, `interest_rate`, `term_months`, `status`, `created_at`, `application_id`, `offer_id`, `disbursed_at`, `closed_at`) VALUES
(1, 3, 4, 3000.00, 8.00, 6, 'active', '2026-08-28 09:32:51', 2, 1, '2026-08-28 12:32:51', NULL),
(2, 5, 2, 5000.00, 14.00, 12, 'paid', '2026-08-28 09:33:52', 5, 2, '2026-08-28 12:33:52', NULL),
(3, 6, 2, 2000.00, 14.00, 6, 'paid', '2026-08-28 09:57:54', 6, 3, '2026-08-28 12:57:54', NULL),
(4, 7, 4, 10000.00, 14.00, 6, 'paid', '2026-08-28 11:16:16', 7, 4, '2026-08-28 14:16:16', NULL),
(5, 8, 4, 10000.00, 8.00, 6, 'paid', '2026-09-04 06:02:56', 8, 5, '2026-09-04 09:02:56', NULL),
(6, 8, 4, 10000.00, 8.00, 6, 'paid', '2026-09-04 06:04:22', 8, 6, '2026-09-04 09:04:22', NULL),
(7, 5, 2, 10000.00, 14.00, 6, 'active', '2026-09-04 06:18:50', 10, 7, '2026-09-04 09:18:50', NULL),
(8, 9, 4, 15000.00, 8.00, 6, 'active', '2026-09-04 06:51:53', 11, 8, '2026-09-04 09:51:53', NULL),
(9, 10, 2, 5000.00, 8.00, 3, 'active', '2026-09-05 09:51:23', 13, 9, '2026-09-05 12:51:23', NULL),
(10, 11, 2, 3000.00, 8.00, 3, 'active', '2026-09-08 11:05:55', 14, 10, '2026-09-08 14:05:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `term_months` int(11) NOT NULL,
  `status` enum('pending','needs_information','approved','rejected','active','paid','defaulted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_applications`
--

INSERT INTO `loan_applications` (`id`, `customer_id`, `amount`, `purpose`, `term_months`, `status`, `created_at`) VALUES
(1, 3, 5000.00, 'Business', 6, 'rejected', '2026-08-28 08:35:11'),
(2, 3, 3000.00, 'Medical Expenses', 6, 'approved', '2026-08-28 08:56:46'),
(3, 5, 10000.00, 'Medical Expenses', 12, 'needs_information', '2026-08-28 09:23:00'),
(4, 5, 10000.00, 'Medical Expenses', 12, 'rejected', '2026-08-28 09:26:46'),
(5, 5, 5000.00, 'Medical Expenses', 12, 'approved', '2026-08-28 09:28:09'),
(6, 6, 2000.00, 'Medical Expenses', 6, 'rejected', '2026-08-28 09:55:52'),
(7, 7, 10000.00, 'Education', 6, 'approved', '2026-08-28 11:13:43'),
(8, 8, 10000.00, 'Other', 6, 'approved', '2026-09-04 06:00:20'),
(9, 6, 4000.00, 'Other', 6, 'pending', '2026-09-04 06:13:33'),
(10, 5, 10000.00, 'Education', 6, 'approved', '2026-09-04 06:18:37'),
(11, 9, 15000.00, 'Medical Expenses', 6, 'approved', '2026-09-04 06:46:46'),
(12, 3, 10000.00, 'Other', 4, 'rejected', '2026-09-05 07:07:34'),
(13, 10, 5000.00, 'Other', 3, 'approved', '2026-09-05 09:49:13'),
(14, 11, 3000.00, 'Medical Expenses', 3, 'approved', '2026-09-08 11:04:28'),
(15, 8, 5000.00, 'Medical Expenses', 3, 'pending', '2026-09-12 08:46:39');

-- --------------------------------------------------------

--
-- Table structure for table `loan_messages`
--

CREATE TABLE `loan_messages` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_messages`
--

INSERT INTO `loan_messages` (`id`, `loan_application_id`, `sender_id`, `message`, `created_at`) VALUES
(1, 3, 2, 'Provide more information', '2026-08-28 09:24:06');

-- --------------------------------------------------------

--
-- Table structure for table `loan_offers`
--

CREATE TABLE `loan_offers` (
  `id` int(11) NOT NULL,
  `lender_id` int(11) NOT NULL,
  `title` varchar(160) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `term_months` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_offers`
--

INSERT INTO `loan_offers` (`id`, `lender_id`, `title`, `amount`, `interest_rate`, `term_months`, `description`, `status`, `created_at`) VALUES
(1, 4, 'Auto-funded loan offer', 3000.00, 8.00, 6, 'Offer created automatically during funding', 'active', '2026-08-28 12:32:51'),
(2, 2, 'Auto-funded loan offer', 5000.00, 14.00, 12, 'Offer created automatically during funding', 'active', '2026-08-28 12:33:52'),
(3, 2, 'Auto-funded loan offer', 2000.00, 14.00, 6, 'Offer created automatically during funding', 'active', '2026-08-28 12:57:54'),
(4, 4, 'Auto-funded loan offer', 10000.00, 14.00, 6, 'Offer created automatically during funding', 'active', '2026-08-28 14:16:16'),
(5, 4, 'Auto-funded loan offer', 10000.00, 8.00, 6, 'Offer created automatically during funding', 'active', '2026-09-04 09:02:56'),
(6, 4, 'Auto-funded loan offer', 10000.00, 8.00, 6, 'Offer created automatically during funding', 'active', '2026-09-04 09:04:22'),
(7, 2, 'Auto-funded loan offer', 10000.00, 14.00, 6, 'Offer created automatically during funding', 'active', '2026-09-04 09:18:50'),
(8, 4, 'Auto-funded loan offer', 15000.00, 8.00, 6, 'Offer created automatically during funding', 'active', '2026-09-04 09:51:53'),
(9, 2, 'Auto-funded loan offer', 5000.00, 8.00, 3, 'Offer created automatically during funding', 'active', '2026-09-05 12:51:23'),
(10, 2, 'Auto-funded loan offer', 3000.00, 8.00, 3, 'Offer created automatically during funding', 'active', '2026-09-08 14:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `method` varchar(60) DEFAULT 'manual',
  `status` enum('posted') NOT NULL DEFAULT 'posted',
  `paid_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `loan_id`, `customer_id`, `amount`, `payment_date`, `method`, `status`, `paid_at`) VALUES
(1, 1, 3, 511.73, '2026-08-28 09:47:35', 'manual', 'posted', '2026-08-28 12:47:35'),
(2, 4, 7, 10700.00, '2026-08-28 11:44:36', 'paypal', 'posted', '2026-08-28 14:44:36'),
(3, 1, 3, 500.00, '2026-09-03 14:00:43', 'paypal', 'posted', '2026-09-03 17:00:43'),
(4, 3, 6, 2140.00, '2026-09-04 06:12:18', 'paypal', 'posted', '2026-09-04 09:12:18'),
(5, 2, 5, 5700.00, '2026-09-04 06:17:17', 'paypal', 'posted', '2026-09-04 09:17:17'),
(6, 6, 8, 10400.00, '2026-09-04 06:36:40', 'paypal', 'posted', '2026-09-04 09:36:40'),
(7, 5, 8, 10400.00, '2026-09-04 06:36:59', 'manual', 'posted', '2026-09-04 09:36:59');

-- --------------------------------------------------------

--
-- Table structure for table `system_activity_logs`
--

CREATE TABLE `system_activity_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(160) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_activity_logs`
--

INSERT INTO `system_activity_logs` (`id`, `actor_id`, `actor_name`, `actor_role`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'Trevor', 'admin', 'reset_password', 'user', 7, 'Reset password for user ID 7', '2026-09-03 15:24:33'),
(2, 3, 'James', 'customer', 'loan_payment', 'loan', 1, 'Made payment of Kshs 500.00 for loan #1', '2026-09-03 17:00:43'),
(3, 4, 'Enko Loans', 'lender', 'fund_loan', 'loan', 8, 'Funded loan application #8 for Kshs 10,000.00', '2026-09-04 09:02:56'),
(4, 4, 'Enko Loans', 'lender', 'fund_loan', 'loan', 8, 'Funded loan application #8 for Kshs 10,000.00', '2026-09-04 09:04:22'),
(5, 6, 'Joshua', 'customer', 'loan_payment', 'loan', 3, 'Made payment of Kshs 2,140.00 for loan #3', '2026-09-04 09:12:18'),
(6, 5, 'Paul', 'customer', 'loan_payment', 'loan', 2, 'Made payment of Kshs 5,700.00 for loan #2', '2026-09-04 09:17:17'),
(7, 2, 'Kenya Lenders', 'lender', 'fund_loan', 'loan', 10, 'Funded loan application #10 for Kshs 10,000.00', '2026-09-04 09:18:50'),
(8, 8, 'Miriam', 'customer', 'loan_payment', 'loan', 6, 'Made payment of Kshs 10,400.00 for loan #6', '2026-09-04 09:36:40'),
(9, 8, 'Miriam', 'customer', 'loan_payment', 'loan', 5, 'Made payment of Kshs 10,400.00 for loan #5', '2026-09-04 09:36:59'),
(10, 4, 'Enko Loans', 'lender', 'fund_loan', 'loan', 11, 'Funded loan application #11 for Kshs 15,000.00', '2026-09-04 09:51:53'),
(11, 2, 'Kenya Lenders', 'lender', 'fund_loan', 'loan', 13, 'Funded loan application #13 for Kshs 5,000.00', '2026-09-05 12:51:23'),
(12, 2, 'Kenya Lenders', 'lender', 'fund_loan', 'loan', 14, 'Funded loan application #14 for Kshs 3,000.00', '2026-09-08 14:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(120) NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `first_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','lender','admin') NOT NULL DEFAULT 'customer',
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `phone` varchar(50) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `employment_status` varchar(120) DEFAULT NULL,
  `annual_income` decimal(12,2) DEFAULT NULL,
  `company_name` varchar(160) DEFAULT NULL,
  `lending_capacity` decimal(12,2) DEFAULT NULL,
  `preferred_loan_types` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `account_age_months` int(11) DEFAULT NULL,
  `utility_payment_history` varchar(60) DEFAULT NULL,
  `digital_activity` varchar(60) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `county` varchar(120) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `postal_code` varchar(40) DEFAULT NULL,
  `id_card_type` varchar(80) DEFAULT NULL,
  `id_card_number` varchar(80) DEFAULT NULL,
  `id_issuing_authority` varchar(160) DEFAULT NULL,
  `id_issue_date` date DEFAULT NULL,
  `id_expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `status`, `phone`, `city`, `employment_status`, `annual_income`, `company_name`, `lending_capacity`, `preferred_loan_types`, `created_at`, `account_age_months`, `utility_payment_history`, `digital_activity`, `street_address`, `address_line_2`, `county`, `country`, `postal_code`, `id_card_type`, `id_card_number`, `id_issuing_authority`, `id_issue_date`, `id_expiry_date`) VALUES
(1, 'Trevor', NULL, NULL, NULL, 'trevor@example.com', '$2y$12$JJo8LG7T8IYEiWRNoO/gr.5wUx4GSwlU3Vt/scTjy5gLQ2dK/hZwe', 'admin', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-28 11:25:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Kenya Lenders', NULL, 'Kenya ', 'Lenders', 'lenders@gmail.com', '$2y$10$mL1ZYuF.Vxbk4AUWJTfXoOD3/CJaxN7IrArPVSjjUEWSyV0Xm6uii', 'lender', 'active', '0706548956', NULL, NULL, NULL, 'Kenya Lenders', 10000000.00, 'Personal,Business,Education', '2026-08-28 11:28:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'James', NULL, 'James', 'Musila', 'jamesmusila@gmail.com', '$2y$10$S4w4ACqcZT0LxD0qlLvT.OseFoCnSu153SWVh7FOusUca3Nj0clu2', 'customer', 'active', '0723658452', 'Nairobi', 'employed', 70000.00, NULL, NULL, NULL, '2026-08-28 11:31:12', 0, 'unknown', 'unknown', 'Makoyeti west', '17698', 'Nairobi', 'Kenya', '', 'Passport', '3467745676', '', NULL, NULL),
(4, 'Enko Loans', NULL, 'Enko', 'Loans', 'enkoloans@gmail.com', '$2y$10$7c.9A6bFQIpKxZWZ0ZVmHunPEzJq7TyKbWgOzCV5W6lmmq9vOeFIK', 'lender', 'active', '0706548957', NULL, NULL, NULL, 'Enko Lenders', 20000000.00, 'Personal,Business,Education', '2026-08-28 11:37:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Paul', NULL, 'Paul', 'Kenzi', 'Paulkenzi@gmail.com', '$2y$10$21JoeTjwJAfgkrpxrAkrQ.zuEOLS3ie/sOXMzLAqQr/0U0hEJVt5O', 'customer', 'active', '0798562345', 'Nakuru', 'self-employed', 40000.00, NULL, NULL, NULL, '2026-08-28 12:22:34', NULL, NULL, NULL, 'Forest Rd', '25522', 'Nakuru', 'Kenya', NULL, 'National ID', '45525555', NULL, NULL, NULL),
(6, 'Joshua', NULL, 'Joshua', 'Gikonyo', 'Joshuajohn@gmail.com', '$2y$10$L1ZozPIwFoe68YcgYg.7NuF4RxfRurjWseyeL/0DuSEa3fUA6zZtu', 'customer', 'active', '0759234567', 'Kakamega', 'student', 8000.00, NULL, NULL, NULL, '2026-08-28 12:52:54', NULL, NULL, NULL, 'Mwalimu Street', '45762', 'Kakamega', 'Kenya', NULL, 'National ID', '565634423', NULL, NULL, NULL),
(7, 'Monica', NULL, 'Monica', 'Wanjiru', 'monicawanjiru@gmail.com', '$2y$10$i4C.uIo6s9Z8GBidzrARser/8CoQomyBWOdxdJowpBOZ71HWd/0zC', 'customer', 'active', '0723658452', 'Nairobi', 'employed', 50000.00, NULL, NULL, NULL, '2026-08-28 14:11:28', NULL, NULL, NULL, 'Don bosco', '7865', 'Nairobi', 'Kenya', NULL, 'National ID', '5569872', NULL, NULL, NULL),
(8, 'Miriam', NULL, 'Miriam', 'Wakesho', 'miriamwakesho@gmail.com', '$2y$10$9E0cY6Me1uwuSlTUbGPuWeT1CPWnI1irivqAGB1RXoU6r9RnXARQC', 'customer', 'active', '0707862354', 'Ongata Rongai', 'employed', 300000.00, NULL, NULL, NULL, '2026-09-04 08:50:14', NULL, NULL, NULL, 'Fifth Avenue', '3465', 'Kajiado', 'Kenya', NULL, 'Passport', '49342444', NULL, NULL, NULL),
(9, 'Allan', NULL, 'Allan', 'Wambua', 'allanwambua@gmail.com', '$2y$10$1D.iSytB6NKrsbAcfhOVsuDh7jcPkOecs2NeMuA7qus4C3UumXJXa', 'customer', 'active', '01145678645', 'Machakos', 'employed', 500000.00, NULL, NULL, NULL, '2026-09-04 09:43:04', NULL, NULL, NULL, 'Sweet waters', '902-00123', 'Machakos', 'Kenya', NULL, '', '347897864', NULL, NULL, NULL),
(10, 'Jacob ', NULL, 'Jacob', 'Awuor', 'jacobawuor@gmail.com', '$2y$10$/pZVZERWC4c5qdFRX7CbWOyTG4/2WIIKFcESS8EtjQOkG5GVA7ysG', 'customer', 'active', '0729193456', 'Kisumu', 'employed', 700000.00, NULL, NULL, NULL, '2026-09-05 10:06:44', NULL, NULL, NULL, 'Dunga Beach', '3223', 'Kisumu', 'Kenya', NULL, 'National ID', '32269909', NULL, NULL, NULL),
(11, 'Rita', NULL, 'Rita', 'Wanjiru', 'ritawanjiru@gmail.com', '$2y$10$KUHhnXPpvbvaeLPLHyFqv.UreqSnuTBSfKUODRG//Hg2FlBvZdSpa', 'customer', 'active', '0746356789', 'Nairobi', 'employed', 300000.00, NULL, NULL, NULL, '2026-09-08 14:00:18', NULL, NULL, NULL, 'Makoyeti west', '23454-00100', 'Nairobi', 'Kenya', NULL, 'National ID', '54455645', NULL, NULL, NULL);
-- User seed data intentionally omitted from the public export.

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lenders`
--
ALTER TABLE `lenders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `lender_id` (`lender_id`);

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `loan_messages`
--
ALTER TABLE `loan_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `loan_offers`
--
ALTER TABLE `loan_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lender_id` (`lender_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `system_activity_logs`
--
ALTER TABLE `system_activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `loan_messages`
--
ALTER TABLE `loan_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_offers`
--
ALTER TABLE `loan_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_activity_logs`
--
ALTER TABLE `system_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lenders`
--
ALTER TABLE `lenders`
  ADD CONSTRAINT `lenders_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`lender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD CONSTRAINT `loan_applications_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_messages`
--
ALTER TABLE `loan_messages`
  ADD CONSTRAINT `loan_messages_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`),
  ADD CONSTRAINT `loan_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_offers`
--
ALTER TABLE `loan_offers`
  ADD CONSTRAINT `loan_offers_ibfk_1` FOREIGN KEY (`lender_id`) REFERENCES `lenders` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
