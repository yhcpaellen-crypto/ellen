-- Run once against the existing accounting_basic database (MySQL 8).
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
