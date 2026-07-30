-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: aprism
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `audit_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_log_id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,3,'Logout','Successful logout.','::1','2026-07-23 03:47:48'),(2,3,'Login','Successful login.','::1','2026-07-23 03:47:53'),(3,3,'Database Backup','Generated backup: aprism_backup_20260723_114757.sql','::1','2026-07-23 03:47:57'),(4,3,'Reset Password','Reset password for John Doe','::1','2026-07-23 03:53:20'),(5,3,'Logout','Successful logout.','::1','2026-07-23 03:53:48'),(6,3,'Login','Successful login.','::1','2026-07-23 03:55:13'),(7,3,'Login','Successful login.','::1','2026-07-23 16:13:59'),(8,3,'Login','Successful login.','::1','2026-07-23 17:08:21'),(9,3,'Database Backup','Generated backup: aprism_backup_20260724_012203.sql','::1','2026-07-23 17:22:03'),(10,3,'Logout','Successful logout.','::1','2026-07-23 18:42:35'),(11,3,'Login','Successful login.','::1','2026-07-23 18:42:43'),(12,3,'Database Backup Download','Downloaded backup: aprism_backup_20260724_012203.sql','::1','2026-07-23 18:49:37'),(13,3,'Logout','Successful logout.','::1','2026-07-23 19:14:49'),(14,3,'Login','Successful login.','::1','2026-07-23 19:22:37'),(15,3,'Login','Successful login.','::1','2026-07-23 20:51:29'),(16,3,'Login','Successful login.','::1','2026-07-24 00:16:07'),(17,3,'Login','Successful login.','::1','2026-07-24 17:20:45'),(18,3,'Login','Successful login.','::1','2026-07-25 00:31:23'),(19,3,'Login','Successful login.','::1','2026-07-25 16:50:31'),(20,3,'Login','Successful login.','::1','2026-07-26 14:11:42'),(21,3,'Logout','Successful logout.','::1','2026-07-26 16:52:01'),(22,3,'Login','Successful login.','::1','2026-07-26 16:52:06'),(23,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:30:44'),(24,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-26 17:36:30'),(25,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:36:45'),(26,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-26 17:41:09'),(27,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:47:49'),(28,3,'System Settings','Updated standard session timeout to 50 minutes.','::1','2026-07-26 17:57:27'),(29,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:26:19'),(30,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:26:23'),(31,3,'System Settings','Updated standard session timeout to 50 minutes.','::1','2026-07-26 18:55:27'),(32,3,'Enable Account','Enabled account for testuser','::1','2026-07-26 18:55:35'),(33,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:55:54'),(34,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:09:51'),(35,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:12:46'),(36,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:12:54'),(37,3,'Disable Account','Disabled account for testuser','::1','2026-07-26 19:13:06'),(38,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:13:21'),(39,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:34:42'),(40,3,'Enable Account','Enabled account for testuser','::1','2026-07-26 19:34:50'),(41,3,'Disable Account','Disabled account for testuser','::1','2026-07-26 19:34:53'),(42,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:35:01'),(43,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:39:01'),(44,3,'Login','Successful login.','::1','2026-07-26 21:36:16'),(45,3,'System Settings','Updated backup retention limit to 30 days.','::1','2026-07-26 22:23:43'),(46,3,'Login','Successful login.','::1','2026-07-26 23:46:09'),(47,3,'Login','Successful login.','::1','2026-07-27 14:55:34'),(48,3,'Login','Successful login.','::1','2026-07-27 16:02:29'),(49,3,'Database Backup','Generated backup: aprism_backup_20260728_000241.sql','::1','2026-07-27 16:02:41'),(50,3,'System Settings','Updated standard session timeout to 1000 minutes.','::1','2026-07-27 16:18:07'),(51,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Monday at 02:00.','::1','2026-07-27 16:34:43'),(52,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Monday at 01:26.','::1','2026-07-27 17:25:10'),(53,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 01:26.','::1','2026-07-27 17:25:18'),(54,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:19.','::1','2026-07-27 18:18:58'),(55,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:20.','::1','2026-07-27 18:19:17'),(56,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:21.','::1','2026-07-27 18:21:22'),(57,NULL,'Scheduled Database Backup','Generated scheduled backup: aprism_backup_20260728_022151.sql',NULL,'2026-07-27 18:21:51'),(58,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:36.','::1','2026-07-27 18:35:07'),(59,NULL,'Scheduled Database Backup','Generated scheduled backup: aprism_backup_20260728_023609.sql',NULL,'2026-07-27 18:36:09'),(60,3,'System Settings','Updated backup retention limit to 45 days.','::1','2026-07-27 22:19:24'),(61,3,'System Settings','Updated automatic backup schedule to Weekly on Wednesday at 03:00.','::1','2026-07-27 22:21:01'),(62,3,'System Settings','Updated attendance policy settings: Grace Period = 10 minute(s); Absent Threshold = 30 minute(s).','::1','2026-07-28 01:21:23'),(63,3,'System Settings','Updated attendance policy settings: Grace Period = Unconfigured; Absent Threshold = 30 minute(s).','::1','2026-07-28 01:24:21'),(64,3,'Login','Successful login.','::1','2026-07-28 15:46:44'),(65,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-28 15:47:29'),(66,3,'System Settings','Updated attendance policy settings: Grace Period = 15 minute(s); Absent Threshold = 30 minute(s).','::1','2026-07-28 15:55:21'),(67,3,'Login','Successful login.','::1','2026-07-28 17:10:12'),(68,3,'System Settings','Updated security settings: Session Timeout = 60 minutes; Maximum Failed Login Attempts = 3 attempts.','::1','2026-07-28 17:10:23'),(69,3,'System Settings','Updated security settings: Session Timeout = 60 minutes;\nMaximum Failed Login Attempts = 5 attempts.','::1','2026-07-28 17:11:29'),(70,3,'System Settings','Updated security settings: Session Timeout = 70 minutes; Maximum Failed Login Attempts = 5 attempts.','::1','2026-07-28 17:14:58'),(71,3,'Enable Account','Enabled account for testuser','::1','2026-07-28 21:46:36'),(72,3,'Disable Account','Disabled account for testuser','::1','2026-07-28 21:46:40'),(73,3,'Login','Successful login.','::1','2026-07-29 00:13:22'),(74,3,'System Settings','Updated Backup Retention Settings\n- Backup Retention Limit: 45 days','::1','2026-07-29 00:13:33'),(75,3,'Enable Account','Enabled account for testuser','::1','2026-07-29 03:21:06'),(76,3,'Disable Account','Disabled account for testuser','::1','2026-07-29 03:21:11'),(77,3,'Login','Successful login.','::1','2026-07-29 15:58:09'),(78,3,'Logout','Successful logout.','::1','2026-07-29 16:29:42'),(79,3,'Login','Successful login.','::1','2026-07-29 16:29:48'),(80,3,'Database Backup','Generated backup: aprism_backup_20260730_003006.sql','::1','2026-07-29 16:30:07'),(81,3,'Login','Successful login.','::1','2026-07-29 22:29:28');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Technical Administrator','Manages the technical configuration and maintenance of the APRISM system.'),(2,'Academic Head','Manages academic settings, users, schedules, and reports.'),(3,'Teacher','Manages attendance, grades, referrals, and student monitoring.'),(4,'Disciplinary Officer','Manages student intervention cases, referrals, and disciplinary records.');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `fk_system_settings_updated_by` (`updated_by`),
  CONSTRAINT `fk_system_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'security_session_timeout_minutes','70',3,'2026-07-26 17:30:44','2026-07-28 17:14:58'),(18,'backup_retention_days','45',3,'2026-07-26 22:23:43','2026-07-27 22:19:24'),(21,'backup_schedule_enabled','1',3,'2026-07-27 16:34:43','2026-07-27 16:34:43'),(22,'backup_schedule_frequency','weekly',3,'2026-07-27 16:34:43','2026-07-27 16:34:43'),(23,'backup_schedule_time','03:00',3,'2026-07-27 16:34:43','2026-07-27 22:21:01'),(24,'backup_schedule_day','wednesday',3,'2026-07-27 16:34:43','2026-07-27 22:21:01'),(61,'attendance_absent_threshold_minutes','30',3,'2026-07-28 01:21:23','2026-07-28 01:21:23'),(64,'attendance_grace_period_minutes','15',3,'2026-07-28 15:55:20','2026-07-28 15:55:20'),(67,'security_max_failed_login_attempts','5',3,'2026-07-28 17:10:23','2026-07-28 17:11:29');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permissions` (
  `user_permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_name` enum('Adviser','Program Head') NOT NULL,
  PRIMARY KEY (`user_permission_id`),
  UNIQUE KEY `uq_user_permission` (`user_id`,`permission_name`),
  CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_security_status`
