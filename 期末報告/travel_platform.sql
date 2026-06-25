SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `itineraries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT 0.00,
  `is_public` enum('no','yes') DEFAULT 'no',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `trip_status` enum('active','finished') DEFAULT 'active',
  `finished_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  CONSTRAINT `itineraries_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `itinerary_members` (
  `itinerary_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`itinerary_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `itinerary_members_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itinerary_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `itinerary_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itinerary_id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invitee_id` int(11) DEFAULT NULL,
  `invitee_email` varchar(100) DEFAULT NULL,
  `status` enum('accepted','pending','rejected') DEFAULT 'accepted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `itinerary_id` (`itinerary_id`),
  KEY `inviter_id` (`inviter_id`),
  KEY `invitee_id` (`invitee_id`),
  CONSTRAINT `itinerary_invitations_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itinerary_invitations_ibfk_2` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itinerary_invitations_ibfk_3` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `spots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itinerary_id` int(11) DEFAULT NULL,
  `spot_name` varchar(255) NOT NULL,
  `place_id` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `day_number` int(11) DEFAULT 1,
  `sequence` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `itinerary_id` (`itinerary_id`),
  CONSTRAINT `spots_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itinerary_id` int(11) DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT '其他',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `itinerary_id` (`itinerary_id`),
  KEY `paid_by` (`paid_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_splits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `share_amount` decimal(10,2) NOT NULL,
  `is_settled` tinyint(1) DEFAULT 0,
  `settled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `expense_splits_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_splits_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `budget_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itinerary_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT '其他',
  `item_name` varchar(255) NOT NULL,
  `estimated_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `itinerary_id` (`itinerary_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `budget_items_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_items_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `itinerary_notes` (
  `itinerary_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`itinerary_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `itinerary_notes_ibfk_1` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itinerary_notes_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
