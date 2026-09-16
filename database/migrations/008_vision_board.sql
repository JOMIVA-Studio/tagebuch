-- Migration 008: Vision-/Wunschboard

CREATE TABLE IF NOT EXISTS vision_boards (
  user_id           INT UNSIGNED PRIMARY KEY,
  background_type   ENUM('color','image') NOT NULL DEFAULT 'color',
  background_color  VARCHAR(20) NOT NULL DEFAULT '#faf8fb',
  background_image  VARCHAR(255) NULL,
  CONSTRAINT fk_vision_boards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vision_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  pos_x      INT NOT NULL DEFAULT 20,
  pos_y      INT NOT NULL DEFAULT 20,
  width      INT NOT NULL DEFAULT 220,
  z_index    INT NOT NULL DEFAULT 1,
  caption    VARCHAR(200) NULL,
  source     ENUM('upload','ai') NOT NULL DEFAULT 'upload',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vision_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_settings (
  id         INT UNSIGNED PRIMARY KEY DEFAULT 1,
  provider   VARCHAR(30) NOT NULL DEFAULT 'openai',
  api_key    VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ai_settings (id) VALUES (1);
