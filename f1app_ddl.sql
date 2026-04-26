-- ============================================================
-- F1 Racing Management System — Full DB Dump (schema + seed data)
-- Generated: 2026-04-26
--
-- SETUP (XAMPP / any MySQL 8+):
--   mysql -u root -p < f1app_ddl.sql
-- or in phpMyAdmin: Import → select this file.
--
-- Then point Apache/XAMPP at /f1app and visit localhost/f1app
-- ============================================================
-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: f1app
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.22.04.1

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
-- Current Database: `f1app`
--

/*!40000 DROP DATABASE IF EXISTS `f1app`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `f1app` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `f1app`;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resource_id` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 17:29:22'),(2,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/team_manager/export.php','127.0.0.1','2026-04-25 17:30:33'),(3,6,'login_success','users',6,NULL,'127.0.0.1','2026-04-25 17:30:33'),(4,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/dashboard.php','127.0.0.1','2026-04-25 17:34:14'),(5,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/penalties.php','127.0.0.1','2026-04-25 17:34:14'),(10,1,'session_displaced','users',1,'1 prior session(s) terminated','127.0.0.1','2026-04-25 17:49:37'),(11,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 17:49:37'),(13,1,'session_displaced','users',1,'1 prior session(s) terminated','127.0.0.1','2026-04-25 17:59:17'),(14,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 17:59:17'),(15,1,'session_displaced','users',1,'1 prior session(s) terminated','127.0.0.1','2026-04-25 18:00:30'),(16,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 18:00:30'),(17,1,'create','teams',11,'Created team: test','127.0.0.1','2026-04-25 18:14:14'),(18,1,'update','teams',6,'Updated team: Alpin','127.0.0.1','2026-04-25 18:15:22'),(19,1,'update','teams',6,'Updated team: Alpine','127.0.0.1','2026-04-25 18:15:31'),(20,1,'create','teams',12,'Created team: test','127.0.0.1','2026-04-25 18:16:54'),(21,NULL,'login_failed',NULL,NULL,'Email: admin@f1system.com','127.0.0.1','2026-04-25 22:42:28'),(22,NULL,'login_failed',NULL,NULL,'Email: admin@f1system.com','127.0.0.1','2026-04-25 22:48:34'),(23,NULL,'login_failed',NULL,NULL,'Email: admin@f1system.com','127.0.0.1','2026-04-25 22:49:16'),(24,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 22:50:10'),(25,1,'create','teams',13,'Created team: Test Scuderia','127.0.0.1','2026-04-25 22:51:11'),(26,1,'delete','teams',13,'Test Scuderia','127.0.0.1','2026-04-25 22:53:05'),(27,1,'create','teams',14,'Created team: Temp Test Team XYZ','127.0.0.1','2026-04-25 22:55:07'),(28,1,'session_displaced','users',1,'1 prior session(s) terminated','127.0.0.1','2026-04-25 23:03:34'),(29,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 23:03:34'),(30,1,'delete','teams',11,'test','127.0.0.1','2026-04-25 23:07:25'),(31,1,'create','teams',15,'Created team: test 2','127.0.0.1','2026-04-25 23:09:30'),(32,1,'create','teams',16,'Created team: test 3','127.0.0.1','2026-04-25 23:09:43'),(33,1,'create','people',21,'test 123 #43','127.0.0.1','2026-04-25 23:26:11'),(34,1,'deactivate','teams',12,'test','127.0.0.1','2026-04-25 23:31:40'),(35,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-25 23:37:24'),(36,1,'create','driver_seasons',NULL,'Person 21 in season 1','127.0.0.1','2026-04-25 23:42:34'),(37,1,'create','seasons',2,'Year: 2026','127.0.0.1','2026-04-25 23:47:57'),(38,1,'update','seasons',2,'Set as active season','127.0.0.1','2026-04-25 23:48:03'),(39,1,'update','seasons',1,'Set as active season','127.0.0.1','2026-04-25 23:48:05'),(40,1,'create','circuits',11,'test 2','127.0.0.1','2026-04-25 23:50:36'),(41,1,'delete','circuits',11,'test 2','127.0.0.1','2026-04-25 23:50:52'),(42,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/results.php?race_id=4','127.0.0.1','2026-04-25 23:51:59'),(43,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:52:03'),(44,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/race_entries.php?race_id=4','127.0.0.1','2026-04-25 23:52:06'),(45,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:52:11'),(46,1,'update','races',4,'Japanese Grand Prix','127.0.0.1','2026-04-25 23:52:21'),(47,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:52:27'),(48,1,'update','races',4,'Japanese Grand Prix','127.0.0.1','2026-04-25 23:52:35'),(49,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:52:41'),(50,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/results.php?race_id=4','127.0.0.1','2026-04-25 23:52:47'),(51,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:52:55'),(52,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=4','127.0.0.1','2026-04-25 23:55:45'),(53,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/qualifying.php?race_id=3','127.0.0.1','2026-04-25 23:56:24'),(54,1,'permission_denied',NULL,NULL,'Attempted to access /f1app/race_director/results.php?race_id=4','127.0.0.1','2026-04-26 00:00:40'),(55,1,'create','penalties',NULL,'Race 3, Person 7','127.0.0.1','2026-04-26 00:09:00'),(56,1,'login_failed',NULL,NULL,'Email: admin@f1app.com','127.0.0.1','2026-04-26 00:10:51'),(57,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-26 00:11:06'),(58,1,'session_displaced','users',1,'1 prior session(s) terminated','127.0.0.1','2026-04-26 01:25:15'),(59,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-26 01:25:15'),(60,1,'logout','users',1,NULL,'127.0.0.1','2026-04-26 01:25:54'),(61,2,'login_success','users',2,NULL,'127.0.0.1','2026-04-26 01:27:02'),(62,2,'create','penalties',NULL,'Type: time_penalty, Person: 21, Race: 4','127.0.0.1','2026-04-26 01:28:17'),(63,2,'create','penalties',NULL,'Type: time_penalty, Person: 21, Race: 3','127.0.0.1','2026-04-26 01:29:53'),(64,2,'create','race_entries',NULL,'Race 4, person 1','127.0.0.1','2026-04-26 01:36:24'),(65,2,'update','results',4,'Race 4 results saved','127.0.0.1','2026-04-26 01:37:04'),(66,2,'update','results',4,'Race 4 results saved','127.0.0.1','2026-04-26 01:37:28'),(67,2,'update','results',3,'Race 3 results saved','127.0.0.1','2026-04-26 01:38:35'),(68,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/dashboard.php','127.0.0.1','2026-04-26 01:41:14'),(69,2,'logout','users',2,NULL,'127.0.0.1','2026-04-26 01:42:12'),(70,3,'login_success','users',3,NULL,'127.0.0.1','2026-04-26 01:42:45'),(71,3,'logout','users',3,NULL,'127.0.0.1','2026-04-26 01:48:39'),(72,4,'login_success','users',4,NULL,'127.0.0.1','2026-04-26 01:48:58'),(73,4,'create','pit_stops',NULL,'Entry: 61 Stop: 1 Lap: 1','127.0.0.1','2026-04-26 01:51:53'),(74,4,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/dashboard.php','127.0.0.1','2026-04-26 01:53:38'),(75,4,'logout','users',4,NULL,'127.0.0.1','2026-04-26 01:53:39'),(76,5,'login_success','users',5,NULL,'127.0.0.1','2026-04-26 01:53:52'),(77,5,'logout','users',5,NULL,'127.0.0.1','2026-04-26 01:59:53'),(78,6,'login_success','users',6,NULL,'127.0.0.1','2026-04-26 02:00:06'),(79,6,'logout','users',6,NULL,'127.0.0.1','2026-04-26 02:04:08'),(80,7,'login_success','users',7,NULL,'127.0.0.1','2026-04-26 02:04:28'),(81,7,'login_success','users',7,NULL,'127.0.0.1','2026-04-26 21:10:18'),(82,7,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 21:11:06'),(83,7,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=7','127.0.0.1','2026-04-26 21:11:09'),(84,7,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=3','127.0.0.1','2026-04-26 21:11:12'),(85,7,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=6','127.0.0.1','2026-04-26 21:11:15'),(86,7,'login_success','users',7,NULL,'127.0.0.1','2026-04-26 21:39:55'),(87,7,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 21:42:42'),(88,7,'logout','users',7,NULL,'127.0.0.1','2026-04-26 21:50:10'),(89,6,'login_success','users',6,NULL,'127.0.0.1','2026-04-26 21:51:03'),(90,6,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 21:55:45'),(91,6,'logout','users',6,NULL,'127.0.0.1','2026-04-26 21:56:10'),(92,5,'login_success','users',5,NULL,'127.0.0.1','2026-04-26 21:56:31'),(93,5,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 21:58:58'),(94,5,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=7','127.0.0.1','2026-04-26 21:59:10'),(95,5,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 22:00:22'),(96,5,'logout','users',5,NULL,'127.0.0.1','2026-04-26 22:02:29'),(97,4,'login_success','users',4,NULL,'127.0.0.1','2026-04-26 22:02:46'),(98,4,'create','lap_telemetry',NULL,'Entry: 61 Lap: 1','127.0.0.1','2026-04-26 22:03:45'),(99,4,'delete','lap_telemetry',11,'Deleted by engineer','127.0.0.1','2026-04-26 22:04:00'),(100,4,'create','pit_stops',NULL,'Entry: 61 Stop: 1 Lap: 1','127.0.0.1','2026-04-26 22:05:10'),(101,4,'delete','pit_stops',8,'Deleted by engineer','127.0.0.1','2026-04-26 22:05:14'),(102,4,'create','lap_telemetry',NULL,'Entry: 61 Lap: 1','127.0.0.1','2026-04-26 22:05:26'),(103,4,'logout','users',4,NULL,'127.0.0.1','2026-04-26 22:08:23'),(104,3,'login_success','users',3,NULL,'127.0.0.1','2026-04-26 22:08:47'),(105,3,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=13','127.0.0.1','2026-04-26 22:17:48'),(106,3,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 22:17:51'),(107,3,'logout','users',3,NULL,'127.0.0.1','2026-04-26 22:18:48'),(108,2,'login_success','users',2,NULL,'127.0.0.1','2026-04-26 22:19:14'),(109,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/circuit_detail.php?id=3','127.0.0.1','2026-04-26 22:20:03'),(110,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/circuit_detail.php?id=1','127.0.0.1','2026-04-26 22:20:06'),(111,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/circuit_detail.php?id=6','127.0.0.1','2026-04-26 22:20:57'),(112,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/circuits_edit.php?id=6','127.0.0.1','2026-04-26 22:21:00'),(113,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/circuits_create.php','127.0.0.1','2026-04-26 22:21:11'),(114,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=21','127.0.0.1','2026-04-26 22:22:19'),(115,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=12','127.0.0.1','2026-04-26 22:22:22'),(116,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/team_detail.php?id=5','127.0.0.1','2026-04-26 22:22:48'),(117,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/person_detail.php?id=1','127.0.0.1','2026-04-26 22:22:57'),(118,2,'permission_denied',NULL,NULL,'Attempted to access /f1app/admin/team_detail.php?id=4','127.0.0.1','2026-04-26 22:23:00'),(119,2,'create','penalties',NULL,'Type: warning, Person: 1, Race: 4','127.0.0.1','2026-04-26 22:23:57'),(120,2,'create','race_entries',NULL,'Bulk added 21 drivers for race 5','127.0.0.1','2026-04-26 22:28:14'),(121,2,'update','qualifying_results',5,'Race 5','127.0.0.1','2026-04-26 22:29:27'),(122,2,'logout','users',2,NULL,'127.0.0.1','2026-04-26 22:29:56'),(123,5,'login_success','users',5,NULL,'127.0.0.1','2026-04-26 22:30:06'),(124,5,'logout','users',5,NULL,'127.0.0.1','2026-04-26 22:30:10'),(125,1,'login_failed',NULL,NULL,'Email: admin@f1app.com','127.0.0.1','2026-04-26 22:30:51'),(126,1,'login_failed',NULL,NULL,'Email: admin@f1app.com','127.0.0.1','2026-04-26 22:30:56'),(127,1,'login_success','users',1,NULL,'127.0.0.1','2026-04-26 22:31:13'),(128,1,'deactivate','teams',15,'test 2','127.0.0.1','2026-04-26 22:32:17'),(129,1,'deactivate','teams',16,'test 3','127.0.0.1','2026-04-26 22:32:20'),(130,1,'deactivate','teams',16,'test 3','127.0.0.1','2026-04-26 22:33:05'),(131,1,'deactivate','teams',16,'test 3','127.0.0.1','2026-04-26 22:34:02'),(132,1,'deactivate','teams',16,'test 3','127.0.0.1','2026-04-26 22:34:20'),(133,1,'deactivate','people',21,'test 123','127.0.0.1','2026-04-26 22:36:47'),(134,1,'deactivate','people',21,'test 123','127.0.0.1','2026-04-26 22:37:08'),(136,1,'create','circuits',12,'test','127.0.0.1','2026-04-26 22:40:55'),(137,1,'delete','circuits',12,'test','127.0.0.1','2026-04-26 22:40:59');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `circuits`
--

DROP TABLE IF EXISTS `circuits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `length_km` float NOT NULL,
  `number_of_laps` int NOT NULL,
  `circuit_type` enum('permanent','street') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lap_record_ms` int DEFAULT NULL,
  `lap_record_person_id` int DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_circuits_lap_record` (`lap_record_person_id`),
  CONSTRAINT `fk_circuits_lap_record` FOREIGN KEY (`lap_record_person_id`) REFERENCES `people` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `circuits`
--

LOCK TABLES `circuits` WRITE;
/*!40000 ALTER TABLE `circuits` DISABLE KEYS */;
INSERT INTO `circuits` VALUES (1,'Bahrain International Circuit','Bahrain','Sakhir',5.412,57,'permanent',91447,1,1),(2,'Jeddah Corniche Circuit','Saudi Arabia','Jeddah',6.174,50,'street',90734,7,1),(3,'Albert Park Circuit','Australia','Melbourne',5.278,58,'permanent',79820,5,1),(4,'Suzuka International Racing','Japan','Suzuka',5.807,53,'permanent',90983,7,1),(5,'Shanghai International Circuit','China','Shanghai',5.451,56,'permanent',93558,7,1),(6,'Miami International Autodrome','USA','Miami',5.412,57,'street',90589,7,1),(7,'Autodromo Enzo e Dino Ferrari','Italy','Imola',4.909,63,'permanent',95701,1,1),(8,'Circuit de Monaco','Monaco','Monte Carlo',3.337,78,'street',74260,1,1),(9,'Circuit Gilles Villeneuve','Canada','Montreal',4.361,70,'permanent',73078,7,1),(10,'Circuit de Barcelona-Catalunya','Spain','Barcelona',4.675,66,'permanent',79981,7,1);
/*!40000 ALTER TABLE `circuits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `constructor_standings`
--

DROP TABLE IF EXISTS `constructor_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `constructor_standings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `team_id` int NOT NULL,
  `points` float DEFAULT '0',
  `wins` int DEFAULT '0',
  `position` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_team` (`season_id`,`team_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `constructor_standings_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `constructor_standings_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `constructor_standings`
--

LOCK TABLES `constructor_standings` WRITE;
/*!40000 ALTER TABLE `constructor_standings` DISABLE KEYS */;
INSERT INTO `constructor_standings` VALUES (1,1,3,78,0,3),(2,1,4,88,2,1),(3,1,1,81,2,2),(4,1,2,52,0,4),(5,1,5,18,0,5),(6,1,7,8,0,6),(7,1,8,4,0,7),(8,1,6,0,0,8),(9,1,9,0,0,9),(10,1,10,0,0,10);
/*!40000 ALTER TABLE `constructor_standings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_seasons`
--

DROP TABLE IF EXISTS `driver_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `driver_seasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int NOT NULL,
  `team_season_id` int NOT NULL,
  `season_id` int NOT NULL,
  `status` enum('active','replaced','injured') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `joined_round` int DEFAULT '1',
  `left_round` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_driver_season` (`person_id`,`season_id`),
  KEY `team_season_id` (`team_season_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `driver_seasons_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_seasons_ibfk_2` FOREIGN KEY (`team_season_id`) REFERENCES `team_seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_seasons_ibfk_3` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_seasons`
--

LOCK TABLES `driver_seasons` WRITE;
/*!40000 ALTER TABLE `driver_seasons` DISABLE KEYS */;
INSERT INTO `driver_seasons` VALUES (1,1,1,1,'active',1,NULL),(2,2,1,1,'active',1,NULL),(3,3,2,1,'active',1,NULL),(4,4,2,1,'active',1,NULL),(5,5,3,1,'active',1,NULL),(6,6,3,1,'active',1,NULL),(7,7,4,1,'active',1,NULL),(8,8,4,1,'active',1,NULL),(9,9,5,1,'active',1,NULL),(10,10,5,1,'active',1,NULL),(11,11,6,1,'active',1,NULL),(12,12,6,1,'active',1,NULL),(13,13,7,1,'active',1,NULL),(14,14,7,1,'active',1,NULL),(15,15,8,1,'active',1,NULL),(16,16,8,1,'active',1,NULL),(17,17,9,1,'active',1,NULL),(18,18,9,1,'active',1,NULL),(19,19,10,1,'active',1,NULL),(20,20,10,1,'active',1,NULL),(21,21,6,1,'active',1,NULL);
/*!40000 ALTER TABLE `driver_seasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_standings`
--

DROP TABLE IF EXISTS `driver_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `driver_standings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `person_id` int NOT NULL,
  `points` float DEFAULT '0',
  `wins` int DEFAULT '0',
  `podiums` int DEFAULT '0',
  `dnfs` int DEFAULT '0',
  `fastest_laps` int DEFAULT '0',
  `position` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_person` (`season_id`,`person_id`),
  KEY `person_id` (`person_id`),
  CONSTRAINT `driver_standings_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_standings_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_standings`
--

LOCK TABLES `driver_standings` WRITE;
/*!40000 ALTER TABLE `driver_standings` DISABLE KEYS */;
INSERT INTO `driver_standings` VALUES (1,1,7,68,2,3,0,1,2),(2,1,5,52,0,3,0,1,3),(3,1,1,80,2,4,0,1,1),(4,1,3,36,0,0,0,0,4),(5,1,8,20,0,0,1,0,6),(6,1,6,26,0,0,0,0,5),(7,1,9,18,0,0,0,0,7),(8,1,4,16,0,0,0,0,8),(9,1,14,8,0,0,0,0,9),(10,1,16,4,0,0,0,0,10),(11,1,2,1,0,0,0,0,11),(12,1,13,0,0,0,0,0,15),(13,1,11,0,0,0,0,0,13),(14,1,15,0,0,0,0,0,16),(15,1,10,0,0,0,0,0,12),(16,1,17,0,0,0,0,0,17),(17,1,19,0,0,0,0,0,19),(18,1,12,0,0,0,0,0,14),(19,1,18,0,0,0,0,0,18),(20,1,20,0,0,0,1,0,20);
/*!40000 ALTER TABLE `driver_standings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lap_telemetry`
--

DROP TABLE IF EXISTS `lap_telemetry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lap_telemetry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_entry_id` int NOT NULL,
  `lap_number` int NOT NULL,
  `lap_time_ms` int NOT NULL,
  `sector1_ms` int DEFAULT NULL,
  `sector2_ms` int DEFAULT NULL,
  `sector3_ms` int DEFAULT NULL,
  `speed_trap_kmh` float DEFAULT NULL,
  `is_pit_lap` tinyint NOT NULL DEFAULT '0',
  `tyre_compound` enum('soft','medium','hard','intermediate','wet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tyre_age_laps` int DEFAULT '0',
  `tyre_condition` enum('good','worn','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'good',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry_lap` (`race_entry_id`,`lap_number`),
  CONSTRAINT `lap_telemetry_ibfk_1` FOREIGN KEY (`race_entry_id`) REFERENCES `race_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lap_telemetry`
--

LOCK TABLES `lap_telemetry` WRITE;
/*!40000 ALTER TABLE `lap_telemetry` DISABLE KEYS */;
INSERT INTO `lap_telemetry` VALUES (1,1,1,91234,NULL,NULL,NULL,NULL,0,'soft',1,'good'),(2,1,2,89876,NULL,NULL,NULL,NULL,0,'soft',2,'good'),(3,1,3,89543,NULL,NULL,NULL,NULL,0,'soft',3,'good'),(4,1,4,89234,NULL,NULL,NULL,NULL,0,'soft',4,'good'),(5,1,5,89012,NULL,NULL,NULL,NULL,0,'soft',5,'good'),(6,7,1,91456,NULL,NULL,NULL,NULL,0,'soft',1,'good'),(7,7,2,90123,NULL,NULL,NULL,NULL,0,'soft',2,'good'),(8,7,3,89789,NULL,NULL,NULL,NULL,0,'soft',3,'good'),(9,7,4,89456,NULL,NULL,NULL,NULL,0,'soft',4,'good'),(10,7,5,89234,NULL,NULL,NULL,NULL,0,'soft',5,'good'),(12,61,1,12000,NULL,NULL,NULL,NULL,0,NULL,NULL,'good');
/*!40000 ALTER TABLE `lap_telemetry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penalties`
--

DROP TABLE IF EXISTS `penalties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penalties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_id` int NOT NULL,
  `person_id` int NOT NULL,
  `issued_by` int NOT NULL,
  `penalty_type` enum('time_penalty','grid_penalty','licence_points','dsq','warning') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_penalty_s` int DEFAULT NULL,
  `grid_penalty_positions` int DEFAULT NULL,
  `licence_points_awarded` int DEFAULT NULL,
  `is_dsq` tinyint DEFAULT '0',
  `issued_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `race_id` (`race_id`),
  KEY `person_id` (`person_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penalties_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penalties_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penalties`
--

LOCK TABLES `penalties` WRITE;
/*!40000 ALTER TABLE `penalties` DISABLE KEYS */;
INSERT INTO `penalties` VALUES (1,1,16,2,'time_penalty','Exceeding track limits at Turn 4 on multiple occasions',5,NULL,2,0,'2026-04-25 03:46:37'),(2,2,2,2,'grid_penalty','Impeding Albon during Q1 — unsportsmanlike behaviour',NULL,3,1,0,'2026-04-25 03:46:37'),(3,3,10,2,'licence_points','Collision with Hadjar at Turn 1 — driver at fault',NULL,NULL,3,0,'2026-04-25 03:46:37'),(7,3,7,1,'time_penalty','jfjk',10,NULL,NULL,0,'2026-04-26 00:09:00'),(8,4,21,2,'time_penalty','fds',1,NULL,NULL,0,'2026-04-26 01:28:17'),(9,3,21,2,'time_penalty','12',1,NULL,NULL,0,'2026-04-26 01:29:53'),(10,4,1,2,'warning','hj',NULL,NULL,NULL,0,'2026-04-26 22:23:57');
/*!40000 ALTER TABLE `penalties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `people`
--

DROP TABLE IF EXISTS `people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date NOT NULL,
  `racing_number` int NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `racing_number` (`racing_number`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `people`
--

LOCK TABLES `people` WRITE;
/*!40000 ALTER TABLE `people` DISABLE KEYS */;
INSERT INTO `people` VALUES (1,'Max','Verstappen','Dutch','1997-09-30',1,NULL,1,'2026-04-25 03:46:37'),(2,'Liam','Lawson','New Zealand','2002-02-11',30,NULL,1,'2026-04-25 03:46:37'),(3,'George','Russell','British','1998-02-15',63,NULL,1,'2026-04-25 03:46:37'),(4,'Kimi','Antonelli','Italian','2006-08-25',12,NULL,1,'2026-04-25 03:46:37'),(5,'Charles','Leclerc','Monegasque','1997-10-16',16,NULL,1,'2026-04-25 03:46:37'),(6,'Lewis','Hamilton','British','1985-01-07',44,NULL,1,'2026-04-25 03:46:37'),(7,'Lando','Norris','British','1999-11-13',4,NULL,1,'2026-04-25 03:46:37'),(8,'Oscar','Piastri','Australian','2001-04-06',81,NULL,1,'2026-04-25 03:46:37'),(9,'Fernando','Alonso','Spanish','1981-07-29',14,NULL,1,'2026-04-25 03:46:37'),(10,'Lance','Stroll','Canadian','1998-10-29',18,NULL,1,'2026-04-25 03:46:37'),(11,'Pierre','Gasly','French','1996-02-07',10,NULL,1,'2026-04-25 03:46:37'),(12,'Jack','Doohan','Australian','2003-01-20',7,NULL,1,'2026-04-25 03:46:37'),(13,'Alex','Albon','Thai','1996-03-23',23,NULL,1,'2026-04-25 03:46:37'),(14,'Carlos','Sainz','Spanish','1994-09-01',55,NULL,1,'2026-04-25 03:46:37'),(15,'Isack','Hadjar','French','2004-09-28',6,NULL,1,'2026-04-25 03:46:37'),(16,'Yuki','Tsunoda','Japanese','2000-05-11',22,NULL,1,'2026-04-25 03:46:37'),(17,'Nico','Hulkenberg','German','1987-08-19',27,NULL,1,'2026-04-25 03:46:37'),(18,'Gabriel','Bortoleto','Brazilian','2004-10-14',5,NULL,1,'2026-04-25 03:46:37'),(19,'Esteban','Ocon','French','1996-09-17',31,NULL,1,'2026-04-25 03:46:37'),(20,'Oliver','Bearman','British','2005-05-08',87,NULL,1,'2026-04-25 03:46:37'),(21,'test','123','British','1985-01-07',43,NULL,0,'2026-04-25 23:26:11');
/*!40000 ALTER TABLE `people` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pit_stops`
--

DROP TABLE IF EXISTS `pit_stops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pit_stops` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_entry_id` int NOT NULL,
  `stop_number` int NOT NULL,
  `lap_number` int NOT NULL,
  `duration_ms` int DEFAULT NULL,
  `tyre_in` enum('soft','medium','hard','intermediate','wet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tyre_out` enum('soft','medium','hard','intermediate','wet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `race_entry_id` (`race_entry_id`),
  CONSTRAINT `pit_stops_ibfk_1` FOREIGN KEY (`race_entry_id`) REFERENCES `race_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pit_stops`
--

LOCK TABLES `pit_stops` WRITE;
/*!40000 ALTER TABLE `pit_stops` DISABLE KEYS */;
INSERT INTO `pit_stops` VALUES (1,1,1,15,2345,'soft','medium'),(2,1,2,38,2567,'medium','soft'),(3,7,1,14,2456,'soft','medium'),(4,7,2,37,2678,'medium','soft'),(5,5,1,16,2389,'soft','hard'),(6,5,2,40,2512,'hard','soft'),(7,61,1,1,2345,'soft','hard');
/*!40000 ALTER TABLE `pit_stops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qualifying_results`
--

DROP TABLE IF EXISTS `qualifying_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qualifying_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_entry_id` int NOT NULL,
  `q1_time_ms` int DEFAULT NULL,
  `q2_time_ms` int DEFAULT NULL,
  `q3_time_ms` int DEFAULT NULL,
  `grid_position` int NOT NULL,
  `eliminated_in` enum('Q1','Q2') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `race_entry_id` (`race_entry_id`),
  CONSTRAINT `qualifying_results_ibfk_1` FOREIGN KEY (`race_entry_id`) REFERENCES `race_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qualifying_results`
--

LOCK TABLES `qualifying_results` WRITE;
/*!40000 ALTER TABLE `qualifying_results` DISABLE KEYS */;
INSERT INTO `qualifying_results` VALUES (1,1,90234,89456,88534,1,NULL),(2,7,90567,89789,88812,2,NULL),(3,5,90789,89934,89023,3,NULL),(4,3,90891,90012,89134,4,NULL),(5,8,91023,90234,89267,5,NULL),(6,6,91134,90345,89389,6,NULL),(7,9,91256,90467,89512,7,NULL),(8,4,91345,90556,89623,8,NULL),(9,14,91456,90667,89734,9,NULL),(10,16,91567,90778,89845,10,NULL),(11,2,91678,90889,NULL,11,'Q2'),(12,13,91789,91000,NULL,12,'Q2'),(13,11,91890,91112,NULL,13,'Q2'),(14,15,92001,91223,NULL,14,'Q2'),(15,10,92112,91334,NULL,15,'Q2'),(16,17,92223,NULL,NULL,16,'Q1'),(17,19,92334,NULL,NULL,17,'Q1'),(18,12,92445,NULL,NULL,18,'Q1'),(19,18,92556,NULL,NULL,19,'Q1'),(20,20,92667,NULL,NULL,20,'Q1'),(21,27,89123,88567,87934,1,NULL),(22,25,89345,88789,88156,2,NULL),(23,21,89567,89012,88378,3,NULL),(24,23,89789,89234,88600,4,NULL),(25,28,90012,89456,88823,5,NULL),(26,26,90234,89678,89045,6,NULL),(27,24,90345,89789,89156,7,NULL),(28,29,90456,89900,89267,8,NULL),(29,34,90567,90012,89389,9,NULL),(30,36,90678,90123,89500,10,NULL),(31,22,90789,90234,NULL,11,'Q2'),(32,33,90900,90345,NULL,12,'Q2'),(33,31,91012,90456,NULL,13,'Q2'),(34,35,91123,90567,NULL,14,'Q2'),(35,30,91234,90678,NULL,15,'Q2'),(36,37,91345,NULL,NULL,16,'Q1'),(37,39,91456,NULL,NULL,17,'Q1'),(38,32,91567,NULL,NULL,18,'Q1'),(39,38,91678,NULL,NULL,19,'Q1'),(40,40,91789,NULL,NULL,20,'Q1'),(41,45,78234,77456,76834,1,NULL),(42,47,78456,77678,77056,2,NULL),(43,41,78678,77900,77278,3,NULL),(44,43,78900,78123,77500,4,NULL),(45,46,79123,78345,77723,5,NULL),(46,48,79345,78567,77945,6,NULL),(47,49,79567,78789,78167,7,NULL),(48,44,79789,79012,78389,8,NULL),(49,54,80012,79234,78612,9,NULL),(50,56,80234,79456,78834,10,NULL),(51,42,80456,79678,NULL,11,'Q2'),(52,53,80678,79900,NULL,12,'Q2'),(53,51,80900,80123,NULL,13,'Q2'),(54,55,81123,80345,NULL,14,'Q2'),(55,50,81345,80567,NULL,15,'Q2'),(56,57,81567,NULL,NULL,16,'Q1'),(57,59,81789,NULL,NULL,17,'Q1'),(58,52,82012,NULL,NULL,18,'Q1'),(59,58,82234,NULL,NULL,19,'Q1'),(60,60,82456,NULL,NULL,20,'Q1'),(61,62,NULL,NULL,NULL,1,NULL);
/*!40000 ALTER TABLE `qualifying_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `race_entries`
--

DROP TABLE IF EXISTS `race_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `race_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_id` int NOT NULL,
  `person_id` int NOT NULL,
  `team_season_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_race_person` (`race_id`,`person_id`),
  KEY `person_id` (`person_id`),
  KEY `team_season_id` (`team_season_id`),
  CONSTRAINT `race_entries_ibfk_1` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `race_entries_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `race_entries_ibfk_3` FOREIGN KEY (`team_season_id`) REFERENCES `team_seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `race_entries`
--

LOCK TABLES `race_entries` WRITE;
/*!40000 ALTER TABLE `race_entries` DISABLE KEYS */;
INSERT INTO `race_entries` VALUES (1,1,1,1),(2,1,2,1),(3,1,3,2),(4,1,4,2),(5,1,5,3),(6,1,6,3),(7,1,7,4),(8,1,8,4),(9,1,9,5),(10,1,10,5),(11,1,11,6),(12,1,12,6),(13,1,13,7),(14,1,14,7),(15,1,15,8),(16,1,16,8),(17,1,17,9),(18,1,18,9),(19,1,19,10),(20,1,20,10),(21,2,1,1),(22,2,2,1),(23,2,3,2),(24,2,4,2),(25,2,5,3),(26,2,6,3),(27,2,7,4),(28,2,8,4),(29,2,9,5),(30,2,10,5),(31,2,11,6),(32,2,12,6),(33,2,13,7),(34,2,14,7),(35,2,15,8),(36,2,16,8),(37,2,17,9),(38,2,18,9),(39,2,19,10),(40,2,20,10),(41,3,1,1),(42,3,2,1),(43,3,3,2),(44,3,4,2),(45,3,5,3),(46,3,6,3),(47,3,7,4),(48,3,8,4),(49,3,9,5),(50,3,10,5),(51,3,11,6),(52,3,12,6),(53,3,13,7),(54,3,14,7),(55,3,15,8),(56,3,16,8),(57,3,17,9),(58,3,18,9),(59,3,19,10),(60,3,20,10),(61,4,1,1),(62,5,1,1),(63,5,7,4),(64,5,18,9),(65,5,15,8),(66,5,12,6),(67,5,11,6),(68,5,4,2),(69,5,9,5),(70,5,5,3),(71,5,10,5),(72,5,16,8),(73,5,13,7),(74,5,17,9),(75,5,2,1),(76,5,19,10),(77,5,21,6),(78,5,6,3),(79,5,14,7),(80,5,3,2),(81,5,8,4),(82,5,20,10);
/*!40000 ALTER TABLE `race_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `race_results`
--

DROP TABLE IF EXISTS `race_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `race_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_entry_id` int NOT NULL,
  `start_position` int NOT NULL,
  `finish_position` int DEFAULT NULL,
  `points_scored` float DEFAULT '0',
  `total_race_time_ms` int DEFAULT NULL,
  `fastest_lap_ms` int DEFAULT NULL,
  `fastest_lap_bonus` tinyint DEFAULT '0',
  `laps_completed` int DEFAULT '0',
  `status` enum('finished','DNF','DNS','DSQ') COLLATE utf8mb4_unicode_ci DEFAULT 'finished',
  PRIMARY KEY (`id`),
  UNIQUE KEY `race_entry_id` (`race_entry_id`),
  CONSTRAINT `race_results_ibfk_1` FOREIGN KEY (`race_entry_id`) REFERENCES `race_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `race_results`
--

LOCK TABLES `race_results` WRITE;
/*!40000 ALTER TABLE `race_results` DISABLE KEYS */;
INSERT INTO `race_results` VALUES (1,1,1,1,25,5247891,88534,1,57,'finished'),(2,7,2,2,18,5254123,88812,0,57,'finished'),(3,5,3,3,15,5261456,89023,0,57,'finished'),(4,3,4,4,12,5268789,89134,0,57,'finished'),(5,8,5,5,10,5276012,89267,0,57,'finished'),(6,6,6,6,8,5283345,89389,0,57,'finished'),(7,9,7,7,6,5290678,89512,0,57,'finished'),(8,4,8,8,4,5298001,89623,0,57,'finished'),(9,14,9,9,2,5305234,89734,0,57,'finished'),(10,16,10,10,1,5312567,89845,0,57,'finished'),(11,2,11,11,0,5319890,90123,0,57,'finished'),(12,13,12,12,0,5327123,90234,0,57,'finished'),(13,11,13,13,0,5334456,90345,0,57,'finished'),(14,15,14,14,0,5341789,90456,0,57,'finished'),(15,10,15,15,0,5349012,90567,0,57,'finished'),(16,17,16,16,0,5356345,90678,0,57,'finished'),(17,19,17,17,0,5363678,90789,0,57,'finished'),(18,12,18,18,0,5371001,90900,0,57,'finished'),(19,18,19,19,0,5378234,91012,0,57,'finished'),(20,20,20,NULL,0,NULL,91123,0,23,'DNF'),(21,27,1,1,25,5412345,87934,1,50,'finished'),(22,25,2,2,18,5419678,88156,0,50,'finished'),(23,21,3,3,15,5427012,88378,0,50,'finished'),(24,23,4,4,12,5434345,88600,0,50,'finished'),(25,28,5,5,10,5441678,88823,0,50,'finished'),(26,26,6,6,8,5449012,89045,0,50,'finished'),(27,24,7,7,6,5456345,89156,0,50,'finished'),(28,29,8,8,4,5463678,89267,0,50,'finished'),(29,34,9,9,2,5471012,89389,0,50,'finished'),(30,36,10,10,1,5478345,89500,0,50,'finished'),(31,22,11,11,0,5485678,89612,0,50,'finished'),(32,33,12,12,0,5493012,89723,0,50,'finished'),(33,31,13,13,0,5500345,89834,0,50,'finished'),(34,35,14,14,0,5507678,89945,0,50,'finished'),(35,30,15,15,0,5515012,90056,0,50,'finished'),(36,37,16,16,0,5522345,90167,0,50,'finished'),(37,39,17,17,0,5529678,90278,0,50,'finished'),(38,32,18,18,0,5537012,90389,0,50,'finished'),(39,38,19,19,0,5544345,90500,0,50,'finished'),(40,40,20,20,0,5551678,90612,0,50,'finished'),(41,45,1,2,19,5312456,76834,1,58,'finished'),(42,47,2,1,25,5319789,77056,0,58,'finished'),(43,41,3,3,15,5327123,77278,0,58,'finished'),(44,43,4,4,12,5334456,77500,0,58,'finished'),(45,46,5,5,10,5341789,77723,0,58,'finished'),(46,48,6,NULL,0,NULL,77945,0,31,'DNF'),(47,49,7,6,8,5349123,78167,0,58,'finished'),(48,44,8,7,6,5356456,78389,0,58,'finished'),(49,54,9,8,4,5363789,78612,0,58,'finished'),(50,56,10,9,2,5371123,78834,0,58,'finished'),(51,42,11,10,1,5378456,79056,0,58,'finished'),(52,53,12,11,0,5385789,79278,0,58,'finished'),(53,51,13,12,0,5393123,79500,0,58,'finished'),(54,55,14,13,0,5400456,79723,0,58,'finished'),(55,50,15,14,0,5407789,79945,0,58,'finished'),(56,57,16,15,0,5415123,80167,0,58,'finished'),(57,59,17,16,0,5422456,80389,0,58,'finished'),(58,52,18,17,0,5429789,80612,0,58,'finished'),(59,58,19,18,0,5437123,80834,0,58,'finished'),(60,60,20,19,0,5444456,81056,0,58,'finished'),(61,61,1,1,25,12200,312000,0,12,'finished');
/*!40000 ALTER TABLE `race_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `races`
--

DROP TABLE IF EXISTS `races`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `races` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `circuit_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `round_number` int NOT NULL,
  `race_date` date NOT NULL,
  `qualifying_date` date DEFAULT NULL,
  `has_sprint` tinyint DEFAULT '0',
  `sprint_date` date DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_round` (`season_id`,`round_number`),
  KEY `circuit_id` (`circuit_id`),
  CONSTRAINT `races_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `races_ibfk_2` FOREIGN KEY (`circuit_id`) REFERENCES `circuits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `races`
--

LOCK TABLES `races` WRITE;
/*!40000 ALTER TABLE `races` DISABLE KEYS */;
INSERT INTO `races` VALUES (1,1,1,'Bahrain Grand Prix',1,'2025-03-02','2025-03-01',0,NULL,'completed'),(2,1,2,'Saudi Arabian Grand Prix',2,'2025-03-09','2025-03-08',0,NULL,'completed'),(3,1,3,'Australian Grand Prix',3,'2025-03-23','2025-03-22',0,NULL,'completed'),(4,1,4,'Japanese Grand Prix',4,'2025-04-06','2025-04-05',0,NULL,'completed'),(5,1,5,'Chinese Grand Prix',5,'2025-04-20','2025-04-19',1,'2025-04-19','scheduled');
/*!40000 ALTER TABLE `races` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles_permissions`
--

DROP TABLE IF EXISTS `roles_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` enum('create','read','update','delete') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_resource_action` (`role`,`resource`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles_permissions`
--

LOCK TABLES `roles_permissions` WRITE;
/*!40000 ALTER TABLE `roles_permissions` DISABLE KEYS */;
INSERT INTO `roles_permissions` VALUES (42,'admin','audit_log','read'),(13,'admin','circuits','create'),(14,'admin','circuits','read'),(15,'admin','circuits','update'),(16,'admin','circuits','delete'),(43,'admin','export','read'),(33,'admin','penalties','create'),(34,'admin','penalties','read'),(35,'admin','penalties','update'),(36,'admin','penalties','delete'),(9,'admin','people','create'),(10,'admin','people','read'),(11,'admin','people','update'),(12,'admin','people','delete'),(25,'admin','race_entries','create'),(26,'admin','race_entries','read'),(27,'admin','race_entries','update'),(28,'admin','race_entries','delete'),(21,'admin','races','create'),(22,'admin','races','read'),(23,'admin','races','update'),(24,'admin','races','delete'),(29,'admin','results','create'),(30,'admin','results','read'),(31,'admin','results','update'),(32,'admin','results','delete'),(17,'admin','seasons','create'),(18,'admin','seasons','read'),(19,'admin','seasons','update'),(20,'admin','seasons','delete'),(41,'admin','standings','read'),(5,'admin','teams','create'),(6,'admin','teams','read'),(7,'admin','teams','update'),(8,'admin','teams','delete'),(37,'admin','telemetry','create'),(38,'admin','telemetry','read'),(39,'admin','telemetry','update'),(40,'admin','telemetry','delete'),(1,'admin','users','create'),(2,'admin','users','read'),(3,'admin','users','update'),(4,'admin','users','delete'),(78,'driver','circuits','read'),(79,'driver','penalties','read'),(80,'driver','people','read'),(75,'driver','results','read'),(77,'driver','standings','read'),(76,'driver','telemetry','read'),(74,'engineer','people','read'),(69,'engineer','pit_stops','create'),(70,'engineer','pit_stops','read'),(71,'engineer','pit_stops','update'),(72,'engineer','results','read'),(73,'engineer','teams','read'),(66,'engineer','telemetry','create'),(67,'engineer','telemetry','read'),(68,'engineer','telemetry','update'),(88,'fan','circuits','read'),(87,'fan','results','read'),(86,'fan','standings','read'),(83,'media','circuits','read'),(84,'media','people','read'),(81,'media','results','read'),(82,'media','standings','read'),(85,'media','teams','read'),(55,'race_director','circuits','read'),(51,'race_director','penalties','create'),(52,'race_director','penalties','read'),(53,'race_director','penalties','update'),(56,'race_director','people','read'),(45,'race_director','race_entries','create'),(46,'race_director','race_entries','read'),(47,'race_director','race_entries','update'),(44,'race_director','races','read'),(48,'race_director','results','create'),(49,'race_director','results','read'),(50,'race_director','results','update'),(54,'race_director','standings','read'),(57,'race_director','teams','read'),(64,'team_manager','circuits','read'),(65,'team_manager','export','read'),(60,'team_manager','people','read'),(61,'team_manager','results','read'),(63,'team_manager','standings','read'),(58,'team_manager','teams','read'),(59,'team_manager','teams','update'),(62,'team_manager','telemetry','read');
/*!40000 ALTER TABLE `roles_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seasons`
--

DROP TABLE IF EXISTS `seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` year NOT NULL,
  `champion_person_id` int DEFAULT NULL,
  `champion_team_id` int DEFAULT NULL,
  `is_active` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `year` (`year`),
  KEY `fk_seasons_champion_person` (`champion_person_id`),
  KEY `fk_seasons_champion_team` (`champion_team_id`),
  CONSTRAINT `fk_seasons_champion_person` FOREIGN KEY (`champion_person_id`) REFERENCES `people` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_seasons_champion_team` FOREIGN KEY (`champion_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seasons`
--

LOCK TABLES `seasons` WRITE;
/*!40000 ALTER TABLE `seasons` DISABLE KEYS */;
INSERT INTO `seasons` VALUES (1,2025,NULL,NULL,1),(2,2026,NULL,NULL,0);
/*!40000 ALTER TABLE `seasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `data` mediumtext COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `last_activity` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('okep8bq7kog2s3jjjgl2cp7qnm',1,NULL,'127.0.0.1','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:149.0) Gecko/20100101 Firefox/149.0','2026-04-26 22:31:13');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sprint_results`
--

DROP TABLE IF EXISTS `sprint_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sprint_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `race_entry_id` int NOT NULL,
  `finish_position` int DEFAULT NULL,
  `points_scored` float DEFAULT '0',
  `status` enum('finished','DNF','DNS','DSQ') COLLATE utf8mb4_unicode_ci DEFAULT 'finished',
  PRIMARY KEY (`id`),
  UNIQUE KEY `race_entry_id` (`race_entry_id`),
  CONSTRAINT `sprint_results_ibfk_1` FOREIGN KEY (`race_entry_id`) REFERENCES `race_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sprint_results`
--

LOCK TABLES `sprint_results` WRITE;
/*!40000 ALTER TABLE `sprint_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `sprint_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_seasons`
--

DROP TABLE IF EXISTS `team_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_seasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `season_id` int NOT NULL,
  `principal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `car_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `power_unit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_points` float DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_season` (`team_id`,`season_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `team_seasons_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_seasons_ibfk_2` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_seasons`
--

LOCK TABLES `team_seasons` WRITE;
/*!40000 ALTER TABLE `team_seasons` DISABLE KEYS */;
INSERT INTO `team_seasons` VALUES (1,1,1,'Christian Horner','RB21','Honda RBPT','Milton Keynes, UK',81),(2,2,1,'Toto Wolff','W16','Mercedes','Brackley, UK',52),(3,3,1,'Frederic Vasseur','SF-25','Ferrari','Maranello, Italy',78),(4,4,1,'Andrea Stella','MCL39','Mercedes','Woking, UK',88),(5,5,1,'Mike Krack','AMR25','Mercedes','Silverstone, UK',18),(6,6,1,'Oliver Oakes','A525','Renault','Enstone, UK',0),(7,7,1,'James Vowles','FW47','Mercedes','Grove, UK',8),(8,8,1,'Laurent Mekies','VCARB 02','Honda RBPT','Faenza, Italy',4),(9,9,1,'Mattia Binotto','C45','Ferrari','Hinwil, Switzerland',0),(10,10,1,'Ayao Komatsu','VF-25','Ferrari','Kannapolis, USA',0);
/*!40000 ALTER TABLE `team_seasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `founded_year` int DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,'Red Bull Racing','RBR','Austrian',2005,1),(2,'Mercedes','MER','German',1954,1),(3,'Ferrari','FER','Italian',1950,1),(4,'McLaren','MCL','British',1966,1),(5,'Aston Martin','AMR','British',2021,1),(6,'Alpine','ALP','French',2021,1),(7,'Williams','WIL','British',1977,1),(8,'RB Formula One Team','RB','Italian',2024,1),(9,'Kick Sauber','SAU','Swiss',1993,1),(10,'Haas F1 Team','HAA','American',2016,1),(12,'test','TEST','test',NULL,0),(15,'test 2','TES','jkjk',NULL,0),(16,'test 3','TES','jkjk',NULL,0);
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','race_director','team_manager','engineer','driver','media','fan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `linked_id` int DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  `must_change_password` tinyint DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@f1app.com','$2y$12$zYWFgYaVXOfh64jHZvEh0O8u6jG6q3JeINOzp9UGmhCCe5PVlX5bu','admin',NULL,1,0,'2026-04-26 22:31:13','2026-04-25 03:46:37',NULL),(2,'Race Director','director@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','race_director',NULL,1,0,'2026-04-26 22:19:13','2026-04-25 03:46:37',NULL),(3,'Red Bull Manager','manager@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','team_manager',1,1,0,'2026-04-26 22:08:47','2026-04-25 03:46:37',NULL),(4,'RB Engineer','engineer@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','engineer',1,1,0,'2026-04-26 22:02:46','2026-04-25 03:46:37',NULL),(5,'Max Verstappen','driver@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','driver',1,1,0,'2026-04-26 22:30:06','2026-04-25 03:46:37',NULL),(6,'Media User','media@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','media',NULL,1,0,'2026-04-26 21:51:03','2026-04-25 03:46:37',NULL),(7,'Fan User','fan@f1app.com','$2y$12$0c2Gl.bRUKx2.VUymkMKJe/Zs9LfGKYfNIcwMPhz1538y6bhnTCzq','fan',NULL,1,0,'2026-04-26 21:39:55','2026-04-25 03:46:37',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'f1app'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-26 22:47:25
