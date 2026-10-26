<?php

// Optional local-only overrides (git-ignored): define() secrets such as SMTP_PASS here.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Read a setting from the environment (Render / Docker), falling back to the
// local-development default so XAMPP keeps working unchanged.
if (!function_exists('envOr')) {
    function envOr($key, $default = '') {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }
}

// ============================================
// Email Configuration (Gmail SMTP)
// ============================================
if (!defined('SMTP_HOST')) define('SMTP_HOST', envOr('SMTP_HOST', 'smtp.gmail.com'));
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int) envOr('SMTP_PORT', 587));
if (!defined('SMTP_USER')) define('SMTP_USER', envOr('SMTP_USER', 'itsabbir37@gmail.com'));  //  CHANGE THIS - Your Gmail address
if (!defined('SMTP_PASS')) define('SMTP_PASS', envOr('SMTP_PASS', ''));     //  CHANGE THIS - 16-char App Password
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', envOr('SMTP_ENCRYPTION', 'tls'));
if (!defined('MAIL_FROM')) define('MAIL_FROM', envOr('MAIL_FROM', 'itsabbir37@gmail.com'));   //  CHANGE THIS - Same as SMTP_USER
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'SafeSpeak');

// ============================================
// SafeSpeak - Core Configuration + Helpers
// ============================================

if (!defined('DB_HOST')) define('DB_HOST', envOr('DB_HOST', '127.0.0.1'));
if (!defined('DB_USER')) define('DB_USER', envOr('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', envOr('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', envOr('DB_NAME', 'safespeak_bd'));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_PORT')) define('DB_PORT', envOr('DB_PORT', '3306'));

if (!defined('SITE_NAME')) define('SITE_NAME', 'SafeSpeak');
// On Render the public URL is provided as RENDER_EXTERNAL_URL (app served from the web root).
$__baseUrl = rtrim(envOr('APP_URL', envOr('RENDER_EXTERNAL_URL', '')), '/');
if (!defined('SITE_URL')) define('SITE_URL', envOr('SITE_URL', $__baseUrl !== '' ? $__baseUrl . '/frontend' : 'http://localhost/safe/frontend'));
if (!defined('BACKEND_URL')) define('BACKEND_URL', envOr('BACKEND_URL', $__baseUrl !== '' ? $__baseUrl . '/backend' : 'http://localhost/safe/backend'));
// Managed MySQL (e.g. Aiven) requires TLS: set DB_SSL=true (optionally DB_SSL_CA_PEM with the CA certificate).
if (!defined('DB_SSL')) define('DB_SSL', in_array(strtolower(envOr('DB_SSL', 'false')), ['1', 'true', 'yes', 'on'], true));
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/evidence/');
if (!defined('UPLOAD_ID_PATH')) define('UPLOAD_ID_PATH', __DIR__ . '/../uploads/id_verification/');
if (!defined('UPLOAD_TERMS_PATH')) define('UPLOAD_TERMS_PATH', __DIR__ . '/../uploads/terms/');
if (!defined('UPLOAD_TERMS_URL')) define('UPLOAD_TERMS_URL', BACKEND_URL . '/uploads/terms');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024);
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);

if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0775, true);
}

if (!is_dir(UPLOAD_ID_PATH)) {
    @mkdir(UPLOAD_ID_PATH, 0775, true);
}

if (!is_dir(UPLOAD_TERMS_PATH)) {
    @mkdir(UPLOAD_TERMS_PATH, 0775, true);
}

