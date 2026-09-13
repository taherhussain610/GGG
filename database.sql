SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance BIGINT NOT NULL DEFAULT 10000,
    xp INT UNSIGNED NOT NULL DEFAULT 0,
    level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_suspended TINYINT(1) NOT NULL DEFAULT 0,
    last_bonus_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    type VARCHAR(40) NOT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_user_created (user_id, created_at),
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game VARCHAR(40) NOT NULL,
    bet BIGINT NOT NULL,
    payout BIGINT NOT NULL DEFAULT 0,
    result VARCHAR(1000) NOT NULL,
    state_json LONGTEXT NULL,
    status ENUM('pending', 'complete') NOT NULL DEFAULT 'complete',
    xp_awarded INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_game_user_created (user_id, created_at),
    INDEX idx_game_name_created (game, created_at),
    CONSTRAINT fk_game_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jackpots (
    game VARCHAR(40) PRIMARY KEY,
    balance BIGINT NOT NULL,
    seed_amount BIGINT NOT NULL,
    winner_user_id INT UNSIGNED NULL,
    won_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jackpot_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport VARCHAR(30) NOT NULL,
    league VARCHAR(100) NOT NULL,
    home_team VARCHAR(100) NOT NULL,
    away_team VARCHAR(100) NOT NULL,
    starts_at DATETIME NOT NULL,
    home_odds DECIMAL(8,2) NOT NULL,
    draw_odds DECIMAL(8,2) NULL,
    away_odds DECIMAL(8,2) NOT NULL,
    status ENUM('upcoming', 'live', 'finished', 'cancelled') NOT NULL DEFAULT 'upcoming',
    result_selection ENUM('home', 'draw', 'away') NULL,
    home_score SMALLINT NULL,
    away_score SMALLINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sports_status_start (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_picks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    selection ENUM('home', 'draw', 'away') NOT NULL,
    odds DECIMAL(8,2) NOT NULL,
    stake BIGINT NOT NULL,
    potential_win BIGINT NOT NULL,
    status ENUM('open', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'open',
    settled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pick_user_created (user_id, created_at),
    INDEX idx_pick_event_status (event_id, status),
    CONSTRAINT fk_pick_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pick_event FOREIGN KEY (event_id) REFERENCES sports_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    details VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO jackpots(game, balance, seed_amount) VALUES
('slots', 250000, 250000),
('roulette', 125000, 125000),
('blackjack', 100000, 100000),
('dice', 75000, 75000),
('poker', 300000, 300000);

INSERT INTO sports_events(
    sport, league, home_team, away_team, starts_at,
    home_odds, draw_odds, away_odds, status
)
SELECT 'Cricket', 'Virtual Premier League', 'Mumbai Falcons', 'Chennai Kings',
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL 25 MINUTE), 1.85, NULL, 1.92, 'upcoming'
WHERE NOT EXISTS (SELECT 1 FROM sports_events LIMIT 1);

INSERT INTO sports_events(
    sport, league, home_team, away_team, starts_at,
    home_odds, draw_odds, away_odds, status
)
SELECT 'Football', 'Virtual Champions Cup', 'Hyderabad City', 'Kolkata United',
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL 55 MINUTE), 2.10, 3.35, 2.75, 'upcoming'
WHERE (SELECT COUNT(*) FROM sports_events) = 1;

INSERT INTO sports_events(
    sport, league, home_team, away_team, starts_at,
    home_odds, draw_odds, away_odds, status
)
SELECT 'Tennis', 'Virtual Open', 'Arjun Rao', 'Vikram Shah',
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 MINUTE), 1.65, NULL, 2.20, 'upcoming'
WHERE (SELECT COUNT(*) FROM sports_events) = 2;

INSERT INTO sports_events(
    sport, league, home_team, away_team, starts_at,
    home_odds, draw_odds, away_odds, status, result_selection, home_score, away_score
)
SELECT 'Basketball', 'Virtual Pro League', 'Delhi Titans', 'Pune Rockets',
       DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), 1.70, NULL, 2.15,
       'finished', 'home', 94, 87
WHERE (SELECT COUNT(*) FROM sports_events) = 3;
