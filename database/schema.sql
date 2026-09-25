-- =====================================================================
--  PawCare Veterinary Clinic — database schema + starter data
--  How to use:
--    • Easiest: open http://localhost/<folder>/setup.php and click Install
--    • Or import this file in phpMyAdmin (Import tab, no database selected)
--  Starter logins:  admin@pawcare.test / admin123   staff@pawcare.test / staff123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `pawcare` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pawcare`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_log`, `notifications`, `contact_messages`, `vaccinations`, `medical_records`, `appointments`, `pets`, `services`, `vets`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Accounts: clinic staff (admin/staff) and pet owners (owner)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`          ENUM('admin','staff','owner') NOT NULL DEFAULT 'owner',
  `first_name`    VARCHAR(60)  NOT NULL,
  `last_name`     VARCHAR(60)  NOT NULL,
  `email`         VARCHAR(120) NOT NULL,
  `phone`         VARCHAR(30)  DEFAULT NULL,
  `address`       VARCHAR(255) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Veterinarians shown on the website and assigned to appointments
-- work_days uses ISO weekday numbers: 1 = Monday … 7 = Sunday
-- ---------------------------------------------------------------------
CREATE TABLE `vets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `title`      VARCHAR(120) NOT NULL,
  `specialty`  VARCHAR(120) DEFAULT NULL,
  `bio`        TEXT,
  `photo`      VARCHAR(255) DEFAULT NULL,
  `work_days`  VARCHAR(20)  NOT NULL DEFAULT '1,2,3,4,5,6',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Services & price list
-- ---------------------------------------------------------------------
CREATE TABLE `services` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `category`         VARCHAR(40)  NOT NULL,
  `description`      VARCHAR(500) NOT NULL DEFAULT '',
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0,
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `icon`             VARCHAR(40)  NOT NULL DEFAULT 'stethoscope',
  `is_vaccination`   TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`       SMALLINT     NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pets (patients) — each belongs to one owner account
-- ---------------------------------------------------------------------
CREATE TABLE `pets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id`    INT UNSIGNED NOT NULL,
  `name`        VARCHAR(60)  NOT NULL,
  `species`     VARCHAR(20)  NOT NULL,
  `breed`       VARCHAR(80)  DEFAULT NULL,
  `sex`         ENUM('Male','Female','Unknown') NOT NULL DEFAULT 'Unknown',
  `birthdate`   DATE         DEFAULT NULL,
  `weight_kg`   DECIMAL(6,2) DEFAULT NULL,
  `color`       VARCHAR(60)  DEFAULT NULL,
  `is_neutered` TINYINT(1)   NOT NULL DEFAULT 0,
  `allergies`   VARCHAR(255) DEFAULT NULL,
  `notes`       TEXT,
  `photo`       VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pets_owner` (`owner_id`),
  KEY `idx_pets_species` (`species`),
  CONSTRAINT `fk_pets_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Appointments (price is copied from the service when booked)
-- ---------------------------------------------------------------------
CREATE TABLE `appointments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`        VARCHAR(12)  NOT NULL,
  `pet_id`           INT UNSIGNED NOT NULL,
  `owner_id`         INT UNSIGNED NOT NULL,
  `service_id`       INT UNSIGNED NOT NULL,
  `vet_id`           INT UNSIGNED DEFAULT NULL,
  `appointment_date` DATE         NOT NULL,
  `start_time`       TIME         NOT NULL,
  `end_time`         TIME         NOT NULL,
  `status`           ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0,
  `reason`           VARCHAR(500) DEFAULT NULL,
  `staff_notes`      TEXT,
  `booked_by`        ENUM('owner','staff') NOT NULL DEFAULT 'owner',
  `cancel_reason`    VARCHAR(255) DEFAULT NULL,
  `reminded_at`      DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointments_reference` (`reference`),
  KEY `idx_appt_date` (`appointment_date`, `start_time`),
  KEY `idx_appt_vet_date` (`vet_id`, `appointment_date`),
  KEY `idx_appt_owner` (`owner_id`),
  KEY `idx_appt_pet` (`pet_id`),
  KEY `idx_appt_status` (`status`),
  CONSTRAINT `fk_appt_pet`     FOREIGN KEY (`pet_id`)     REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_appt_vet`     FOREIGN KEY (`vet_id`)     REFERENCES `vets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Medical records (visit notes) and vaccinations
