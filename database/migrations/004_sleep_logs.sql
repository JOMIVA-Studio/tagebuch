CREATE TABLE IF NOT EXISTS sleep_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  sleep_date DATE NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  quality TINYINT UNSIGNED NOT NULL,
  UNIQUE KEY uniq_sleep_user_date (user_id, sleep_date),
  CONSTRAINT fk_sleep_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
