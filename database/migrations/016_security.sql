-- Sicherheits-Update: Login-Drosselung, Passwort-Reset, erzwungener Admin-Passwortwechsel,
-- Dateien statt BLOBs für manifestierte Visionen.

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope        VARCHAR(30)  NOT NULL,
  identifier   VARCHAR(190) NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_lookup (scope, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admins ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0;

-- Admins mit dem früher öffentlich dokumentierten Standardpasswort müssen es beim nächsten Login ändern.
UPDATE admins SET must_change_password = 1
WHERE password_hash = '$2b$10$cIdGwFRMiyzMWN9HpVgy6ubes/QD4nVkVDhhAxSFRIJiCJa7cIE.m';

-- Manifestierte Visionen werden künftig als Dateien in storage/manifestations/ abgelegt.
ALTER TABLE vision_manifestations MODIFY image_data MEDIUMBLOB NULL;
ALTER TABLE vision_manifestations MODIFY pdf_data MEDIUMBLOB NULL;

INSERT IGNORE INTO email_templates (template_key, name, subject, body_html) VALUES
  ('password_reset', 'Passwort zurücksetzen',
   'Dein Link zum Zurücksetzen des Passworts',
   '<p>Hallo {{name}},</p><p>du hast angefragt, dein Passwort zurückzusetzen. Klicke auf den folgenden Link, um ein neues Passwort zu vergeben:</p><p><a href="{{link}}">{{link}}</a></p><p>Der Link ist 60 Minuten gültig. Wenn du das nicht warst, kannst du diese E-Mail ignorieren – dein Passwort bleibt dann unverändert.</p>');
