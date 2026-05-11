DROP DATABASE IF EXISTS f1app;
CREATE DATABASE f1app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE f1app;

CREATE TABLE roles (
    id TINYINT UNSIGNED PRIMARY KEY,
    display_name VARCHAR(50) NOT NULL UNIQUE,
    internal_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL UNIQUE,
    base_location VARCHAR(150),
    principal_name VARCHAR(100),
    engine_supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE drivers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    nationality VARCHAR(80),
    racing_number INT UNSIGNED,
    street VARCHAR(250),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    zip VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_drivers_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
    CONSTRAINT uq_drivers_team_number UNIQUE (team_id, racing_number),
    INDEX idx_drivers_team (team_id),
    INDEX idx_drivers_name (last_name, first_name)
);

CREATE TABLE circuits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    circuit_name VARCHAR(150) NOT NULL UNIQUE,
    location VARCHAR(150) NOT NULL,
    country VARCHAR(80) NOT NULL,
    length_km DECIMAL(5,3) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE races (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    circuit_id INT UNSIGNED NOT NULL,
    race_name VARCHAR(150) NOT NULL,
    race_date DATE NOT NULL,
    scheduled_laps INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_races_circuit FOREIGN KEY (circuit_id) REFERENCES circuits(id) ON DELETE RESTRICT,
    CONSTRAINT uq_races_name_date UNIQUE (race_name, race_date),
    INDEX idx_races_circuit (circuit_id),
    INDEX idx_races_date (race_date)
);

CREATE TABLE driver_statistics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT UNSIGNED NOT NULL,
    race_id INT UNSIGNED NOT NULL,
    finish_position INT UNSIGNED,
    points DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    laps_completed INT UNSIGNED NOT NULL DEFAULT 0,
    pit_stops INT UNSIGNED NOT NULL DEFAULT 0,
    best_lap_time_ms INT UNSIGNED,
    dnf TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_driver_statistics_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_driver_statistics_race FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    CONSTRAINT uq_driver_statistics_driver_race UNIQUE (driver_id, race_id),
    CONSTRAINT uq_driver_statistics_race_finish UNIQUE (race_id, finish_position),
    INDEX idx_driver_statistics_driver (driver_id),
    INDEX idx_driver_statistics_race (race_id)
);

CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL DEFAULT 4,
    team_id INT UNSIGNED,
    driver_id INT UNSIGNED,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_accounts_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounts_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_accounts_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    INDEX idx_accounts_role (role_id),
    INDEX idx_accounts_team (team_id),
    INDEX idx_accounts_driver (driver_id),
    INDEX idx_accounts_active (is_active)
);

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_password_resets_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_password_resets_account (account_id),
    INDEX idx_password_resets_expires (expires_at)
);

CREATE TABLE failed_logins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    account_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_failed_logins_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_failed_logins_email (email),
    INDEX idx_failed_logins_account (account_id),
    INDEX idx_failed_logins_attempted (attempted_at)
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT UNSIGNED,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_logs_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_audit_logs_account (account_id),
    INDEX idx_audit_logs_action (action),
    INDEX idx_audit_logs_entity (entity_type, entity_id),
    INDEX idx_audit_logs_created (created_at)
);

INSERT INTO roles (id, display_name, internal_name) VALUES
(1, 'League Director', 'league_director'),
(2, 'Team Manager', 'team_manager'),
(3, 'Driver', 'driver'),
(4, 'Viewer', 'viewer');

INSERT INTO teams (id, team_name, base_location, principal_name, engine_supplier) VALUES
(1, 'Red Bull Racing', 'Milton Keynes, United Kingdom', 'Christian Horner', 'Honda RBPT'),
(2, 'Ferrari', 'Maranello, Italy', 'Frederic Vasseur', 'Ferrari'),
(3, 'Mercedes', 'Brackley, United Kingdom', 'Toto Wolff', 'Mercedes'),
(4, 'McLaren', 'Woking, United Kingdom', 'Andrea Stella', 'Mercedes');

INSERT INTO drivers (id, team_id, first_name, last_name, date_of_birth, nationality, racing_number, city, country) VALUES
(1, 1, 'Max', 'Verstappen', '1997-09-30', 'Netherlands', 1, 'Hasselt', 'Belgium'),
(2, 1, 'Sergio', 'Perez', '1990-01-26', 'Mexico', 11, 'Guadalajara', 'Mexico'),
(3, 2, 'Charles', 'Leclerc', '1997-10-16', 'Monaco', 16, 'Monte Carlo', 'Monaco'),
(4, 2, 'Carlos', 'Sainz', '1994-09-01', 'Spain', 55, 'Madrid', 'Spain'),
(5, 3, 'Lewis', 'Hamilton', '1985-01-07', 'United Kingdom', 44, 'Stevenage', 'United Kingdom'),
(6, 4, 'Lando', 'Norris', '1999-11-13', 'United Kingdom', 4, 'Bristol', 'United Kingdom');

INSERT INTO circuits (id, circuit_name, location, country, length_km) VALUES
(1, 'Bahrain International Circuit', 'Sakhir', 'Bahrain', 5.412),
(2, 'Albert Park Circuit', 'Melbourne', 'Australia', 5.278);

INSERT INTO races (id, circuit_id, race_name, race_date, scheduled_laps) VALUES
(1, 1, 'Bahrain Grand Prix', '2024-03-02', 57),
(2, 2, 'Australian Grand Prix', '2024-03-24', 58);

INSERT INTO driver_statistics (driver_id, race_id, finish_position, points, laps_completed, pit_stops, best_lap_time_ms, dnf) VALUES
(1, 1, 1, 25.00, 57, 2, 95440, 0),
(3, 1, 2, 18.00, 57, 2, 96220, 0),
(6, 1, 3, 15.00, 57, 2, 96610, 0),
(5, 2, NULL, 0.00, 17, 1, 81120, 1);

INSERT INTO accounts (id, email, username, password_hash, role_id, team_id, driver_id, is_active) VALUES
(1, 'director@f1app.test', 'director', '$2y$10$RKLE4.zZ9oIkZCiUjvi6t.cGdmlkIkKQ7JuwmVXN2JyABMhqZ.JWW', 1, NULL, NULL, 1),
(2, 'redbull.manager@f1app.test', 'redbull_manager', '$2y$10$zfxwYXL6i5vQ71HTaZFiMeMVD/qPDmZp/HBWxb1bmkTeoAFg0AGCS', 2, 1, NULL, 1),
(3, 'max.verstappen@f1app.test', 'max_verstappen', '$2y$10$PWzsfAbrzIxKcw/jbNchte/vWvE0olvglHbrRAJi7oDb1v3nWR25m', 3, NULL, 1, 1),
(4, 'viewer@f1app.test', 'viewer', '$2y$10$/oVjay3vQQkileZzh6xM0ulyWaMxcM5MoJMTwEXmPqU7bcKb3Od6e', 4, NULL, NULL, 1);