if (!function_exists('getDB')) {
    function getDB() {
        static $pdo = null;
        if ($pdo === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];

            if (DB_SSL) {
                $caPem = envOr('DB_SSL_CA_PEM', '');
                if ($caPem !== '') {
                    $caFile = sys_get_temp_dir() . '/db-ca-' . md5($caPem) . '.pem';
                    if (!is_file($caFile)) { file_put_contents($caFile, str_replace('\n', PHP_EOL, $caPem)); }
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                } else {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = envOr('DB_SSL_CA_FILE', '/etc/ssl/certs/ca-certificates.crt');
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }

            $attempts = [
                ['label' => DB_HOST . ':' . DB_PORT, 'dsn' => "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET],
                ['label' => "127.0.0.1 (no port)", 'dsn' => "mysql:host=127.0.0.1;dbname=" . DB_NAME . ";charset=" . DB_CHARSET],
                ['label' => "localhost (socket/named pipe)", 'dsn' => "mysql:host=localhost;dbname=" . DB_NAME . ";charset=" . DB_CHARSET],
                ['label' => "127.0.0.1:3307", 'dsn' => "mysql:host=127.0.0.1;port=3307;dbname=" . DB_NAME . ";charset=" . DB_CHARSET],
            ];

            // Remote database: only the configured host is tried (the local fallbacks are for XAMPP).
            if (!in_array(DB_HOST, ['127.0.0.1', 'localhost'], true)) {
                $attempts = [$attempts[0]];
            }

            $errors = [];
            foreach ($attempts as $attempt) {
                try {
                    $pdo = new PDO($attempt['dsn'], DB_USER, DB_PASS, $options);
                    return $pdo;
                } catch (PDOException $e) {
                    $errors[] = ['attempt' => $attempt['label'], 'message' => $e->getMessage()];
                }
            }

            $advice = [
                'Start MySQL from XAMPP Control Panel and verify it is running.',
                'Confirm the MySQL port (usually 3306 or 3307) and update DB_PORT if needed.',
                'Check firewall/antivirus rules for blocked MySQL ports.',
                'Verify the database "' . DB_NAME . '" exists by importing database.sql.',
            ];

            header('Content-Type: text/html; charset=UTF-8');
            echo '<h2>Database connection failed</h2>';
            echo '<p>The application could not connect to MySQL. Attempts:</p><ul>';
            foreach ($errors as $err) {
                echo '<li><strong>' . htmlentities($err['attempt']) . '</strong>: ' . htmlentities($err['message']) . '</li>';
            }
            echo '</ul><p>Try the following:</p><ul>';
            foreach ($advice as $item) {
                echo '<li>' . htmlentities($item) . '</li>';
            }
            echo '</ul>';
            echo '<p>Quick test page: <a href="' . BACKEND_URL . '/test_db.php">test_db.php</a></p>';
            exit;
        }

        return $pdo;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!function_exists('generateTrackingID')) {
    function generateTrackingID() {
        $db = getDB();
        do {
            $id = 'SS-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT id FROM reports WHERE tracking_id = ?");
            $stmt->execute([$id]);
        } while ($stmt->fetch());

        return $id;
    }
}

if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(strip_tags(trim((string) $input)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

if (!function_exists('isAdminLoggedIn')) {
    function isAdminLoggedIn() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
    }
}

if (!function_exists('isStudentLoggedIn')) {
    function isStudentLoggedIn() {
        return isset($_SESSION['student_id']);
    }
}

if (!function_exists('requireAdminLogin')) {
    function requireAdminLogin() {
        if (!isAdminLoggedIn()) {
            redirect(SITE_URL . '/admin/login.php');
        }
    }
}

if (!function_exists('requireStudentLogin')) {
    function requireStudentLogin() {
        if (!isStudentLoggedIn()) {
            redirect(SITE_URL . '/login.php');
        }
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body, $isHTML = true) {
        $composerAutoload = __DIR__ . '/../vendor/autoload.php';
        $manualAutoload  = __DIR__ . '/phpmailer/src/PHPMailer.php';

        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } elseif (file_exists($manualAutoload)) {
            require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/src/SMTP.php';
            require_once __DIR__ . '/phpmailer/src/Exception.php';
        } else {
            $headers  = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
            $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            return mail($to, $subject, $body, $headers);
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            if (!$isHTML) {
                $mail->AltBody = strip_tags($body);
            }
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendReportConfirmationEmail')) {
    function sendReportConfirmationEmail($email, $trackingId, $title, $category, $isAnonymous = true) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = "Report Confirmation - SafeSpeak";
        $categoryDisplay = ucwords(str_replace('_', ' ', $category));
        $statusLink = SITE_URL . "/report_status.php?id=" . urlencode($trackingId);

        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0a0e27, #1a56e8); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 10px 10px; }
                .tracking-id { background: #e8f4f8; padding: 15px; text-align: center; font-size: 24px; font-family: monospace; letter-spacing: 2px; border-radius: 8px; margin: 15px 0; }
                .btn { display: inline-block; background: linear-gradient(135deg, #1a56e8, #06b6d4); color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; margin-top: 15px; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; }
                .info-box { background: #e8f5e9; padding: 12px; border-radius: 8px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>SafeSpeak</h2>
                    <p>Your Voice, Protected</p>
                </div>
                <div class="content">
                    <h3>Thank You for Your Report</h3>
                    <p>We have received your complaint and it is now being processed. Your report has been analyzed by our AI system for proper prioritization.</p>

                    <div class="info-box">
                        <strong>📋 Report Summary:</strong><br>
                        Tracking ID: <strong>' . htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') . '</strong><br>
                        Category: ' . htmlspecialchars($categoryDisplay, ENT_QUOTES, 'UTF-8') . '<br>
                        Title: ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '<br>
                        Submitted: ' . date('F j, Y, g:i a') . '<br>
                        Anonymous: ' . ($isAnonymous ? 'Yes (Identity Protected)' : 'No') . '
                    </div>

                    <div class="tracking-id">
                        ' . htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') . '
                    </div>

                    <p>Use this Tracking ID to check the status of your report. Please save it for future reference.</p>

                    <div style="text-align: center;">
                        <a href="' . htmlspecialchars($statusLink, ENT_QUOTES, 'UTF-8') . '" class="btn">Track Your Report →</a>
                    </div>

                    <p style="margin-top: 20px; font-size: 14px;"><strong>What happens next?</strong><br>
                    1. Our AI has analyzed your report for urgency and authenticity<br>
                    2. A proctor will review the report within 24-48 hours<br>
                    3. You can track progress using the Tracking ID above<br>
                    4. Updates will be reflected on the status page</p>

                    <p style="font-size: 12px; color: #666; margin-top: 20px;">This is an automated confirmation. Please do not reply to this email. For any concerns, use the contact form on our website.</p>
                </div>
                <div class="footer">
                    &copy; ' . date('Y') . ' SafeSpeak. All rights reserved.<br>
                    Confidential Reporting Platform
                </div>
            </div>
        </body>
        </html>';

        return sendEmail($email, $subject, $body, true);
    }
}

if (!function_exists('verifyAndUpgradePassword')) {
    function verifyAndUpgradePassword(PDO $db, $table, $userId, $plainPassword, $storedPassword) {
        $table = (string) $table;
        $plainPassword = (string) $plainPassword;
        $storedPassword = (string) $storedPassword;
        $userId = (int) $userId;

        if ($plainPassword === '' || $storedPassword === '' || $userId <= 0) {
            return false;
        }

        if (!in_array($table, ['admins', 'students'], true)) {
            return false;
        }

        $passwordInfo = password_get_info($storedPassword);
        $isHashed = !empty($passwordInfo['algo']);

        if ($isHashed) {
            $isValid = password_verify($plainPassword, $storedPassword);
            if (!$isValid) {
                $legacyDemoHashes = [
                    '$2y$10$wH6Qw1Qw1Qw1Qw1Qw1Qw1u1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw',
                ];
                if ($plainPassword === 'password' && in_array($storedPassword, $legacyDemoHashes, true)) {
                    $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                    $update = $db->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
                    $update->execute([$newHash, $userId]);
                    return true;
                }
                return false;
            }

            if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
                $update->execute([$newHash, $userId]);
            }

            return true;
        }

        if (!hash_equals($storedPassword, $plainPassword)) {
            return false;
        }

        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);

        return true;
    }
}

if (!function_exists('analyzeReport')) {
    function analyzeReport($text) {
        $textLower = strtolower((string) $text);
        $wordCount = str_word_count((string) $text);

        $highRisk = ['violence', 'violent', 'threat', 'threatened', 'attack', 'hit', 'beat', 'abuse', 'abused', 'rape', 'sexual', 'assault', 'weapon', 'knife', 'kill', 'hurt', 'harm', 'danger', 'dangerous', 'scared', 'fear', 'terrified'];
        $mediumRisk = ['bully', 'bullying', 'harass', 'harassment', 'discrimin', 'mock', 'insult', 'humiliate', 'embarrass', 'pressure', 'force', 'forced', 'uncomfortable', 'inappropriate'];
        $negativeWords = ['bad', 'terrible', 'horrible', 'awful', 'hate', 'angry', 'upset', 'frustrated', 'anxious', 'depressed', 'sad', 'crying', 'alone', 'helpless'];
        $spamPatterns = ['/(.)\1{4,}/', '/(\b\w+\b)(\s+\1){3,}/', '/^[^a-zA-Z]*$/', '/test|asdf|qwerty|lorem|dummy/i'];

        $highCount = 0;
        $mediumCount = 0;
        $negativeCount = 0;
        $foundKeywords = [];

        foreach ($highRisk as $word) {
            if (strpos($textLower, $word) !== false) {
                $highCount++;
                $foundKeywords[] = $word;
            }
        }
        foreach ($mediumRisk as $word) {
            if (strpos($textLower, $word) !== false) {
                $mediumCount++;
            }
        }
        foreach ($negativeWords as $word) {
            if (strpos($textLower, $word) !== false) {
                $negativeCount++;
            }
        }

        $spamScore = 0;
        if ($wordCount < 10) {
            $spamScore += 0.4;
        }
        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, (string) $text)) {
                $spamScore += 0.3;
            }
        }
        $spamScore = min(1.0, $spamScore);
        $isSpam = $spamScore > 0.7;

        $rawSentiment = ($highCount * 0.4 + $mediumCount * 0.2 + $negativeCount * 0.1);
        $sentimentScore = min(1.0, $rawSentiment);

        if ($sentimentScore >= 0.6) {
            $sentimentLabel = 'very_negative';
        } elseif ($sentimentScore >= 0.35) {
            $sentimentLabel = 'negative';
        } elseif ($sentimentScore >= 0.15) {
            $sentimentLabel = 'neutral';
        } else {
            $sentimentLabel = 'positive';
        }

        if ($highCount >= 2 || ($highCount >= 1 && $wordCount >= 50)) {
            $priorityScore = 0.85 + (random_int(0, 15) / 100);
        } elseif ($highCount >= 1 || $mediumCount >= 2) {
            $priorityScore = 0.55 + (random_int(0, 25) / 100);
        } else {
            $priorityScore = 0.2 + (random_int(0, 30) / 100);
        }
        $priorityScore = min(1.0, $priorityScore);

        if ($priorityScore >= 0.75) {
            $priority = 'high';
        } elseif ($priorityScore >= 0.45) {
            $priority = 'medium';
        } else {
            $priority = 'low';
        }

        if ($isSpam) {
            $priority = 'low';
        }

        if ($isSpam) {
            $aiNote = 'SPAM DETECTED: Report flagged for suspicious patterns.';
        } elseif ($priority === 'high') {
            $aiNote = 'High urgency detected. Keywords: ' . implode(', ', array_slice($foundKeywords, 0, 5)) . '. Immediate attention required.';
        } elseif ($priority === 'medium') {
            $aiNote = 'Moderate concern detected. Standard review recommended.';
        } else {
            $aiNote = 'Low priority report. Routine review.';
        }

        return [
            'sentiment_score' => round($sentimentScore, 4),
            'sentiment_label' => $sentimentLabel,
            'priority_score'  => round($priorityScore, 4),
            'priority'        => $priority,
            'spam_score'      => round($spamScore, 4),
            'is_spam'         => $isSpam ? 1 : 0,
            'risk_keywords'   => implode(', ', $foundKeywords),
            'ai_notes'        => $aiNote,
        ];
    }
}

