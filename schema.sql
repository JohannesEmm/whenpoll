-- WhenPoll MySQL schema (migrated from SQLite)
-- Import via phpMyAdmin: Import tab → choose this file → Go
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS votes;
DROP TABLE IF EXISTS slots;
DROP TABLE IF EXISTS polls;
DROP TABLE IF EXISTS calendars;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(255) NOT NULL,
    email    VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created  INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendars (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    provider      VARCHAR(32)  NOT NULL,
    label         VARCHAR(255),
    access_token  TEXT,
    refresh_token TEXT,
    token_expiry  INT UNSIGNED,
    caldav_url    VARCHAR(512),
    caldav_user   VARCHAR(255),
    caldav_pass   VARCHAR(255),
    KEY idx_cal_user (user_id),
    CONSTRAINT fk_cal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polls (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id         VARCHAR(100) NOT NULL UNIQUE,
    user_id           INT UNSIGNED NOT NULL,
    title             VARCHAR(255) NOT NULL,
    description       TEXT,
    admin_token       VARCHAR(64)  NOT NULL,
    created           INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP()),
    deadline          VARCHAR(32),
    finalized_slot_id INT UNSIGNED,
    KEY idx_poll_user (user_id),
    CONSTRAINT fk_poll_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE slots (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    poll_id  INT UNSIGNED NOT NULL,
    slot_dt  VARCHAR(32)  NOT NULL,
    duration INT UNSIGNED NOT NULL DEFAULT 60,
    KEY idx_slot_poll (poll_id),
    CONSTRAINT fk_slot_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE votes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    poll_id     INT UNSIGNED NOT NULL,
    participant VARCHAR(191) NOT NULL,
    email       VARCHAR(255),
    slot_id     INT UNSIGNED NOT NULL,
    status      VARCHAR(16)  NOT NULL DEFAULT 'yes',
    UNIQUE KEY uniq_vote (poll_id, participant, slot_id),
    KEY idx_vote_slot (slot_id),
    CONSTRAINT fk_vote_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
