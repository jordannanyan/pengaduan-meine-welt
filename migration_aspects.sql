-- ================================================================
-- MIGRASI: tambah tabel complaint_aspects ke database yang sudah ada
-- Jalankan di phpMyAdmin (pilih database meine_welt_db -> tab SQL)
-- atau: mysql -u root meine_welt_db < migration_aspects.sql
-- Aman dijalankan berulang (IF NOT EXISTS).
-- ================================================================

USE meine_welt_db;

CREATE TABLE IF NOT EXISTS complaint_aspects (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT NOT NULL,
  aspect_code VARCHAR(10) NOT NULL,            -- PLY / PRD / HRG / SUI
  sentiment VARCHAR(10) NOT NULL,              -- positif / negatif
  aspect_confidence DECIMAL(5,4),
  sentiment_confidence DECIMAL(5,4),
  source_segment VARCHAR(255),                 -- klausa asal (bukti BAB IV)
  method VARCHAR(20),                          -- lexicon / ml / default
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  UNIQUE KEY uq_complaint_aspect (complaint_id, aspect_code)
) ENGINE=InnoDB;
