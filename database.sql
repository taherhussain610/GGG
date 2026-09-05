CREATE DATABASE IF NOT EXISTS neon_royale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE neon_royale;

CREATE TABLE users(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(40) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 balance BIGINT NOT NULL DEFAULT 10000,
 xp INT UNSIGNED NOT NULL DEFAULT 0,
 level INT UNSIGNED NOT NULL DEFAULT 1,
 is_admin TINYINT(1) NOT NULL DEFAULT 0,
 last_bonus_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE game_transactions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 game VARCHAR(40) NOT NULL,
 bet BIGINT NOT NULL,
 payout BIGINT NOT NULL DEFAULT 0,
 result VARCHAR(500) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id,created_at),
 CONSTRAINT fk_tx_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sports_events(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sport VARCHAR(30) NOT NULL,
 league VARCHAR(100) NOT NULL,
 home_team VARCHAR(100) NOT NULL,
 away_team VARCHAR(100) NOT NULL,
 starts_at DATETIME NOT NULL,
 home_odds DECIMAL(8,2) NOT NULL,
 draw_odds DECIMAL(8,2) NULL,
 away_odds DECIMAL(8,2) NOT NULL,
 status ENUM('upcoming','live','finished') NOT NULL DEFAULT 'upcoming',
 winner ENUM('home','draw','away') NULL,
 result_summary VARCHAR(120) NULL
) ENGINE=InnoDB;

CREATE TABLE sports_picks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 event_id INT UNSIGNED NOT NULL,
 selection VARCHAR(20) NOT NULL,
 odds DECIMAL(8,2) NOT NULL,
 stake BIGINT NOT NULL,
 potential_win BIGINT NOT NULL,
 status ENUM('open','won','lost') NOT NULL DEFAULT 'open',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_pick_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_pick_event FOREIGN KEY(event_id) REFERENCES sports_events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sports_events(sport,league,home_team,away_team,starts_at,home_odds,draw_odds,away_odds,status,winner,result_summary) VALUES
('Cricket','Virtual Premier League','Mumbai Falcons','Chennai Kings',DATE_ADD(NOW(),INTERVAL 25 MINUTE),1.85,NULL,1.92,'upcoming',NULL,NULL),
('Football','Virtual Champions Cup','Hyderabad FC','Kolkata United',DATE_ADD(NOW(),INTERVAL 55 MINUTE),2.10,3.35,2.75,'upcoming',NULL,NULL),
('Tennis','Virtual Open','Arjun Rao','Vikram Shah',DATE_ADD(NOW(),INTERVAL 90 MINUTE),1.65,NULL,2.20,'upcoming',NULL,NULL),
('Basketball','Virtual Pro League','Delhi Titans','Pune Rockets',DATE_ADD(NOW(),INTERVAL 2 HOUR),1.70,NULL,2.15,'upcoming',NULL,NULL),
('Cricket','Virtual Super Series','Jaipur Blazers','Surat Sharks',DATE_SUB(NOW(),INTERVAL 75 MINUTE),1.95,NULL,1.80,'finished','away','Surat Sharks won by 5 wickets'),
('Football','Virtual League One','Goa Mariners','Lucknow Tigers',DATE_SUB(NOW(),INTERVAL 2 HOUR),2.05,3.10,2.90,'finished','draw','Goa Mariners 1 - 1 Lucknow Tigers');