if (!function_exists('normalizeReportText')) {
    function normalizeReportText($text) {
        $text = strtolower((string) $text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    }
}

if (!function_exists('buildContentHash')) {
    function buildContentHash($text) {
        $normalized = normalizeReportText($text);
        return $normalized === '' ? null : md5($normalized);
    }
}

if (!function_exists('getEmailDomain')) {
    function getEmailDomain($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        $parts = explode('@', $email);
        return strtolower(end($parts));
    }
}

if (!function_exists('isDisposableEmail')) {
    function isDisposableEmail($email) {
        $domain = getEmailDomain($email);
        if ($domain === '') {
            return false;
        }
        $disposableDomains = [
            'mailinator.com', '10minutemail.com', 'guerrillamail.com', 'tempmail.com',
            'yopmail.com', 'trashmail.com', 'getnada.com', 'fakeinbox.com',
        ];
        return in_array($domain, $disposableDomains, true);
    }
}

if (!function_exists('checkRateLimit')) {
    function checkRateLimit($ip, $action, $windowSeconds, $limit) {
        $ip = trim((string) $ip);
        $action = trim((string) $action);
        $windowSeconds = (int) $windowSeconds;
        $limit = (int) $limit;

        if ($ip === '' || $action === '' || $windowSeconds <= 0 || $limit <= 0) {
            return ['count' => 0, 'limit' => $limit, 'blocked' => false];
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, count, window_start FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $db->prepare("INSERT INTO rate_limits (ip_address, action, count, window_start) VALUES (?, ?, 1, NOW())");
            $insert->execute([$ip, $action]);
            return ['count' => 1, 'limit' => $limit, 'blocked' => false];
        }

        $windowStart = strtotime((string) $row['window_start']);
        if ($windowStart === false || (time() - $windowStart) > $windowSeconds) {
            $reset = $db->prepare("UPDATE rate_limits SET count = 1, window_start = NOW() WHERE id = ?");
            $reset->execute([$row['id']]);
            return ['count' => 1, 'limit' => $limit, 'blocked' => false];
        }

        $newCount = (int) $row['count'] + 1;
        $update = $db->prepare("UPDATE rate_limits SET count = ? WHERE id = ?");
        $update->execute([$newCount, $row['id']]);

        return ['count' => $newCount, 'limit' => $limit, 'blocked' => $newCount > $limit];
    }
}

if (!function_exists('getDuplicateReportCount')) {
    function getDuplicateReportCount($contentHash, $windowDays = 7) {
        $contentHash = trim((string) $contentHash);
        $windowDays = (int) $windowDays;
        if ($contentHash === '' || $windowDays <= 0) {
            return 0;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM reports WHERE content_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$contentHash, $windowDays]);
        return (int) ($stmt->fetch()['cnt'] ?? 0);
    }
}

