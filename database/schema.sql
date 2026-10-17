-- ============================================================
-- Schema database untuk LaundryKu
-- Dibuat berdasarkan migration Laravel yang ada
-- Database: laundryku
-- ============================================================

CREATE DATABASE IF NOT EXISTS laundryku
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE laundryku;

-- ------------------------------------------------------------
-- Tabel: users (auth Laravel)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: password_reset_tokens
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: sessions (session database driver)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) NOT NULL,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent TEXT NULL DEFAULT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  PRIMARY KEY (id),
  KEY sessions_user_id_index (user_id),
  KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: laundry_packages (paket/jenis layanan laundry)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laundry_packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description VARCHAR(255) NULL DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  unit VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: customers (pelanggan)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(255) NOT NULL,
  address VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: orders (pesanan laundry)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  package_id BIGINT UNSIGNED NOT NULL,
  weight DECIMAL(8,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  status VARCHAR(255) NOT NULL,
  order_date TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY orders_customer_id_foreign (customer_id),
  KEY orders_package_id_foreign (package_id),
  CONSTRAINT orders_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id)
    ON DELETE CASCADE,
  CONSTRAINT orders_package_id_foreign
    FOREIGN KEY (package_id) REFERENCES laundry_packages (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: ai_conversations (percakapan AI chat)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ai_conversations_user_id_foreign (user_id),
  CONSTRAINT ai_conversations_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: ai_messages (pesan dalam percakapan AI)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  role VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ai_messages_conversation_id_foreign (conversation_id),
  CONSTRAINT ai_messages_conversation_id_foreign
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;