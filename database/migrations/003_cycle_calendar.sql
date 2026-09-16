-- Migration 003: Zyklus-Tracker auf Tages-Kalender mit Haken umgestellt
-- (statt einem einzelnen Zyklusstart-Datum werden einzelne Perioden-Tage
-- markiert). cycle_entries war noch ungenutzt (0 Zeilen) und wird ersetzt.

DROP TABLE IF EXISTS cycle_entries;

CREATE TABLE IF NOT EXISTS cycle_days (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  day_date   DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_day (user_id, day_date),
  CONSTRAINT fk_cycle_days_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
