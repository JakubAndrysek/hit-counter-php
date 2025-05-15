USE `hits`;

-- Create or update the hits table to store total hit counts
CREATE TABLE IF NOT EXISTS hits (
    url VARCHAR(255) PRIMARY KEY,
    hits INT NOT NULL DEFAULT 0
);

-- Create or update the access_logs table to store chart data for up to 1 year
CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    access_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (url),
    INDEX (access_time)
);