if (!function_exists('assessSpamSignals')) {
    function assessSpamSignals($text, $email, $rateLimitCount, $duplicateCount, array $settings = []) {
        $text = (string) $text;
        $textLower = strtolower($text);
        $flags = [];
        $score = 0.0;

        $blacklist = $settings['blacklist_keywords'] ?? ['buy now', 'free money', 'crypto', 'bitcoin', 'loan', 'bit.ly', 't.me', 'telegram', 'whatsapp'];
        foreach ($blacklist as $word) {
            if (strpos($textLower, $word) !== false) {
                $flags[] = 'blacklist_keywords';
                $score += 0.3;
                break;
            }
        }

        $disposableDomains = $settings['disposable_domains'] ?? null;
        if ($disposableDomains && is_array($disposableDomains)) {
            $isDisposable = in_array(getEmailDomain($email), $disposableDomains, true);
        } else {
            $isDisposable = isDisposableEmail($email);
        }

        if ($isDisposable) {
            $flags[] = 'disposable_email';
            $score += 0.4;
        }

        $duplicateThreshold = (int) ($settings['duplicate_threshold'] ?? 2);
        if ($duplicateCount >= $duplicateThreshold) {
            $flags[] = 'duplicate_text';
            $score += 0.4;
        }

        $rateLimitMax = (int) ($settings['rate_limit_max'] ?? 5);
        if ($rateLimitCount > $rateLimitMax) {
            $flags[] = 'rate_limit';
            $score += 0.5;
        }

        $linkShortWordCount = (int) ($settings['link_short_wordcount'] ?? 25);
        if (preg_match('/https?:\/\//i', $text) && str_word_count($text) < $linkShortWordCount) {
            $flags[] = 'link_in_short_report';
            $score += 0.2;
        }

        $score = min(1.0, $score);
        $verificationThreshold = (float) ($settings['verification_threshold'] ?? 0.6);
        $hardRateLimit = (int) ($settings['hard_rate_limit'] ?? 7);
        $needsVerification = $score >= $verificationThreshold || $rateLimitCount > $hardRateLimit;

        return [
            'score' => round($score, 4),
            'flags' => $flags,
            'needs_verification' => $needsVerification,
        ];
    }
}