-- ---------------------------------------------------------------------
CREATE TABLE `medical_records` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pet_id`         INT UNSIGNED NOT NULL,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `vet_id`         INT UNSIGNED DEFAULT NULL,
  `visit_date`     DATE         NOT NULL,
  `weight_kg`      DECIMAL(6,2) DEFAULT NULL,
  `temperature_c`  DECIMAL(4,1) DEFAULT NULL,
  `diagnosis`      VARCHAR(255) NOT NULL,
  `treatment`      TEXT,
  `prescription`   TEXT,
  `notes`          TEXT,
  `follow_up_date` DATE         DEFAULT NULL,
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_records_appointment` (`appointment_id`),
  KEY `idx_records_pet` (`pet_id`, `visit_date`),
  CONSTRAINT `fk_records_pet`  FOREIGN KEY (`pet_id`)         REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_records_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_records_vet`  FOREIGN KEY (`vet_id`)         REFERENCES `vets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_records_user` FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vaccinations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pet_id`         INT UNSIGNED NOT NULL,
  `vaccine_name`   VARCHAR(100) NOT NULL,
  `date_given`     DATE         NOT NULL,
  `next_due_date`  DATE         DEFAULT NULL,
  `batch_no`       VARCHAR(50)  DEFAULT NULL,
  `vet_id`         INT UNSIGNED DEFAULT NULL,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `notes`          VARCHAR(255) DEFAULT NULL,
  `reminded_at`    DATETIME     DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vax_pet` (`pet_id`, `vaccine_name`, `date_given`),
  KEY `idx_vax_due` (`next_due_date`),
  CONSTRAINT `fk_vax_pet`  FOREIGN KEY (`pet_id`)         REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vax_vet`  FOREIGN KEY (`vet_id`)         REFERENCES `vets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vax_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- In-app notifications, website inquiries and the activity feed
-- ---------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(30)  NOT NULL DEFAULT 'system',
  `title`      VARCHAR(150) NOT NULL,
  `message`    VARCHAR(500) NOT NULL DEFAULT '',
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(120) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `subject`    VARCHAR(150) NOT NULL,
  `message`    TEXT         NOT NULL,
  `status`     ENUM('new','read','resolved') NOT NULL DEFAULT 'new',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `icon`       VARCHAR(40)  NOT NULL DEFAULT 'activity',
  `link`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Starter data
-- =====================================================================

-- Passwords: admin123 / staff123 (bcrypt hashes created with password_hash)
INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `phone`, `address`, `password_hash`, `created_at`) VALUES
(1, 'admin', 'Andrea', 'Reyes', 'admin@pawcare.test', '0917 123 4567', '123 Rizal Avenue, Brgy. San Roque, Quezon City', '$2y$10$79jpMytCc88RjMZYLaYknOl7RLDBLsHQKiH3YAe1Q.mxBCqhhjl/i', '2024-01-08 09:00:00'),
(2, 'staff', 'Joy', 'Ramirez', 'staff@pawcare.test', '0917 765 4321', 'Brgy. Kamuning, Quezon City', '$2y$10$zavsE2bKXs29c22fXuR.RuIfL/Z97f9cMjxL8k5SHOI/FdKt319V.', '2024-01-08 09:30:00');

INSERT INTO `vets` (`id`, `name`, `title`, `specialty`, `bio`, `photo`, `work_days`) VALUES
(1, 'Dr. Andrea Reyes', 'Chief Veterinarian', 'General Practice & Soft-Tissue Surgery', 'Andrea founded PawCare after years at a busy animal hospital. She loves solving tricky cases and making anxious pets feel safe.', 'assets/img/vets/andrea.jpg', '1,2,3,4,5'),
(2, 'Dr. Paolo Mendoza', 'Veterinarian', 'Internal Medicine & Diagnostics', 'Paolo leads our laboratory and imaging team, turning bloodwork and X-rays into clear answers for worried pet parents.', 'assets/img/vets/paolo.jpg', '1,2,4,5,6'),
(3, 'Dr. Kristine Lim', 'Veterinarian', 'Feline, Rabbit & Exotic Pet Care', 'Kristine is our go-to vet for cats, rabbits and birds. Her fear-free handling helps even nervous pets relax.', 'assets/img/vets/kristine.jpg', '2,3,4,6'),
(4, 'Dr. Rafael Bautista', 'Veterinary Surgeon', 'Surgery & Dentistry', 'Rafael performs our spay, neuter and dental procedures with a gentle touch and a calm, well-monitored operating room.', 'assets/img/vets/rafael.jpg', '1,3,4,5,6');

INSERT INTO `services` (`id`, `name`, `category`, `description`, `price`, `duration_minutes`, `icon`, `is_vaccination`, `sort_order`) VALUES
(1,  'General Check-up', 'Wellness', 'Nose-to-tail physical exam, weight and vital signs, plus personalised advice on diet and care.', 500, 30, 'stethoscope', 0, 1),
(2,  'Vaccination', 'Wellness', 'Core and booster vaccines for dogs and cats, recorded in your pet''s digital vaccine card.', 850, 30, 'syringe', 1, 2),
(3,  'Deworming & Parasite Control', 'Wellness', 'Deworming plus flea, tick and heartworm prevention tailored to your pet''s lifestyle.', 400, 30, 'shield-check', 0, 3),
(4,  'Microchipping', 'Wellness', 'A quick, permanent ID chip so your pet can always find the way home.', 1200, 30, 'microchip', 0, 4),
(5,  'Sick Pet Consultation', 'Medical', 'Not eating, vomiting, limping or scratching? We find out what is wrong and start treatment right away.', 650, 30, 'heart-pulse', 0, 5),
(6,  'Skin & Allergy Care', 'Medical', 'Diagnosis and treatment plans for itching, hot spots, ear infections and food allergies.', 750, 30, 'sparkles', 0, 6),
(7,  'Laboratory Tests', 'Diagnostics', 'Complete blood count, blood chemistry, urinalysis and fecalysis with same-day results.', 1800, 30, 'flask-conical', 0, 7),
(8,  'Digital X-Ray', 'Diagnostics', 'Fast, low-dose digital radiographs to check bones, chest and abdomen.', 1500, 30, 'scan-line', 0, 8),
(9,  'Ultrasound', 'Diagnostics', 'Painless imaging for pregnancy checks and abdominal organs.', 1400, 30, 'activity', 0, 9),
(10, 'Spay & Neuter', 'Surgery', 'Safe sterilisation under monitored anaesthesia, with pain relief and after-care instructions.', 4500, 120, 'hospital', 0, 10),
(11, 'Minor Surgery', 'Surgery', 'Wound repair, lump removal and other day procedures performed by our surgical team.', 3500, 90, 'bandage', 0, 11),
(12, 'Dental Cleaning', 'Dental', 'Scaling and polishing to remove tartar, fight bad breath and protect your pet''s gums.', 2500, 60, 'smile', 0, 12),
(13, 'Full Grooming', 'Grooming', 'Bath, blow-dry, haircut, nail trim and ear cleaning. Your pet leaves fresh and fluffy.', 900, 90, 'scissors', 0, 13),
(14, 'Bath & Nail Trim', 'Grooming', 'A quick refresh: shampoo bath, blow-dry, nail clipping and ear cleaning.', 450, 30, 'bath', 0, 14);
