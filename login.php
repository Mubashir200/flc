<?php
require_once __DIR__ . '/includes/db.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = strtolower(trim($_POST['user_id'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($user_id) || empty($password)) {
        flash('error', 'Please fill in all fields.');
        header('Location: ' . BASE_URL . '/login.php'); exit;
    }

    $db = getDB();

    // Check admin table first
    $stmt = $db->prepare("SELECT * FROM admins WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['role'] = $admin['role'] ?? 'super_admin';
        flash('success', 'Welcome back, ' . $admin['name'] . '!');
        header('Location: ' . BASE_URL . '/admin/dashboard.php'); exit;
    }

    // Check players table
    $stmt = $db->prepare("SELECT * FROM players WHERE LOWER(unique_id) = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $player = $stmt->fetch();

    if ($player && password_verify($password, $player['password'])) {
        if (!$player['is_active']) {
            flash('error', 'Your account is currently INACTIVE. Please contact the administrator or request reactivation.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        $_SESSION['user_id'] = $player['id'];
        $_SESSION['user_name'] = $player['name'];
        $_SESSION['role'] = 'player';
        flash('success', 'Welcome, ' . $player['name'] . '!');
        header('Location: ' . BASE_URL . '/player/dashboard.php'); exit;
    }

    flash('error', 'Invalid User ID or password.');
    header('Location: ' . BASE_URL . '/login.php'); exit;
}

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdmin() ? '/admin/dashboard.php' : '/player/dashboard.php')); exit;
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <h1><span class="red">Football Leaders</span> Academy</h1>
        <p class="subtitle">Enter your credentials to continue</p>
        <form method="POST">
            <div class="form-group"><label>User ID</label><input type="text" name="user_id" class="form-control" placeholder="e.g., fla-001 or FLA-002" required></div>
            <div class="form-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter password" required>
                    <button type="button" class="password-toggle" id="toggleLoginPw" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-red" style="width:100%;">Sign In</button></div>
        </form>
        <div class="auth-links"><p><a href="<?= BASE_URL ?>/">← Back to Home</a></p></div>
    </div>
</div>
<script>
document.getElementById('toggleLoginPw').addEventListener('click', function() {
    var pw = document.getElementById('loginPassword');
    var icon = this.querySelector('i');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        this.classList.add('active');
    } else {
        pw.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        this.classList.remove('active');
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
