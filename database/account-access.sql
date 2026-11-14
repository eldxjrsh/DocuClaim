-- Apply to the catalog database after registration.sql.

INSERT INTO roles (role_id, role_slug, role_name, description)
VALUES (2, 'staff', 'Registrar Staff', 'Registrar personnel verifying students');

CREATE TABLE `login_attempts` (
    `attempt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier_number` VARCHAR(30) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_id`),
    KEY `idx_login_attempts_lookup` (`identifier_number`, `attempted_at`),
    KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `security_tokens` (
    `token_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `issued_by_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'Registrar Staff or Admin who generated the slip; NULL if self-requested',
    `token_type` ENUM('ACTIVATION_SLIP', 'PASSWORD_RESET_SLIP', 'PASSWORD_RESET_EMAIL', 'STAFF_SETUP_CODE') NOT NULL,
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the one-time code or token',
    `expires_at` DATETIME NOT NULL COMMENT '7 days for activation slips; short TTL for recovery/setup codes',
    `used_at` DATETIME DEFAULT NULL,
    `invalidated_at` DATETIME DEFAULT NULL COMMENT 'Set immediately when a newer token of the same type is generated',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token_id`),
    UNIQUE KEY `uq_security_tokens_hash` (`token_hash`),
    KEY `idx_security_tokens_user_type` (`user_id`, `token_type`, `used_at`, `invalidated_at`),
    KEY `idx_security_tokens_issued_by` (`issued_by_user_id`),
    CONSTRAINT `fk_security_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_security_tokens_issuer`
        FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