if (!function_exists('getSpamSettings')) {
    function getSpamSettings() {
        $defaults = [
            'blacklist_keywords' => ['buy now', 'free money', 'crypto', 'bitcoin', 'loan', 'bit.ly', 't.me', 'telegram', 'whatsapp'],
            'disposable_domains' => ['mailinator.com', '10minutemail.com', 'guerrillamail.com', 'tempmail.com', 'yopmail.com', 'trashmail.com', 'getnada.com', 'fakeinbox.com'],
            'rate_limit_max' => 5,
            'hard_rate_limit' => 7,
            'duplicate_window_days' => 7,
            'duplicate_threshold' => 2,
            'verification_threshold' => 0.6,
            'spam_score_threshold' => 0.7,
            'link_short_wordcount' => 25,
        ];

        $db = getDB();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS spam_settings (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "blacklist_keywords TEXT NULL,\n"
            . "disposable_domains TEXT NULL,\n"
            . "rate_limit_max INT DEFAULT 5,\n"
            . "hard_rate_limit INT DEFAULT 7,\n"
            . "duplicate_window_days INT DEFAULT 7,\n"
            . "duplicate_threshold INT DEFAULT 2,\n"
            . "verification_threshold DECIMAL(5,4) DEFAULT 0.6,\n"
            . "spam_score_threshold DECIMAL(5,4) DEFAULT 0.7,\n"
            . "link_short_wordcount INT DEFAULT 25,\n"
            . "updated_by INT NULL,\n"
            . "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $row = $db->query("SELECT * FROM spam_settings ORDER BY id DESC LIMIT 1")->fetch();
        if (!$row) {
            return $defaults;
        }

        $parseList = function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return [];
            }
            $items = array_map('trim', explode(',', $value));
            return array_values(array_filter($items, static function ($item) {
                return $item !== '';
            }));
        };

        return [
            'blacklist_keywords' => $parseList($row['blacklist_keywords'] ?? ''),
            'disposable_domains' => $parseList($row['disposable_domains'] ?? ''),
            'rate_limit_max' => (int) ($row['rate_limit_max'] ?? $defaults['rate_limit_max']),
            'hard_rate_limit' => (int) ($row['hard_rate_limit'] ?? $defaults['hard_rate_limit']),
            'duplicate_window_days' => (int) ($row['duplicate_window_days'] ?? $defaults['duplicate_window_days']),
            'duplicate_threshold' => (int) ($row['duplicate_threshold'] ?? $defaults['duplicate_threshold']),
            'verification_threshold' => (float) ($row['verification_threshold'] ?? $defaults['verification_threshold']),
            'spam_score_threshold' => (float) ($row['spam_score_threshold'] ?? $defaults['spam_score_threshold']),
            'link_short_wordcount' => (int) ($row['link_short_wordcount'] ?? $defaults['link_short_wordcount']),
        ];
    }
}

if (!function_exists('saveSpamSettings')) {
    function saveSpamSettings(array $settings, $adminId = null) {
        $db = getDB();
        $serializeList = function ($value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            return trim((string) $value);
        };

        $stmt = $db->prepare(
            "INSERT INTO spam_settings (blacklist_keywords, disposable_domains, rate_limit_max, hard_rate_limit, duplicate_window_days, duplicate_threshold, verification_threshold, spam_score_threshold, link_short_wordcount, updated_by) "
            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $serializeList($settings['blacklist_keywords'] ?? ''),
            $serializeList($settings['disposable_domains'] ?? ''),
            (int) ($settings['rate_limit_max'] ?? 5),
            (int) ($settings['hard_rate_limit'] ?? 7),
            (int) ($settings['duplicate_window_days'] ?? 7),
            (int) ($settings['duplicate_threshold'] ?? 2),
            (float) ($settings['verification_threshold'] ?? 0.6),
            (float) ($settings['spam_score_threshold'] ?? 0.7),
            (int) ($settings['link_short_wordcount'] ?? 25),
            $adminId,
        ]);
    }
}

