ALTER TABLE points_of_interest ADD COLUMN poi_type TEXT NOT NULL DEFAULT 'place';
ALTER TABLE point_of_interest_translations ADD COLUMN external_url TEXT NOT NULL DEFAULT '';
ALTER TABLE point_of_interest_translations ADD COLUMN external_label TEXT NOT NULL DEFAULT '';
