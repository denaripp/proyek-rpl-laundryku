-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: laundryku
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_chat_messages`
--

DROP TABLE IF EXISTS `ai_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `role` enum('user','assistant','system','tool') COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `function_call` json DEFAULT NULL,
  `function_result` json DEFAULT NULL,
  `token_usage` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_chat_messages_session_id_foreign` (`session_id`),
  KEY `ai_chat_messages_created_at_index` (`created_at`),
  CONSTRAINT `ai_chat_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `ai_chat_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_chat_sessions`
--

DROP TABLE IF EXISTS `ai_chat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_chat_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_chat_sessions_session_token_unique` (`session_token`),
  KEY `ai_chat_sessions_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_chat_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_conversations`
--

DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_conversations_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_messages`
--

DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_messages_conversation_id_foreign` (`conversation_id`),
  CONSTRAINT `ai_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned DEFAULT NULL,
  `actor_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_id` bigint unsigned DEFAULT NULL,
  `before_value` json DEFAULT NULL,
  `after_value` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_id_index` (`actor_id`),
  KEY `audit_logs_resource_type_resource_id_index` (`resource_type`,`resource_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `idx_audit_logs_actor_time` (`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commission_settings`
--

DROP TABLE IF EXISTS `commission_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commission_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `fee_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `fixed_amount` decimal(10,2) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commission_settings_store_id_foreign` (`store_id`),
  CONSTRAINT `commission_settings_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courier_gps`
--

DROP TABLE IF EXISTS `courier_gps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courier_gps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy_meters` decimal(6,2) DEFAULT NULL,
  `heading` decimal(5,2) DEFAULT NULL,
  `speed_kmh` decimal(6,2) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `courier_gps_task_id_foreign` (`task_id`),
  KEY `courier_gps_recorded_at_index` (`recorded_at`),
  KEY `idx_courier_gps_task_time` (`task_id`,`recorded_at`),
  CONSTRAINT `courier_gps_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `courier_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courier_tasks`
--

DROP TABLE IF EXISTS `courier_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courier_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `courier_id` bigint unsigned NOT NULL,
  `task_type` enum('pickup','delivery') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('assigned','accepted','arrived','picked_up','delivered','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `duration_minutes` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courier_tasks_order_task_unique` (`order_id`,`task_type`),
  KEY `courier_tasks_courier_id_foreign` (`courier_id`),
  KEY `courier_tasks_status_index` (`status`),
  KEY `idx_courier_tasks_courier_status` (`courier_id`,`status`),
  CONSTRAINT `courier_tasks_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courier_tasks_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `couriers`
--

DROP TABLE IF EXISTS `couriers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `couriers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `vehicle_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_plate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `last_location_update` timestamp NULL DEFAULT NULL,
  `service_area` json DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `total_tasks` int unsigned DEFAULT '0',
  `completed_tasks` int unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `couriers_user_id_unique` (`user_id`),
  KEY `couriers_store_id_foreign` (`store_id`),
  KEY `couriers_is_available_index` (`is_available`),
  CONSTRAINT `couriers_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `couriers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customer_addresses`
--

DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rt_rw` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kelurahan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `address_note` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_user_id_foreign` (`user_id`),
  CONSTRAINT `customer_addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_login_attempts`
--

DROP TABLE IF EXISTS `failed_login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `failed_login_attempts_user_id_index` (`user_id`),
  KEY `failed_login_attempts_email_index` (`email`),
  KEY `failed_login_attempts_ip_address_index` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `laundry_packages`
--

DROP TABLE IF EXISTS `laundry_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laundry_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_preferences`
--

DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_user_channel_event_unique` (`user_id`,`channel`,`event_type`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `delivered_via` json DEFAULT NULL,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `email_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  KEY `notifications_read_at_index` (`read_at`),
  KEY `notifications_created_at_index` (`created_at`),
  KEY `idx_notifications_user_read` (`user_id`,`read_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `unit` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_service_id_foreign` (`service_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `actor_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_history_order_id_foreign` (`order_id`),
  KEY `order_status_history_status_index` (`status`),
  KEY `order_status_history_created_at_index` (`created_at`),
  CONSTRAINT `order_status_history_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `status` enum('created','waiting_pickup','courier_assigned','courier_to_customer','picked_up','in_transit_to_store','received_by_store','weighed','payment_required','paid','processing','ready_for_delivery','courier_assigned_for_delivery','out_for_delivery','delivered','completed','cancelled','pickup_failed','delivery_failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created',
  `pickup_address_id` bigint unsigned DEFAULT NULL,
  `delivery_address_id` bigint unsigned DEFAULT NULL,
  `pickup_address` text COLLATE utf8mb4_unicode_ci,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `pickup_latitude` decimal(10,8) DEFAULT NULL,
  `pickup_longitude` decimal(11,8) DEFAULT NULL,
  `delivery_latitude` decimal(10,8) DEFAULT NULL,
  `delivery_longitude` decimal(11,8) DEFAULT NULL,
  `estimated_weight` decimal(8,2) DEFAULT NULL,
  `actual_weight` decimal(8,2) DEFAULT NULL,
  `weight_unit` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'kg',
  `estimated_price` decimal(10,2) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `pickup_fee` decimal(10,2) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT NULL,
  `platform_fee` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('online','cod') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','expired','refund_requested','refund_processing','refunded','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `courier_id` bigint unsigned DEFAULT NULL,
  `courier_assigned_at` timestamp NULL DEFAULT NULL,
  `courier_arrived_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `received_by_store_at` timestamp NULL DEFAULT NULL,
  `weighed_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `processing_started_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `customer_notes` text COLLATE utf8mb4_unicode_ci,
  `store_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_customer_id_foreign` (`customer_id`),
  KEY `orders_store_id_foreign` (`store_id`),
  KEY `orders_status_index` (`status`),
  KEY `orders_payment_status_index` (`payment_status`),
  KEY `orders_courier_id_foreign` (`courier_id`),
  KEY `orders_created_at_index` (`created_at`),
  KEY `orders_completed_at_index` (`completed_at`),
  KEY `orders_cancelled_by_foreign` (`cancelled_by`),
  KEY `idx_orders_customer_store_status` (`customer_id`,`store_id`,`status`),
  KEY `idx_orders_created_completed` (`created_at`,`completed_at`),
  CONSTRAINT `orders_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_method` enum('online','cod') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed','expired','refund_requested','refund_processing','refunded','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `xendit_invoice_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_payment_channel` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `customer_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_user_agent` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_order_id_unique` (`order_id`),
  KEY `payments_status_index` (`status`),
  KEY `payments_xendit_invoice_id_index` (`xendit_invoice_id`),
  KEY `idx_payments_order_status` (`order_id`,`status`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_category_index` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('requested','processing','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_refund_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refunds_payment_id_unique` (`payment_id`),
  KEY `refunds_order_id_foreign` (`order_id`),
  KEY `refunds_status_index` (`status`),
  KEY `refunds_requested_by_foreign` (`requested_by`),
  KEY `refunds_approved_by_foreign` (`approved_by`),
  CONSTRAINT `refunds_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refunds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `refunds_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `refunds_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` bigint unsigned DEFAULT NULL,
  `moderation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_order_id_unique` (`order_id`),
  KEY `reviews_customer_id_foreign` (`customer_id`),
  KEY `reviews_store_id_foreign` (`store_id`),
  KEY `reviews_rating_index` (`rating`),
  KEY `reviews_moderated_by_foreign` (`moderated_by`),
  CONSTRAINT `reviews_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviews_moderated_by_foreign` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_chk_1` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_prices`
--

DROP TABLE IF EXISTS `service_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `unit` enum('kg','item') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_prices_store_service_unique` (`store_id`,`service_id`),
  KEY `service_prices_service_id_foreign` (`service_id`),
  CONSTRAINT `service_prices_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `service_prices_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_name_unique` (`name`),
  KEY `services_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_applications`
--

DROP TABLE IF EXISTS `store_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `revision_required_at` timestamp NULL DEFAULT NULL,
  `revision_notes` text COLLATE utf8mb4_unicode_ci,
  `resubmitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_applications_store_id_foreign` (`store_id`),
  KEY `store_applications_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `store_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_applications_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_documents`
--

DROP TABLE IF EXISTS `store_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_documents_store_id_foreign` (`store_id`),
  KEY `store_documents_verified_by_foreign` (`verified_by`),
  CONSTRAINT `store_documents_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_documents_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_settings`
--

DROP TABLE IF EXISTS `store_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_settings_store_key_unique` (`store_id`,`key`),
  CONSTRAINT `store_settings_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_staff`
--

DROP TABLE IF EXISTS `store_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_staff` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `permissions` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `invited_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_staff_store_user_unique` (`store_id`,`user_id`),
  KEY `store_staff_user_id_foreign` (`user_id`),
  CONSTRAINT `store_staff_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whatsapp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rt_rw` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kelurahan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','submitted','under_review','revision_required','resubmitted','approved','rejected','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `cod_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `operational_hours` json DEFAULT NULL,
  `service_radius_km` decimal(5,2) DEFAULT NULL,
  `minimum_order_amount` decimal(10,2) DEFAULT NULL,
  `pickup_fee` decimal(10,2) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspension_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stores_slug_unique` (`slug`),
  KEY `stores_owner_user_id_foreign` (`owner_user_id`),
  KEY `stores_is_active_index` (`is_active`),
  KEY `stores_status_index` (`status`),
  KEY `stores_latitude_longitude_index` (`latitude`,`longitude`),
  CONSTRAINT `stores_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_configurations`
--

DROP TABLE IF EXISTS `system_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_editable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_configurations_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_role_store_unique` (`user_id`,`role_id`,`store_id`),
  KEY `user_roles_role_id_foreign` (`role_id`),
  KEY `user_roles_store_id_foreign` (`store_id`),
  KEY `user_roles_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `user_roles_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xendit_webhooks`
--

DROP TABLE IF EXISTS `xendit_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `xendit_webhooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `status` enum('received','processed','failed','duplicate') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `processing_error` text COLLATE utf8mb4_unicode_ci,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `xendit_webhooks_webhook_id_unique` (`webhook_id`),
  KEY `xendit_webhooks_event_type_index` (`event_type`),
  KEY `xendit_webhooks_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-29 21:02:26
