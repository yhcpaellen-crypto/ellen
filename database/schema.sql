CREATE DATABASE IF NOT EXISTS accounting_basic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE accounting_basic;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  role ENUM('admin','bookkeeper','approver','viewer') NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  company_name VARCHAR(180) NOT NULL DEFAULT '我的組織',
  base_currency CHAR(3) NOT NULL DEFAULT 'TWD',
  amount_decimals TINYINT UNSIGNED NOT NULL DEFAULT 2,
  fiscal_year_start_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Taipei',
  voucher_prefix VARCHAR(12) NOT NULL DEFAULT 'JV',
  CONSTRAINT chk_company_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO company_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS accounting_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_by BIGINT UNSIGNED NULL,
  closed_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_period_dates (start_date, end_date),
  KEY ix_period_range (start_date, end_date),
  CONSTRAINT fk_period_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_period_closer FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_period_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  normal_balance ENUM('debit','credit') NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  is_postable TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_accounts_parent (parent_id),
  KEY ix_accounts_type (type, is_active),
  CONSTRAINT fk_accounts_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO accounts(code,name,type,normal_balance,is_postable) VALUES
('1000','現金','asset','debit',1),
('1100','銀行存款','asset','debit',1),
('1200','應收款項','asset','debit',1),
('1500','設備','asset','debit',1),
('2000','應付款項','liability','credit',1),
('2100','借款','liability','credit',1),
('3000','資本','equity','credit',1),
('3100','保留盈餘','equity','credit',1),
('4000','營業收入','revenue','credit',1),
('5000','銷貨成本','expense','debit',1),
('6000','租金費用','expense','debit',1),
('6100','薪資費用','expense','debit',1),
('6200','水電費','expense','debit',1),
('6900','其他費用','expense','debit',1);

CREATE TABLE IF NOT EXISTS vouchers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_no VARCHAR(40) NOT NULL UNIQUE,
  voucher_date DATE NOT NULL,
  type ENUM('general','income','expense','receipt','payment','transfer','opening','reversal') NOT NULL DEFAULT 'general',
  description VARCHAR(500) NOT NULL,
  status ENUM('draft','pending','approved','rejected','posted','void') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  approval_note VARCHAR(500) NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  reversal_of_id BIGINT UNSIGNED NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_vouchers_date_status (voucher_date, status),
  KEY ix_vouchers_creator (created_by),
  CONSTRAINT fk_vouchers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_vouchers_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_vouchers_poster FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_vouchers_reversal FOREIGN KEY (reversal_of_id) REFERENCES vouchers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voucher_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id BIGINT UNSIGNED NOT NULL,
  line_no SMALLINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(300) NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_voucher_line (voucher_id, line_no),
  KEY ix_lines_account (account_id),
  CONSTRAINT fk_lines_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
  CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT chk_line_nonnegative CHECK (debit >= 0 AND credit >= 0),
  CONSTRAINT chk_line_one_side CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_entity (entity_type, entity_id, created_at),
  KEY ix_audit_actor (actor_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  transcription_model VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini-transcribe',
  vision_model VARCHAR(80) NOT NULL DEFAULT 'gpt-4.1-mini',
  max_file_bytes INT UNSIGNED NOT NULL DEFAULT 8388608,
  daily_limit INT UNSIGNED NOT NULL DEFAULT 50,
  retention_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO ai_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS ai_intake_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by BIGINT UNSIGNED NULL,
  source_type ENUM('audio','image') NOT NULL,
  source_path VARCHAR(255) NULL,
  source_mime VARCHAR(100) NULL,
  status ENUM('processing','review','accepted','rejected','failed') NOT NULL DEFAULT 'processing',
  consent_at DATETIME NOT NULL,
  transcript_original MEDIUMTEXT NULL,
  transcript_text MEDIUMTEXT NULL,
  extraction_json JSON NULL,
  model_id VARCHAR(80) NULL,
  schema_version VARCHAR(40) NOT NULL DEFAULT '1.0',
  response_id VARCHAR(120) NULL,
  usage_json JSON NULL,
  error_code VARCHAR(80) NULL,
  error_message VARCHAR(500) NULL,
  accepted_voucher_id BIGINT UNSIGNED NULL,
  processing_ms INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_ai_jobs_user_status (created_by,status,created_at),
  KEY ix_ai_jobs_retention (status,created_at),
  CONSTRAINT fk_ai_job_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_job_voucher FOREIGN KEY (accepted_voucher_id) REFERENCES vouchers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