if (!function_exists('startSpamVerification')) {
    function startSpamVerification($email, $context, array $pendingData) {
        $email = trim((string) $email);
        $context = trim((string) $context);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $context === '') {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        $hash = hash('sha256', $code);
        $_SESSION['spam_verification'] = [
            'context' => $context,
            'email' => $email,
            'code_hash' => $hash,
            'expires_at' => time() + (15 * 60),
            'pending' => $pendingData,
        ];

        $subject = 'SafeSpeak Verification Code';
        $body = '<p>Your verification code is <strong>' . htmlentities($code) . '</strong>.</p><p>This code expires in 15 minutes.</p>';
        return sendEmail($email, $subject, $body, true);
    }
}

if (!function_exists('consumeSpamVerification')) {
    function consumeSpamVerification($context, $code) {
        $context = trim((string) $context);
        $code = trim((string) $code);
        $session = $_SESSION['spam_verification'] ?? null;

        if (!$session || $context === '' || $context !== ($session['context'] ?? '')) {
            return ['ok' => false, 'error' => 'Verification session expired. Please try again.'];
        }

        if (($session['expires_at'] ?? 0) < time()) {
            unset($_SESSION['spam_verification']);
            return ['ok' => false, 'error' => 'Verification code expired. Please request a new one.'];
        }

        if ($code === '' || !hash_equals((string) ($session['code_hash'] ?? ''), hash('sha256', $code))) {
            return ['ok' => false, 'error' => 'Invalid verification code.'];
        }

        $pending = $session['pending'] ?? [];
        unset($_SESSION['spam_verification']);
        return ['ok' => true, 'pending' => $pending];
    }
}

if (!function_exists('applyPriorityOverrides')) {
    function applyPriorityOverrides($category, $text, $aiPriority) {
        $priority = (string) $aiPriority;
        $category = (string) $category;
        $textLower = strtolower((string) $text);

        $criticalKeywords = ['weapon', 'knife', 'gun', 'kill', 'murder', 'rape', 'assault', 'suicide'];
        foreach ($criticalKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                return 'critical';
            }
        }

        $highCategories = ['violence', 'sexual_harassment'];
        if (in_array($category, $highCategories, true) && $priority !== 'critical') {
            return 'high';
        }

        return $priority;
    }
}

if (!function_exists('sendReportStatusUpdateEmail')) {
    function sendReportStatusUpdateEmail($email, $trackingId, $title, $status, $priority) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = "Report Status Update - SafeSpeak";
        $statusDisplay = ucwords(str_replace('_', ' ', (string) $status));
        $priorityDisplay = ucfirst((string) $priority);
        $statusLink = SITE_URL . "/report_status.php?id=" . urlencode($trackingId);

        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0a0e27, #1a56e8); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 10px 10px; }
                .badge { display: inline-block; padding: 6px 10px; border-radius: 6px; background: #e2e8f0; font-weight: bold; }
                .btn { display: inline-block; background: linear-gradient(135deg, #1a56e8, #06b6d4); color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; margin-top: 15px; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>SafeSpeak</h2>
                    <p>Your Report Status Update</p>
                </div>
                <div class="content">
                    <p>Your report has a new update.</p>
                    <p><strong>Tracking ID:</strong> ' . htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') . '</p>
                    <p><strong>Title:</strong> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
                    <p><strong>Status:</strong> <span class="badge">' . htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8') . '</span></p>
                    <p><strong>Priority:</strong> ' . htmlspecialchars($priorityDisplay, ENT_QUOTES, 'UTF-8') . '</p>
                    <div style="text-align:center;">
                        <a href="' . htmlspecialchars($statusLink, ENT_QUOTES, 'UTF-8') . '" class="btn">View Report</a>
                    </div>
                </div>
                <div class="footer">
                    &copy; ' . date('Y') . ' SafeSpeak. All rights reserved.
                </div>
            </div>
        </body>
        </html>';

        return sendEmail($email, $subject, $body, true);
    }
}

