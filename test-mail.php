<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/includes/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isAdmin()) {
    die("Unauthorized access. Please login as admin.");
}

$db = getDB();

echo "<h1>Standalone SMTP Diagnostic Test</h1>";
echo "<h3>Fetching settings from database...</h3>";

$host = getSetting($db, 'smtp_host');
$user = getSetting($db, 'smtp_user');
$pass = getSetting($db, 'smtp_pass');
$port = getSetting($db, 'smtp_port');
$fromEmail = getSetting($db, 'smtp_from_email');
$fromName = getSetting($db, 'smtp_from_name');

echo "<ul>";
echo "<li><strong>Host:</strong> " . htmlspecialchars($host) . "</li>";
echo "<li><strong>Port:</strong> " . htmlspecialchars($port) . "</li>";
echo "<li><strong>User:</strong> " . htmlspecialchars($user) . "</li>";
echo "<li><strong>From:</strong> " . htmlspecialchars($fromName) . " &lt;" . htmlspecialchars($fromEmail) . "&gt;</li>";
echo "<li><strong>Password length:</strong> " . strlen($pass) . " characters</li>";
echo "</ul>";

echo "<h3>Initializing PHPMailer...</h3>";
$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2; // Detailed debug output
    $mail->Debugoutput = 'html'; // Format debug output as HTML
    
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

    $mail->setFrom($fromEmail, $fromName);
    // Send test email to a generic known email or the user itself
    $mail->addAddress(SMTP_FROM_EMAIL); // Fallback to config if needed or DB email
    $mail->addAddress('mubashir9823king@gmail.com');
    
    $mail->isHTML(true);
    $mail->Subject = 'FLA SMTP Diagnostic Test';
    $mail->Body    = 'This is a test email sent from the standalone diagnostic script. If you received this, SMTP is perfectly configured.';

    echo "<h3>Attempting to connect and send...</h3>";
    echo "<div style='background:#111; color:#0f0; padding:15px; font-family:monospace; margin-bottom:20px; overflow-x:auto;'>";
    
    $mail->send();
    
    echo "</div>";
    echo "<h2 style='color:green;'>SUCCESS: Email sent successfully!</h2>";
    
} catch (Exception $e) {
    echo "</div>";
    echo "<h2 style='color:red;'>FAILED: Message could not be sent.</h2>";
    echo "<strong>Mailer Error:</strong> " . htmlspecialchars($mail->ErrorInfo) . "<br>";
    echo "<strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
