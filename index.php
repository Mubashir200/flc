<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/razorpay.php';
$pageTitle = 'Football Leaders Academy';
$db = getDB();

// Fetch active subscription plans
$activePlans = $db->query("SELECT * FROM subscription_plans WHERE status='active' ORDER BY price ASC")->fetchAll();

// Handle Join Form submission (with photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'join') {
            if (empty(trim($_POST['full_name'])) || empty($_POST['age']) || empty(trim($_POST['phone'])) || empty(trim($_POST['position']))) {
                flash('error', 'Please fill all required fields.');
                header('Location: ' . BASE_URL . '/#home'); exit;
            }
            $photo = null;
            $croppedData = $_POST['cropped_data'] ?? '';
            
            // Validate that we have either cropped data or an uploaded file
            if (empty($croppedData) && (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK)) {
                flash('error', 'Please upload your profile photo.');
                header('Location: ' . BASE_URL . '/#home'); exit;
            }

            if (!empty($croppedData)) {
                $res = saveBase64Image($croppedData, STUDENT_IMG_DIR);
                if ($res['ok']) $photo = $res['filename'];
                else { flash('error', 'Cropped image save failed: '.$res['error']); header('Location: ' . BASE_URL . '/#home'); exit; }
            } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $res = uploadStudentImage($_FILES['photo']);
                if ($res['ok']) $photo = $res['filename'];
                else { flash('error', 'Image upload failed: '.$res['error']); header('Location: ' . BASE_URL . '/#home'); exit; }
            }
            $stmt = $db->prepare("INSERT INTO join_requests (full_name, father_name, mother_name, email, age, dob, phone, alternative_phone, blood_group, address, position, experience, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($_POST['full_name']),
                trim($_POST['father_name'] ?? ''),
                trim($_POST['mother_name'] ?? ''),
                trim($_POST['email'] ?? ''),
                (int)$_POST['age'],
                trim($_POST['dob'] ?? ''),
                trim($_POST['phone']),
                trim($_POST['alternative_phone'] ?? ''),
                trim($_POST['blood_group'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['position']),
                trim($_POST['experience'] ?? ''),
                $photo
            ]);
            flash('success', 'Application submitted! Our coaches will review it shortly.');
            header('Location: ' . BASE_URL . '/#home'); exit;
        }
        if ($_POST['action'] === 'contact') {
            if (empty(trim($_POST['name'])) || empty(trim($_POST['email'])) || empty(trim($_POST['message']))) {
                flash('error', 'Please fill all required fields.');
                header('Location: ' . BASE_URL . '/#contact'); exit;
            }
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?,?,?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['message'])]);
            flash('success', 'Message sent! We will get back to you soon.');
            header('Location: ' . BASE_URL . '/#contact'); exit;
        }
        if ($_POST['action'] === 'newsletter') {
            $email = trim($_POST['email'] ?? '');
            if ($email) {
                $chk = $db->prepare("SELECT COUNT(*) FROM newsletter WHERE email=?");
                $chk->execute([$email]);
                if ($chk->fetchColumn() == 0) {
                    $db->prepare("INSERT INTO newsletter (email) VALUES (?)")->execute([$email]);
                    flash('success', 'Subscribed successfully!');
                } else {
                    flash('success', 'You are already subscribed!');
                }
            }
            header('Location: ' . BASE_URL . '/#footer'); exit;
        }
        if ($_POST['action'] === 'apply_event' && isPlayer()) {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId) {
                // Check if event is expired
                $evt = $db->prepare("SELECT event_date, event_time FROM events WHERE id=?");
                $evt->execute([$eventId]);
                $eventData = $evt->fetch();
                if ($eventData) {
                    $evDateTime = strtotime($eventData['event_date'] . ' ' . ($eventData['event_time'] ?? '00:00:00'));
                    if ($evDateTime < time()) {
                        flash('error', 'This event has already ended. You cannot apply for it.');
                        header('Location: ' . BASE_URL . '/#events'); exit;
                    }
                }
                $chk = $db->prepare("SELECT COUNT(*) FROM event_applications WHERE player_id=? AND event_id=?");
                $chk->execute([$_SESSION['user_id'], $eventId]);
                if ($chk->fetchColumn() == 0) {
                    $db->prepare("INSERT INTO event_applications (player_id, event_id) VALUES (?,?)")->execute([$_SESSION['user_id'], $eventId]);
                    flash('success', 'Event application submitted!');
                } else {
                    flash('success', 'You have already applied for this event.');
                }
            }
            header('Location: ' . BASE_URL . '/#events'); exit;
        }
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false || strpos($msg, 'UNIQUE constraint') !== false) {
            if (strpos($msg, 'email') !== false) flash('error', 'This email is already registered.');
            elseif (strpos($msg, 'phone') !== false) flash('error', 'This phone number is already registered.');
            else flash('error', 'Duplicate information entered.');
        } else {
            flash('error', 'An error occurred: ' . $msg);
        }
        header('Location: ' . BASE_URL . '/'); exit;
    }
}

