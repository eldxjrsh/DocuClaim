-- Apply to the catalog database after account-access.sql and document-requests.sql.

INSERT INTO roles (role_id, role_slug, role_name, description)
VALUES (3, 'admin', 'System Admin', 'Administrator managing pickup windows');

CREATE TABLE `pickup_windows` (
    `window_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pickup_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `max_capacity` SMALLINT UNSIGNED NOT NULL,
    `window_status` ENUM('Open', 'Closed', 'Replaced') NOT NULL DEFAULT 'Open',
    `replaced_by_window_id` INT UNSIGNED DEFAULT NULL COMMENT 'Target window when Admin closes & migrates bookings',
    `created_by_admin_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`window_id`),
    UNIQUE KEY `uq_pickup_window_slot` (`pickup_date`, `start_time`, `end_time`),
    KEY `idx_pickup_windows_date_status` (`pickup_date`, `window_status`),
    KEY `idx_pickup_windows_replaced_by` (`replaced_by_window_id`),
    KEY `idx_pickup_windows_admin` (`created_by_admin_id`),
    CONSTRAINT `chk_pickup_window_times` CHECK (`end_time` > `start_time`),
    CONSTRAINT `chk_pickup_window_capacity` CHECK (`max_capacity` > 0),
    CONSTRAINT `fk_pickup_windows_replacement`
        FOREIGN KEY (`replaced_by_window_id`) REFERENCES `pickup_windows` (`window_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_pickup_windows_admin`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pickup_appointments` (
    `appointment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(30) NOT NULL,
    `student_user_id` INT UNSIGNED NOT NULL,
    `window_id` INT UNSIGNED NOT NULL,
    `appointment_status` ENUM('Scheduled', 'Completed', 'Missed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    `processed_by_staff_id` INT UNSIGNED DEFAULT NULL,
    `cancellation_reason` VARCHAR(255) DEFAULT NULL,
    `booked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_id`),
    UNIQUE KEY `uq_appointments_ref` (`booking_reference`),
    KEY `idx_appointments_window_status` (`window_id`, `appointment_status`),
    KEY `idx_appointments_student_status` (`student_user_id`, `appointment_status`),
    KEY `idx_appointments_staff` (`processed_by_staff_id`),
    CONSTRAINT `fk_appointments_student`
        FOREIGN KEY (`student_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_window`
        FOREIGN KEY (`window_id`) REFERENCES `pickup_windows` (`window_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_staff`
        FOREIGN KEY (`processed_by_staff_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_documents` (
    `appointment_doc_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` BIGINT UNSIGNED NOT NULL,
    `request_id` BIGINT UNSIGNED NOT NULL,
    `collection_state` ENUM('Attached', 'Collected', 'Uncollected', 'Detached') NOT NULL DEFAULT 'Attached',
    `collected_at` DATETIME DEFAULT NULL,
    `released_by_staff_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_doc_id`),
    UNIQUE KEY `uq_appointment_doc_pair` (`appointment_id`, `request_id`),
    KEY `idx_appointment_docs_request` (`request_id`, `collection_state`),
    KEY `idx_appointment_docs_staff` (`released_by_staff_id`),
    CONSTRAINT `fk_appointment_docs_appointment`
        FOREIGN KEY (`appointment_id`) REFERENCES `pickup_appointments` (`appointment_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_appointment_docs_request`
        FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_appointment_docs_staff`
        FOREIGN KEY (`released_by_staff_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE appointment_documents
    ADD COLUMN active_pickup_request BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN collection_state = 'Attached' THEN request_id ELSE NULL END
    ) VIRTUAL,
    ADD UNIQUE KEY uq_active_pickup_request (active_pickup_request);
