-- DocuClaim document catalog schema and seed data.
CREATE DATABASE IF NOT EXISTS docuclaim_catalog
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE docuclaim_catalog;

CREATE TABLE `document_groups` (
    `group_code` CHAR(1) NOT NULL COMMENT 'A or B',
    `group_name` VARCHAR(80) NOT NULL,
    `requires_academic_year` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_academic_term` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_groups` (`group_code`, `group_name`, `requires_academic_year`, `requires_academic_term`) VALUES
    ('A', 'Group A - Term-Specific Academic Documents', 1, 1),
    ('B', 'Group B - Cumulative / General Records', 0, 0);

CREATE TABLE `document_types` (
    `doc_type_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_code` CHAR(1) NOT NULL,
    `type_code` VARCHAR(20) NOT NULL,
    `type_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`doc_type_id`),
    UNIQUE KEY `uq_doc_types_code` (`type_code`),
    UNIQUE KEY `uq_doc_types_name` (`type_name`),
    KEY `idx_doc_types_group` (`group_code`),
    KEY `idx_doc_types_active` (`is_active`),
    CONSTRAINT `fk_doc_types_group`
        FOREIGN KEY (`group_code`) REFERENCES `document_groups` (`group_code`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_types` (`group_code`, `type_code`, `type_name`, `description`, `is_active`) VALUES
    ('A', 'COE', 'Certificate of Enrollment', 'Official certification of enrollment for a specific academic year and term.', 1),
    ('A', 'COG', 'Certificate of Grades', 'Official report of grades earned for a specific academic year and term.', 1),
    ('B', 'TOR', 'Transcript of Records', 'Comprehensive official academic transcript of the student.', 1),
    ('B', 'GMC', 'Good Moral Certificate', 'Certification of good moral character and disciplinary standing.', 1),
    ('B', 'HD',  'Honorable Dismissal', 'Official transfer credential and clearance for leaving the university.', 1);
