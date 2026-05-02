<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$uid = trim($_GET['id'] ?? '');
$token = trim($_GET['t'] ?? '');

// Styled error page function
function showCardError($title, $message) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlspecialchars($title) . '</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">';
    echo '<style>body{font-family:"Roboto",sans-serif;background:#000;color:#fff;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;}.error-card{background:#111;padding:40px;border-radius:20px;text-align:center;max-width:400px;border:1px solid rgba(229,9,20,0.3);}.error-card i{font-size:3rem;color:#e50914;margin-bottom:15px;}.error-card h2{font-family:"Orbitron",sans-serif;font-size:1.2rem;margin:0 0 10px;}.error-card p{color:#888;font-size:.9rem;}</style>';
    echo '</head><body><div class="error-card"><i class="fas fa-exclamation-triangle"></i><h2>' . htmlspecialchars($title) . '</h2><p>' . htmlspecialchars($message) . '</p></div></body></html>';
    exit;
}

if (empty($uid) || empty($token)) {
    showCardError('Invalid Link', 'This QR code or link is invalid. Please contact the academy for a valid player card link.');
}

$stmt = $db->prepare("SELECT p.*, t.name as team_name, t.team_image as team_logo FROM players p LEFT JOIN teams t ON p.team_id = t.id WHERE p.unique_id = ? AND p.player_token = ?");
$stmt->execute([$uid, $token]);
$player = $stmt->fetch();

if (!$player) {
    showCardError('Player Not Found', 'No player profile found for this link. The link may have expired or the player ID is incorrect.');
}

// Fetch stats
$statsStmt = $db->prepare("SELECT * FROM player_stats WHERE player_id = ?");
$statsStmt->execute([$player['id']]);
$stats = $statsStmt->fetch();

$pageTitle = 'Player Verification - ' . $player['name'];

// FETCH SUBSCRIPTION
$subStmt = $db->prepare("SELECT s.*, pl.name as plan_name FROM subscriptions s JOIN subscription_plans pl ON s.plan_id = pl.id WHERE s.player_id=? AND s.status='active' ORDER BY s.expiry_date DESC LIMIT 1");
$subStmt->execute([$player['id']]);
$currentSub = $subStmt->fetch();

$isElite = ($currentSub && stripos($currentSub['plan_name'], 'Elite') !== false);
$isPro = ($currentSub && stripos($currentSub['plan_name'], 'Pro') !== false);
$isBasic = ($currentSub && !$isElite && !$isPro);
$themeClass = $isElite ? 'theme-elite' : ($isPro ? 'theme-pro' : ($isBasic ? 'theme-basic' : ''));

