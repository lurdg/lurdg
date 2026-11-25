CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(80) PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','administrator','manager','operations','agent') NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    last_login_at DATETIME(6) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(500) NOT NULL DEFAULT '',
    notes TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    KEY customer_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    bucket ENUM('pending','awaiting','transit','delivered','exception','cancelled') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO statuses (code,label,bucket,sort_order) VALUES
    ('created','Shipment Created','pending',10),
    ('received','Shipment Received','pending',20),
    ('processing','Processing','pending',30),
    ('awaiting-dispatch','Awaiting Dispatch','awaiting',40),
    ('dispatched','Dispatched','transit',50),
    ('in-transit','In Transit','transit',60),
    ('at-origin','Arrived at Origin Facility','transit',70),
    ('departed-origin','Departed Origin Facility','transit',80),
    ('customs','Customs Clearance','transit',90),
    ('at-transit','Arrived at Transit Facility','transit',100),
    ('at-destination','Arrived at Destination Country','transit',110),
    ('out-for-delivery','Out for Delivery','transit',120),
    ('delivered','Delivered','delivered',130),
    ('attempted','Delivery Attempted','exception',140),
    ('exception','Delivery Exception','exception',150),
    ('on-hold','On Hold','exception',160),
    ('cancelled','Cancelled','cancelled',170);

CREATE TABLE IF NOT EXISTS shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(96) NOT NULL,
    lookup_key BINARY(32) NOT NULL UNIQUE,
    verification_key BINARY(32) NULL,
    legacy BOOLEAN NOT NULL DEFAULT FALSE,
    reference VARCHAR(100) NOT NULL DEFAULT '',
    shipper_name VARCHAR(160) NOT NULL DEFAULT '',
    shipper_phone VARCHAR(40) NOT NULL DEFAULT '',
    shipper_email VARCHAR(254) NOT NULL DEFAULT '',
    shipper_address VARCHAR(500) NOT NULL DEFAULT '',
    receiver_name VARCHAR(160) NOT NULL DEFAULT '',
    receiver_phone VARCHAR(40) NOT NULL DEFAULT '',
    receiver_email VARCHAR(254) NOT NULL DEFAULT '',
    receiver_address VARCHAR(500) NOT NULL DEFAULT '',
    origin VARCHAR(100) NOT NULL DEFAULT '',
    destination VARCHAR(100) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    category VARCHAR(90) NOT NULL DEFAULT '',
    packages INT UNSIGNED NULL,
    weight DECIMAL(12,3) UNSIGNED NULL,
    dimensions VARCHAR(100) NOT NULL DEFAULT '',
    method VARCHAR(90) NOT NULL DEFAULT '',
    pickup_at DATETIME(6) NULL,
    departure_at DATETIME(6) NULL,
    estimated_delivery DATE NULL,
    status_id INT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    internal_notes TEXT NOT NULL,
    public_notes TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    deleted_at DATETIME(6) NULL,
    source_key VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL UNIQUE,
    source_digest BINARY(32) NULL,
    CONSTRAINT shipment_status FOREIGN KEY (status_id) REFERENCES statuses(id),
    CONSTRAINT shipment_agent FOREIGN KEY (agent_id) REFERENCES staff(id) ON DELETE SET NULL,
    CONSTRAINT shipment_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    KEY shipment_date (created_at,id),
    KEY shipment_status_date (status_id,created_at,id),
    KEY shipment_agent_date (agent_id,created_at,id),
    KEY shipment_route (origin,destination,created_at),
    KEY shipment_destination (destination,created_at),
    KEY shipment_reference (reference),
    KEY shipment_lifecycle (deleted_at,archived_at,estimated_delivery)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shipment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('status','location') NOT NULL,
    status_id INT UNSIGNED NULL,
    status_label VARCHAR(100) NOT NULL DEFAULT '',
    occurred_at DATETIME(6) NOT NULL,
    recorded_at DATETIME(6) NOT NULL,
    location VARCHAR(200) NOT NULL DEFAULT '',
    latitude DECIMAL(9,6) NULL,
    longitude DECIMAL(9,6) NULL,
    description TEXT NOT NULL,
    customer_remarks TEXT NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    actor_name VARCHAR(160) NOT NULL DEFAULT '',
    deleted_at DATETIME(6) NULL,
    CONSTRAINT event_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT event_status FOREIGN KEY (status_id) REFERENCES statuses(id),
    CONSTRAINT event_actor FOREIGN KEY (actor_id) REFERENCES staff(id) ON DELETE SET NULL,
    CONSTRAINT event_location CHECK (
        (latitude IS NULL AND longitude IS NULL) OR
        (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
    ),
    KEY event_timeline (shipment_id,deleted_at,occurred_at,id),
    KEY event_latest_kind (shipment_id,kind,deleted_at,occurred_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enquiries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('contact','quote') NOT NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(160) NOT NULL DEFAULT '',
    origin VARCHAR(100) NOT NULL DEFAULT '',
    destination VARCHAR(100) NOT NULL DEFAULT '',
    method VARCHAR(90) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    stage ENUM('new','in-progress','closed') NOT NULL DEFAULT 'new',
    staff_notes TEXT NULL,
    created_at DATETIME(6) NOT NULL,
    KEY inbox_stage (kind,stage,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,
    stored_name CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    display_name VARCHAR(200) NOT NULL,
    media_type VARCHAR(100) NOT NULL,
    byte_count INT UNSIGNED NOT NULL,
    digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    CONSTRAINT attachment_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    KEY attachment_digest (shipment_id,digest)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    actor_name VARCHAR(160) NOT NULL DEFAULT '',
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(32) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    detail VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME(6) NOT NULL,
    KEY audit_target (target_type,target_id,created_at),
    KEY audit_created (created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket BINARY(32) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    KEY rate_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    token_hash BINARY(32) PRIMARY KEY,
    staff_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    CONSTRAINT reset_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    KEY reset_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NULL,
    recipient VARCHAR(254) NOT NULL,
    reply_to VARCHAR(254) NOT NULL DEFAULT '',
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME(6) NOT NULL,
    sent_at DATETIME(6) NULL,
    failed_at DATETIME(6) NULL,
    CONSTRAINT queue_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    KEY queue_due (sent_at,failed_at,available_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS geocode_cache (
    query_key BINARY(32) PRIMARY KEY,
    candidates TEXT NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    KEY geocode_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
