-- ============================================================
--  PULSESTREAM — MySQL / MariaDB schema for XAMPP (phpMyAdmin)
--  Import: phpMyAdmin -> Import -> choose this file -> Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pulsestream`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pulsestream`;

-- ------------------------------------------------------------
-- 1) Every visit / page view (tells you how many users tried the site)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_id`  CHAR(32)        NOT NULL,
  `ip_address`  VARCHAR(45)     NOT NULL DEFAULT '0.0.0.0',
  `user_agent`  VARCHAR(500)    NULL,
  `referrer`    VARCHAR(500)    NULL,
  `page`        VARCHAR(190)    NOT NULL DEFAULT '/',
  `device_type` ENUM('mobile','tablet','desktop') NOT NULL DEFAULT 'desktop',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visits_visitor` (`visitor_id`),
  KEY `idx_visits_created` (`created_at`),
  KEY `idx_visits_device`  (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2) Every fetch / download attempt
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `download_attempts` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_id`       CHAR(32)        NOT NULL,
  `ip_address`       VARCHAR(45)     NOT NULL DEFAULT '0.0.0.0',
  `platform`         ENUM('youtube','tiktok','instagram','unknown') NOT NULL DEFAULT 'unknown',
  `action`           ENUM('fetch','download','copy_link','history_redownload') NOT NULL DEFAULT 'fetch',
  `url`              VARCHAR(1000)   NOT NULL,
  `title`            VARCHAR(500)    NULL,
  `author`           VARCHAR(255)    NULL,
  `format`           ENUM('mp4','mp3') NULL,
  `quality`          ENUM('max','standard','compact') NULL,
  `status`           ENUM('success','error') NOT NULL DEFAULT 'success',
  `error_message`    VARCHAR(500)    NULL,
  `duration_seconds` INT UNSIGNED    NULL,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_att_visitor`  (`visitor_id`),
  KEY `idx_att_platform` (`platform`),
  KEY `idx_att_action`   (`action`),
  KEY `idx_att_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3) Fast daily rollup counters
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_stats` (
  `stat_date` DATE            NOT NULL,
  `visits`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `fetches`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `downloads` INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4) Handy reporting views
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_traffic_overview` AS
SELECT
  (SELECT COUNT(*) FROM `visits`)                                    AS total_visits,
  (SELECT COUNT(DISTINCT visitor_id) FROM `visits`)                  AS unique_visitors,
  (SELECT COUNT(*) FROM `download_attempts`)                         AS total_attempts,
  (SELECT COUNT(*) FROM `download_attempts` WHERE action='download') AS total_downloads,
  (SELECT COUNT(*) FROM `download_attempts` WHERE status='error')    AS failed_attempts;

CREATE OR REPLACE VIEW `v_platform_breakdown` AS
SELECT platform,
       COUNT(*)                                   AS attempts,
       SUM(action = 'download')                   AS downloads,
       SUM(status = 'error')                      AS errors,
       COUNT(DISTINCT visitor_id)                 AS users
FROM `download_attempts`
GROUP BY platform
ORDER BY attempts DESC;

CREATE OR REPLACE VIEW `v_last_30_days` AS
SELECT stat_date, visits, fetches, downloads
FROM `daily_stats`
WHERE stat_date >= CURDATE() - INTERVAL 30 DAY
ORDER BY stat_date DESC;

-- ------------------------------------------------------------
-- 5) Useful ad-hoc queries (copy/paste into the SQL tab)
-- ------------------------------------------------------------
-- How many people tried the website in total?
-- SELECT COUNT(DISTINCT visitor_id) AS people_who_tried FROM visits;

-- How many tried it today?
-- SELECT COUNT(DISTINCT visitor_id) AS people_today FROM visits WHERE DATE(created_at) = CURDATE();

-- Most requested media
-- SELECT title, platform, COUNT(*) c FROM download_attempts GROUP BY title, platform ORDER BY c DESC LIMIT 20;

-- Mobile vs desktop split
-- SELECT device_type, COUNT(*) FROM visits GROUP BY device_type;
