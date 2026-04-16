-- Always rebuild the database entirely when running this file
DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;

-- Use the new database we just made
USE FORMULA_ONE;

-- Represent each indnividual competing team
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

-- Represent each individual driver
CREATE TABLE drivers(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    season_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    racing_number INT NOT NULL,
    date_of_birth DATE NOT NULL,
    total_points INT UNSIGNED NOT NULL DEFAULT 0,
    total_wins INT UNSIGNED NOT NULL DEFAULT 0,

    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);