if (!function_exists('getReportNotifyEmail')) {
    function getReportNotifyEmail($reportId) {
        $reportId = (int) $reportId;
        if ($reportId <= 0) {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT r.notify_email, s.email AS student_email FROM reports r LEFT JOIN students s ON r.student_id = s.id WHERE r.id = ?");
        $stmt->execute([$reportId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $email = $row['notify_email'] ?: $row['student_email'];
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }
}

if (!function_exists('sendReporterNotificationEmail')) {
    function sendReporterNotificationEmail($email, $trackingId, $title, $subject, $message) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $statusLink = SITE_URL . "/report_status.php?id=" . urlencode($trackingId);
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0a0e27, #1a56e8); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #1a56e8, #06b6d4); color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; margin-top: 15px; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>SafeSpeak</h2>
                    <p>Notification</p>
                </div>
                <div class="content">
                    <p><strong>Tracking ID:</strong> ' . htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') . '</p>
                    <p><strong>Title:</strong> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
                    <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
                    <div style="text-align:center;">
                        <a href="' . htmlspecialchars($statusLink, ENT_QUOTES, 'UTF-8') . '" class="btn">View Report</a>
                    </div>
                </div>
                <div class="footer">
                    &copy; ' . date('Y') . ' SafeSpeak. All rights reserved.
                </div>
            </div>
        </body>
        </html>';

        return sendEmail($email, $subject, $body, true);
    }
}

if (!function_exists('getPriorityBadge')) {
    function getPriorityBadge($priority) {
        $map = [
            'critical' => '<span class="badge badge-critical">Critical</span>',
            'high'     => '<span class="badge badge-high">High</span>',
            'medium'   => '<span class="badge badge-medium">Medium</span>',
            'low'      => '<span class="badge badge-low">Low</span>',
        ];

        return $map[$priority] ?? '<span class="badge">Unknown</span>';
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $map = [
            'pending'       => '<span class="badge status-pending">Pending</span>',
            'under_review'  => '<span class="badge status-review">Under Review</span>',
            'investigating' => '<span class="badge status-investigating">Investigating</span>',
            'resolved'      => '<span class="badge status-resolved">Resolved</span>',
            'rejected'      => '<span class="badge status-rejected">Rejected</span>',
            'escalated'     => '<span class="badge status-escalated">Escalated</span>',
        ];

        return $map[$status] ?? '<span class="badge">Unknown</span>';
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime((string) $datetime);
        if ($time === false) {
            return 'unknown';
        }

        $diff = time() - $time;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        if ($diff < 2592000) {
            return floor($diff / 86400) . 'd ago';
        }

        return date('M j, Y', $time);
    }
}

if (!function_exists('ensureReportingTables')) {
    function ensureReportingTables() {
        $db = getDB();
        $schema = DB_NAME;

        $addColumnIfMissing = function ($table, $column, $definition) use ($db, $schema) {
            $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$schema, $table, $column]);
            $exists = (int) ($stmt->fetch()['cnt'] ?? 0) > 0;
            if (!$exists) {
                $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        };

        $db->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "ip_address VARCHAR(45) NOT NULL,\n"
            . "action VARCHAR(100) NOT NULL,\n"
            . "count INT DEFAULT 1,\n"
            . "window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "INDEX idx_ip_action (ip_address, action)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $addColumnIfMissing('reports', 'content_hash', 'CHAR(32) NULL');
        $addColumnIfMissing('reports', 'spam_score', 'DECIMAL(5,4) DEFAULT NULL');
        $addColumnIfMissing('reports', 'spam_flags', 'TEXT NULL');
        $addColumnIfMissing('reports', 'spam_needs_verification', 'TINYINT(1) DEFAULT 0');
        $addColumnIfMissing('reports', 'manual_spam', 'TINYINT(1) DEFAULT 0');
        $addColumnIfMissing('reports', 'manual_spam_by', 'INT NULL');
        $addColumnIfMissing('reports', 'manual_spam_at', 'DATETIME NULL');
        $addColumnIfMissing('reports', 'manual_spam_note', 'TEXT NULL');
        $addColumnIfMissing('reports', 'id_verification_required', "TINYINT(1) DEFAULT 0");
        $addColumnIfMissing('reports', 'id_verification_status', "ENUM('not_requested','requested','submitted','approved','rejected') DEFAULT 'not_requested'");
        $addColumnIfMissing('reports', 'id_verification_file', 'VARCHAR(255) NULL');
        $addColumnIfMissing('reports', 'id_verification_submitted_at', 'DATETIME NULL');
        $addColumnIfMissing('reports', 'id_verification_requested_by', 'INT NULL');
        $addColumnIfMissing('reports', 'id_verification_requested_at', 'DATETIME NULL');
        $addColumnIfMissing('reports', 'id_verification_reviewed_by', 'INT NULL');
        $addColumnIfMissing('reports', 'id_verification_reviewed_at', 'DATETIME NULL');
        $addColumnIfMissing('reports', 'id_verification_review_note', 'TEXT NULL');
        $addColumnIfMissing('reports', 'user_agent', 'TEXT NULL');

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_messages (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "sender_type ENUM('admin','reporter') NOT NULL,\n"
            . "admin_id INT NULL,\n"
            . "message TEXT NOT NULL,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,\n"
            . "FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_evidence_access_log (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "admin_id INT NULL,\n"
            . "file_name VARCHAR(255) NOT NULL,\n"
            . "access_type ENUM('view','download','link') DEFAULT 'view',\n"
            . "ip_address VARCHAR(45) DEFAULT NULL,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,\n"
            . "FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_evidence_annotations (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "admin_id INT NULL,\n"
            . "evidence_key TEXT NOT NULL,\n"
            . "note TEXT NOT NULL,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,\n"
            . "FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_outcome_surveys (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "satisfaction_score TINYINT NOT NULL,\n"
            . "response_time_score TINYINT NOT NULL,\n"
            . "fairness_score TINYINT NOT NULL,\n"
            . "comments TEXT NULL,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_followup_tokens (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "token_hash CHAR(64) NOT NULL UNIQUE,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "last_used_at DATETIME NULL,\n"
            . "expires_at DATETIME NULL,\n"
            . "UNIQUE KEY uniq_report_token (report_id),\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS report_tasks (\n"
            . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "report_id INT NOT NULL,\n"
            . "title VARCHAR(255) NOT NULL,\n"
            . "status ENUM('open','done') DEFAULT 'open',\n"
            . "due_date DATE NULL,\n"
            . "created_by INT NULL,\n"
            . "completed_at DATETIME NULL,\n"
            . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,\n"
            . "FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('createFollowupToken')) {
    function createFollowupToken($reportId, $ttlDays = 90) {
        $reportId = (int) $reportId;
        $ttlDays = (int) $ttlDays;
        if ($reportId <= 0) {
            return null;
        }

        $db = getDB();
        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token);
        $expiresAt = $ttlDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$ttlDays} days")) : null;

        $stmt = $db->prepare("SELECT id FROM report_followup_tokens WHERE report_id = ?");
        $stmt->execute([$reportId]);
        if ($stmt->fetch()) {
            $update = $db->prepare("UPDATE report_followup_tokens SET token_hash = ?, created_at = NOW(), last_used_at = NULL, expires_at = ? WHERE report_id = ?");
            $update->execute([$hash, $expiresAt, $reportId]);
        } else {
            $insert = $db->prepare("INSERT INTO report_followup_tokens (report_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $insert->execute([$reportId, $hash, $expiresAt]);
        }

        return $token;
    }
}

if (!function_exists('validateFollowupToken')) {
    function validateFollowupToken($token) {
        $token = trim((string) $token);
        if ($token === '') {
            return ['report_id' => null, 'error' => 'Token required.'];
        }

        $db = getDB();
        $hash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT report_id, expires_at FROM report_followup_tokens WHERE token_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['report_id' => null, 'error' => 'Invalid follow-up token.'];
        }

        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return ['report_id' => null, 'error' => 'Follow-up token has expired.'];
        }

        $update = $db->prepare("UPDATE report_followup_tokens SET last_used_at = NOW() WHERE token_hash = ?");
        $update->execute([$hash]);

        return ['report_id' => (int) $row['report_id'], 'error' => null];
    }
}

