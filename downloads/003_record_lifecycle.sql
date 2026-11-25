-- Existing installations: import this file once, not schema.sql.
SET NAMES utf8mb4;
SET time_zone='+00:00';
ALTER TABLE users ADD deleted_at DATETIME NULL, ADD INDEX(deleted_at);
ALTER TABLE quote_requests ADD deleted_at DATETIME NULL, ADD INDEX(deleted_at);
ALTER TABLE enquiries ADD deleted_at DATETIME NULL, ADD INDEX(deleted_at);
ALTER TABLE tracking_events ADD deleted_at DATETIME NULL, ADD INDEX(deleted_at), MODIFY staff_id BIGINT UNSIGNED NULL;
ALTER TABLE documents ADD deleted_at DATETIME NULL, ADD INDEX(deleted_at);
ALTER TABLE shipments ADD pickup_at DATETIME NULL, ADD departure_at DATETIME NULL, MODIFY origin_city VARCHAR(120) NOT NULL DEFAULT '', MODIFY destination_city VARCHAR(120) NOT NULL DEFAULT '', MODIFY created_by BIGINT UNSIGNED NULL;
ALTER TABLE quote_replies MODIFY staff_id BIGINT UNSIGNED NULL;
ALTER TABLE enquiry_replies MODIFY staff_id BIGINT UNSIGNED NULL;
ALTER TABLE import_batches MODIFY created_by BIGINT UNSIGNED NULL;
CREATE TABLE file_deletions(stored_name CHAR(64) PRIMARY KEY,queued_at DATETIME NOT NULL,last_error VARCHAR(190) NULL,attempts INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB;
INSERT INTO permissions VALUES('records.delete','Move records to Trash and restore'),('records.purge','Permanently delete trashed records');
INSERT INTO role_permissions(role_id,permission_id) VALUES('super_admin','records.delete'),('super_admin','records.purge'),('admin','records.delete'),('admin','records.purge');
INSERT INTO settings(setting_key,value) VALUES('record_lifecycle_lock','1');
INSERT INTO schema_migrations VALUES('003_record_lifecycle',UTC_TIMESTAMP());
