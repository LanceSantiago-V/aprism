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
-- Table structure for table `academic_periods`
--

DROP TABLE IF EXISTS `academic_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods` (
  `academic_period_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_year_id` int(10) unsigned NOT NULL,
  `academic_level` enum('College','Senior High School') NOT NULL,
  `semester` enum('First Semester','Second Semester') DEFAULT NULL,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `semester_key` varchar(30) GENERATED ALWAYS AS (coalesce(`semester`,'')) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`academic_period_id`),
  UNIQUE KEY `uq_academic_period` (`school_year_id`,`academic_level`,`semester_key`,`period_name`),
  KEY `idx_academic_periods_school_year` (`school_year_id`),
  KEY `idx_academic_periods_level` (`academic_level`),
  KEY `idx_academic_periods_dates` (`start_date`,`end_date`),
  KEY `idx_academic_periods_archived` (`is_archived`),
  CONSTRAINT `fk_academic_periods_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_academic_period_dates` CHECK (`start_date` <= `end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_periods`
--

LOCK TABLES `academic_periods` WRITE;
/*!40000 ALTER TABLE `academic_periods` DISABLE KEYS */;
INSERT INTO `academic_periods` VALUES (2,4,'College','First Semester','Prelim','2026-08-10','2026-09-01',0,'First Semester','2026-08-11 09:17:40','2026-08-11 23:24:16'),(3,4,'College','First Semester','Midterm','2026-09-03','2026-10-01',0,'First Semester','2026-08-11 23:26:30','2026-08-12 01:22:53');
/*!40000 ALTER TABLE `academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_periods_migration_backup`
--

DROP TABLE IF EXISTS `academic_periods_migration_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods_migration_backup` (
  `academic_period_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_year_id` int(10) unsigned NOT NULL,
  `academic_level` enum('College','Senior High School') NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`academic_period_id`),
  UNIQUE KEY `uq_academic_period` (`school_year_id`,`academic_level`,`period_name`),
  KEY `idx_academic_periods_school_year` (`school_year_id`),
  KEY `idx_academic_periods_level` (`academic_level`),
  KEY `idx_academic_periods_dates` (`start_date`,`end_date`),
  KEY `idx_academic_periods_archived` (`is_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_periods_migration_backup`
--

LOCK TABLES `academic_periods_migration_backup` WRITE;
/*!40000 ALTER TABLE `academic_periods_migration_backup` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_periods_migration_backup` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=390 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,3,'Logout','Successful logout.','::1','2026-07-23 03:47:48'),(2,3,'Login','Successful login.','::1','2026-07-23 03:47:53'),(3,3,'Database Backup','Generated backup: aprism_backup_20260723_114757.sql','::1','2026-07-23 03:47:57'),(4,3,'Reset Password','Reset password for John Doe','::1','2026-07-23 03:53:20'),(5,3,'Logout','Successful logout.','::1','2026-07-23 03:53:48'),(6,3,'Login','Successful login.','::1','2026-07-23 03:55:13'),(7,3,'Login','Successful login.','::1','2026-07-23 16:13:59'),(8,3,'Login','Successful login.','::1','2026-07-23 17:08:21'),(9,3,'Database Backup','Generated backup: aprism_backup_20260724_012203.sql','::1','2026-07-23 17:22:03'),(10,3,'Logout','Successful logout.','::1','2026-07-23 18:42:35'),(11,3,'Login','Successful login.','::1','2026-07-23 18:42:43'),(12,3,'Database Backup Download','Downloaded backup: aprism_backup_20260724_012203.sql','::1','2026-07-23 18:49:37'),(13,3,'Logout','Successful logout.','::1','2026-07-23 19:14:49'),(14,3,'Login','Successful login.','::1','2026-07-23 19:22:37'),(15,3,'Login','Successful login.','::1','2026-07-23 20:51:29'),(16,3,'Login','Successful login.','::1','2026-07-24 00:16:07'),(17,3,'Login','Successful login.','::1','2026-07-24 17:20:45'),(18,3,'Login','Successful login.','::1','2026-07-25 00:31:23'),(19,3,'Login','Successful login.','::1','2026-07-25 16:50:31'),(20,3,'Login','Successful login.','::1','2026-07-26 14:11:42'),(21,3,'Logout','Successful logout.','::1','2026-07-26 16:52:01'),(22,3,'Login','Successful login.','::1','2026-07-26 16:52:06'),(23,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:30:44'),(24,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-26 17:36:30'),(25,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:36:45'),(26,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-26 17:41:09'),(27,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 17:47:49'),(28,3,'System Settings','Updated standard session timeout to 50 minutes.','::1','2026-07-26 17:57:27'),(29,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:26:19'),(30,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:26:23'),(31,3,'System Settings','Updated standard session timeout to 50 minutes.','::1','2026-07-26 18:55:27'),(32,3,'Enable Account','Enabled account for testuser','::1','2026-07-26 18:55:35'),(33,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 18:55:54'),(34,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:09:51'),(35,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:12:46'),(36,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:12:54'),(37,3,'Disable Account','Disabled account for testuser','::1','2026-07-26 19:13:06'),(38,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:13:21'),(39,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:34:42'),(40,3,'Enable Account','Enabled account for testuser','::1','2026-07-26 19:34:50'),(41,3,'Disable Account','Disabled account for testuser','::1','2026-07-26 19:34:53'),(42,3,'System Settings','Updated standard session timeout to 45 minutes.','::1','2026-07-26 19:35:01'),(43,3,'System Settings','Updated standard session timeout to 46 minutes.','::1','2026-07-26 19:39:01'),(44,3,'Login','Successful login.','::1','2026-07-26 21:36:16'),(45,3,'System Settings','Updated backup retention limit to 30 days.','::1','2026-07-26 22:23:43'),(46,3,'Login','Successful login.','::1','2026-07-26 23:46:09'),(47,3,'Login','Successful login.','::1','2026-07-27 14:55:34'),(48,3,'Login','Successful login.','::1','2026-07-27 16:02:29'),(49,3,'Database Backup','Generated backup: aprism_backup_20260728_000241.sql','::1','2026-07-27 16:02:41'),(50,3,'System Settings','Updated standard session timeout to 1000 minutes.','::1','2026-07-27 16:18:07'),(51,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Monday at 02:00.','::1','2026-07-27 16:34:43'),(52,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Monday at 01:26.','::1','2026-07-27 17:25:10'),(53,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 01:26.','::1','2026-07-27 17:25:18'),(54,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:19.','::1','2026-07-27 18:18:58'),(55,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:20.','::1','2026-07-27 18:19:17'),(56,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:21.','::1','2026-07-27 18:21:22'),(57,NULL,'Scheduled Database Backup','Generated scheduled backup: aprism_backup_20260728_022151.sql',NULL,'2026-07-27 18:21:51'),(58,3,'System Settings','Updated backup settings. Retention: 30 days. Automatic backup schedule: Weekly on Tuesday at 02:36.','::1','2026-07-27 18:35:07'),(59,NULL,'Scheduled Database Backup','Generated scheduled backup: aprism_backup_20260728_023609.sql',NULL,'2026-07-27 18:36:09'),(60,3,'System Settings','Updated backup retention limit to 45 days.','::1','2026-07-27 22:19:24'),(61,3,'System Settings','Updated automatic backup schedule to Weekly on Wednesday at 03:00.','::1','2026-07-27 22:21:01'),(62,3,'System Settings','Updated attendance policy settings: Grace Period = 10 minute(s); Absent Threshold = 30 minute(s).','::1','2026-07-28 01:21:23'),(63,3,'System Settings','Updated attendance policy settings: Grace Period = Unconfigured; Absent Threshold = 30 minute(s).','::1','2026-07-28 01:24:21'),(64,3,'Login','Successful login.','::1','2026-07-28 15:46:44'),(65,3,'System Settings','Updated standard session timeout to 60 minutes.','::1','2026-07-28 15:47:29'),(66,3,'System Settings','Updated attendance policy settings: Grace Period = 15 minute(s); Absent Threshold = 30 minute(s).','::1','2026-07-28 15:55:21'),(67,3,'Login','Successful login.','::1','2026-07-28 17:10:12'),(68,3,'System Settings','Updated security settings: Session Timeout = 60 minutes; Maximum Failed Login Attempts = 3 attempts.','::1','2026-07-28 17:10:23'),(69,3,'System Settings','Updated security settings: Session Timeout = 60 minutes;\nMaximum Failed Login Attempts = 5 attempts.','::1','2026-07-28 17:11:29'),(70,3,'System Settings','Updated security settings: Session Timeout = 70 minutes; Maximum Failed Login Attempts = 5 attempts.','::1','2026-07-28 17:14:58'),(71,3,'Enable Account','Enabled account for testuser','::1','2026-07-28 21:46:36'),(72,3,'Disable Account','Disabled account for testuser','::1','2026-07-28 21:46:40'),(73,3,'Login','Successful login.','::1','2026-07-29 00:13:22'),(74,3,'System Settings','Updated Backup Retention Settings\n- Backup Retention Limit: 45 days','::1','2026-07-29 00:13:33'),(75,3,'Enable Account','Enabled account for testuser','::1','2026-07-29 03:21:06'),(76,3,'Disable Account','Disabled account for testuser','::1','2026-07-29 03:21:11'),(77,3,'Login','Successful login.','::1','2026-07-29 15:58:09'),(78,3,'Logout','Successful logout.','::1','2026-07-29 16:29:42'),(79,3,'Login','Successful login.','::1','2026-07-29 16:29:48'),(80,3,'Database Backup','Generated backup: aprism_backup_20260730_003006.sql','::1','2026-07-29 16:30:07'),(81,3,'Login','Successful login.','::1','2026-07-29 22:29:28'),(82,3,'Database Backup','Generated backup: aprism_backup_20260730_071823.sql','::1','2026-07-29 23:18:23'),(83,3,'Database Backup','Generated backup: aprism_backup_20260730_071830.sql','::1','2026-07-29 23:18:31'),(84,3,'Enable Account','Enabled account for testuser','::1','2026-07-29 23:19:20'),(85,3,'Disable Account','Disabled account for testuser','::1','2026-07-29 23:19:23'),(86,3,'Logout','Successful logout.','::1','2026-07-29 23:23:55'),(87,3,'Login','Successful login.','::1','2026-07-29 23:24:06'),(88,3,'Logout','Successful logout.','::1','2026-07-29 23:25:25'),(89,3,'Login','Successful login.','::1','2026-07-29 23:25:32'),(90,3,'Logout','Successful logout.','::1','2026-07-29 23:26:03'),(91,3,'Login','Successful login.','::1','2026-07-29 23:26:20'),(92,3,'Logout','Successful logout.','::1','2026-07-29 23:26:33'),(93,3,'Login','Successful login.','::1','2026-07-29 23:26:38'),(94,3,'Reset Password','Reset password for John Doe','::1','2026-07-29 23:27:24'),(95,3,'Logout','Successful logout.','::1','2026-07-29 23:27:42'),(96,3,'Login','Successful login.','::1','2026-07-29 23:27:56'),(97,3,'Enable Account','Enabled account for testuser','::1','2026-07-29 23:28:07'),(98,3,'Logout','Successful logout.','::1','2026-07-29 23:28:10'),(99,4,'Login','Successful login.','::1','2026-07-29 23:28:27'),(100,4,'Disable Account','Disabled account for admin','::1','2026-07-29 23:28:59'),(101,4,'Enable Account','Enabled account for admin','::1','2026-07-29 23:29:03'),(102,4,'Logout','Successful logout.','::1','2026-07-29 23:29:23'),(103,3,'Login','Successful login.','::1','2026-07-29 23:29:41'),(104,3,'System Settings','Updated Security Settings\n- Standard Session Timeout: 70 minutes\n- Temporary Lock Duration: 15 minutes\n- Maximum Failed Login Attempts: 5 attempts','::1','2026-07-29 23:31:02'),(105,3,'System Settings','Updated Backup Retention Settings\n- Backup Retention Limit: 45 days','::1','2026-07-29 23:31:07'),(106,3,'System Settings','Updated Automatic Backup Schedule\n- Automatic Backups: Enabled\n- Schedule: Weekly on Wednesday at 03:00','::1','2026-07-29 23:31:11'),(107,3,'Database Backup Download','Downloaded backup: aprism_backup_20260730_071830.sql','::1','2026-07-29 23:31:27'),(108,3,'Database Backup','Generated backup: aprism_backup_20260730_074037.sql','::1','2026-07-29 23:40:38'),(109,3,'Login','Successful login.','::1','2026-07-30 15:54:18'),(110,3,'Login','Successful login.','::1','2026-07-30 17:14:27'),(111,3,'Login','Successful login.','::1','2026-07-30 20:48:43'),(112,3,'Logout','Successful logout.','::1','2026-07-30 20:50:54'),(113,3,'Login','Successful login.','::1','2026-07-30 20:51:12'),(114,3,'Logout','Successful logout.','::1','2026-07-30 21:05:42'),(115,3,'Login','Successful login.','::1','2026-07-30 21:06:36'),(116,3,'Logout','Successful logout.','::1','2026-07-30 21:08:15'),(117,3,'Login','Successful login.','::1','2026-07-30 21:10:52'),(118,3,'Logout','Successful logout.','::1','2026-07-30 21:15:08'),(119,3,'Login','Successful login.','::1','2026-07-30 21:15:14'),(120,3,'Disable Account','Disabled account for testuser','::1','2026-07-31 00:56:50'),(121,3,'Database Backup','Generated backup: aprism_backup_20260731_085659.sql','::1','2026-07-31 00:56:59'),(122,3,'Logout','Successful logout.','::1','2026-07-31 01:24:04'),(123,3,'Login','Successful login.','::1','2026-07-31 01:41:44'),(124,3,'Logout','Successful logout.','::1','2026-07-31 01:41:57'),(125,3,'Login','Successful login.','::1','2026-07-31 02:48:25'),(126,3,'Logout','Successful logout.','::1','2026-07-31 02:48:29'),(127,3,'Login','Successful login.','::1','2026-07-31 03:01:14'),(128,3,'Enable Account','Enabled account for testuser','::1','2026-07-31 03:04:28'),(129,3,'Reset Password','Reset password for John Doe','::1','2026-07-31 03:04:32'),(130,3,'Logout','Successful logout.','::1','2026-07-31 03:04:44'),(131,4,'Login','Successful login.','::1','2026-07-31 03:04:54'),(132,3,'Login','Successful login.','::1','2026-07-31 17:46:56'),(133,3,'Login','Successful login.','::1','2026-07-31 19:11:46'),(134,3,'Login','Successful login.','::1','2026-08-01 01:22:51'),(135,3,'Create User','Created account for Academic Head','::1','2026-08-01 01:23:38'),(136,3,'Logout','Successful logout.','::1','2026-08-01 01:23:45'),(137,5,'Login','Successful login.','::1','2026-08-01 01:24:01'),(138,3,'Login','Successful login.','::1','2026-08-01 16:02:26'),(139,3,'Logout','Successful logout.','::1','2026-08-01 16:05:25'),(140,5,'Login','Successful login.','::1','2026-08-01 16:05:37'),(141,5,'Logout','Successful logout.','::1','2026-08-01 18:17:10'),(142,3,'Login','Successful login.','::1','2026-08-01 18:17:14'),(143,3,'Logout','Successful logout.','::1','2026-08-01 19:42:43'),(144,5,'Login','Successful login.','::1','2026-08-01 19:42:49'),(145,5,'Logout','Successful logout.','::1','2026-08-01 20:33:37'),(146,3,'Login','Successful login.','::1','2026-08-01 20:33:51'),(147,3,'Logout','Successful logout.','::1','2026-08-01 20:34:39'),(148,5,'Login','Successful login.','::1','2026-08-01 20:34:45'),(149,5,'Logout','Successful logout.','::1','2026-08-01 21:27:10'),(150,3,'Login','Successful login.','::1','2026-08-01 21:27:15'),(151,3,'Logout','Successful logout.','::1','2026-08-01 21:27:23'),(152,5,'Login','Successful login.','::1','2026-08-01 21:27:34'),(153,5,'Login','Successful login.','::1','2026-08-02 02:32:41'),(154,5,'Logout','Successful logout.','::1','2026-08-02 02:43:19'),(155,5,'Login','Successful login.','::1','2026-08-02 02:43:36'),(156,5,'Logout','Successful logout.','::1','2026-08-02 03:10:59'),(157,3,'Login','Successful login.','::1','2026-08-02 03:11:04'),(158,3,'Logout','Successful logout.','::1','2026-08-02 03:13:21'),(159,5,'Login','Successful login.','::1','2026-08-02 03:13:28'),(160,5,'Logout','Successful logout.','::1','2026-08-02 03:50:41'),(161,3,'Login','Successful login.','::1','2026-08-02 03:50:46'),(162,3,'Logout','Successful logout.','::1','2026-08-02 03:53:22'),(163,5,'Login','Successful login.','::1','2026-08-02 03:53:28'),(164,5,'Logout','Successful logout.','::1','2026-08-02 03:55:12'),(165,3,'Login','Successful login.','::1','2026-08-02 03:55:22'),(166,3,'Logout','Successful logout.','::1','2026-08-02 03:58:50'),(167,5,'Login','Successful login.','::1','2026-08-02 03:58:55'),(168,5,'Logout','Successful logout.','::1','2026-08-02 04:31:17'),(169,3,'Login','Successful login.','::1','2026-08-02 04:31:21'),(170,3,'Logout','Successful logout.','::1','2026-08-02 04:31:25'),(171,5,'Login','Successful login.','::1','2026-08-02 04:31:30'),(172,5,'Logout','Successful logout.','::1','2026-08-02 05:12:23'),(173,3,'Login','Successful login.','::1','2026-08-02 05:12:27'),(174,3,'Logout','Successful logout.','::1','2026-08-02 05:13:57'),(175,5,'Login','Successful login.','::1','2026-08-02 05:14:05'),(176,5,'Logout','Successful logout.','::1','2026-08-02 06:02:27'),(177,3,'Login','Successful login.','::1','2026-08-02 06:02:32'),(178,3,'Disable Account','Disabled account for testuser','::1','2026-08-02 06:15:54'),(179,3,'Enable Account','Enabled account for testuser','::1','2026-08-02 06:15:59'),(180,5,'Login','Successful login.','::1','2026-08-02 19:14:01'),(181,5,'Login','Successful login.','::1','2026-08-02 21:18:57'),(182,5,'Login','Successful login.','::1','2026-08-04 15:58:53'),(183,5,'Logout','Successful logout.','::1','2026-08-04 20:38:35'),(184,3,'Login','Successful login.','::1','2026-08-04 20:38:41'),(185,3,'Logout','Successful logout.','::1','2026-08-04 20:39:21'),(186,5,'Login','Successful login.','::1','2026-08-04 20:39:31'),(187,5,'Logout','Successful logout.','::1','2026-08-04 20:39:40'),(188,3,'Login','Successful login.','::1','2026-08-04 20:39:44'),(189,3,'Logout','Successful logout.','::1','2026-08-04 20:44:56'),(190,5,'Login','Successful login.','::1','2026-08-04 20:45:03'),(191,5,'Logout','Successful logout.','::1','2026-08-04 22:08:32'),(192,3,'Login','Successful login.','::1','2026-08-04 22:08:36'),(193,3,'Logout','Successful logout.','::1','2026-08-04 22:11:25'),(194,5,'Login','Successful login.','::1','2026-08-04 22:11:35'),(195,5,'Logout','Successful logout.','::1','2026-08-04 23:20:48'),(196,3,'Login','Successful login.','::1','2026-08-04 23:20:52'),(197,3,'Logout','Successful logout.','::1','2026-08-04 23:21:15'),(198,5,'Login','Successful login.','::1','2026-08-04 23:21:20'),(199,5,'Logout','Successful logout.','::1','2026-08-05 01:24:02'),(200,3,'Login','Successful login.','::1','2026-08-05 01:24:10'),(201,3,'Logout','Successful logout.','::1','2026-08-05 01:26:45'),(202,5,'Login','Successful login.','::1','2026-08-05 01:26:57'),(203,5,'Logout','Successful logout.','::1','2026-08-05 02:55:20'),(204,3,'Login','Successful login.','::1','2026-08-05 02:55:28'),(205,3,'Logout','Successful logout.','::1','2026-08-05 02:56:04'),(206,5,'Login','Successful login.','::1','2026-08-05 02:56:10'),(207,5,'Logout','Successful logout.','::1','2026-08-05 03:14:25'),(208,3,'Login','Successful login.','::1','2026-08-05 03:14:30'),(209,3,'Disable Account','Disabled account for testuser','::1','2026-08-05 03:18:27'),(210,3,'System Settings','Updated Automatic Backup Schedule\n- Automatic Backups: Enabled\n- Schedule: Weekly on Wednesday at 12:00','::1','2026-08-05 03:29:29'),(211,3,'System Settings','Updated Automatic Backup Schedule\n- Automatic Backups: Enabled\n- Schedule: Weekly on Wednesday at 12:00','::1','2026-08-05 03:29:32'),(212,3,'System Settings','Updated Security Settings\n- Standard Session Timeout: 70 minutes\n- Temporary Lock Duration: 15 minutes\n- Maximum Failed Login Attempts: 5 attempts','::1','2026-08-05 03:29:36'),(213,3,'Logout','Successful logout.','::1','2026-08-05 03:34:04'),(214,5,'Login','Successful login.','::1','2026-08-05 03:35:37'),(215,5,'Logout','Successful logout.','::1','2026-08-05 06:16:19'),(216,3,'Login','Successful login.','::1','2026-08-05 06:16:23'),(217,3,'Logout','Successful logout.','::1','2026-08-05 06:18:59'),(218,5,'Login','Successful login.','::1','2026-08-05 06:19:05'),(219,5,'Logout','Successful logout.','::1','2026-08-05 06:33:53'),(220,3,'Login','Successful login.','::1','2026-08-05 06:33:59'),(221,3,'Logout','Successful logout.','::1','2026-08-05 06:40:35'),(222,5,'Login','Successful login.','::1','2026-08-05 06:40:44'),(223,5,'Login','Successful login.','::1','2026-08-05 17:42:19'),(224,5,'Login','Successful login.','::1','2026-08-05 23:24:09'),(225,5,'Login','Successful login.','::1','2026-08-06 01:22:21'),(226,5,'Login','Successful login.','::1','2026-08-06 06:27:57'),(227,5,'Login','Successful login.','::1','2026-08-06 19:29:01'),(228,5,'Login','Successful login.','::1','2026-08-07 00:52:12'),(229,5,'Login','Successful login.','::1','2026-08-07 02:12:14'),(230,5,'Login','Successful login.','::1','2026-08-07 04:45:55'),(231,5,'Logout','Successful logout.','::1','2026-08-07 05:34:34'),(232,5,'Login','Successful login.','::1','2026-08-07 20:01:51'),(233,5,'Logout','Successful logout.','::1','2026-08-07 21:40:36'),(234,3,'Login','Successful login.','::1','2026-08-07 21:40:44'),(235,3,'Create User','Created account for teacher','::1','2026-08-07 21:43:52'),(236,3,'Logout','Successful logout.','::1','2026-08-07 21:44:38'),(237,6,'Login','Successful login.','::1','2026-08-07 21:44:51'),(238,6,'Logout','Successful logout.','::1','2026-08-07 21:54:07'),(239,3,'Login','Successful login.','::1','2026-08-07 21:54:15'),(240,3,'Logout','Successful logout.','::1','2026-08-07 21:54:22'),(241,6,'Login','Successful login.','::1','2026-08-07 21:54:34'),(242,6,'Logout','Successful logout.','::1','2026-08-07 23:15:49'),(243,5,'Login','Successful login.','::1','2026-08-07 23:15:58'),(244,5,'Logout','Successful logout.','::1','2026-08-07 23:16:20'),(245,3,'Login','Successful login.','::1','2026-08-07 23:16:26'),(246,3,'Logout','Successful logout.','::1','2026-08-07 23:17:04'),(247,6,'Login','Successful login.','::1','2026-08-07 23:17:14'),(248,6,'Login','Successful login.','::1','2026-08-08 02:38:52'),(249,6,'Logout','Successful logout.','::1','2026-08-08 02:48:02'),(250,5,'Login','Successful login.','::1','2026-08-08 02:48:09'),(251,5,'Logout','Successful logout.','::1','2026-08-08 02:48:14'),(252,5,'Login','Successful login.','::1','2026-08-08 02:48:31'),(253,5,'Logout','Successful logout.','::1','2026-08-08 02:48:35'),(254,3,'Login','Successful login.','::1','2026-08-08 02:48:41'),(255,3,'Logout','Successful logout.','::1','2026-08-08 02:49:01'),(256,5,'Login','Successful login.','::1','2026-08-08 02:49:07'),(257,5,'Logout','Successful logout.','::1','2026-08-08 02:49:17'),(258,6,'Login','Successful login.','::1','2026-08-08 02:49:25'),(259,6,'Logout','Successful logout.','::1','2026-08-08 03:05:11'),(260,5,'Login','Successful login.','::1','2026-08-08 03:05:16'),(261,5,'Logout','Successful logout.','::1','2026-08-08 03:05:30'),(262,3,'Login','Successful login.','::1','2026-08-08 03:05:36'),(263,3,'Logout','Successful logout.','::1','2026-08-08 04:01:55'),(264,5,'Login','Successful login.','::1','2026-08-08 04:02:02'),(265,5,'Logout','Successful logout.','::1','2026-08-08 04:06:23'),(266,3,'Login','Successful login.','::1','2026-08-08 04:06:31'),(267,3,'Logout','Successful logout.','::1','2026-08-08 04:06:39'),(268,5,'Login','Successful login.','::1','2026-08-08 04:06:45'),(269,5,'Logout','Successful logout.','::1','2026-08-08 04:07:03'),(270,3,'Login','Successful login.','::1','2026-08-08 04:07:09'),(271,3,'Logout','Successful logout.','::1','2026-08-08 04:07:28'),(272,5,'Login','Successful login.','::1','2026-08-08 04:07:34'),(273,5,'Logout','Successful logout.','::1','2026-08-08 04:26:41'),(274,6,'Login','Successful login.','::1','2026-08-08 04:26:52'),(275,6,'Logout','Successful logout.','::1','2026-08-08 04:26:58'),(276,3,'Login','Successful login.','::1','2026-08-08 04:32:42'),(277,3,'Logout','Successful logout.','::1','2026-08-08 04:33:02'),(278,5,'Login','Successful login.','::1','2026-08-08 04:33:10'),(279,5,'Logout','Successful logout.','::1','2026-08-08 04:34:44'),(280,6,'Login','Successful login.','::1','2026-08-08 04:34:50'),(281,6,'Logout','Successful logout.','::1','2026-08-08 04:35:03'),(282,5,'Login','Successful login.','::1','2026-08-08 04:35:13'),(283,5,'Logout','Successful logout.','::1','2026-08-08 04:35:22'),(284,3,'Login','Successful login.','::1','2026-08-08 04:35:34'),(285,3,'Logout','Successful logout.','::1','2026-08-08 04:38:08'),(286,5,'Login','Successful login.','::1','2026-08-08 04:38:15'),(287,5,'Logout','Successful logout.','::1','2026-08-08 04:38:35'),(288,6,'Login','Successful login.','::1','2026-08-08 04:38:40'),(289,6,'Logout','Successful logout.','::1','2026-08-08 04:40:35'),(290,5,'Login','Successful login.','::1','2026-08-08 04:40:41'),(291,5,'Logout','Successful logout.','::1','2026-08-08 04:40:51'),(292,3,'Login','Successful login.','::1','2026-08-08 04:40:59'),(293,3,'Logout','Successful logout.','::1','2026-08-08 04:41:08'),(294,6,'Login','Successful login.','::1','2026-08-08 05:08:11'),(295,6,'Login','Successful login.','::1','2026-08-08 08:55:33'),(296,6,'Login','Successful login.','::1','2026-08-08 18:55:31'),(297,6,'Login','Successful login.','::1','2026-08-08 20:32:34'),(298,6,'Login','Successful login.','::1','2026-08-09 05:48:18'),(299,6,'Logout','Successful logout.','::1','2026-08-09 05:51:54'),(300,3,'Login','Successful login.','::1','2026-08-09 05:51:59'),(301,3,'Update Responsibilities','Updated responsibilities for teacher','::1','2026-08-09 05:52:07'),(302,3,'Logout','Successful logout.','::1','2026-08-09 05:52:10'),(303,6,'Login','Successful login.','::1','2026-08-09 05:52:17'),(304,6,'Logout','Successful logout.','::1','2026-08-09 06:53:51'),(305,3,'Login','Successful login.','::1','2026-08-09 06:53:58'),(306,3,'Update Responsibilities','Updated responsibilities for teacher','::1','2026-08-09 06:54:05'),(307,3,'Logout','Successful logout.','::1','2026-08-09 06:54:10'),(308,6,'Login','Successful login.','::1','2026-08-09 06:54:17'),(309,6,'Logout','Successful logout.','::1','2026-08-09 22:58:28'),(310,5,'Login','Successful login.','::1','2026-08-09 22:58:37'),(311,5,'Create School Year','Created School Year 2026-2027 (2026-06-08 to 2027-03-23) with status Inactive.','::1','2026-08-10 00:46:55'),(312,5,'Create School Year','Created School Year 2027-2028 (2027-06-08 to 2028-03-23) with status Inactive.','::1','2026-08-10 01:22:33'),(313,5,'Create School Year','Created School Year 2028-2029 (2028-06-09 to 2029-03-23) with status Inactive.','::1','2026-08-10 01:23:20'),(314,5,'Logout','Successful logout.','::1','2026-08-10 01:33:06'),(315,3,'Login','Successful login.','::1','2026-08-10 01:33:12'),(316,3,'Enable Account','Enabled account for testuser','::1','2026-08-10 01:33:19'),(317,3,'Logout','Successful logout.','::1','2026-08-10 01:50:03'),(318,5,'Login','Successful login.','::1','2026-08-10 01:50:11'),(319,5,'Activate School Year','Activated School Year 2028-2029 (ID: 3).','::1','2026-08-10 04:13:57'),(320,5,'Activate School Year','Activated School Year 2027-2028 (ID: 2).','::1','2026-08-10 04:14:08'),(321,5,'Activate School Year','Activated School Year 2028-2029 (ID: 3).','::1','2026-08-10 04:14:13'),(322,5,'Activate School Year','Activated School Year 2027-2028 (ID: 2).','::1','2026-08-10 04:14:28'),(323,5,'Archive School Year','Archived School Year 2028-2029 (ID: 3).','::1','2026-08-10 04:14:33'),(324,5,'Archive School Year','Archived School Year 2026-2027 (ID: 1).','::1','2026-08-10 04:23:04'),(325,5,'Create School Year','Created School Year 2026-2027 (ID: 4).','::1','2026-08-10 04:25:54'),(326,5,'Logout','Successful logout.','::1','2026-08-10 04:26:35'),(327,3,'Login','Successful login.','::1','2026-08-10 04:26:41'),(328,3,'Disable Account','Disabled account for testuser','::1','2026-08-10 04:26:46'),(329,3,'Logout','Successful logout.','::1','2026-08-10 04:31:03'),(330,5,'Login','Successful login.','::1','2026-08-10 04:31:09'),(331,5,'Activate School Year','Activated School Year 2026-2027 (ID: 4).','::1','2026-08-10 06:33:52'),(332,5,'Logout','Successful logout.','::1','2026-08-10 08:05:48'),(333,6,'Login','Successful login.','::1','2026-08-10 08:05:55'),(334,6,'Logout','Successful logout.','::1','2026-08-10 08:08:36'),(335,5,'Login','Successful login.','::1','2026-08-10 08:08:41'),(336,5,'Logout','Successful logout.','::1','2026-08-10 08:42:36'),(337,3,'Login','Successful login.','::1','2026-08-10 08:42:43'),(338,3,'Enable Account','Enabled account for testuser','::1','2026-08-10 08:49:36'),(339,3,'Disable Account','Disabled account for testuser','::1','2026-08-10 08:49:40'),(340,3,'Disable Account','Disabled account for Academic Head','::1','2026-08-10 08:52:06'),(341,3,'Enable Account','Enabled account for Academic Head','::1','2026-08-10 08:52:09'),(342,3,'Reset Password','Reset password for John Doe','::1','2026-08-10 08:52:16'),(343,3,'Update User','Updated account for testuser','::1','2026-08-10 08:52:41'),(344,3,'Update User','Updated account for testuser','::1','2026-08-10 08:53:00'),(345,3,'Logout','Successful logout.','::1','2026-08-10 09:03:48'),(346,6,'Login','Successful login.','::1','2026-08-10 09:16:49'),(347,5,'Login','Successful login.','::1','2026-08-10 23:43:10'),(348,5,'Login','Successful login.','::1','2026-08-11 02:31:50'),(349,5,'Create Academic Period','Created academic period \"Prelim\" for College - First Semester.','::1','2026-08-11 04:55:31'),(350,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-11 04:56:04'),(351,5,'Activate School Year','Activated School Year 2026-2027 (ID: 4).','::1','2026-08-11 04:56:33'),(352,5,'Update Academic Period','Updated academic period \"Prelim\" for College.','::1','2026-08-11 04:57:08'),(353,5,'Login','Successful login.','::1','2026-08-11 08:33:30'),(354,5,'Create Academic Period','Created academic period \"Prelim\" for College - First Semester.','::1','2026-08-11 09:17:40'),(355,5,'Update Academic Period','Updated academic period \"Prelim\" for College.','::1','2026-08-11 23:24:16'),(356,5,'Login','Successful login.','::1','2026-08-11 23:24:24'),(357,5,'Create Academic Period','Created academic period \"Midterm\" for College - First Semester in School Year 2026-2027.','::1','2026-08-11 23:26:30'),(358,5,'Login','Successful login.','::1','2026-08-12 01:22:42'),(359,5,'Update Academic Period','Updated academic period \"Midterm\" for College.','::1','2026-08-12 01:22:53'),(360,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-12 01:23:02'),(361,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-12 01:23:14'),(362,5,'Login','Successful login.','::1','2026-08-12 03:35:55'),(363,5,'Activate School Year','Activated School Year 2026-2027 (ID: 4).','::1','2026-08-12 03:36:21'),(364,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-12 03:36:35'),(365,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-12 03:37:03'),(366,5,'Update School Year','Updated School Year 2026-2027 (ID: 4).','::1','2026-08-12 03:37:34'),(367,5,'Create School Year','Created School Year 2027-2028 (ID: 5).','::1','2026-08-12 04:23:45'),(368,5,'Login','Successful login.','::1','2026-08-12 06:16:01'),(369,6,'Login','Successful login.','::1','2026-08-13 22:15:19'),(370,6,'Logout','Successful logout.','::1','2026-08-14 01:44:01'),(371,5,'Login','Successful login.','::1','2026-08-14 01:44:08'),(372,5,'Login','Successful login.','::1','2026-08-14 04:13:48'),(373,5,'Logout','Successful logout.','::1','2026-08-14 04:14:00'),(374,6,'Login','Successful login.','::1','2026-08-14 04:14:07'),(375,6,'Logout','Successful logout.','::1','2026-08-14 04:14:40'),(376,5,'Login','Successful login.','::1','2026-08-14 04:14:46'),(377,5,'Logout','Successful logout.','::1','2026-08-14 04:19:06'),(378,3,'Login','Successful login.','::1','2026-08-14 04:19:27'),(379,3,'Logout','Successful logout.','::1','2026-08-14 04:19:50'),(380,5,'Login','Successful login.','::1','2026-08-14 04:19:56'),(381,5,'Logout','Successful logout.','::1','2026-08-14 04:44:49'),(382,5,'Login','Successful login.','::1','2026-08-14 04:44:59'),(383,5,'Login','Successful login.','::1','2026-08-14 08:38:11'),(384,6,'Login','Successful login.','::1','2026-08-14 10:30:45'),(385,3,'Login','Successful login.','::1','2026-08-14 13:18:08'),(386,3,'Enable Account','Enabled account for testuser','::1','2026-08-14 13:25:50'),(387,3,'Reset Password','Reset password for John Doe','::1','2026-08-14 13:25:57'),(388,3,'Logout','Successful logout.','::1','2026-08-14 13:26:44'),(389,4,'Login','Successful login.','::1','2026-08-14 13:27:12');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_schedules`
--

DROP TABLE IF EXISTS `class_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_schedules` (
  `class_schedule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `operational_class_id` int(10) unsigned NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_schedule_id`),
  UNIQUE KEY `uq_class_schedule` (`operational_class_id`,`day`,`start_time`,`end_time`,`room`),
  KEY `idx_class_schedules_operational_class` (`operational_class_id`),
  KEY `idx_class_schedules_day_time` (`day`,`start_time`,`end_time`),
  KEY `idx_class_schedules_status` (`status`),
  CONSTRAINT `fk_class_schedules_operational_class` FOREIGN KEY (`operational_class_id`) REFERENCES `operational_classes` (`operational_class_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_schedules`
--

LOCK TABLES `class_schedules` WRITE;
/*!40000 ALTER TABLE `class_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operational_classes`
--

DROP TABLE IF EXISTS `operational_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operational_classes` (
  `operational_class_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `section_id` int(10) unsigned NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `semester` varchar(30) NOT NULL,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`operational_class_id`),
  UNIQUE KEY `uq_operational_class` (`teacher_id`,`subject_id`,`section_id`,`school_year`,`semester`),
  KEY `idx_operational_classes_teacher` (`teacher_id`),
  KEY `idx_operational_classes_subject` (`subject_id`),
  KEY `idx_operational_classes_section` (`section_id`),
  KEY `idx_operational_classes_period_status` (`school_year`,`semester`,`status`),
  CONSTRAINT `fk_operational_classes_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_operational_classes_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_operational_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operational_classes`
--

LOCK TABLES `operational_classes` WRITE;
/*!40000 ALTER TABLE `operational_classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `operational_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `program_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `academic_level` enum('College','Senior High School') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `uq_program_code` (`program_code`),
  UNIQUE KEY `uq_program_name` (`program_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
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
-- Table structure for table `school_years`
--

DROP TABLE IF EXISTS `school_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_years` (
  `school_year_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_year` varchar(9) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Inactive','Archived') NOT NULL DEFAULT 'Inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`school_year_id`),
  UNIQUE KEY `uq_school_year` (`school_year`),
  KEY `idx_school_years_status` (`status`),
  KEY `idx_school_years_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_years`
--

LOCK TABLES `school_years` WRITE;
/*!40000 ALTER TABLE `school_years` DISABLE KEYS */;
INSERT INTO `school_years` VALUES (4,'2026-2027','2026-08-09','2027-06-14','Active','2026-08-10 04:25:54','2026-08-12 03:37:34'),(5,'2027-2028','2027-08-10','2028-06-05','Inactive','2026-08-12 04:23:45','2026-08-12 04:23:45');
/*!40000 ALTER TABLE `school_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `section_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `year_level` enum('1','2','3','4') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `uq_program_section` (`program_id`,`section_name`),
  CONSTRAINT `fk_sections_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `subject_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` decimal(3,1) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `uq_subject_code` (`subject_code`),
  UNIQUE KEY `uq_subject_name` (`subject_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'security_session_timeout_minutes','70',3,'2026-07-26 17:30:44','2026-07-28 17:14:58'),(18,'backup_retention_days','45',3,'2026-07-26 22:23:43','2026-07-27 22:19:24'),(21,'backup_schedule_enabled','1',3,'2026-07-27 16:34:43','2026-07-27 16:34:43'),(22,'backup_schedule_frequency','weekly',3,'2026-07-27 16:34:43','2026-07-27 16:34:43'),(23,'backup_schedule_time','12:00',3,'2026-07-27 16:34:43','2026-08-05 03:29:29'),(24,'backup_schedule_day','wednesday',3,'2026-07-27 16:34:43','2026-07-27 22:21:01'),(61,'attendance_absent_threshold_minutes','30',3,'2026-07-28 01:21:23','2026-07-28 01:21:23'),(64,'attendance_grace_period_minutes','15',3,'2026-07-28 15:55:20','2026-07-28 15:55:20'),(67,'security_max_failed_login_attempts','5',3,'2026-07-28 17:10:23','2026-07-28 17:11:29'),(74,'security_temporary_lock_duration_minutes','15',3,'2026-07-29 23:31:02','2026-07-29 23:31:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
INSERT INTO `user_permissions` VALUES (5,6,'Adviser'),(6,6,'Program Head');
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
INSERT INTO `user_security_status` VALUES (3,0,NULL,NULL,'2026-08-14 13:18:08'),(4,0,NULL,NULL,'2026-08-14 13:27:12'),(5,0,NULL,NULL,'2026-08-14 08:38:11'),(6,0,NULL,NULL,'2026-08-14 10:30:45');
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
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (227,4,'rnqu3pdrgitp2j4oibuouusdgf','2026-08-14 21:29:44','2026-08-14 22:39:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (3,1,'EMP0001','admin','admin@dasmarinas.sti.edu.ph','System','Administrator','$2y$10$uSLJWjIEU3fl4p6ESFYlwOIYsfj5xlI9OK08p/Sx5IMka8FGskOAa',0,'Active','2026-08-14 21:18:08','2026-07-10 19:07:07'),(4,1,'EMP-TEST-001','testuser','testuser@dasmarinas.sti.edu.ph','John','Doe','$2y$10$YEIq57wPYjYxI7JQZ9NqT.xjzE1CI/i5e.9gqH8MUqYMd4DIYFuNK',0,'Active','2026-08-14 21:27:12','2026-07-15 22:24:45'),(5,2,'EMP-2026-001','Academic Head','acadhead@dasmarinas.sti.edu.ph','John','Doe','$2y$10$2luS/FeKwdNOfwJYIw8cuuUtdn2VQRxMQ8w9HtfJlwY8BoHLODtSq',0,'Active','2026-08-14 16:38:11','2026-08-01 01:23:38'),(6,3,'EMP-2026-002','teacher','teacher@dasmarinas.sti.edu.ph','John','Doe','$2y$10$vy4qLaujY6p7ry//B1Xvo.72FPtNFiZbyCjXTnqD5dH/e2SOR3f66',0,'Active','2026-08-14 18:30:45','2026-08-07 21:43:52');
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

-- Dump completed on 2026-08-14 21:29:44
