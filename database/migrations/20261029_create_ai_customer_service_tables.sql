-- Additive schema for AI Customer Service.
-- Run against an existing VPN2S database after backing it up.
-- Requires vc_users(id) as BIGINT UNSIGNED and does not alter or drop existing tables.
-- CREATE TABLE IF NOT EXISTS is safe to re-run, but does not repair an existing incompatible table.

CREATE TABLE IF NOT EXISTS `vc_ai_conversations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` ENUM('website', 'facebook') NOT NULL DEFAULT 'website',
    `external_id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `guest_email` VARCHAR(255) NULL,
    `guest_name` VARCHAR(100) NULL,
    `state` ENUM('ai_active', 'ai_paused', 'staff_active', 'waiting', 'error') NOT NULL DEFAULT 'ai_active',
    `current_provider` ENUM('openrouter', 'kiraai', 'openai', 'gemini') NULL,
    `handoff_staff_id` BIGINT UNSIGNED NULL,
    `last_message_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_channel_external` (`channel`, `external_id`),
    KEY `idx_ai_conv_created` (`created_at`),
    KEY `idx_ai_conv_state` (`state`),
    KEY `idx_ai_conv_user` (`user_id`),
    KEY `idx_ai_conv_handoff_staff` (`handoff_staff_id`),
    CONSTRAINT `fk_ai_conversations_user`
        FOREIGN KEY (`user_id`) REFERENCES `vc_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_conversations_handoff_staff`
        FOREIGN KEY (`handoff_staff_id`) REFERENCES `vc_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_ai_external_ids` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` ENUM('website', 'facebook') NOT NULL,
    `external_id` VARCHAR(255) NOT NULL,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_channel_external` (`channel`, `external_id`),
    KEY `idx_ai_ext_conv` (`conversation_id`),
    CONSTRAINT `fk_ai_external_ids_conversation`
        FOREIGN KEY (`conversation_id`) REFERENCES `vc_ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_ai_knowledge` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NULL,
    `content` LONGTEXT NOT NULL,
    `status` ENUM('draft', 'pending', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_knowledge_slug` (`slug`),
    KEY `idx_ai_knowledge_slug` (`slug`),
    KEY `idx_ai_knowledge_status` (`status`),
    KEY `idx_ai_knowledge_created_by` (`created_by`),
    KEY `idx_ai_knowledge_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_ai_knowledge_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `vc_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_knowledge_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `vc_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_ai_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender_type` ENUM('user', 'ai', 'staff') NOT NULL,
    `sender_id` BIGINT UNSIGNED NULL,
    `channel` ENUM('website', 'facebook') NOT NULL,
    `external_message_id` VARCHAR(255) NULL,
    `content` TEXT NOT NULL,
    `provider` ENUM('openrouter', 'kiraai', 'openai', 'gemini') NULL,
    `provider_response_ms` INT UNSIGNED NULL,
    `token_usage_prompt` INT UNSIGNED NULL,
    `token_usage_completion` INT UNSIGNED NULL,
    `cost_usd` DECIMAL(10, 6) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_msg_external` (`channel`, `external_message_id`),
    KEY `idx_ai_msg_conv` (`conversation_id`, `created_at`),
    KEY `idx_ai_msg_created` (`created_at`),
    CONSTRAINT `fk_ai_messages_conversation`
        FOREIGN KEY (`conversation_id`) REFERENCES `vc_ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_ai_state_transitions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `from_state` ENUM('ai_active', 'ai_paused', 'staff_active', 'waiting', 'error') NULL,
    `to_state` ENUM('ai_active', 'ai_paused', 'staff_active', 'waiting', 'error') NOT NULL,
    `triggered_by` ENUM('user', 'staff', 'ai', 'system') NOT NULL,
    `trigger_reason` VARCHAR(255) NULL,
    `staff_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_state_conv` (`conversation_id`),
    KEY `idx_ai_state_created` (`created_at`),
    KEY `idx_ai_state_staff` (`staff_id`),
    CONSTRAINT `fk_ai_state_transitions_conversation`
        FOREIGN KEY (`conversation_id`) REFERENCES `vc_ai_conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ai_state_transitions_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `vc_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_ai_webhook_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` ENUM('website', 'facebook') NOT NULL,
    `event_id` VARCHAR(255) NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `payload_hash` VARCHAR(64) NOT NULL,
    `processed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_channel_event` (`channel`, `event_id`),
    KEY `idx_ai_webhook_unprocessed` (`processed`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
