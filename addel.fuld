/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: p2pmonero
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1

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
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `balance_ledger`
--

DROP TABLE IF EXISTS `balance_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `balance_ledger` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `related_type` enum('deposit','withdrawal','escrow_lock','escrow_release','fee') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `amount` decimal(20,12) NOT NULL,
  `direction` enum('credit','debit') NOT NULL,
  `status` enum('locked','unlocked') NOT NULL DEFAULT 'locked',
  `balance_after` decimal(20,12) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balance_ledger`
--

LOCK TABLES `balance_ledger` WRITE;
/*!40000 ALTER TABLE `balance_ledger` DISABLE KEYS */;
INSERT INTO `balance_ledger` VALUES
(1,2,'deposit',1,0.001000000000,'credit','unlocked',0.001000000000,'2026-01-26 06:57:14'),
(2,2,'deposit',2,0.001653922900,'credit','unlocked',0.002653922900,'2026-01-26 06:57:14'),
(3,1,'deposit',3,0.001000000000,'credit','unlocked',0.001000000000,'2026-01-26 07:17:05');
/*!40000 ALTER TABLE `balance_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deposits`
--

DROP TABLE IF EXISTS `deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deposits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subaddress_id` int(11) DEFAULT NULL,
  `txid` varchar(100) NOT NULL,
  `amount` decimal(20,12) NOT NULL,
  `confirmations` int(11) NOT NULL DEFAULT 0,
  `credited` tinyint(1) NOT NULL DEFAULT 0,
  `height` int(11) DEFAULT NULL,
  `unlock_height` int(11) DEFAULT NULL,
  `blocks_left` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','locked','confirmed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `seen_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `txid` (`txid`),
  KEY `user_id` (`user_id`),
  KEY `fk_deposits_subaddress` (`subaddress_id`),
  CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deposits_subaddress` FOREIGN KEY (`subaddress_id`) REFERENCES `subaddresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposits`
--

LOCK TABLES `deposits` WRITE;
/*!40000 ALTER TABLE `deposits` DISABLE KEYS */;
INSERT INTO `deposits` VALUES
(1,2,2,'e7973df817a3f9a0f97ed4ea9d95b8551c869d6fbb9295c237df8a8198c6635d',0.001000000000,368,1,3595746,NULL,0,'confirmed','2026-01-26 06:57:14','2026-01-26 06:57:14'),
(2,2,2,'04185002ea27720ae5c1a3bc7fcd8ceb074d58f8b3df58d9b2705f65327819f2',0.001653922900,678,1,3595436,NULL,0,'confirmed','2026-01-26 06:57:14','2026-01-26 06:57:14'),
(3,1,3,'70554949b06cc50e192be4bfee85e77ffc291990d86bd97f35a9c1b762e08cc0',0.001000000000,10,1,3596118,NULL,0,'confirmed','2026-01-26 07:07:25','2026-01-26 07:07:25');
/*!40000 ALTER TABLE `deposits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listings`
--

DROP TABLE IF EXISTS `listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('buy','sell') NOT NULL,
  `crypto_pay` enum('btc','eth','ltc','bch','xrp','xlm','link','dot','yfi','sol','usdt') NOT NULL,
  `margin_percent` decimal(6,3) NOT NULL COMMENT 'Positive = over market, Negative = under market',
  `min_xmr` decimal(20,12) NOT NULL,
  `max_xmr` decimal(20,12) NOT NULL,
  `payment_time_limit` int(11) NOT NULL COMMENT 'Minutes before trade expires',
  `terms` text DEFAULT NULL,
  `status` enum('active','paused','closed') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_listing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listings`
--

LOCK TABLES `listings` WRITE;
/*!40000 ALTER TABLE `listings` DISABLE KEYS */;
INSERT INTO `listings` VALUES
(2,2,'buy','usdt',0.500,1.000000000000,10.000000000000,10,'ltcguyx fr4gh99r45g6yhi88hh4467jjgr45hh7jht4','active','2026-01-28 07:58:27'),
(3,2,'buy','bch',1.000,1.000000000000,10.000000000000,10,'ltcdf56tffttyuhbhrdse5578ikmfdsee45f6un8n8bfe35uj','active','2026-01-28 07:59:17'),
(4,1,'buy','btc',0.300,8.000000000000,25.000000000000,10,'btcvfdtyhjikiokny67h78ihy55fgy7u88ij','active','2026-01-28 15:57:54'),
(6,2,'sell','link',1.000,0.001000000000,0.002000000000,10,'linkdhshdjcjdiaiajnxjoekjdjcndjnd','active','2026-01-30 05:50:27');
/*!40000 ALTER TABLE `listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subaddresses`
--

DROP TABLE IF EXISTS `subaddresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subaddresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address` varchar(120) NOT NULL,
  `index_no` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `subaddresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subaddresses`
--

LOCK TABLES `subaddresses` WRITE;
/*!40000 ALTER TABLE `subaddresses` DISABLE KEYS */;
INSERT INTO `subaddresses` VALUES
(1,2,'88Z6Xz3e11m2Borr98s55vCbReWgWGY2hAHaaKS76Qnuc9L3Cw15KLNE3eiHgxcYArLz6B2MSpqsSMx1MTEL8PmZKS4NZwJ',3,'2026-01-23 11:43:49'),
(2,2,'8A5ngL5UZ6weiERjkfCFUMWH5iPJcR8FuWWecwiGdMrmVzXMTooStocHwuu4JR3Xvp7EDZWg5UTvQYB9gTsuUV2gUzDejxw',4,'2026-01-25 08:21:49'),
(3,1,'8AwycLJkpim6v95Lpz2BTa11oSz2Nd98MJ1hevZBk68jda82Y6SjE6YedArUnuppVqRwNYkEwso5fj5sKU12ADRyGSkVZnh',5,'2026-01-26 06:59:31'),
(4,2,'86EzGrABuLZHXz4kE1Susz7GBhcPoHNfD5XTJHXsWxMQSMi4FccF2oodttXebjKoZ9iDsyFLcdTCTZtVBNDEX1xUGpVNeSE',6,'2026-01-26 18:45:21'),
(5,2,'882kwQPAa2Q3hzhHSHrYNgGX4FQeLBmP9bgerbub7x7xhas8X2wLoBSVHSYtuyj83gWu6ymETf4qQdb9k9CMGJCMFudTcqY',7,'2026-01-26 18:45:32'),
(6,3,'89eL4gfGpRF9gw9XG6R7oPNuC7KcPY5EAZ1fiRgmtQ4jG5KSMzhu8zeCZfbj7isgaMKdW7Q7kQh7rEHF4xPYzEpwHWTBMq1',8,'2026-01-27 04:09:52'),
(7,6,'84ZydfJdb3HNzfRgBE7wkza8rbBYtz7XPZNXgdHGHQf9G5QLae6VRTgVWmfUtpGvCC7m8dr3kVmxBKEh5BriHt9mVnJfJd7',9,'2026-01-31 08:08:51'),
(8,6,'8A7GkvyaCPDV5A9iQWMCgGEhLLZjwae1dZpioDXDPowM5kyuWxC2QQYMRN9WWk7JVkZG9QYkpnAFpXet33Zttwb7FU6E4wJ',10,'2026-01-31 08:08:55'),
(9,6,'84HpX3Q4zTsffGVZfq4eb7i7Q1gqx1KQ3fL9PwoyqYrqT18DqkwKbD1i6Y37rNSiwahLAKQR5xceJXQezUYpz7gh3jghxxz',11,'2026-01-31 08:08:58'),
(10,6,'84VNmK3QhBB6TJ57FvF5nTQkGJVJhtqm3Nzin7fSKa2ujju2JYbdkAjhZaxZ9ELtyqXwid1P7V4Z9BhG1wBRcRKQNAqsLPz',12,'2026-01-31 08:09:05');
/*!40000 ALTER TABLE `subaddresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trade_payments`
--

DROP TABLE IF EXISTS `trade_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trade_payments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `trade_id` bigint(20) NOT NULL,
  `crypto` enum('btc','eth','ltc','bch','xrp','xlm','link','dot','yfi','sol','usdt') NOT NULL,
  `txid` varchar(255) NOT NULL,
  `amount` decimal(20,12) NOT NULL,
  `confirmations` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_txid` (`txid`),
  KEY `fk_payment_trade` (`trade_id`),
  CONSTRAINT `fk_payment_trade` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trade_payments`
--

LOCK TABLES `trade_payments` WRITE;
/*!40000 ALTER TABLE `trade_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `trade_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trades`
--

DROP TABLE IF EXISTS `trades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trades` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `xmr_amount` decimal(20,12) NOT NULL,
  `crypto_pay` enum('btc','eth','ltc','bch','xrp','xlm','link','dot','yfi','sol','usdt') NOT NULL,
  `market_price_snapshot` decimal(20,12) NOT NULL COMMENT 'Live market price at trade start',
  `margin_percent` decimal(6,3) NOT NULL,
  `final_price` decimal(20,12) NOT NULL COMMENT 'Market price after margin applied',
  `crypto_amount` decimal(20,12) NOT NULL COMMENT 'Amount buyer must pay',
  `fee_xmr` decimal(20,12) NOT NULL COMMENT '1% XMR fee paid by buyer',
  `status` enum('pending_payment','paid','released','cancelled','expired','disputed') NOT NULL DEFAULT 'pending_payment',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_buyer` (`buyer_id`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_trade_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_trade_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`),
  CONSTRAINT `fk_trade_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trades`
--

LOCK TABLES `trades` WRITE;
/*!40000 ALTER TABLE `trades` DISABLE KEYS */;
/*!40000 ALTER TABLE `trades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `pgp_public` text DEFAULT NULL,
  `recovery_code_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `backup_completed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Habibi','$argon2id$v=19$m=65536,t=4,p=1$MHQycFhOWmVCZkMwWnVCSQ$ruWWljCgHMvFBNNatLdOO/LkoWW43Q2k5BaooP57lfk','-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmDMEaXMz5xYJKwYBBAHaRw8BAQdApXsjnCHHLL4MJPzXCQwJI5NWFu5J/GOXQ1IB\nY6tuUFe0H0hhYmliaSA8SGFiaWJpQHAycG1vbmVyby5sb2NhbD6IkwQTFgoAOxYh\nBLLl7cBaYo3xjBHkZJ0nYWXwNi4OBQJpczPnAhsDBQsJCAcCAiICBhUKCQgLAgQW\nAgMBAh4HAheAAAoJEJ0nYWXwNi4OJfMBAI/gIUYe0hVShRY00xUJshE1OAd2Ob7H\nxHFlk4XzwJRYAQDvT5nTSLgIIB59+EbMJpPdZODUHIsOZwCbdT9cwF+ZDbg4BGlz\nM+cSCisGAQQBl1UBBQEBB0BzHnm1vaaXMdK/4Y04VU9Neq27b1DGREJV0DLONwjo\nUQMBCAeIeAQYFgoAIBYhBLLl7cBaYo3xjBHkZJ0nYWXwNi4OBQJpczPnAhsMAAoJ\nEJ0nYWXwNi4Ou60A/j4WZ+WVW4IIr879lkQqhafmZhvPbsa6Wdrl806MxR7OAQCV\nunQu+CIEuxcul9/QH1sTlGRSxw0BVeMXFfWQirjyD5gzBGlzTNgWCSsGAQQB2kcP\nAQEHQFKhkgLHU+NcCra2xIy2ECe+5Pwu1ElrRdk9br3uz9FJtB9IYWJpYmkgPEhh\nYmliaUBwMnBtb25lcm8ubG9jYWw+iJMEExYKADsWIQTwsZU85lZwZJRBfscDHxVR\n/zddFQUCaXNM2AIbAwULCQgHAgIiAgYVCgkICwIEFgIDAQIeBwIXgAAKCRADHxVR\n/zddFU2tAQC3ARKTIPkJytcPBpKV5vGPzykihdHpw1UtmS58SmtRJwD9Fho0DRFj\n51nOib3viOPSr7Vp+D71/gUHOzgFprNwPgm4OARpc0zYEgorBgEEAZdVAQUBAQdA\nUNGPRx3tZWPWUX46CYkYYNtLsnE2fTuewy82rq/FnlMDAQgHiHgEGBYKACAWIQTw\nsZU85lZwZJRBfscDHxVR/zddFQUCaXNM2AIbDAAKCRADHxVR/zddFToaAQDqYICM\nhFY6OJT7/jvst2vI447OFbSXAoRZQpGaUovepwEAz5UAUPmf1ff71eFh0mTy+hr7\nUTpyGSvnAmyrslWw5ASYMwRpc1HCFgkrBgEEAdpHDwEBB0Dz2BlNvHWvqDPF2Uwe\nMYzMkzmXH06/9hHvyKkzJvp6VLQfSGFiaWJpIDxIYWJpYmlAcDJwbW9uZXJvLmxv\nY2FsPoiTBBMWCgA7FiEES+iLq5WKOO9WBI4S1dI5vzVHjTYFAmlzUcICGwMFCwkI\nBwICIgIGFQoJCAsCBBYCAwECHgcCF4AACgkQ1dI5vzVHjTYzUAEArRey3Mki79W7\nYxSrspZzJpNTTjwfETRYNIrwhN3tJ6cBAKJQ97YClK2VOfcgmukTo+iiA3hZ2aiP\nNGFiPSJ11zQAuDgEaXNRwhIKKwYBBAGXVQEFAQEHQHAFSYXBoq8PkR4jEzP661bk\nrWrsbLmwCMQNN8zdErsOAwEIB4h4BBgWCgAgFiEES+iLq5WKOO9WBI4S1dI5vzVH\njTYFAmlzUcICGwwACgkQ1dI5vzVHjTbJ8wD/RNc15pgNYGxD8EKtmJWieZ1sSRFs\nA134whOZjta2f6QBAM7eXMQy+dQTo5ogVCtYmyD+SNsbdFTazR8SjgQJF/EM\n=iU3l\n-----END PGP PUBLIC KEY BLOCK-----','$2y$10$LCUJMMSi08KuECgyVhy7aOOhs57/AALxmhMIM9h7x4gzk01AdU.Wm','2026-01-17 13:12:20',1),
(2,'anonwan','$argon2id$v=19$m=65536,t=4,p=1$dmo1dDliNlpBWndjblFidg$HIXtQqIVb5m+hK+UJ82Jq8OBWwNAT70MUWOWlyCZHkA','-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmDMEaXNSVxYJKwYBBAHaRw8BAQdAP2lHWTViI0d/0h09UVOqdgaYej9rdSp7Ymoo\nIgxB5w+0IWFub253YW4gPGFub253YW5AcDJwbW9uZXJvLmxvY2FsPoiTBBMWCgA7\nFiEEh1/sovWzy1mzVMLoJ7W21U6FMbwFAmlzUlcCGwMFCwkIBwICIgIGFQoJCAsC\nBBYCAwECHgcCF4AACgkQJ7W21U6FMbzD0QEAiKwtrLLP8JeIVXVRAGSU4shtit/d\nSXXJdA2fGiPFo7YBAKVXmm736FoNCyGcAWsMZkGTakmhXAqKFCA1DTJALlQNuDgE\naXNSVxIKKwYBBAGXVQEFAQEHQBo05fwVYHuY9WNKsYX6T5Ryb7dtJsl03/ds5jh+\nGchdAwEIB4h4BBgWCgAgFiEEh1/sovWzy1mzVMLoJ7W21U6FMbwFAmlzUlcCGwwA\nCgkQJ7W21U6FMbxw7AEAqVdiqBbj83ILpp8FNiaZQnG2tpjRMXUrtPFOMAOVxg8B\nAII3CLOJKpEEhvGD54aYACGCtIViJZrCFXzakwiOpsED\n=NxnJ\n-----END PGP PUBLIC KEY BLOCK-----','$2y$10$4ZGOTHQEQxR0Wql1iq81NOxtXREc3o1IMLmcgwdkdU4OI5a3B858q','2026-01-23 10:25:04',1),
(3,'sawiti','$argon2id$v=19$m=65536,t=4,p=1$MHFURjBxQklPZ0cwek9iZA$1htnYi50g9B5SvjccxMl0GECfGF9VVRuJyjdJGEdHPI','-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmDMEaXg5bxYJKwYBBAHaRw8BAQdAh/d+fwiLJtcwI09O6qO4oz/o+qG+l0991nWH\ngWiqyta0H3Nhd2l0aSA8c2F3aXRpQHAycG1vbmVyby5sb2NhbD6IkwQTFgoAOxYh\nBARClHQjZNzRmW4NwaknTxc0fJJaBQJpeDlvAhsDBQsJCAcCAiICBhUKCQgLAgQW\nAgMBAh4HAheAAAoJEKknTxc0fJJai2gA+gPBuT31kHOWTJ6b5KkyoynxcIhLTj/y\nZOX8F7chuJcDAQD/3Wrm4AtYzLil2Bb0fS0ExXub8DNLylFR6aYKYZoSArg4BGl4\nOW8SCisGAQQBl1UBBQEBB0CAIER/zXnZSMgOmoGiFpz9T1huxF6+OCwHsfnoCsak\ndAMBCAeIeAQYFgoAIBYhBARClHQjZNzRmW4NwaknTxc0fJJaBQJpeDlvAhsMAAoJ\nEKknTxc0fJJaX+0BAKJA0f0XuEjFv3OtTnd9ieeVXPcxYMJABTF6G8cQ+w78AQDA\n57Z3rPS9lOZfPtkq8XCo7MtHmW+dnO4QLYrvIIxECw==\n=TSNP\n-----END PGP PUBLIC KEY BLOCK-----','$2y$10$gfnxuCtdgNufWLOdG557N.to9lJqeCfg/7pWLoXCtSRjuKvzKav.6','2026-01-24 21:51:47',1),
(4,'Danie','$argon2id$v=19$m=65536,t=4,p=1$TTZUMHhjTTU4Z0dESE1JRg$hczeQMCpVjjzdwPbC5z0/7pFb7mSKVNoHRlKk4r8FWQ',NULL,NULL,'2026-01-28 19:32:30',0),
(5,'homelander','$argon2id$v=19$m=65536,t=4,p=1$Z2hERDlERDFGUjEyTXRabA$mEdip3qjV/ZX5TSfRjdVEbVGq6igWfCI1a8pn/cPEAg','-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmDMEaXpthxYJKwYBBAHaRw8BAQdAab7jP0PEDGdU+Rsry9nU48fDcLOq8It7d7GP\noO4Yh6W0J2hvbWVsYW5kZXIgPGhvbWVsYW5kZXJAcDJwbW9uZXJvLmxvY2FsPoiT\nBBMWCgA7FiEEz/t3YWPKRWSfR3AX2JySHTqhIY0FAml6bYcCGwMFCwkIBwICIgIG\nFQoJCAsCBBYCAwECHgcCF4AACgkQ2JySHTqhIY0WrwEA+l0Puoz5oDB2+8F41NzR\nHXbuSxO4DqyBRHZdZugoLfgA/jIujwa2QouBMmNuQnGIWeTjtyuHxx5hKyZOMb/v\nXB8IuDgEaXpthxIKKwYBBAGXVQEFAQEHQMz6dEXMuFryM/7i38FEa+62QdgAIzSs\n65r7zLJUbegbAwEIB4h4BBgWCgAgFiEEz/t3YWPKRWSfR3AX2JySHTqhIY0FAml6\nbYcCGwwACgkQ2JySHTqhIY305gD+OItDi71Qgam+2SzrzJ34OHooVRtKY5cLcs8B\nVCu6eDgBAMYoIUqekcjyV4J+W5vObRW41PNozWWj3kJeHFcifKIP\n=mdwj\n-----END PGP PUBLIC KEY BLOCK-----','$2y$10$qLHOVzYhJeSnZRoH5SIeU.W6ZO5yku5SKsL2JEF1zYAkA6S/K3.nS','2026-01-28 20:10:34',1),
(6,'Champez','$argon2id$v=19$m=65536,t=4,p=1$TzVhOEZmekd0QmdqWnIydQ$09xOfW3v8rk/s6jCbHvXr5UKRh28jxC8UBfz4UaG32Y','-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmDMEaX24SRYJKwYBBAHaRw8BAQdADxiGWKGKiY+3EE4l8Wf2F8yjfShe9JZKRg0Y\ngBcH1qa0IUNoYW1wZXogPENoYW1wZXpAcDJwbW9uZXJvLmxvY2FsPoiTBBMWCgA7\nFiEEysRsk+cUnWClXFTIOTmW5nm9qRkFAml9uEkCGwMFCwkIBwICIgIGFQoJCAsC\nBBYCAwECHgcCF4AACgkQOTmW5nm9qRkQBgD+P/LpmCX6nWFXOFUJV+dyMUfkqXap\nXaWmuyCDRrqQNHwBAIh+pNz/cR4lQmqjzBmkkdzHVL3/6qlNexeOdw6QumQIuDgE\naX24SRIKKwYBBAGXVQEFAQEHQMLevBRApAFZVlTD6S77caVb7jzMbzTF+E9JuhFj\nEt5RAwEIB4h4BBgWCgAgFiEEysRsk+cUnWClXFTIOTmW5nm9qRkFAml9uEkCGwwA\nCgkQOTmW5nm9qRnWWQEAvhzJri74AiHoQ8yoWuXUsGbue2kX5vme9B1jBIbz6VoA\n/10SXOd/wk++J0hz3XDTX0GKTSjLpTc7ywqOuf+HKPAC\n=Jw8m\n-----END PGP PUBLIC KEY BLOCK-----','$2y$10$AVZHl5ddMb/j5JQhF7uuDuPxpK5Kj8nI4h3MitlZ//OapbQL131sO','2026-01-31 08:06:15',1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawals`
--

DROP TABLE IF EXISTS `withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address` varchar(120) NOT NULL,
  `amount` decimal(20,12) NOT NULL,
  `txid` varchar(100) DEFAULT NULL,
  `status` enum('pending','broadcast','confirmed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `txid` (`txid`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawals`
--

LOCK TABLES `withdrawals` WRITE;
/*!40000 ALTER TABLE `withdrawals` DISABLE KEYS */;
/*!40000 ALTER TABLE `withdrawals` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-01  5:34:43
