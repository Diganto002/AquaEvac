-- =========================================================================
-- AquaEvac - Safe XAMPP Database Upgrade
-- =========================================================================
-- Purpose:
--   Upgrade an EXISTING aquaevac_db database to the current AquaEvac build.
--
-- IMPORTANT:
--   - This script is safe to run more than once.
--   - It checks whether each new column/index already exists before adding it.
--   - It does NOT drop the database or delete normal user/project data.
--   - For a brand-new installation, import schema.sql instead.
--
-- Tested target: MySQL 8.x / XAMPP MySQL 8.x
-- =========================================================================

CREATE DATABASE IF NOT EXISTS aquaevac_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE aquaevac_db;

-- -------------------------------------------------------------------------
-- 1. USERS: session invalidation + responder availability
-- -------------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users'
       AND column_name = 'session_version') = 0,
    'ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users'
       AND column_name = 'responder_availability') = 0,
    'ALTER TABLE users ADD COLUMN responder_availability ENUM(''available'',''busy'',''offline'') NOT NULL DEFAULT ''available''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------------
-- 2. SOS ALERTS: lifecycle timestamps + verified location source
-- -------------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'location_source') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN location_source ENUM(''gps'',''manual'') NOT NULL DEFAULT ''gps''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'acknowledged_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN acknowledged_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'assigned_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN assigned_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'in_progress_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN in_progress_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'resolved_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'cancelled_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND column_name = 'last_location_update_at') = 0,
    'ALTER TABLE sos_alerts ADD COLUMN last_location_update_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add the lifecycle index only if it is missing.
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'sos_alerts'
       AND index_name = 'idx_sos_lifecycle') = 0,
    'ALTER TABLE sos_alerts ADD INDEX idx_sos_lifecycle (status, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------------
-- 3. SHELTER CHECK-INS: prevent duplicate active check-ins
-- -------------------------------------------------------------------------
-- Before adding the unique key, clean up duplicate ACTIVE check-ins for the
-- same authenticated user/shelter. The oldest record is kept active; newer
-- duplicates are marked checked out. This prevents the upgrade from failing.
UPDATE shelter_checkins sc
JOIN (
    SELECT shelter_id, user_id, MIN(id) AS keep_id
    FROM shelter_checkins
    WHERE checked_out_at IS NULL
      AND user_id IS NOT NULL
    GROUP BY shelter_id, user_id
    HAVING COUNT(*) > 1
) dup
  ON dup.shelter_id = sc.shelter_id
 AND dup.user_id = sc.user_id
SET sc.checked_out_at = NOW()
WHERE sc.checked_out_at IS NULL
  AND sc.id <> dup.keep_id;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'shelter_checkins'
       AND column_name = 'active_checkin_key') = 0,
    'ALTER TABLE shelter_checkins ADD COLUMN active_checkin_key VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN checked_out_at IS NULL AND user_id IS NOT NULL THEN CONCAT(shelter_id, CHAR(58), user_id) ELSE NULL END) STORED',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'shelter_checkins'
       AND index_name = 'uq_active_checkin_user_shelter') = 0,
    'ALTER TABLE shelter_checkins ADD UNIQUE KEY uq_active_checkin_user_shelter (active_checkin_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------------
-- 4. FLOOD PREDICTIONS: keep AI predictions separate from verified labels
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS flood_predictions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rainfall_mm    DECIMAL(7, 2) NOT NULL,
    river_level_m  DECIMAL(6, 2) NOT NULL,
    elevation_m    DECIMAL(7, 2) NOT NULL,
    predicted_risk ENUM('low', 'moderate', 'high', 'severe') NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_predictions_created (created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 5. Initialize missing values for existing records where appropriate.
-- -------------------------------------------------------------------------
UPDATE users
SET session_version = 1
WHERE session_version IS NULL;

UPDATE users
SET responder_availability = 'offline'
WHERE role <> 'responder'
  AND responder_availability IS NULL;

-- -------------------------------------------------------------------------
-- Done.
-- The application can now use the current AquaEvac code without manually
-- adding session_version, responder_availability, SOS lifecycle fields, or
-- shelter duplicate-check fields one at a time.
-- -------------------------------------------------------------------------