if (!function_exists('getReportIdByFollowupToken')) {
    function getReportIdByFollowupToken($token) {
        $result = validateFollowupToken($token);
        return $result['report_id'];
    }
}

if (!function_exists('getStudentFromDirectory')) {
    function getStudentFromDirectory($studentId) {
        if (empty($studentId)) return null;

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM student_directory WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $record = $stmt->fetch();
        if ($record) {
            return $record;
        }

        // Fallback to live student accounts if directory data is missing.
        $stmt = $db->prepare("SELECT student_id, full_name, email, department, batch FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) {
            return null;
        }

        $graduationYear = null;
        if (!empty($student['batch']) && is_numeric($student['batch'])) {
            $graduationYear = (int) $student['batch'];
        }

        return [
            'student_id' => $student['student_id'],
            'full_name' => $student['full_name'],
            'email' => $student['email'],
            'department' => $student['department'],
            'gpa' => null,
            'graduation_year' => $graduationYear,
        ];
    }
}

if (!function_exists('searchStudentsInDirectory')) {
    function searchStudentsInDirectory($searchTerm) {
        if (empty($searchTerm)) return [];

        $db = getDB();
        $search = '%' . $searchTerm . '%';
        $stmt = $db->prepare("SELECT * FROM student_directory WHERE student_id LIKE ? OR full_name LIKE ? OR email LIKE ? OR department LIKE ? LIMIT 20");
        $stmt->execute([$search, $search, $search, $search]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('validateStudentId')) {
    function validateStudentId($studentId) {
        if (empty($studentId)) return false;

        $db = getDB();
        $stmt = $db->prepare("SELECT student_id FROM student_directory WHERE student_id = ?");
        $stmt->execute([$studentId]);
        return $stmt->fetch() !== false;
    }
}

if (!function_exists('getStudentDirectoryStats')) {
    function getStudentDirectoryStats() {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) as total, COUNT(DISTINCT department) as departments, AVG(gpa) as avg_gpa FROM student_directory");
        return $stmt->fetch();
    }
}

?>