CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_id` BIGINT UNIQUE NOT NULL,
    `full_name` VARCHAR(255) DEFAULT NULL,
    `inn` VARCHAR(12) DEFAULT NULL,
    `address` VARCHAR(500) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `bank_name` VARCHAR(255) DEFAULT NULL,
    `account_number` VARCHAR(20) DEFAULT NULL,
    `bik` VARCHAR(9) DEFAULT NULL,
    `corr_account` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `client_type` ENUM('fiz', 'ip', 'org') NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `inn` VARCHAR(12) DEFAULT NULL,
    `kpp` VARCHAR(9) DEFAULT NULL,
    `ogrn` VARCHAR(15) DEFAULT NULL,
    `ogrnip` VARCHAR(15) DEFAULT NULL,
    `representative_title` VARCHAR(255) DEFAULT NULL,
    `representative_name` VARCHAR(255) DEFAULT NULL,
    `basis` VARCHAR(255) DEFAULT NULL,
    `passport_series` VARCHAR(4) DEFAULT NULL,
    `passport_number` VARCHAR(6) DEFAULT NULL,
    `passport_issued` VARCHAR(500) DEFAULT NULL,
    `address` VARCHAR(500) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `bank_details` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `doc_type` ENUM('contract', 'act', 'invoice') NOT NULL,
    `contract_number` VARCHAR(50) NOT NULL,
    `service_description` TEXT DEFAULT NULL,
    `amount` DECIMAL(12,2) DEFAULT NULL,
    `amount_words` VARCHAR(500) DEFAULT NULL,
    `date_start` DATE DEFAULT NULL,
    `date_end` DATE DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT 'Москва',
    `payment_terms` VARCHAR(255) DEFAULT NULL,
    `payment_days` INT DEFAULT 5,
    `payment_moment` VARCHAR(255) DEFAULT 'с момента подписания Акта сдачи-приёмки',
    `acceptance_days` INT DEFAULT 5,
    `termination_days` INT DEFAULT 14,
    `pdf_path` VARCHAR(500) DEFAULT NULL,
    `parent_contract_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_states` (
    `telegram_id` BIGINT PRIMARY KEY,
    `state` VARCHAR(100) NOT NULL DEFAULT '',
    `data` JSON DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: add subscription
ALTER TABLE `users` ADD COLUMN `subscription_status` ENUM('free', 'paid') NOT NULL DEFAULT 'free' AFTER `corr_account`;

-- Migration: 152-ФЗ — добавить поле согласия на обработку ПД
ALTER TABLE `users` ADD COLUMN `consent_given` TINYINT(1) NOT NULL DEFAULT 0 AFTER `subscription_status`;

-- Migration: добавить тип документа 'invoice' (счёт на оплату)
ALTER TABLE `documents` MODIFY COLUMN `doc_type` ENUM('contract', 'act', 'invoice') NOT NULL;
