<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

echo "Starting Database Sync...\n";

// 0. Ensure Tables Exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        duration_days INT NOT NULL,
        features TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS programs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image VARCHAR(255),
        learn_more TEXT,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tables ensured.\n";
} catch (Exception $e) { echo "Table Creation Error: " . $e->getMessage() . "\n"; }

// 1. Fix Tournaments Table
try {
    $db->exec("ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS type ENUM('league', 'knockout', 'group_stage') DEFAULT 'league' AFTER name");
    $db->exec("ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS banner VARCHAR(255) DEFAULT NULL AFTER status");
    echo "Tournaments table synced.\n";
} catch (Exception $e) { echo "Tournaments Sync Error: " . $e->getMessage() . "\n"; }

// 2. Fix Gallery Table
try {
    $db->exec("ALTER TABLE gallery ADD COLUMN IF NOT EXISTS category ENUM('general', 'student') DEFAULT 'general' AFTER image");
    echo "Gallery table synced.\n";
} catch (Exception $e) { echo "Gallery Sync Error: " . $e->getMessage() . "\n"; }

// 3. Fix Matches Table
try {
    $db->exec("ALTER TABLE matches ADD COLUMN IF NOT EXISTS match_date DATE DEFAULT NULL AFTER team2_id");
    $db->exec("ALTER TABLE matches ADD COLUMN IF NOT EXISTS match_time TIME DEFAULT NULL AFTER match_date");
    echo "Matches table synced.\n";
} catch (Exception $e) { echo "Matches Sync Error: " . $e->getMessage() . "\n"; }

// 4. Fix Players Table
try {
    $db->exec("ALTER TABLE players ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL AFTER name");
    $db->exec("ALTER TABLE players ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER plain_password");
    echo "Players table synced.\n";
} catch (Exception $e) { echo "Players Sync Error: " . $e->getMessage() . "\n"; }

// 5. Fix Subscription Plans Table
try {
    $db->exec("ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active'");
    echo "Subscription Plans table synced.\n";
} catch (Exception $e) { echo "Subscription Plans Sync Error: " . $e->getMessage() . "\n"; }

echo "Database Sync Complete.\n";