// FETCH VISIBILITY SETTINGS
$settingsRows = $db->query("SELECT setting_key, is_visible FROM id_card_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
function isVis($key, $rows) { return isset($rows[$key]) && $rows[$key] == 1; }

// Remaining days calculation
$daysLeft = 0;
if ($currentSub) {
    $today = strtotime(date('Y-m-d'));
    $expiry = strtotime($currentSub['expiry_date']);
    $daysLeft = ceil(($expiry - $today) / 86400);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --red: #e50914; --dark: #000; --card-bg: #111; }
        body { font-family: 'Roboto', sans-serif; background: var(--dark); color: #fff; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; overflow-x: hidden; }
        
        .verification-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 420px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.9);
            position: relative;
            margin: auto;
        }

        .card-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #000 100%);
            padding: 25px;
            text-align: center;
            border-bottom: 2px solid var(--red);
        }

        .academy-logo { height: 50px; margin-bottom: 10px; border-radius: 8px; }
        .academy-name { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; color: #fff; margin: 0; }
        .academy-name span { color: var(--red); }

        .player-photo-wrap {
            width: 150px;
            height: 150px;
            margin: -75px auto 15px;
            border-radius: 50%;
            border: 4px solid var(--red);
            overflow: hidden;
            background: #222;
            box-shadow: 0 10px 20px rgba(229, 9, 20, 0.4);
            position: relative;
            z-index: 10;
        }
        .player-photo { width: 100%; height: 100%; object-fit: cover; }

        .card-body { padding: 85px 20px 30px; text-align: center; }

        .player-name { font-size: 1.6rem; font-weight: 700; margin: 0 0 5px; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
        .player-id { font-family: 'Orbitron', sans-serif; font-size: 0.9rem; color: var(--red); margin-bottom: 20px; letter-spacing: 2px; }

        .status-verified {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid rgba(16, 185, 129, 0.2);
            margin-bottom: 25px;
            text-transform: uppercase;
        }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; margin-bottom: 25px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; }
        .info-item label { display: block; font-size: 0.65rem; color: #666; text-transform: uppercase; margin-bottom: 3px; font-weight: 600; }
        .info-item span { display: block; font-size: 0.9rem; font-weight: 500; color: #eee; }

        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 10px; 
            margin-bottom: 25px; 
            background: rgba(229, 9, 20, 0.05); 
            padding: 15px; 
            border-radius: 12px; 
            border: 1px solid rgba(229, 9, 20, 0.1);
        }
        .stat-item { text-align: center; }
        .stat-value { display: block; font-family: 'Orbitron', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--red); }
        .stat-label { display: block; font-size: 0.6rem; color: #888; text-transform: uppercase; margin-top: 2px; }

        .qr-section {
            background: #fff;
            padding: 10px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .qr-section img { width: 100px; height: 100px; display: block; }

        .card-footer {
            background: rgba(0,0,0,0.4);
            padding: 15px;
            text-align: center;
            font-size: 0.75rem;
            color: #444;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .jersey-overlay {
            position: absolute;
            top: 100px;
            right: 20px;
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            color: rgba(255,255,255,0.03);
            line-height: 1;
            pointer-events: none;
        }

        /* THEMES */
        .theme-elite .card-header { background: linear-gradient(135deg, #2a2a05 0%, #000 100%); border-bottom: 2px solid #ffd700; }
        .theme-elite .academy-name span { color: #ffd700; }
        .theme-elite .player-photo-wrap { border-color: #ffd700; box-shadow: 0 10px 20px rgba(255,215,0,0.3); }
        .theme-elite .stat-value { color: #ffd700; }
        .badge-elite { background: linear-gradient(90deg, #ffd700, #ff8c00); color: #000; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 900; letter-spacing: 1px; box-shadow: 0 0 15px rgba(255,215,0,0.4); display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }

        .theme-pro .card-header { background: linear-gradient(135deg, #051a2a 0%, #000 100%); border-bottom: 2px solid #00d4ff; }
        .theme-pro .academy-name span { color: #00d4ff; }
        .theme-pro .player-photo-wrap { border-color: #00d4ff; box-shadow: 0 10px 20px rgba(0,212,255,0.3); }
        .theme-pro .stat-value { color: #00d4ff; }
        .badge-pro { background: linear-gradient(90deg, #00d4ff, #0072ff); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }

        .theme-basic .badge-basic { background: rgba(255,255,255,0.1); color: #ccc; border: 1px solid rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }

        @media (max-width: 480px) {
            body { padding: 0; align-items: flex-start; }
            .verification-card { border-radius: 0; min-height: 100vh; border: none; box-shadow: none; }
            .card-header { padding: 40px 20px 20px; }
            .player-photo-wrap { width: 130px; height: 130px; margin-top: -65px; }
            .card-body { padding-top: 75px; }
            .stats-grid { margin-left: 10px; margin-right: 10px; }
            .info-grid { margin-left: 10px; margin-right: 10px; }
        }
    </style>
</head>
<body>

    <div class="verification-card <?= $themeClass ?>">
        <div class="card-header">
            <?php 
            $teamLogo = !empty($player['team_logo']) && file_exists(TEAM_IMG_DIR . $player['team_logo']) 
                ? TEAM_IMG_URL . $player['team_logo'] 
                : BASE_URL . '/assets/images/logo/logo.png'; 
            ?>
            <img src="<?= $teamLogo ?>" alt="Logo" class="academy-logo" loading="lazy">
            <h1 class="academy-name">Football <span>Leaders</span> Academy</h1>
        </div>

        <div class="card-body">
            <div class="player-photo-wrap">
                <img src="<?= playerImage($player['image']) ?>" alt="<?= sanitize($player['name']) ?>" class="player-photo" loading="lazy">
            </div>

            <div class="jersey-overlay">#<?= $player['jersey_number'] ?: '00' ?></div>

            <h2 class="player-name"><?= sanitize($player['name']) ?></h2>
            <div class="player-id"><?= $player['unique_id'] ?></div>

            <?php if($isElite): ?><div class="badge-elite"><i class="fas fa-crown"></i> ELITE SQUAD</div>
            <?php elseif($isPro): ?><div class="badge-pro"><i class="fas fa-star"></i> PRO DEVELOPMENT</div>
            <?php elseif($isBasic): ?><div class="badge-basic"><i class="fas fa-shield-alt"></i> BASIC TRAINING</div>
            <?php else: ?>
            <div class="status-verified">
                <i class="fas fa-check-circle"></i> VERIFIED PLAYER
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['goals'] ?? 0 ?></span>
                    <span class="stat-label">Goals</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['assists'] ?? 0 ?></span>
                    <span class="stat-label">Assists</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $stats['matches_played'] ?? 0 ?></span>
                    <span class="stat-label">Played</span>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label>Team</label>
                    <span><?= sanitize($player['team_name'] ?: 'Academy Team') ?></span>
                </div>
                <div class="info-item">
                    <label>Position</label>
                    <span><?= sanitize($player['position'] ?: '—') ?></span>
                </div>
                <?php if(isVis('show_blood_group', $settingsRows)): ?>
                <div class="info-item">
                    <label>Blood Group</label>
                    <span><?= sanitize($player['blood_group'] ?: '—') ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Jersey No.</label>
                    <span>#<?= $player['jersey_number'] ?: '—' ?></span>
                </div>
                <?php if(isVis('show_dob', $settingsRows)): ?>
                <div class="info-item">
                    <label>Date of Birth</label>
                    <span><?= !empty($player['dob']) ? date('d/m/Y', strtotime($player['dob'])) : '—' ?> (<?= $player['age'] ?? '—' ?> yrs)</span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Join Date</label>
                    <span><?= date('d/m/Y', strtotime($player['created_at'])) ?></span>
                </div>
                <?php if(isVis('show_emergency', $settingsRows)): ?>
                <div class="info-item">
                    <label>Father's Name</label>
                    <span><?= sanitize($player['father_name'] ?: '—') ?></span>
                </div>
                <div class="info-item">
                    <label>Mother's Name</label>
                    <span><?= sanitize($player['mother_name'] ?: '—') ?></span>
                </div>
                <?php endif; ?>
                <?php if(isVis('show_phone', $settingsRows)): ?>
                <div class="info-item">
                    <label>Phone</label>
                    <span><?= sanitize($player['phone'] ?: '—') ?></span>
                </div>
                <?php endif; ?>
                <?php if(isVis('show_email', $settingsRows)): ?>
                <div class="info-item">
                    <label>Email</label>
                    <span style="word-break: break-all;"><?= sanitize($player['email'] ?: '—') ?></span>
                </div>
                <?php endif; ?>
                <?php if(isVis('show_address', $settingsRows)): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <span><?= sanitize($player['address'] ?: '—') ?></span>
                </div>
                <?php endif; ?>

                <?php if(isVis('show_status', $settingsRows) && $currentSub): ?>
                <div class="info-item" style="grid-column: 1 / -1; background: rgba(16, 185, 129, 0.05); padding: 10px; border-radius: 8px; border: 1px dashed rgba(16, 185, 129, 0.2); text-align: center;">
                    <label style="color:#10b981;">Subscription Validity</label>
                    <span style="color:#fff; font-weight:700; font-size:1rem;"><?= $daysLeft ?> Days Left</span>
                    <small style="color:#666;">Expires on <?= date('d/m/Y', strtotime($currentSub['expiry_date'])) ?></small>
                </div>
                <?php endif; ?>
            </div>

            <?php if(isVis('show_qr', $settingsRows)): ?>
            <div class="qr-section">
                <?php if($player['qr_code'] && file_exists(QR_IMG_DIR . $player['qr_code'])): ?>
                    <img src="<?= QR_IMG_URL . $player['qr_code'] ?>" alt="QR" loading="lazy">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <p style="color:#444; font-size:0.65rem; margin-top:15px; text-transform:uppercase; letter-spacing:1px;">Scannable Identity • Digital Player Pass</p>
        </div>

        <div class="card-footer">
            Official FLA Player Verification System &copy; <?= date('Y') ?>
        </div>
    </div>

</body>
</html>
