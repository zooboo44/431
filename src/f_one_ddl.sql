-- Always rebuild the database entirely when running this file
DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;

-- Use the new database we just made
USE FORMULA_ONE;

-- Represent each indnividual competing team
CREATE TABLE teams(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    season_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    principal VARCHAR(100) NOT NULL,
    car_name VARCHAR(100) NOT NULL,
    power_unit VARCHAR(100) NOT NULL,
    total_points INT UNSIGNED NOT NULL DEFAULT 0,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);