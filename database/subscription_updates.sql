-- AlQuran Subscription System Database Updates
-- This script adds subscription-related tables to an existing AlQuran database

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lessons_per_month` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_subscriptions`
--

CREATE TABLE IF NOT EXISTS `student_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `circle_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `duration_months` int(11) NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','bank_transfer','paymob','other') NOT NULL DEFAULT 'cash',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `circle_id` (`circle_id`),
  KEY `plan_id` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','paymob','other') NOT NULL DEFAULT 'cash',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'EGP',
  `status` enum('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `paymob_order_id` varchar(255) DEFAULT NULL,
  `paymob_transaction_id` varchar(255) DEFAULT NULL,
  `paymob_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE IF NOT EXISTS `payment_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Constraints for table `student_subscriptions`
--

ALTER TABLE `student_subscriptions`
  ADD CONSTRAINT `student_subscriptions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_subscriptions_ibfk_2` FOREIGN KEY (`circle_id`) REFERENCES `study_circles` (`id`),
  ADD CONSTRAINT `student_subscriptions_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`);

-- --------------------------------------------------------

--
-- Constraints for table `payment_transactions`
--

ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `student_subscriptions` (`id`);

-- --------------------------------------------------------

--
-- Insert default subscription plans
--

INSERT INTO `subscription_plans` (`lessons_per_month`, `price`, `is_active`) VALUES
(4, 50.00, 1),
(8, 90.00, 1),
(12, 120.00, 1),
(16, 140.00, 1);

-- --------------------------------------------------------

--
-- Insert default payment settings
--

INSERT INTO `payment_settings` (`setting_key`, `setting_value`, `is_active`) VALUES
('paymob_api_key', '', 1),
('paymob_integration_id', '', 1),
('paymob_iframe_id', '', 1),
('paymob_hmac_secret', '', 1),
('payment_currency', 'EGP', 1),
('payment_enabled', '1', 1);
