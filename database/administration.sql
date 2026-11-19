-- Apply to the catalog database after pickups.sql.

CREATE TABLE `in_app_notifications` (
    `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `event_type` ENUM(
        'DOCUMENT_READY',
        'APPOINTMENT_RESCHEDULED',
        'APPOINTMENT_CANCELLED',
        'REQUEST_STATUS_CHANGED'
    ) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `related_request_id` BIGINT UNSIGNED DEFAULT NULL,
    `related_appointment_id` BIGINT UNSIGNED DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`),
    KEY `idx_notifications_user_unread` (`user_id`, `is_read`, `created_at`),
    KEY `idx_notifications_request` (`related_request_id`),
    KEY `idx_notifications_appointment` (`related_appointment_id`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_request`
        FOREIGN KEY (`related_request_id`) REFERENCES `document_requests` (`request_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_appointment`
        FOREIGN KEY (`related_appointment_id`) REFERENCES `pickup_appointments` (`appointment_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
    `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actor_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL only on unauthenticated failed login for unknown identifier',
    `action_type` VARCHAR(80) NOT NULL,
    `target_id` VARCHAR(64) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `metadata` JSON DEFAULT NULL COMMENT 'Structured context (previous/new values, reason, window IDs)',
    PRIMARY KEY (`log_id`),
    KEY `idx_audit_logs_timestamp` (`timestamp`),
    KEY `idx_audit_logs_actor` (`actor_id`, `timestamp`),
    KEY `idx_audit_logs_action` (`action_type`, `timestamp`),
    KEY `idx_audit_logs_target` (`target_id`),
    CONSTRAINT `fk_audit_logs_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
