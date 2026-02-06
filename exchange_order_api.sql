CREATE TABLE `trade_logs` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`signal_id` BIGINT(19) NULL DEFAULT NULL,
	`account_id` BIGINT(19) NULL DEFAULT NULL,
	`exchange` VARCHAR(50) NULL DEFAULT 'binance' COLLATE 'utf8mb4_unicode_ci',
	`symbol` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`endpoint` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`payload` JSON NULL DEFAULT NULL,
	`response` JSON NULL DEFAULT NULL,
	`status_code` INT(10) NULL DEFAULT NULL,
	`client_order_id` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_signal_id` (`signal_id`) USING BTREE,
	INDEX `idx_account_id` (`account_id`) USING BTREE,
	INDEX `idx_exchange` (`exchange`) USING BTREE,
	INDEX `idx_client_order_id` (`client_order_id`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=11
;


CREATE TABLE `trading_accounts` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`account_name` VARCHAR(100) NOT NULL COLLATE 'utf8mb3_general_ci',
	`exchange` VARCHAR(50) NOT NULL DEFAULT 'binance' COLLATE 'utf8mb3_general_ci',
	`api_key` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
	`secret_key` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
	`is_active` TINYINT(1) NOT NULL DEFAULT '1',
	`created_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=2
;


CREATE TABLE `strategy_accounts` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`strategy_id` BIGINT(20) UNSIGNED NOT NULL,
	`account_id` BIGINT(20) UNSIGNED NOT NULL,
	`is_active` TINYINT(1) NOT NULL DEFAULT '1',
	`created_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `unique_strategy_account` (`strategy_id`, `account_id`) USING BTREE,
	INDEX `idx_strategy_id` (`strategy_id`) USING BTREE,
	INDEX `idx_account_id` (`account_id`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=3
;


CREATE TABLE `signal_mirror_status` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`qc_signal_id` BIGINT(20) UNSIGNED NOT NULL,
	`strategy_id` BIGINT(20) UNSIGNED NOT NULL,
	`status` ENUM('pending','processing','completed','partial_failed','failed') NOT NULL DEFAULT 'pending' COLLATE 'utf8mb3_general_ci',
	`processed_at` TIMESTAMP NULL DEFAULT NULL,
	`created_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `unique_signal` (`qc_signal_id`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=6
;


CREATE TABLE `positions` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`strategy_id` BIGINT(20) UNSIGNED NOT NULL,
	`account_id` BIGINT(20) UNSIGNED NOT NULL,
	`symbol` VARCHAR(50) NOT NULL COLLATE 'utf8mb3_general_ci',
	`side` ENUM('long','short') NOT NULL COLLATE 'utf8mb3_general_ci',
	`quantity` DECIMAL(20,8) NOT NULL,
	`entry_price` DECIMAL(20,8) NOT NULL,
	`leverage` INT(10) NOT NULL DEFAULT '10',
	`status` ENUM('active','closed') NOT NULL DEFAULT 'active' COLLATE 'utf8mb3_general_ci',
	`opened_at` TIMESTAMP NULL DEFAULT NULL,
	`closed_at` TIMESTAMP NULL DEFAULT NULL,
	`created_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `unique_active_position` (`strategy_id`, `account_id`, `symbol`, `status`) USING BTREE,
	INDEX `idx_account` (`account_id`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=2
;

CREATE TABLE `executions` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`qc_signal_id` BIGINT(20) UNSIGNED NOT NULL,
	`strategy_id` BIGINT(20) UNSIGNED NOT NULL,
	`account_id` BIGINT(20) UNSIGNED NOT NULL,
	`symbol` VARCHAR(50) NOT NULL COLLATE 'utf8mb3_general_ci',
	`type` ENUM('entry','exit') NOT NULL COLLATE 'utf8mb3_general_ci',
	`side` ENUM('long','short') NOT NULL COLLATE 'utf8mb3_general_ci',
	`master_quantity` DECIMAL(20,8) NOT NULL,
	`follower_quantity` DECIMAL(20,8) NOT NULL,
	`leverage` INT(10) NOT NULL DEFAULT '10',
	`binance_order_id` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb3_general_ci',
	`status` ENUM('pending','success','failed','retrying') NOT NULL DEFAULT 'pending' COLLATE 'utf8mb3_general_ci',
	`error_message` TEXT NULL DEFAULT NULL COLLATE 'utf8mb3_general_ci',
	`executed_price` DECIMAL(20,8) NULL DEFAULT NULL,
	`executed_at` TIMESTAMP NULL DEFAULT NULL,
	`created_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_signal` (`qc_signal_id`) USING BTREE,
	INDEX `idx_account` (`account_id`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=5
;



