-- Requires 003_record_lifecycle. Import once into an existing installation.
SET NAMES utf8mb4;
ALTER TABLE shipments ADD origin_latitude DECIMAL(10,7) NULL, ADD origin_longitude DECIMAL(10,7) NULL, ADD destination_latitude DECIMAL(10,7) NULL, ADD destination_longitude DECIMAL(10,7) NULL;
ALTER TABLE tracking_events ADD latitude DECIMAL(10,7) NULL, ADD longitude DECIMAL(10,7) NULL;
INSERT INTO schema_migrations VALUES('004_tracking_map',UTC_TIMESTAMP());
