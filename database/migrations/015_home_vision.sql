CREATE TABLE IF NOT EXISTS home_visions (
 user_id INT UNSIGNED PRIMARY KEY,
 manifestation_id INT UNSIGNED NOT NULL,
 CONSTRAINT fk_home_vision_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_home_vision_manifest FOREIGN KEY (manifestation_id) REFERENCES vision_manifestations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
