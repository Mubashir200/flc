<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$tournaments = $db->query("SELECT * FROM tournaments WHERE status != 'Draft' ORDER BY start_date DESC")->fetchAll();
$pageTitle = 'Tournaments - Football Leaders Academy';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-banner" style="background: linear-gradient(rgba(0,0,0,0.8), rgba(0,0,0,0.8)), url('<?= BASE_URL ?>/assets/images/slider1.jpg') center/cover;">
    <div class="container">
        <h1>Academy <span class="red">Tournaments</span></h1>
        <p>Witness the rising stars in action across our official leagues and cups.</p>
    </div>
</section>

<section class="py-60">
    <div class="container">
        <?php if (empty($tournaments)): ?>
            <div class="text-center" style="padding:100px 0;">
                <i class="fas fa-trophy" style="font-size:4rem;color:#222;margin-bottom:20px;"></i>
                <h3>No Ongoing Tournaments</h3>
                <p style="color:#666;">Check back soon for upcoming academy events and leagues.</p>
            </div>
        <?php else: ?>
            <div class="grid-3">
                <?php foreach($tournaments as $t): ?>
                    <div class="program-card" style="display:flex;flex-direction:column;">
                        <div class="program-img" style="height:200px;position:relative;">
                            <?php if($t['banner']): ?>
                                <img src="<?= BASE_URL ?>/assets/images/tournaments/<?= $t['banner'] ?>" alt="<?= sanitize($t['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;color:#333;"><i class="fas fa-trophy fa-3x"></i></div>
                            <?php endif; ?>
                            <span class="badge" style="position:absolute;top:15px;right:15px;background:#e50914;"><?= $t['status'] ?></span>
                        </div>
                        <div class="program-info" style="flex:1;display:flex;flex-direction:column;padding:25px;">
                            <span class="red" style="font-size:0.75rem;font-weight:bold;text-transform:uppercase;letter-spacing:1px;"><?= $t['type'] ?></span>
                            <h3 style="margin:10px 0;"><?= sanitize($t['name']) ?></h3>
                            <p style="font-size:0.9rem;color:#999;margin-bottom:20px;"><?= sanitize(substr($t['description'], 0, 100)) ?>...</p>
                            
                            <div style="margin-top:auto;border-top:1px solid #222;padding-top:15px;display:flex;justify-content:space-between;align-items:center;">
                                <div style="font-size:0.8rem;color:#666;">
                                    <i class="fas fa-calendar-alt"></i> <?= date('M Y', strtotime($t['start_date'])) ?>
                                </div>
                                <a href="tournament-details.php?id=<?= $t['id'] ?>" class="btn-red" style="padding:8px 15px;font-size:0.8rem;">View Standings</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
