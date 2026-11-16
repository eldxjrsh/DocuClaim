-- Apply to the catalog database after registration.sql.

CREATE TABLE `document_requests` (
    `request_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_no` VARCHAR(30) NOT NULL,
    `student_user_id` INT UNSIGNED NOT NULL,
    `doc_type_id` SMALLINT UNSIGNED NOT NULL,
    `purpose` VARCHAR(255) NOT NULL,
    `academic_year` VARCHAR(20) DEFAULT NULL COMMENT 'Required for Group A (e.g., 2025-2026); NULL for Group B',
    `academic_term` ENUM('1st Semester', '2nd Semester', 'Midyear') DEFAULT NULL COMMENT 'Required for Group A; NULL for Group B',
    `student_note` TEXT DEFAULT NULL COMMENT 'Optional note submitted by the student',
    `status` ENUM(
        'Pending Review',
        'Approved',
        'Processing',
        'Ready for Pickup',
        'Completed',
        'Rejected',
        'Cancelled'
    ) NOT NULL DEFAULT 'Pending Review',
    `status_reason` TEXT DEFAULT NULL COMMENT 'Mandatory reason when Rejected or Cancelled by Staff',
    `cancelled_by_role` ENUM('Student', 'Registrar Staff') DEFAULT NULL,
    `handled_by_staff_id` INT UNSIGNED DEFAULT NULL,
    `active_request_lock` VARCHAR(64) GENERATED ALWAYS AS (
        CASE
            WHEN `status` IN ('Pending Review', 'Approved', 'Processing', 'Ready for Pickup')
            THEN CONCAT(`student_user_id`, ':', `doc_type_id`)
            ELSE NULL
        END
    ) VIRTUAL COMMENT 'Enforces 1 active request per student per document type at DB level',
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_at` DATETIME DEFAULT NULL,
    `processing_at` DATETIME DEFAULT NULL,
    `ready_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`request_id`),
    UNIQUE KEY `uq_doc_requests_ref` (`reference_no`),
    UNIQUE KEY `uq_doc_requests_exclusivity` (`active_request_lock`),
    KEY `idx_doc_requests_student_status` (`student_user_id`, `status`),
    KEY `idx_doc_requests_type_status` (`doc_type_id`, `status`),
    KEY `idx_doc_requests_status_date` (`status`, `requested_at`),
    KEY `idx_doc_requests_staff` (`handled_by_staff_id`),
    CONSTRAINT `fk_doc_requests_student`
        FOREIGN KEY (`student_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_requests_doctype`
        FOREIGN KEY (`doc_type_id`) REFERENCES `document_types` (`doc_type_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_requests_staff`
        FOREIGN KEY (`handled_by_staff_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
