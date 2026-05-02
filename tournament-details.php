<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id=? AND status != 'Draft'");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) { header('Location: tournaments.php'); exit; }

// Fetch Standings
$standings = $db->prepare("SELECT s.*, t.name as team_name, t.team_image 
                           FROM tournament_standings s 
                           JOIN teams t ON s.team_id = t.id 
                           WHERE s.tournament_id=? 
                           ORDER BY s.group_name, s.points DESC, s.gd DESC, s.gf DESC");
$standings->execute([$id]);
$rows = $standings->fetchAll();
$groups = [];
foreach($rows as $r) { $g = $r['group_name'] ?: 'League'; $groups[$g][] = $r; }

// Fetch Fixtures
$matches = $db->prepare("SELECT m.*, t1.name as team_a, t2.name as team_b, t1.team_image as img_a, t2.team_image as img_b 
                         FROM tournament_matches m 
                         JOIN teams t1 ON m.team_a_id=t1.id 
                         JOIN teams t2 ON m.team_b_id=t2.id 
                         WHERE m.tournament_id=? 
                         ORDER BY m.match_date ASC, m.match_time ASC");
$matches->execute([$id]);
$matches = $matches->fetchAll();

$pageTitle = $t['name'] . ' - Standings & Fixtures';
require_once __DIR__ . '/includes/header.php';
?>
<section class="tournament-hero" style="background: linear-gradient(rgba(0,0,0,0.85), rgba(0,0,0,0.85)), url('<?= BASE_URL ?>/assets/images/tournaments/<?= $t['banner'] ?>') center/cover; padding: 80px 0;">
    <div class="container text-center">
        <span class="badge" style="background:#e50914;margin-bottom:15px;"><?= $t['status'] ?></span>
        <h1 style="font-size:3.5rem;margin-bottom:10px;"><?= sanitize($t['name']) ?></h1>
        <div style="color:#999;font-size:1.1rem;display:flex;justify-content:center;gap:20px;">
            <span><i class="fas fa-futbol red"></i> <?= $t['type'] ?></span>
            <span><i class="fas fa-map-marker-alt red"></i> <?= sanitize($t['location']) ?></span>
            <span><i class="fas fa-users red"></i> <?= $t['age_category'] ?></span>
        </div>
    </div>
</section>

<div class="container py-60">
    <div style="display:grid;grid-template-columns: 1fr 350px; gap:40px; align-items: flex-start;" class="mobile-stack">
        <div>
            <!-- STANDINGS -->
            <?php if(!empty($groups)): ?>
                <h2 style="margin-bottom:25px;"><i class="fas fa-table red"></i> Standings</h2>
                <?php foreach($groups as $gName => $rows): ?>
                    <div class="data-card" style="margin-bottom:30px;background:#111;border:1px solid #222;">
                        <?php if($gName !== 'League'): ?><h3 style="padding:15px 20px;margin:0;border-bottom:1px solid #222;">Group <?= $gName ?></h3><?php endif; ?>
                        <div class="table-responsive">
                            <table class="data-table" style="width:100%;">
                                <thead><tr><th style="width:40px;">#</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GD</th><th>Pts</th></tr></thead>
                                <tbody>
                                    <?php $pos=1; foreach($rows as $r): ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:bold;color:<?= $pos<=2?'#e50914':'#666' ?>"><?= $pos++ ?></td>
                                        <td><div style="display:flex;align-items:center;gap:10px;"><strong><?= $r['team_name'] ?></strong></div></td>
                                        <td><?= $r['played'] ?></td><td><?= $r['won'] ?></td><td><?= $r['drawn'] ?></td><td><?= $r['lost'] ?></td>
                                        <td><?= ($r['gd']>0?'+':'').$r['gd'] ?></td><td style="font-weight:bold;color:#fff;"><?= $r['points'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- FIXTURES -->
            <h2 style="margin:40px 0 25px;"><i class="fas fa-calendar-alt red"></i> Match Schedule</h2>
            <?php if(empty($matches)): ?>
                <p style="color:#666;">No matches scheduled yet.</p>
            <?php else: foreach($matches as $m): ?>
                <div class="match-card" style="background:#111;border:1px solid #222;border-radius:12px;margin-bottom:15px;padding:20px;display:flex;align-items:center;gap:20px;">
                    <div style="flex:1;text-align:right;font-weight:bold;font-size:1.1rem;"><?= $m['team_a'] ?></div>
                    <div style="text-align:center;min-width:100px;">
                        <?php if($m['status']==='Completed'): ?>
                            <div style="font-size:1.8rem;font-weight:900;color:#e50914;"><?= $m['score_a'] ?> - <?= $m['score_b'] ?></div>
                        <?php else: ?>
                            <div style="background:#222;padding:5px 10px;border-radius:6px;font-size:0.8rem;color:#999;"><?= $m['match_time'] ? date('H:i', strtotime($m['match_time'])) : 'TBA' ?></div>
                        <?php endif; ?>
                        <div style="font-size:0.7rem;color:#666;text-transform:uppercase;margin-top:5px;"><?= $m['status'] ?></div>
                    </div>
                    <div style="flex:1;text-align:left;font-weight:bold;font-size:1.1rem;"><?= $m['team_b'] ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <aside>
            <div class="data-card" style="padding:25px;background:#111;border:1px solid #222;position:sticky;top:100px;">
                <h3 style="margin-top:0;margin-bottom:20px;"><i class="fas fa-info-circle red"></i> Tournament Info</h3>
                <div style="display:flex;flex-direction:column;gap:15px;font-size:0.95rem;">
                    <div><strong style="color:#666;font-size:0.75rem;text-transform:uppercase;display:block;margin-bottom:3px;">Description</strong><p style="color:#ccc;margin:0;"><?= nl2br(sanitize($t['description'])) ?></p></div>
                    <div><strong style="color:#666;font-size:0.75rem;text-transform:uppercase;display:block;margin-bottom:3px;">Max Teams</strong><div style="color:#fff;"><?= $t['max_teams'] ?> Teams</div></div>
                    <div><strong style="color:#666;font-size:0.75rem;text-transform:uppercase;display:block;margin-bottom:3px;">Age Category</strong><div style="color:#fff;"><?= $t['age_category'] ?></div></div>
                    <div><strong style="color:#666;font-size:0.75rem;text-transform:uppercase;display:block;margin-bottom:3px;">Dates</strong><div style="color:#fff;"><?= date('M d', strtotime($t['start_date'])) ?> — <?= date('M d, Y', strtotime($t['end_date'])) ?></div></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .mobile-stack { grid-template-columns: 1fr !important; }
    .match-card { flex-direction: column; text-align: center; }
    .match-card div { text-align: center !important; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
