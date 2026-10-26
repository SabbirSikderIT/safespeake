<?php
require_once __DIR__ . '/includes/config.php';

$testEmail = 'itsabbir37@gmail.com';  // Change to YOUR email address to test

echo "<h2>Testing Email Configuration</h2>";
echo "<p>Sending test email to: <strong>" . htmlspecialchars($testEmail) . "</strong></p>";

$subject = "Test Email from SafeSpeak";
$body = "<h1>✅ SMTP Test Successful!</h1>
         <p>Your Gmail SMTP configuration is working correctly.</p>
         <p>SafeSpeak can now send confirmation emails to users.</p>
         <hr>
         <p><strong>Configuration:</strong><br>
         SMTP Host: " . SMTP_HOST . "<br>
         SMTP Port: " . SMTP_PORT . "<br>
         From Email: " . MAIL_FROM . "</p>";

if (sendEmail($testEmail, $subject, $body, true)) {
    echo "<p style='color:green; font-weight:bold;'>✅ Email sent successfully! Check your inbox (and spam folder).</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ Failed to send email. Check your SMTP credentials.</p>";
    echo "<p>Make sure:<br>
          - SMTP_USER is your full Gmail address<br>
          - SMTP_PASS is the 16-char App Password from Google<br>
          - 2-Step Verification is ON in your Google Account</p>";
}
?>

