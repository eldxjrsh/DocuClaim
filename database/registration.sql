-- Apply to the catalog database after importing catalog.sql.

CREATE TABLE `roles` (
    `role_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_slug` VARCHAR(30) NOT NULL,
    `role_name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`role_id`),
    UNIQUE KEY `uq_roles_slug` (`role_slug`),
    UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`, `role_slug`, `role_name`, `description`) VALUES
    (1, 'student', 'Student', 'University student');

CREATE TABLE `users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` TINYINT UNSIGNED NOT NULL,
    `identifier_number` VARCHAR(30) NOT NULL COMMENT 'Student Number for students; Employee ID for staff/admin',
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) DEFAULT NULL COMMENT 'Bcrypt/Argon2id hash; NULL for staff awaiting initial setup code',
    `email` VARCHAR(191) DEFAULT NULL COMMENT 'Active verified notification email address',
    `email_verified_at` DATETIME DEFAULT NULL,
    `is_activated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 once student enters staff activation code or staff completes setup',
    `activated_at` DATETIME DEFAULT NULL,
    `account_status` ENUM('Pending Activation', 'Active', 'Suspended', 'Deactivated') NOT NULL DEFAULT 'Pending Activation',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_identifier` (`identifier_number`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_status` (`account_status`),
    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