--

DROP TABLE IF EXISTS `user_security_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_security_status` (
  `user_id` int(11) NOT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `last_failed_login_at` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_security_status_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_security_status`
--

LOCK TABLES `user_security_status` WRITE;
/*!40000 ALTER TABLE `user_security_status` DISABLE KEYS */;
INSERT INTO `user_security_status` VALUES (3,0,NULL,NULL,'2026-07-29 22:29:28');
/*!40000 ALTER TABLE `user_security_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `last_activity_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `session_token` (`session_token`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (87,3,'61f7g404mct4epc19lsbc95cd5','2026-07-30 07:18:23','2026-07-30 08:28:23');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `employee_number` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `account_status` enum('Active','Disabled') NOT NULL DEFAULT 'Active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `employee_number` (`employee_number`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (3,1,'EMP0001','admin','admin@dasmarinas.sti.edu.ph','System','Administrator','$2y$10$uSLJWjIEU3fl4p6ESFYlwOIYsfj5xlI9OK08p/Sx5IMka8FGskOAa',0,'Active','2026-07-30 06:29:28','2026-07-10 19:07:07'),(4,1,'EMP-TEST-001','testuser','testuser@dasmarinas.sti.edu.ph','John','Doe','$2y$10$0H5m/Ddk58Mk/EV.0p6fz.aJAq97Aj6YAIzewoLDYWh2F.eILzWeG',1,'Disabled','2026-07-18 05:23:54','2026-07-15 22:24:45');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-30  7:18:23
