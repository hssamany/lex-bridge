-- Migration: Change unique constraint from lex_contact_id to Nummer
-- MySQL allows multiple NULL values in unique indexes, so uniqueness only applies to non-NULL values
-- Remove unique constraint on lex_contact_id
ALTER TABLE kunde
DROP INDEX lex_contact_id;

-- Add unique constraint on Nummer
ALTER TABLE kunde ADD UNIQUE INDEX uk_nummer (Nummer);