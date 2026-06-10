-- ================================================================
-- DATABASE SCHEMA: SISTEM PENGADUAN MEINE WELT KAFE
-- Sesuai dengan ERD pada BAB III Dokumen Pengembangan Web
-- ================================================================

CREATE DATABASE IF NOT EXISTS meine_welt_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meine_welt_db;

-- ================================================================
-- TABEL: roles
-- ================================================================
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: users
-- ================================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: categories
-- ================================================================
CREATE TABLE categories (
  code VARCHAR(10) PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  description VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: complaints (tabel utama pengaduan)
-- ================================================================
CREATE TABLE complaints (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_code VARCHAR(30) NOT NULL UNIQUE,
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reporter_name VARCHAR(100),
  reporter_contact VARCHAR(100),
  is_anonymous TINYINT(1) DEFAULT 1,
  order_type ENUM('dine_in','take_away','online') DEFAULT 'dine_in',
  complaint_text TEXT NOT NULL,
  status ENUM('new','in_progress','resolved','cancelled') DEFAULT 'new',
  -- Mendukung multi-label: 'PRD', 'PRD,HRG', dll
  predicted_category_code VARCHAR(50),
  predicted_confidence DECIMAL(5,4),
  sentiment VARCHAR(10),
  sentiment_confidence DECIMAL(5,4),
  final_category_code VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket (ticket_code),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: complaint_aspects (hasil ABSA per-aspek)
-- Satu pengaduan bisa punya beberapa pasangan (aspek, sentimen).
-- Menjawab revisi: banyak aspek + sentimen per-aspek + negasi.
-- ================================================================
CREATE TABLE complaint_aspects (
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

-- ================================================================
-- TABEL: attachments
-- ================================================================
CREATE TABLE attachments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT NOT NULL,
  uploaded_by_user_id INT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80),
  file_size INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: followups (tindak lanjut)
-- ================================================================
CREATE TABLE followups (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT NOT NULL,
  actor_user_id INT NULL,
  message TEXT NOT NULL,
  visibility ENUM('public','internal') DEFAULT 'public',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: status_history
-- ================================================================
CREATE TABLE status_history (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT NOT NULL,
  changed_by_user_id INT NULL,
  from_status VARCHAR(20),
  to_status VARCHAR(20),
  reason VARCHAR(255),
  changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================================
-- TABEL: activity_logs
-- ================================================================
CREATE TABLE activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(50),
  entity_id VARCHAR(50),
  detail TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================================
-- SEED DATA
-- ================================================================

-- Roles
INSERT INTO roles (name, description) VALUES
  ('admin', 'Admin Kafe - akses penuh sistem'),
  ('petugas', 'Petugas Kafe - menangani pengaduan');

-- Users default
-- Password default: admin123 (bcrypt hash) & petugas123
-- Hash di bawah adalah hasil password_hash('admin123', PASSWORD_DEFAULT) dan password_hash('petugas123', PASSWORD_DEFAULT)
INSERT INTO users (role_id, username, password_hash, full_name, is_active) VALUES
  (1, 'admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS7SjDbJHN7SbqDzIBJTfgRygvrG', 'Administrator Kafe', 1),
  (2, 'petugas', '$2y$10$Ue8SgS/5RqCy.HWzxLhYxO.Iix0BqGEAdqyoN.ENv9lJSzOKzh7H6', 'Petugas Kafe', 1);

-- Categories (sesuai 4 aspek pengaduan)
INSERT INTO categories (code, name, description, is_active) VALUES
  ('PLY', 'pelayanan', 'Pengaduan terkait pelayanan staff/karyawan kafe', 1),
  ('PRD', 'produk',    'Pengaduan terkait makanan/minuman/produk kafe', 1),
  ('HRG', 'harga',     'Pengaduan terkait harga dan kesesuaian biaya', 1),
  ('SUI', 'suasana',   'Pengaduan terkait suasana/fasilitas/tempat kafe', 1);