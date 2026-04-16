-- Always rebuild the database entirely when running this file
DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;

-- Use the new database we just made
USE FORMULA_ONE;

-- Represent each indnividual competing team for each season
CREATE TABLE teams(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    season_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) UNIQUE NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    principal VARCHAR(100) NOT NULL,
    car_name VARCHAR(100) NOT NULL,
    power_unit VARCHAR(100) NOT NULL,
    total_points INT UNSIGNED NOT NULL DEFAULT 0,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Represent each individual driver
CREATE TABLE drivers(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    racing_number INT NOT NULL,
    date_of_birth DATE NOT NULL,
    total_points INT UNSIGNED NOT NULL DEFAULT 0,
    total_wins INT UNSIGNED NOT NULL DEFAULT 0,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

-- Represent each season of competition
CREATE TABLE seasons(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    year YEAR NOT NULL,
    champion_driver_id INT,
    champion_team_id INT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (champion_driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (champion_team_id) REFERENCES teams(id) ON DELETE CASCADE
);

-- Represent each individual account of the database
CREATE TABLE users(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    name VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role TINYINT NOT NULL DEFAULT 0,
    linked_id INT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
);

-- Represent each existing circuit
CREATE TABLE circuits(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    name VARCHAR(100) NOT NULL,
    country VARCHAR(50) NOT NULL,
    city VARCHAR(50) NOT NULL,
    length_km FLOAT NOT NULL DEFAULT 1.0,
    number_of_laps INT NOT NULL DEFAULT 10,
    circuit_type VARCHAR(20) NOT NULL,
    lap_record_time_ms INT,
    lap_record_driver_id INT,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (lap_record_driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

-- Represent current driver standings
CREATE TABLE driver_standings(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    season_id INT NOT NULL,
    driver_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    wins INT NOT NULL DEFAULT 0,
    podiums INT NOT NULL DEFAULT 0,
    position INT NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

-- Represent current constructors championship standings
CREATE TABLE constructor_standings(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    season_id INT NOT NULL,
    team_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    wins INT NOT NULL DEFAULT 0,
    position INT NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

