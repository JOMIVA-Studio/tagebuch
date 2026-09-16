-- Migration 009: Vorgefertigte Hintergrund-Optiken fuers Visionboard
-- (Korkwand, Magnetwand, Wandtafel) zusaetzlich zu Farbe/eigenem Bild.

ALTER TABLE vision_boards
  MODIFY COLUMN background_type ENUM('color','image','cork','chalk','metal') NOT NULL DEFAULT 'cork';
