<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

echo "Starting Subscription System Sync...\n";

try {
    // 1. Create player_subscriptions table
    $db->exec("CREATE TABLE IF NOT EXISTS player_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        plan_id INT NOT NULL,
        amount_paid DECIMAL(10,2) DEFAULT 0,
        transaction_id VARCHAR(100) DEFAULT NULL,
        start_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        status ENUM('active', 'expired', 'cancelled', 'pending', 'upgraded') DEFAULT 'active',
        payment_method VARCHAR(50) DEFAULT 'Online',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (player_id),
        INDEX (status)
    )");

    // 2. Create subscription_history table (for upgrade logs)
    $db->exec("CREATE TABLE IF NOT EXISTS subscription_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        old_plan_id INT DEFAULT NULL,
        new_plan_id INT NOT NULL,
        action_type ENUM('new', 'upgrade', 'renew', 'extend') DEFAULT 'new',
        remaining_days_credited INT DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Create id_card_settings table
    $db->exec("CREATE TABLE IF NOT EXISTS id_card_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        is_visible TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 4. Seed ID Card Settings if empty
    $settings = [
        'show_phone', 'show_email', 'show_address', 'show_qr', 
        'show_emergency', 'show_dob', 'show_blood_group', 'show_status'
    ];
    $check = $db->query("SELECT COUNT(*) FROM id_card_settings")->fetchColumn();
    if ($check == 0) {
        $stmt = $db->prepare("INSERT INTO id_card_settings (setting_key, is_visible) VALUES (?, 1)");
        foreach ($settings as $s) {
            $stmt->execute([$s]);
        }
    }

    echo "✅ Subscription System Tables Synced Successfully.\n";

} catch (Exception $e) {
    echo "❌ Sync Error: " . $e->getMessage() . "\n";
}
