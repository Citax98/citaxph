-- =========================================================
-- citaxph* — Database schema (MySQL/MariaDB)
-- Proprietario: Federico Citarella
-- Versione: 1.0
-- =========================================================
-- NOTE DI SICUREZZA (IMPORTANTE):
-- - Nel DB salviamo SOLO metadata e path relativi delle immagini.
-- - Le immagini devono stare FUORI dalla web root (es. /var/citaxph_storage/...)
-- - Le pagine PHP serviranno le immagini in modo controllato (session/token).
-- =========================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */;

-- =========================================================
-- 1) Database (opzionale)
--    Se il tuo hosting non consente CREATE DATABASE, commenta la riga.
-- =========================================================
CREATE DATABASE IF NOT EXISTS citaxph_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE citaxph_db;

-- =========================================================
-- 2) Admin users (accesso al gestionale)
-- =========================================================
CREATE TABLE IF NOT EXISTS admin_users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL, -- password_hash() PHP (bcrypt/argon2)
  display_name    VARCHAR(120) NOT NULL DEFAULT 'Federico Citarella',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 3) Events (album / eventi)
-- =========================================================
CREATE TABLE IF NOT EXISTS events (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title             VARCHAR(160) NOT NULL,
  slug              VARCHAR(190) NOT NULL,
  event_date        DATE NULL,
  location          VARCHAR(190) NULL,
  description       TEXT NULL,

  -- Toggles
  is_published      TINYINT(1) NOT NULL DEFAULT 1,

  -- Storage
  storage_folder    VARCHAR(255) NOT NULL, -- es: events/2026-01-29_matrimonio_luca_maria
  cover_photo_id    BIGINT UNSIGNED NULL,

  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_events_slug (slug),
  UNIQUE KEY uk_events_access_code (access_code),
  KEY idx_events_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 4) Photos (foto associate a un evento)
-- =========================================================
CREATE TABLE IF NOT EXISTS photos (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id           BIGINT UNSIGNED NOT NULL,

  -- Nome originale file caricato (solo informativo)
  original_name     VARCHAR(255) NULL,

  -- Path relativo (NON URL pubblico). Il base path reale lo gestisce config.php
  -- es: events/2026-01-29_matrimonio_luca_maria/preview_0001.jpg
  relative_path     VARCHAR(255) NOT NULL,

  -- Mime / dimensioni (opzionali)
  mime_type         VARCHAR(120) NULL,
  file_size_bytes   BIGINT UNSIGNED NULL,
  width_px          INT UNSIGNED NULL,
  height_px         INT UNSIGNED NULL,

  -- Ordinamento e stato
  sort_order        INT NOT NULL DEFAULT 0,
  is_visible        TINYINT(1) NOT NULL DEFAULT 1,

  -- Watermark / info utili
  has_watermark     TINYINT(1) NOT NULL DEFAULT 1,

  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_photos_event (event_id),
  KEY idx_photos_sort (event_id, sort_order),
  KEY idx_photos_visible (event_id, is_visible),
  CONSTRAINT fk_photos_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FK cover_photo_id dopo la creazione di photos
ALTER TABLE events
  ADD CONSTRAINT fk_events_cover_photo
  FOREIGN KEY (cover_photo_id) REFERENCES photos(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

-- =========================================================
-- 5) Client tokens (accesso gallery via token temporaneo)
--    Accesso base: access_code -> crea token -> cookie/sessione.
-- =========================================================

-- =========================================================
-- 6) Contact messages (opzionale) — per salvare i contatti
-- =========================================================
CREATE TABLE IF NOT EXISTS contact_messages (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  message       TEXT NOT NULL,
  ip_address    VARCHAR(64) NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 7) Seed minimale (admin)
--    ATTENZIONE: sostituisci password_hash con un valore generato via PHP.
-- =========================================================
-- INSERT INTO admin_users (email, password_hash, display_name)
-- VALUES ('tuo@email.it', '$2y$10$.................................................', 'Federico Citarella');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET_COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
