-- ============================================================
-- schema.sql — โครงสร้างฐานข้อมูล plant_stress_db
-- ใช้ MySQLi / phpMyAdmin import ก็ได้ หรือรันผ่าน install/setup.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS plant_stress_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE plant_stress_db;

CREATE TABLE IF NOT EXISTS sensor_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    device_id     VARCHAR(20)  NOT NULL DEFAULT 'ESP32-01',
    class_id      TINYINT      NOT NULL COMMENT '0=Normal 1=Mild 2=Severe',
    class_label   VARCHAR(20)  NOT NULL,
    confidence    FLOAT        NOT NULL,
    soil_moisture FLOAT        NOT NULL,
    inference_ms  FLOAT        NOT NULL,
    fft_energy    FLOAT        DEFAULT 0,
    uptime_ms     BIGINT       DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device (device_id),
    INDEX idx_time   (created_at),
    INDEX idx_class  (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
