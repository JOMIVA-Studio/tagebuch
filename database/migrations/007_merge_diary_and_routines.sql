-- Migration 007: Tagebuch und Routinen zusammenfuehren
-- Ein Tagebucheintrag pro Tag deckt jetzt sowohl die Morgen- als auch die
-- Abend-Reflexion ab, statt separater morning_entries/evening_entries-
-- Tabellen. Vorhandene Daten werden uebernommen, bevor die alten Tabellen
-- entfernt werden.

ALTER TABLE diary_entries
  ADD COLUMN gratitude   TEXT NULL AFTER free_text,
  ADD COLUMN intention   TEXT NULL AFTER gratitude,
  ADD COLUMN affirmation TEXT NULL AFTER intention,
  ADD COLUMN nice_moment TEXT NULL AFTER affirmation,
  ADD COLUMN learning    TEXT NULL AFTER nice_moment;

INSERT INTO diary_entries (user_id, entry_date, gratitude, intention, affirmation)
SELECT user_id, entry_date, gratitude, intention, affirmation FROM morning_entries
ON DUPLICATE KEY UPDATE
  gratitude = VALUES(gratitude), intention = VALUES(intention), affirmation = VALUES(affirmation);

INSERT INTO diary_entries (user_id, entry_date, nice_moment, learning)
SELECT user_id, entry_date, nice_moment, learning FROM evening_entries
ON DUPLICATE KEY UPDATE
  nice_moment = VALUES(nice_moment), learning = VALUES(learning);

DROP TABLE IF EXISTS morning_entries;
DROP TABLE IF EXISTS evening_entries;
