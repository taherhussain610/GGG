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

CREATE TABLE providers(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(80) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 integration_type ENUM('aggregator','direct','demo') NOT NULL DEFAULT 'demo',
 adapter ENUM('demo','placeholder') NOT NULL DEFAULT 'placeholder',
 environment ENUM('demo','sandbox') NOT NULL DEFAULT 'sandbox',
 api_base_url VARCHAR(255) NULL,
 credential_hint VARCHAR(190) NOT NULL DEFAULT 'Not configured',
 callback_path VARCHAR(190) NOT NULL DEFAULT '/provider/webhook.php',
 is_enabled TINYINT(1) NOT NULL DEFAULT 0,
 health_status ENUM('healthy','degraded','offline','unconfigured') NOT NULL DEFAULT 'unconfigured',
 last_health_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE games(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NOT NULL,
 provider_game_id VARCHAR(100) NOT NULL,
 slug VARCHAR(120) NOT NULL UNIQUE,
 title VARCHAR(140) NOT NULL,
 category ENUM('slots','live','instant','table','cards') NOT NULL,
 description VARCHAR(500) NOT NULL,
 thumbnail_url VARCHAR(255) NOT NULL DEFAULT '/assets/provider-game.svg',
 is_enabled TINYINT(1) NOT NULL DEFAULT 1,
 min_bet BIGINT UNSIGNED NOT NULL DEFAULT 10,
 max_bet BIGINT UNSIGNED NOT NULL DEFAULT 1000,
 sort_order INT NOT NULL DEFAULT 0,
 metadata_json LONGTEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE(provider_id,provider_game_id),
 INDEX(category,is_enabled),
 CONSTRAINT fk_games_provider FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE game_sessions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(32) NOT NULL UNIQUE,
 token_hash CHAR(64) NOT NULL UNIQUE,
 user_id INT UNSIGNED NOT NULL,
 provider_id INT UNSIGNED NOT NULL,
 game_id INT UNSIGNED NOT NULL,
 status ENUM('active','closed','expired') NOT NULL DEFAULT 'active',
 balance_snapshot BIGINT NOT NULL,
 expires_at DATETIME NOT NULL,
 last_activity_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id,status,expires_at),
 INDEX(provider_id,status),
 CONSTRAINT fk_sessions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sessions_provider FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE,
 CONSTRAINT fk_sessions_game FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE game_rounds(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 game_session_id BIGINT UNSIGNED NOT NULL,
 external_round_id VARCHAR(80) NOT NULL UNIQUE,
 bet BIGINT UNSIGNED NOT NULL,
 payout BIGINT UNSIGNED NOT NULL DEFAULT 0,
 result VARCHAR(500) NOT NULL,
 status ENUM('settled','void') NOT NULL DEFAULT 'settled',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 settled_at DATETIME NOT NULL,
 INDEX(game_session_id,created_at),
 CONSTRAINT fk_rounds_session FOREIGN KEY(game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_transactions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NOT NULL,
 game_session_id BIGINT UNSIGNED NOT NULL,
 game_round_id BIGINT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 external_transaction_id VARCHAR(120) NOT NULL,
 transaction_type ENUM('bet','win','refund') NOT NULL,
 amount BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','completed','rejected') NOT NULL DEFAULT 'completed',
 payload_json LONGTEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(provider_id,external_transaction_id),
 INDEX(user_id,created_at),
 CONSTRAINT fk_provider_tx_provider FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE,
 CONSTRAINT fk_provider_tx_session FOREIGN KEY(game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
 CONSTRAINT fk_provider_tx_round FOREIGN KEY(game_round_id) REFERENCES game_rounds(id) ON DELETE CASCADE,
 CONSTRAINT fk_provider_tx_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE wallet_transactions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 provider_transaction_id BIGINT UNSIGNED NOT NULL,
 game_session_id BIGINT UNSIGNED NOT NULL,
 direction ENUM('debit','credit') NOT NULL,
 amount BIGINT UNSIGNED NOT NULL,
 balance_before BIGINT NOT NULL,
 balance_after BIGINT NOT NULL,
 reason VARCHAR(120) NOT NULL,
 idempotency_key VARCHAR(140) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id,created_at),
 CONSTRAINT fk_wallet_tx_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_wallet_tx_provider_tx FOREIGN KEY(provider_transaction_id) REFERENCES provider_transactions(id) ON DELETE CASCADE,
 CONSTRAINT fk_wallet_tx_session FOREIGN KEY(game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE jackpots(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NULL,
 game_id INT UNSIGNED NULL,
 name VARCHAR(120) NOT NULL,
 amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_jackpots_provider FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE SET NULL,
 CONSTRAINT fk_jackpots_game FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE provider_api_logs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NULL,
 direction ENUM('inbound','outbound','internal') NOT NULL,
 endpoint VARCHAR(190) NOT NULL,
 http_method VARCHAR(10) NOT NULL,
 request_id VARCHAR(140) NULL UNIQUE,
 status_code SMALLINT UNSIGNED NOT NULL,
 duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
 request_summary LONGTEXT NULL,
 response_summary LONGTEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(provider_id,created_at),
 CONSTRAINT fk_api_logs_provider FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO providers(slug,name,integration_type,adapter,environment,api_base_url,credential_hint,callback_path,is_enabled,health_status,last_health_at) VALUES
('neon-demo','Neon Demo Aggregator','demo','demo','demo',NULL,'Built-in demo adapter; no external credentials required','/provider/webhook.php',1,'healthy',NOW()),
('evolution-placeholder','Evolution integration placeholder','direct','placeholder','sandbox',NULL,'Requires an authorized operator contract and provider-issued credentials','/provider/callback.php',0,'unconfigured',NULL),
('pragmatic-placeholder','Pragmatic Play integration placeholder','direct','placeholder','sandbox',NULL,'Requires an authorized operator contract and provider-issued credentials','/provider/callback.php',0,'unconfigured',NULL);

INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-SLOT-001','neon-reels','Neon Reels','slots','A local three-reel sandbox game from the fake catalog.','/assets/provider-game.svg',1,10,1000,10,'{"feed":"fake","rtp_label":"demo"}' FROM providers WHERE slug='neon-demo';
INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-LIVE-001','royale-live-demo','Royale Live Demo','live','A simulated table feed with no dealer or external stream.','/assets/provider-game.svg',1,10,1000,20,'{"feed":"fake","live":false}' FROM providers WHERE slug='neon-demo';
INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-INSTANT-001','rocket-rush','Rocket Rush','instant','A fast local multiplier round for demo credits.','/assets/provider-game.svg',1,10,1000,30,'{"feed":"fake"}' FROM providers WHERE slug='neon-demo';
INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-TABLE-001','neon-wheel','Neon Wheel','table','A server-settled demo wheel using virtual credits.','/assets/provider-game.svg',1,10,1000,40,'{"feed":"fake"}' FROM providers WHERE slug='neon-demo';
INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-CARD-001','card-studio','Card Studio','cards','A compact sandbox card round from the local adapter.','/assets/provider-game.svg',1,10,1000,50,'{"feed":"fake"}' FROM providers WHERE slug='neon-demo';
INSERT INTO games(provider_id,provider_game_id,slug,title,category,description,thumbnail_url,is_enabled,min_bet,max_bet,sort_order,metadata_json)
SELECT id,'NR-INSTANT-002','dice-flash','Dice Flash','instant','A local high-energy dice-style demo round.','/assets/provider-game.svg',1,10,1000,60,'{"feed":"fake"}' FROM providers WHERE slug='neon-demo';

INSERT INTO jackpots(provider_id,game_id,name,amount,is_active)
SELECT p.id,g.id,'Demo Network Jackpot',250000,1
FROM providers p
JOIN games g ON g.provider_id=p.id AND g.slug='neon-reels'
WHERE p.slug='neon-demo';
