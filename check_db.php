<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$tables = ['tournaments', 'matches', 'gallery', 'subscription_plans', 'players', 'programs'];

echo "<pre>";
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
echo "</pre>";
