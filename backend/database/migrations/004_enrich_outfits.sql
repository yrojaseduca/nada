ALTER TABLE outfits
    ADD COLUMN source VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER notes;

ALTER TABLE outfit_garments
    ADD COLUMN role VARCHAR(40) NULL AFTER position;