// Fetch data
try {
    $filter = $_GET['event_filter'] ?? 'all';
    $sort = $_GET['event_sort'] ?? 'nearest';
    
    $query = "SELECT * FROM events";
    $where = [];
    $today = date('Y-m-d');
    
    if ($filter === 'upcoming') $where[] = "event_date > '$today'";
    elseif ($filter === 'completed') $where[] = "event_date < '$today'";
    elseif ($filter === 'ongoing') $where[] = "event_date = '$today'";
    
    if (!empty($where)) $query .= " WHERE " . implode(" AND ", $where);
    
    if ($sort === 'nearest') $query .= " ORDER BY ABS(DATEDIFF(event_date, '$today')) ASC";
    elseif ($sort === 'oldest') $query .= " ORDER BY event_date ASC";
    else $query .= " ORDER BY event_date DESC"; // default newest
    
    $events = $db->query($query)->fetchAll();
} catch (PDOException $e) { $events = []; }

$igUrl = getSetting($db, 'instagram_url');
$fbUrl = getSetting($db, 'facebook_url');

// Dynamic Programs
$dynamicPrograms = $db->query("SELECT * FROM programs ORDER BY display_order ASC")->fetchAll();
// Dynamic Gallery (Separated)
$studentGallery = $db->query("SELECT * FROM gallery WHERE category='student' ORDER BY created_at DESC LIMIT 12")->fetchAll();
$generalGallery = $db->query("SELECT * FROM gallery WHERE category='general' ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Dynamic Subscriptions
$activePlans = $db->query("SELECT * FROM subscription_plans WHERE status='active' ORDER BY price ASC")->fetchAll();


require_once __DIR__ . '/includes/header.php';
?>

<!-- ══ HERO SECTION ══════════════════════════ -->
<section class="hero" id="home">
    <div class="hero-content">
        <h1>FLA <span class="red">#No 1 Academy</span> in Marathwada (Est2013)</h1>
        <p>Develop your skills, build character, and rise to the top under the guidance of professional coaches with years of competitive experience.</p>
        <button class="btn-outline-red" onclick="document.getElementById('<?= isPlayer() ? 'alreadyMemberModal' : 'joinModal' ?>').classList.add('active')">Join The Club</button>
    </div>
    <div class="hero-slider" id="heroSlider">
        <div class="slide active"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero1.jpg" alt="Football Training" width="800" height="500"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero2.jpg" alt="Match Day" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero3.jpg" alt="Team Spirit" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero4.jpg" alt="Academy Life" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero5.jpg" alt="Champions" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero6.jpg" alt="Practice Session" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero7.jpg" alt="Team Photo" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero8.jpg" alt="Academy Ground" width="800" height="500" loading="lazy"></div>
        <div class="slide"><img src="<?= BASE_URL ?>/assets/images/landing/hero/hero9.jpg" alt="Training Drills" width="800" height="500" loading="lazy"></div>
    </div>
</section>

<!-- ══ JOIN THE CLUB MODAL ═══════════════════ -->
<div class="modal-overlay" id="joinModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('joinModal').classList.remove('active')">&times;</button>

        <!-- Logo -->
        <div style="text-align:center;margin-bottom:15px;"><img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="FLA Logo" style="height:60px;width:auto;border-radius:8px;" loading="lazy"></div>

        <h2>Welcome to <span class="red">Football Leaders</span> Academy</h2>
        <p class="modal-intro">Our academy is built on the pillars of <strong>discipline</strong>, a structured <strong>training system</strong>, and a clear path for <strong>career growth in football</strong>. We nurture raw talent into professional athletes through world-class coaching and competitive exposure.</p>

        <div class="modal-notice">
            <i class="fas fa-shield-alt"></i>
            <div>
                <p><strong>All applications are reviewed by certified coaches.</strong></p>
                <p>Selection depends on skill, discipline, and consistency.</p>
            </div>
        </div>

        <div class="modal-contact-row">
            <div><i class="fas fa-phone"></i> +91 90214 44477</div>
            <div><i class="fas fa-envelope"></i> footballleadersclub@gmail.com</div>
            <div><i class="fas fa-map-marker-alt"></i> Aurangabad 431001</div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/" enctype="multipart/form-data">
            <input type="hidden" name="action" value="join">
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="form-row">
                <div class="form-group"><label>Father's Name *</label><input type="text" name="father_name" class="form-control" required></div>
                <div class="form-group"><label>Mother's Name *</label><input type="text" name="mother_name" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Date of Birth *</label><input type="date" name="dob" class="form-control" required></div>
                <div class="form-group"><label>Age *</label><input type="number" name="age" class="form-control" min="5" max="45" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Phone *</label><input type="tel" name="phone" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Position *</label>
                    <select name="position" class="form-control" required>
                        <option value="">Select Position</option>
                        <option value="Goalkeeper">Goalkeeper</option>
                        <option value="Defender">Defender</option>
                        <option value="Midfielder">Midfielder</option>
                        <option value="Forward">Forward</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Profile Photo * <small style="color:#666;">(Will be cropped to square)</small></label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png" id="photoInput" required>
                    <input type="hidden" name="cropped_data" id="croppedData">
                    <div id="photoPreview"></div>
                </div>
            </div>
            <div class="form-group"><label>Address *</label><input type="text" name="address" class="form-control" placeholder="Your full address" required></div>
            <div class="form-row">
                <div class="form-group"><label>Alternative Phone</label><input type="tel" name="alternative_phone" class="form-control"></div>
                <div class="form-group"><label>Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?= $bg ?>"><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-red" style="width:100%;">Submit Application</button>
        </form>
    </div>
</div>

<!-- ══ ABOUT SECTION ═════════════════════════ -->
<section class="section" id="about">
    <div class="container">
        <h2 class="section-title">About <span class="red">Us</span></h2>
        <div class="about-grid">
            <div class="about-image"><img src="<?= BASE_URL ?>/assets/images/landing/about/about.jpg" alt="About Football Leaders Academy" loading="lazy"></div>
            <div class="about-text">
                <h3>Our <span class="red">Achievements</span></h3>
                <ul class="achievement-list">
                    <li><i class="fas fa-trophy"></i> 2 International Players</li>
                    <li><i class="fas fa-medal"></i> 5 National Players</li>
                    <li><i class="fas fa-star"></i> 2 Players Playing National Club</li>
                    <li><i class="fas fa-futbol"></i> 10+ State Level Players</li>
                    <li><i class="fas fa-futbol"></i> 20+ Senior Club Players</li>
                    <li><i class="fas fa-futbol"></i> 15+ District Level Players</li>
                </ul>
                <h3>Why Choose <span class="red">FLA</span></h3>
                <ul class="why-list">
                    <li><i class="fas fa-check-circle"></i> WIFA & AIFF Licensed Coaches</li>
                    <li><i class="fas fa-check-circle"></i> Fun & Engaging</li>
                    <li><i class="fas fa-check-circle"></i> Every 3 month Fitness & Drill Assessment</li>
                    <li><i class="fas fa-check-circle"></i> Chance to play Tournaments</li>
                    <li><i class="fas fa-check-circle"></i> Positive Environment</li>
                    <li><i class="fas fa-check-circle"></i> and many more</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ══ PROGRAMS SECTION ══════════════════════ -->
<section class="section section-dark" id="programs">
    <div class="container">
        <h2 class="section-title">Our <span class="red">Programs</span></h2>
        <div class="programs-grid">
            <?php if(empty($dynamicPrograms)): ?>
            <div class="program-card">
                <img src="<?= BASE_URL ?>/assets/images/landing/programs/program1.jpg" alt="Elite Training" loading="lazy">
                <div class="program-card-body"><h3>Elite <span class="red">Training</span></h3><p>Advanced training program for competitive players looking to reach the next level. Includes tactical analysis, fitness conditioning, and match simulation.</p><button class="btn-red" onclick="document.getElementById('programModal1').classList.add('active')">Learn More</button></div>
            </div>
            <div class="program-card">
                <img src="<?= BASE_URL ?>/assets/images/landing/programs/program2.jpg" alt="Youth Development" loading="lazy">
                <div class="program-card-body"><h3>Youth <span class="red">Development</span></h3><p>Structured development program for young players aged 8-16. Focus on technical skills, team play, and building a strong football foundation.</p><button class="btn-red" onclick="document.getElementById('programModal2').classList.add('active')">Learn More</button></div>
            </div>
            <?php else: foreach($dynamicPrograms as $idx => $p): ?>
            <div class="program-card">
                <?php 
                $progImg = $p['image'] ? PROGRAM_IMG_URL . $p['image'] : BASE_URL . "/assets/images/landing/programs/program" . (($idx % 3) + 1) . ".jpg";
                ?>
                <img src="<?= $progImg ?>" alt="<?= sanitize($p['title']) ?>" loading="lazy">
                <div class="program-card-body">
                    <h3><?= sanitize($p['title']) ?></h3>
                    <p><?= sanitize($p['description']) ?></p>
                    <button class="btn-red" onclick="document.getElementById('dynamicProgramModal<?= $p['id'] ?>').classList.add('active')">Learn More</button>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- Dynamic Program Modals -->
<?php foreach($dynamicPrograms as $p): ?>
<div class="modal-overlay" id="dynamicProgramModal<?= $p['id'] ?>">
    <div class="modal-box">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        <h2><?= sanitize($p['title']) ?></h2>
        <div style="color:#ccc;font-size:.95rem;line-height:1.8;margin-top:15px;">
            <?= $p['learn_more'] ?>
        </div>
        <button class="btn-red" style="width:100%;margin-top:20px;" onclick="this.closest('.modal-overlay').classList.remove('active');document.getElementById('<?= isPlayer() ? 'alreadyMemberModal' : 'joinModal' ?>').classList.add('active')">Apply Now</button>
    </div>
</div>
<?php endforeach; ?>

<!-- Program Modals -->
<div class="modal-overlay" id="programModal1">
    <div class="modal-box">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        <h2>Elite <span class="red">Training</span> Program</h2>
        <div style="color:#ccc;font-size:.95rem;line-height:1.8;margin-top:15px;">
            <p><strong style="color:#e50914;">Duration:</strong> 12 Weeks (3 sessions/week)</p>
            <p><strong style="color:#e50914;">Age Group:</strong> 17+ years</p>
            <p style="margin-top:12px;">Our Elite Training program is designed for competitive players who want to reach the professional level. The program includes:</p>
            <ul style="margin:12px 0;padding-left:20px;list-style:disc;">
                <li>Advanced tactical analysis and match strategy</li>
                <li>High-intensity fitness and conditioning</li>
                <li>Match simulation and competitive drills</li>
                <li>Video analysis of professional matches</li>
                <li>Mental toughness and sports psychology</li>
                <li>Nutrition and recovery guidance</li>
            </ul>
            <p>Players will receive personalized feedback and development plans from our certified coaching staff.</p>
        </div>
        <button class="btn-red" style="width:100%;margin-top:20px;" onclick="this.closest('.modal-overlay').classList.remove('active');document.getElementById('<?= isPlayer() ? 'alreadyMemberModal' : 'joinModal' ?>').classList.add('active')">Apply Now</button>
    </div>
</div>
<div class="modal-overlay" id="programModal2">
    <div class="modal-box">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        <h2>Youth <span class="red">Development</span> Program</h2>
        <div style="color:#ccc;font-size:.95rem;line-height:1.8;margin-top:15px;">
            <p><strong style="color:#e50914;">Duration:</strong> Ongoing (4 sessions/week)</p>
            <p><strong style="color:#e50914;">Age Group:</strong> 8-16 years</p>
            <p style="margin-top:12px;">Our Youth Development program builds the foundation for future football stars:</p>
            <ul style="margin:12px 0;padding-left:20px;list-style:disc;">
                <li>Technical skills: dribbling, passing, shooting</li>
                <li>Teamwork and communication drills</li>
                <li>Small-sided games for practical learning</li>
                <li>Age-appropriate fitness training</li>
                <li>Regular progress assessments</li>
                <li>Inter-academy friendly matches</li>
            </ul>
            <p>Every young player gets individual attention to develop their unique strengths.</p>
        </div>
        <button class="btn-red" style="width:100%;margin-top:20px;" onclick="this.closest('.modal-overlay').classList.remove('active');document.getElementById('<?= isPlayer() ? 'alreadyMemberModal' : 'joinModal' ?>').classList.add('active')">Apply Now</button>
    </div>
</div>
<div class="modal-overlay" id="programModal3">
    <div class="modal-box">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        <h2>Goalkeeper <span class="red">Academy</span></h2>
        <div style="color:#ccc;font-size:.95rem;line-height:1.8;margin-top:15px;">
            <p><strong style="color:#e50914;">Duration:</strong> 10 Weeks (2 sessions/week)</p>
            <p><strong style="color:#e50914;">Age Group:</strong> All ages</p>
            <p style="margin-top:12px;">Specialized goalkeeper training covering all aspects of the position:</p>
            <ul style="margin:12px 0;padding-left:20px;list-style:disc;">
                <li>Shot-stopping and reflex training</li>
                <li>Positioning and angle play</li>
                <li>Distribution: throws and kicks</li>
                <li>Cross collection and aerial dominance</li>
                <li>1-on-1 situation handling</li>
                <li>Communication and command of the box</li>
            </ul>
            <p>Coached by former professional goalkeeper with 15+ years of experience.</p>
        </div>
        <button class="btn-red" style="width:100%;margin-top:20px;" onclick="this.closest('.modal-overlay').classList.remove('active');document.getElementById('<?= isPlayer() ? 'alreadyMemberModal' : 'joinModal' ?>').classList.add('active')">Apply Now</button>
    </div>
</div>

<!-- ══ STUDENTS SECTION ══════════════════════ -->
<section class="section" id="students">
    <div class="container">
        <h2 class="section-title">Our <span class="red">Students</span></h2>
        <div class="students-grid">
            <?php if(empty($studentGallery)): ?>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                <div class="student-card"><img src="<?= BASE_URL ?>/assets/images/landing/students/student<?= $i ?>.jpg" alt="Student <?= $i ?>" loading="lazy"></div>
                <?php endfor; ?>
            <?php else: foreach($studentGallery as $img): ?>
                <div class="student-card"><img src="<?= GALLERY_IMG_URL . $img['image'] ?>" alt="Student" loading="lazy"></div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- ══ COACHES SECTION ═══════════════════════ -->
<section class="section section-dark" id="coaches">
    <div class="container">
        <h2 class="section-title">Our <span class="red">Coaches</span></h2>
        <div class="coach-card-main">
            <div class="coach-image"><img src="<?= BASE_URL ?>/assets/images/landing/coaches/coach1.jpg" alt="Head Coach" loading="lazy"></div>
            <div class="coach-info">
                <h3>Mr. Azhar Shaikh &amp; Mr. Mazhar Shaikh</h3>
                <div class="coach-licenses"><span class="license-badge">WIFA Licensed</span><span class="license-badge">AIFF Licensed Coaches</span></div>
                <div class="coach-stats">
                    <div class="coach-stat"><span class="stat-num">Est.</span><span class="stat-label">2013</span></div>
                    <div class="coach-stat"><span class="stat-num">No.1</span><span class="stat-label">in Marathwada</span></div>
                </div>
                <div class="coach-tags"><span>Tactical Analysis</span><span>Youth Development</span><span>Fitness Training</span><span>Match Strategy</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ══ GALLERY SECTION (Auto-Scroll) ═════════ -->
<section class="section" id="gallery">
    <div class="container">
        <h2 class="section-title">Photo <span class="red">Gallery</span></h2>
        <div class="gallery-scroll-wrapper">
            <div class="gallery-scroll-track">
                <?php if(empty($generalGallery)): ?>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                    <div class="gallery-item"><img src="<?= BASE_URL ?>/assets/images/landing/gallery/gallery<?= $i ?>.jpg" alt="Gallery <?= $i ?>" loading="lazy"></div>
                    <?php endfor; ?>
                <?php else: ?>
                    <?php foreach($generalGallery as $img): ?>
                    <div class="gallery-item"><img src="<?= GALLERY_IMG_URL . $img['image'] ?>" alt="Gallery" loading="lazy"></div>
                    <?php endforeach; ?>
                    <?php // Duplicate for infinite scroll effect ?>
                    <?php foreach($generalGallery as $img): ?>
                    <div class="gallery-item"><img src="<?= GALLERY_IMG_URL . $img['image'] ?>" alt="Gallery" loading="lazy"></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>



<!-- ══ COMMUNITY (TESTIMONIALS) ══════════════ -->
<section class="section" id="community">
    <div class="container">
        <h2 class="section-title">Our <span class="red">Community</span></h2>
        <div class="community-grid">
            <div class="testimonial-card"><img src="<?= BASE_URL ?>/assets/images/landing/community/user1.png" alt="Parent" loading="lazy"><p class="quote">"My son's skills have improved dramatically since joining the academy. The coaches are truly dedicated."</p><h4>Priya Sharma</h4></div>
            <div class="testimonial-card"><img src="<?= BASE_URL ?>/assets/images/landing/community/user2.png" alt="Player" loading="lazy"><p class="quote">"The training here is world-class. I've been selected for the state team thanks to the academy."</p><h4>Rohan Patel</h4></div>
            <div class="testimonial-card"><img src="<?= BASE_URL ?>/assets/images/landing/community/user3.png" alt="Alumni" loading="lazy"><p class="quote">"Football Leaders Academy gave me the foundation to pursue football professionally. Forever grateful."</p><h4>Amit Desai</h4></div>
        </div>
    </div>
</section>

<!-- ══ MATCHES & HIGHLIGHTS ══════════════════ -->
<section class="section" id="matches">
    <div class="container">
        <h2 class="section-title">Matches &amp; <span class="red">Highlights</span></h2>
        <div class="matches-grid">
            <div class="match-video">
                <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/ET-j0DQMH9w?si=1-GbmHeEX1po1f1m" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                <h4>Season Opener Highlights</h4>
            </div>
            <div class="match-video">
                <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/580Vf9ORcIM?si=KbIVG_hS7rfH9moA" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                <h4>Championship Semi-Final</h4>
            </div>
            <div class="match-video">
                <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/Do9Ab4bz78w?si=663hi9dYE3XvRMIg" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                <h4>Training Session Recap</h4>
            </div>
        </div>
    </div>
</section>

<!-- ══ EVENTS SECTION (DYNAMIC + COUNTDOWN) ══ -->
<section class="section section-dark" id="events">
    <div class="container">
        <h2 class="section-title">Academy <span class="red">Events</span></h2>
        
        <div class="filter-sort-bar" style="margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
            <div class="filter-btns" style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="?event_filter=all&event_sort=<?= $sort ?>#events" class="btn-filter <?= $filter==='all'?'active':'' ?>">All</a>
                <a href="?event_filter=upcoming&event_sort=<?= $sort ?>#events" class="btn-filter <?= $filter==='upcoming'?'active':'' ?>">Upcoming</a>
                <a href="?event_filter=ongoing&event_sort=<?= $sort ?>#events" class="btn-filter <?= $filter==='ongoing'?'active':'' ?>">Ongoing</a>
                <a href="?event_filter=completed&event_sort=<?= $sort ?>#events" class="btn-filter <?= $filter==='completed'?'active':'' ?>">Completed</a>
            </div>
            <form method="GET" action="#events" style="display:flex;align-items:center;gap:10px;">
                <input type="hidden" name="event_filter" value="<?= $filter ?>">
                <label style="color:#999;font-size:.85rem;">Sort by:</label>
                <select name="event_sort" class="form-control" onchange="this.form.submit()" style="background:#111;border:1px solid #333;color:#fff;padding:5px 10px;border-radius:4px;font-size:.85rem;width:auto;">
                    <option value="nearest" <?= $sort==='nearest'?'selected':'' ?>>Nearest Date</option>
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest Added</option>
                    <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
                </select>
            </form>
        </div>

        <div class="events-grid">
            <?php if (empty($events)): ?>
            <div class="event-card"><div class="event-info"><h3>No upcoming events</h3><p>Check back soon for new events and announcements.</p></div></div>
            <?php else: foreach ($events as $ev):
                $evDate = strtotime($ev['event_date']);
                $today = strtotime(date('Y-m-d'));
                $daysLeft = (int)(($evDate - $today) / 86400);
                $timeStr = !empty($ev['event_time']) ? date('g:i A', strtotime($ev['event_time'])) : null;
                $evDateTime = strtotime($ev['event_date'] . ' ' . ($ev['event_time'] ?? '00:00:00'));
            ?>
            <div class="event-card" id="event-<?= $ev['id'] ?>">
                <div class="event-date">
                    <span class="event-day"><?= date('d', $evDate) ?></span>
                    <span class="event-month"><?= date('M Y', $evDate) ?></span>
                    <?php if($timeStr): ?><span style="font-size:.75rem;color:#e50914;margin-top:4px;"><?= $timeStr ?></span><?php endif; ?>
                    <?php if ($daysLeft > 0): ?>
                    <span class="countdown-badge"><?= $daysLeft ?> Day<?= $daysLeft > 1 ? 's' : '' ?> Left</span>
                    <?php elseif ($daysLeft === 0): ?>
                    <span class="countdown-badge countdown-today">Today!</span>
                    <?php else: ?>
                    <span class="countdown-badge countdown-past">Completed</span>
                    <?php endif; ?>
                </div>
                <div class="event-info">
                    <h3><?= sanitize($ev['title']) ?></h3>
                    <?php if(!empty($ev['location'])): ?><p style="color:#e50914;font-size:.85rem;margin-bottom:8px;"><i class="fas fa-map-marker-alt"></i> <?= sanitize($ev['location']) ?></p><?php endif; ?>
                    <p><?= sanitize($ev['description']) ?></p>
                    <?php if($daysLeft > 0 && $timeStr): ?><p style="color:#e50914;font-size:.85rem;font-weight:600;margin-top:4px;">Event starts in <?= $daysLeft ?> day<?= $daysLeft>1?'s':'' ?> at <?= $timeStr ?></p><?php endif; ?>
                    
                    <?php if ($evDateTime < time()): ?>
                    <div style="margin-top:15px;"><span style="display:inline-block;background:rgba(229,9,20,.1);color:#e50914;border:1px solid rgba(229,9,20,.3);padding:6px 12px;border-radius:4px;font-size:.85rem;font-weight:bold;">EVENT COMPLETED</span></div>
                    <?php elseif (isPlayer()): ?>
                    <form method="POST" action="<?= BASE_URL ?>/" style="display:inline;"><input type="hidden" name="action" value="apply_event"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><button type="submit" class="btn-red" style="margin-top:10px;">Apply for Event</button></form>
                    <?php else: ?>
                    <button class="btn-red" style="margin-top:10px;" onclick="document.getElementById('loginRequiredModal').classList.add('active')">Apply Now</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- ══ CONTACT SECTION (→ DB) ════════════════ -->
<section class="section" id="contact">
    <div class="container">
        <h2 class="section-title">Contact <span class="red">Us</span></h2>
        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-item"><i class="fas fa-map-marker-alt"></i><div><h4>Address</h4><p>Football Leaders Academy<br>Plot No. 147 P, Opposite Maulana Azad College<br>Near Milan Kirana, National Colony, Aurangabad 431001 Maharashtra</p></div></div>
                <div class="contact-item"><i class="fas fa-phone"></i><div><h4>Phone</h4><p>+91 90214 44477<br>+91 98239 41123<br>+91 98232 82105</p></div></div>
                <div class="contact-item"><i class="fas fa-envelope"></i><div><h4>Email</h4><p>footballleadersclub@gmail.com</p></div></div>
            </div>
            <form class="contact-form" method="POST" action="<?= BASE_URL ?>/">
                <input type="hidden" name="action" value="contact">
                <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="5" required></textarea></div>
                <button type="submit" class="btn-red">Send Message</button>
            </form>
        </div>
    </div>
</section>

<!-- ══ SUBSCRIPTION PLANS ═══════════════════ -->
<section class="section section-dark" id="membership">
    <div class="container">
        <h2 class="section-title">Membership <span class="red">Plans</span></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:30px;margin-top:40px;">
            <?php if(empty($activePlans)): ?>
                <div style="grid-column:1/-1;text-align:center;color:#666;">Plans coming soon...</div>
            <?php else: foreach($activePlans as $plan): 
                $feats = explode(',', $plan['features']);
                $isElite = $plan['price'] > 5000;
                $cardBg = $isElite ? 'linear-gradient(145deg, #150505 0%, #2a080a 100%)' : 'rgba(15, 15, 15, 0.8)';
                $borderColor = $isElite ? 'rgba(229, 9, 20, 0.5)' : 'rgba(255, 255, 255, 0.05)';
                $hoverBorder = $isElite ? '#ff3333' : 'rgba(229, 9, 20, 0.8)';
            ?>
            <div class="subscription-card" style="background: <?= $cardBg ?>; padding: 40px; text-align: center; border: 1px solid <?= $borderColor ?>; border-radius: 16px; backdrop-filter: blur(10px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);" onmouseover="this.style.borderColor='<?= $hoverBorder ?>'; this.style.transform='translateY(-12px)'; this.style.boxShadow='0 20px 40px rgba(229,9,20,0.15)';" onmouseout="this.style.borderColor='<?= $borderColor ?>'; this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 30px rgba(0,0,0,0.5)';">
                <?php if($isElite): ?>
                    <div style="position: absolute; top: 25px; right: -35px; background: linear-gradient(90deg, #e50914, #ff4d4d); color: #fff; padding: 5px 40px; transform: rotate(45deg); font-size: .75rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 10px rgba(229,9,20,0.5); letter-spacing: 1px;">Premium</div>
                <?php endif; ?>
                <h3 style="font-size: 1.8rem; margin-bottom: 15px; font-weight: 700; color: #fff;"><?= sanitize($plan['name']) ?></h3>
                <div style="margin-bottom: 25px; display: flex; flex-direction: column; align-items: center;">
                    <span style="font-size: 3rem; font-weight: 900; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">₹<?= number_format($plan['price'], 0) ?></span>
                    <span style="color: <?= $isElite ? '#ffb3b3' : '#888' ?>; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; margin-top: 5px;">Validity: <?= $plan['duration_days'] ?> Days</span>
                </div>
                <div style="height: 1px; width: 100%; background: linear-gradient(90deg, transparent, <?= $isElite ? 'rgba(229,9,20,0.5)' : 'rgba(255,255,255,0.1)' ?>, transparent); margin-bottom: 25px;"></div>
                <ul style="list-style: none; padding: 0; margin-bottom: 35px; text-align: left; min-height: 180px;">
                    <?php foreach($feats as $f): ?>
                    <li style="margin-bottom: 15px; color: #ddd; display: flex; align-items: flex-start; gap: 12px; font-size: 0.95rem; line-height: 1.5;">
                        <div style="background: rgba(229,9,20,0.1); border-radius: 50%; padding: 4px; display: flex; align-items: center; justify-content: center; min-width: 24px; min-height: 24px; margin-top: 2px;">
                            <i class="fas fa-check" style="color: #e50914; font-size: .8rem;"></i>
                        </div>
                        <?= sanitize(trim($f)) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <button class="<?= $isElite ? 'btn-red' : 'btn-outline-red' ?>" style="width: 100%; padding: 16px; font-size: 1.1rem; border-radius: 8px; font-weight: 700; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; position: relative; z-index: 10;" onmouseover="this.style.boxShadow='0 5px 15px rgba(229,9,20,0.4)'" onmouseout="this.style.boxShadow='none'" onclick="console.log('Button clicked. isPlayer: <?= isPlayer() ? 'YES' : 'NO' ?>'); <?= isPlayer() ? "initiateSubscriptionPayment({$plan['id']}, {$plan['price']}, '".addslashes($plan['name'])."')" : "console.log('Showing login modal'); document.getElementById('loginRequiredModal').classList.add('active')" ?>">Choose Plan</button>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<style>
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); display:none; align-items:center; justify-content:center; z-index:100000; backdrop-filter:blur(5px); }
.modal-overlay.active { display:flex !important; }
.modal-box { background:#111; border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:30px; width:95%; max-width:450px; animation:modalFade .3s ease; }
@keyframes modalFade { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
</style>

<!-- ══ LOGIN REQUIRED MODAL ══════════════════ -->
<div class="modal-overlay" id="loginRequiredModal">
    <div class="modal-box" style="text-align:center;max-width:400px;">
        <i class="fas fa-lock" style="font-size:3rem;color:#e50914;margin-bottom:15px;"></i>
        <h2 style="font-size:1.5rem;margin-bottom:10px;">LOGIN <span class="red">REQUIRED</span></h2>
        <p style="color:#ccc;margin-bottom:20px;">You need to login with your player account before applying for events.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <a href="<?= BASE_URL ?>/login.php" class="btn-red">Login</a>
            <button class="btn-outline-red" onclick="document.getElementById('loginRequiredModal').classList.remove('active')">Cancel</button>
        </div>
    </div>
</div>

<!-- ══ ALREADY MEMBER MODAL ══════════════════ -->
<div class="modal-overlay" id="alreadyMemberModal">
    <div class="modal-box" style="text-align:center;max-width:400px;">
        <i class="fas fa-check-circle" style="font-size:3rem;color:#10b981;margin-bottom:15px;"></i>
        <h2 style="font-size:1.5rem;margin-bottom:10px;">ALREADY A <span style="color:#10b981;">MEMBER</span></h2>
        <p style="color:#ccc;margin-bottom:20px;">You are already an official member of Football Leaders Academy.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <a href="<?= BASE_URL ?>/player/dashboard.php" class="btn-red">Go to Dashboard</a>
            <button class="btn-outline" style="border-color:#555;color:#ccc;" onclick="document.getElementById('alreadyMemberModal').classList.remove('active')">Close</button>
        </div>
    </div>
</div>
<!-- RAZORPAY SCRIPT -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function initiateSubscriptionPayment(planId, price, planName) {
    console.log('Initiating payment for plan:', planId, price, planName);
    if (typeof Razorpay === 'undefined') {
        alert('Razorpay script not loaded. Please check your internet connection.');
        return;
    }
    // 1. Create Order
    fetch('<?= BASE_URL ?>/payment/create-order.php?plan_id=' + planId)
    .then(response => response.json())
    .then(order => {
        if (order.error) {
            alert('Order Creation Failed: ' + order.error);
            return;
        }

        var options = {
            "key": "<?= RAZORPAY_KEY_ID ?>",
            "amount": order.amount,
            "currency": "INR",
            "name": "Football Leaders Academy",
            "description": "Subscription: " + planName,
            "image": "<?= BASE_URL ?>/assets/images/logo/logo.png",
            "order_id": order.id,
            "handler": function (response){
                // 2. Verify Payment
                fetch('<?= BASE_URL ?>/payment/verify-payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature
                    })
                })
                .then(res => res.json())
                .then(verify => {
                    if (verify.status === 'success') {
                        window.location.href = '<?= BASE_URL ?>/player/dashboard.php?payment=success';
                    } else {
                        alert('Payment Verification Failed: ' + verify.message);
                    }
                });
            },
            "prefill": {
                "name": "<?= $_SESSION['user_name'] ?? '' ?>",
                "email": "<?= $_SESSION['user_email'] ?? '' ?>"
            },
            "theme": {
                "color": "#e50914"
            }
        };
        console.log('Razorpay Options:', options);
        var rzp1 = new Razorpay(options);
        rzp1.open();
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        alert('An error occurred. Please check console.');
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
