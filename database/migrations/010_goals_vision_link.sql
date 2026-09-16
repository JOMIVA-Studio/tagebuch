-- Migration 010: Ziele mit dem Visionboard verknuepfen
-- Ein Ziel kann optional ein Bild vom eigenen Visionboard als
-- Motivationsbild zeigen.

ALTER TABLE goals
  ADD COLUMN vision_item_id INT UNSIGNED NULL AFTER description,
  ADD CONSTRAINT fk_goals_vision_item FOREIGN KEY (vision_item_id) REFERENCES vision_items(id) ON DELETE SET NULL;
