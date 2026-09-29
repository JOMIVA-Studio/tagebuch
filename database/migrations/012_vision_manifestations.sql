CREATE TABLE IF NOT EXISTS vision_manifestations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 request_key CHAR(32) NOT NULL,
 image_data MEDIUMBLOB NOT NULL,
 pdf_data MEDIUMBLOB NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_manifest_request (user_id, request_key),
 KEY idx_manifest_user (user_id, id),
 CONSTRAINT fk_manifest_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
