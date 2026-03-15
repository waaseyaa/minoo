-- Add consent_public and consent_ai_training to entity types missing them.
-- Default: consent_public=1 (public), consent_ai_training=0 (opt-in only).
--
-- Entity types affected: event, group, cultural_group,
-- cultural_collection, resource_person, leader
--
-- Waaseyaa's entity storage auto-creates columns for NEW tables from
-- field definitions, but does NOT alter existing tables (schema drift).
-- These ALTER TABLE statements provision the columns on existing databases.

ALTER TABLE event ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE event ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;

ALTER TABLE "group" ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE "group" ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;

ALTER TABLE cultural_group ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE cultural_group ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;

ALTER TABLE cultural_collection ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE cultural_collection ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;

ALTER TABLE resource_person ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE resource_person ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;

ALTER TABLE leader ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1;
ALTER TABLE leader ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0;
