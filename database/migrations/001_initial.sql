-- Migration 001: Grundschema
-- Diese Datei wird von der App selbst automatisch ausgefuehrt (src/migrate.php).
-- Nicht manuell in einen SQL-Client kopieren.

CREATE TABLE IF NOT EXISTS users (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                    VARCHAR(120) NOT NULL,
  email                   VARCHAR(190) NOT NULL UNIQUE,
  password_hash           VARCHAR(255) NOT NULL,
  reminder_emails_enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_activity_at        DATETIME NULL,
  last_reminder_sent_at   DATETIME NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_text VARCHAR(500) NOT NULL,
  answer_type   ENUM('text','scale_1_5','yesno','number') NOT NULL DEFAULT 'text',
  sort_order    INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diary_entries (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  entry_date DATE NOT NULL,
  free_text  TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_date (user_id, entry_date),
  CONSTRAINT fk_diary_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diary_answers (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  diary_entry_id   INT UNSIGNED NOT NULL,
  question_id      INT UNSIGNED NOT NULL,
  answer_text      TEXT NULL,
  UNIQUE KEY uniq_entry_question (diary_entry_id, question_id),
  CONSTRAINT fk_diary_answers_entry FOREIGN KEY (diary_entry_id) REFERENCES diary_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_diary_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goals (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  title            VARCHAR(200) NOT NULL,
  description      VARCHAR(500) NULL,
  frequency_type   ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  frequency_target INT UNSIGNED NOT NULL DEFAULT 1,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id    INT UNSIGNED NOT NULL,
  log_date   DATE NOT NULL,
  status     ENUM('done','partial','not_done') NOT NULL DEFAULT 'done',
  note       VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_goal_date (goal_id, log_date),
  CONSTRAINT fk_goal_logs_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key  VARCHAR(60) NOT NULL UNIQUE,
  name          VARCHAR(150) NOT NULL,
  subject       VARCHAR(255) NOT NULL,
  body_html     TEXT NOT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smtp_settings (
  id         INT UNSIGNED PRIMARY KEY DEFAULT 1,
  host       VARCHAR(190) NULL,
  port       INT UNSIGNED NULL,
  encryption ENUM('none','ssl','tls') NOT NULL DEFAULT 'tls',
  username   VARCHAR(190) NULL,
  password   VARCHAR(255) NULL,
  from_email VARCHAR(190) NULL,
  from_name  VARCHAR(190) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  id                         INT UNSIGNED PRIMARY KEY DEFAULT 1,
  inactivity_threshold_days INT UNSIGNED NOT NULL DEFAULT 7
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  template_key  VARCHAR(60) NOT NULL,
  recipient     VARCHAR(190) NOT NULL,
  status        ENUM('sent','failed') NOT NULL,
  error_message VARCHAR(500) NULL,
  sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (id, inactivity_threshold_days) VALUES (1, 7);

INSERT IGNORE INTO smtp_settings (id, host, port, encryption, from_email, from_name)
VALUES (1, '', 587, 'tls', 'noreply@example.com', 'Mein Tagebuch');

INSERT IGNORE INTO questions (question_text, answer_type, sort_order) VALUES
  ('Wie war deine Stimmung heute?', 'scale_1_5', 1),
  ('Wie viele Stunden hast du geschlafen?', 'number', 2),
  ('Hattest du heute genug Energie?', 'yesno', 3),
  ('Was hat dich heute am meisten beschaeftigt?', 'text', 4);

INSERT IGNORE INTO email_templates (template_key, name, subject, body_html) VALUES
  ('inactivity_reminder', 'Erinnerung bei Inaktivitaet',
   'Wir vermissen dich in deinem Tagebuch',
   '<p>Hallo {{name}},</p><p>du warst seit einer Weile nicht mehr in deinem Tagebuch aktiv. Nimm dir doch kurz Zeit fuer einen neuen Eintrag.</p><p>Bis bald!</p>'),
  ('welcome', 'Willkommens-Mail nach Registrierung',
   'Willkommen in deinem Tagebuch',
   '<p>Hallo {{name}},</p><p>dein Konto wurde erfolgreich erstellt. Wir freuen uns, dich auf deiner Reise zu begleiten.</p>');

-- Admin-Zugänge werden bewusst NICHT per Migration angelegt, sondern per CLI mit eigenem Passwort:
--   php bin/create-admin.php
