CREATE DATABASE IF NOT EXISTS brightsmile
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brightsmile;

SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS users (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email            VARCHAR(190) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,             -- store hashed password (argon2id)
  first_name       VARCHAR(100) NULL,
  last_name        VARCHAR(100) NULL,
  dob              DATE NULL,
  avatar_url       VARCHAR(255) NULL,
  phone            VARCHAR(30) NULL,

  last_login       DATETIME NULL,
  created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- token fields (don’t store plaintext);
  token_hash       VARCHAR(255) NULL,                 -- hash (for reset pw)
  token_expires_at DATETIME NULL,

  is_doctor        TINYINT(1) NOT NULL DEFAULT 0,
  is_admin        TINYINT(1) NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_last_login (last_login),
  KEY idx_is_doctor (is_doctor),
  KEY idx_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOCTORS
CREATE TABLE IF NOT EXISTS doctors (
  user_id INT UNSIGNED PRIMARY KEY,
  specialization VARCHAR(120),
  bio TEXT,

  CONSTRAINT fk_doctors_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- APPOINTMENTS
CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_user_id INT UNSIGNED NOT NULL,
  doctor_user_id INT UNSIGNED NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL,
  status ENUM('confirmed','completed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appt_patient (patient_user_id),
  KEY idx_appt_doctor (doctor_user_id),
  KEY idx_doctor_date_time (doctor_user_id, appt_date, appt_time),
  KEY idx_appt_status (status),

  CONSTRAINT fk_appt_patient
    FOREIGN KEY (patient_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_appt_doctor
    FOREIGN KEY (doctor_user_id) REFERENCES doctors(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;