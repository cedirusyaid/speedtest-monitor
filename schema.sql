-- ============================================================
-- Schema Database Speedtest Monitoring Center
-- Database: db_monitoring
-- Table: log_speedtest & log_speedtest_notif
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_monitoring` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_monitoring`;

CREATE TABLE IF NOT EXISTS `log_speedtest` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ping_ms` DECIMAL(8,2) NULL COMMENT 'Latency dalam milidetik (ms)',
  `jitter_ms` DECIMAL(8,2) NULL COMMENT 'Jitter dalam milidetik (ms)',
  `download_mbps` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Kecepatan download (Mbps)',
  `upload_mbps` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Kecepatan upload (Mbps)',
  `packet_loss_pct` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Persentase packet loss',
  `isp_name` VARCHAR(150) NULL COMMENT 'Nama ISP / Provider',
  `server_name` VARCHAR(150) NULL COMMENT 'Nama Server Speedtest',
  `server_sponsor` VARCHAR(150) NULL COMMENT 'Sponsor Server',
  `server_location` VARCHAR(100) NULL COMMENT 'Lokasi Server',
  `client_ip` VARCHAR(45) NULL COMMENT 'IP Publik Klien',
  `wifi_ssid` VARCHAR(100) NULL COMMENT 'SSID WiFi / Tipe Koneksi Lokal',
  `raw_output` LONGTEXT NULL COMMENT 'Raw JSON output',
  `status` ENUM('SUCCESS', 'FAILED') DEFAULT 'SUCCESS',
  `error_message` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_status` (`status`),
  INDEX `idx_isp_name` (`isp_name`),
  INDEX `idx_wifi_ssid` (`wifi_ssid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `log_speedtest_notif` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `notif_type` VARCHAR(50) NOT NULL DEFAULT 'DAILY_09AM',
  `period_key` VARCHAR(50) NOT NULL COMMENT 'Format YYYY-MM-DD atau YYYY-MM-DD HH',
  `speedtest_id` BIGINT UNSIGNED NULL,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `message_preview` TEXT NULL,
  `status` ENUM('SUCCESS', 'FAILED') DEFAULT 'SUCCESS',
  UNIQUE KEY `uniq_period_type` (`notif_type`, `period_key`),
  INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
