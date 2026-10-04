<?php
/*
 * ============================================================
 * MOBILE REMOTE BARCODE SCANNER API
 * ============================================================
 *
 * Desktop POS:
 *   action=start    -> creates an active scanner session
 *   action=poll     -> retrieves queued barcodes
 *   action=stop     -> stops the scanner session
 *
 * Mobile phone:
 *   action=discover -> finds the active scanner session on this POS
 *   action=connect  -> connects to the discovered session
 *   action=scan     -> sends a barcode to the desktop queue
 *
 * No database table is required. Scanner sessions are stored as
 * small JSON files inside mobile_scanner_sessions/.
 *
 * Pairing codes are no longer required. The mobile page discovers
 * the currently active POS session on the same server automatically.
 * ============================================================
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$storeDir = __DIR__ . DIRECTORY_SEPARATOR . 'mobile_scanner_sessions';

if (!is_dir($storeDir)) {
    @mkdir($storeDir, 0777, true);
}

/* Prevent Apache from serving scanner session files directly. */
$htaccess = $storeDir . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents(
        $htaccess,
        "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
        LOCK_EX
    );
}

function jsonResponse(array $data, $status = 200)
{
    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireDesktopSession()
{
    // The POS page already authenticated this browser session.
    // Do not include auth.php here because an AJAX fetch must receive JSON,
    // not an HTML redirect to the login page.
    if (empty($_SESSION['user']) && empty($_SESSION['id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Desktop POS session is not authenticated. Log in to the POS and try again.'
        ], 401);
    }
}

function cleanToken($token)
{
    return preg_replace('/[^a-f0-9]/i', '', (string)$token);
}

function getSessionFile($token)
{
    global $storeDir;
    return $storeDir . DIRECTORY_SEPARATOR . cleanToken($token) . '.json';
}

function readJsonFile($file)
{
    if (!is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function writeJsonFile($file, array $data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

function updateJsonFile($file, callable $callback)
{
    if (!is_file($file)) {
        return null;
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return null;
    }

    if (!@flock($fp, LOCK_EX)) {
        fclose($fp);
        return null;
    }

    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = [];

    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $result = $callback($data);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    rewind($fp);
    ftruncate($fp, 0);
    if ($json !== false) {
        fwrite($fp, $json);
    }
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);

    return $result;
}

function cleanupExpiredSessions()
{
    global $storeDir;

    $files = glob($storeDir . DIRECTORY_SEPARATOR . '*.json');
    if (!$files) {
        return;
    }

    $now = time();
    foreach ($files as $file) {
        $data = readJsonFile($file);
        if (!$data) {
            continue;
        }

        $expires = (int)($data['expires_at'] ?? 0);
        if ($expires > 0 && $expires < $now) {
            @unlink($file);
        }
    }
}

function generateToken()
{
    return bin2hex(random_bytes(24));
}

function findLatestActiveSession()
{
    global $storeDir;

    $files = glob($storeDir . DIRECTORY_SEPARATOR . '*.json');
    if (!$files) {
        return null;
    }

    $now = time();
    $latest = null;

    foreach ($files as $file) {
        $data = readJsonFile($file);
        if (!$data) {
            continue;
        }

        if ((int)($data['expires_at'] ?? 0) < $now) {
            @unlink($file);
            continue;
        }

        if (empty($data['active'])) {
            continue;
        }

        if ($latest === null || (int)($data['created_at'] ?? 0) > (int)($latest['created_at'] ?? 0)) {
            $data['_file'] = $file;
            $latest = $data;
        }
    }

    return $latest;
}

function sessionBelongsToDesktop($token)
{
    $token = cleanToken($token);
    $desktopToken = (string)($_SESSION['mobile_scanner_token'] ?? '');

    return $token !== '' && $desktopToken !== '' && hash_equals($desktopToken, $token);
}

cleanupExpiredSessions();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

/* ------------------------------------------------------------
 * DESKTOP: START
 * ------------------------------------------------------------ */
if ($action === 'start') {
    requireDesktopSession();

    $oldToken = (string)($_SESSION['mobile_scanner_token'] ?? '');
    if ($oldToken !== '') {
        $oldFile = getSessionFile($oldToken);
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    try {
        $token = generateToken();
        $now = time();

        $data = [
            'token' => $token,
            'created_at' => $now,
            'expires_at' => $now + 3600,
            'active' => true,
            'paired' => false,
            'device' => '',
            'paired_at' => 0,
            'pending_barcode' => '',
            'last_scan_at' => 0
        ];

        if (!writeJsonFile(getSessionFile($token), $data)) {
            jsonResponse([
                'success' => false,
                'message' => 'Unable to create mobile scanner session. Check write permissions for assets/scripts/mobile_scanner_sessions.'
            ], 500);
        }

        $_SESSION['mobile_scanner_token'] = $token;

        jsonResponse([
            'success' => true,
            'token' => $token,
            'expires_at' => $data['expires_at']
        ]);
    } catch (Exception $e) {
        error_log('Mobile scanner start error: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Could not start the mobile scanner session: ' . $e->getMessage()
        ], 500);
    }
}

/* ------------------------------------------------------------
 * PHONE: DISCOVER ACTIVE POS SESSION (NO CODE)
 * ------------------------------------------------------------ */
if ($action === 'discover') {
    $session = findLatestActiveSession();

    if (!$session) {
        jsonResponse([
            'success' => false,
            'available' => false,
            'message' => 'No active desktop POS scanner session was found.'
        ], 404);
    }

    $token = cleanToken((string)($session['token'] ?? ''));
    if (strlen($token) !== 48 && strlen($token) !== 64) {
        jsonResponse([
            'success' => false,
            'available' => false,
            'message' => 'The active scanner session is invalid. Start Mobile Scanner again on the desktop POS.'
        ], 500);
    }

    jsonResponse([
        'success' => true,
        'available' => true,
        'token' => $token,
        'paired' => !empty($session['paired']),
        'device' => (string)($session['device'] ?? ''),
        'expires_at' => (int)$session['expires_at']
    ]);
}

/* ------------------------------------------------------------
 * PHONE: CONNECT TO DISCOVERED SESSION
 * ------------------------------------------------------------ */
if ($action === 'connect') {
    // Accept token from POST first, then GET as a fallback.
    $tokenValue = trim((string)($_POST['token'] ?? ''));
    if ($tokenValue === '') {
        $tokenValue = trim((string)($_GET['token'] ?? ''));
    }

    $token = cleanToken($tokenValue);

    // Support both the current 48-character token and older 64-character
    // sessions that may still exist in a phone browser.
    if (strlen($token) !== 48 && strlen($token) !== 64) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid scanner session token. Please use Find POS Automatically again.'
        ], 400);
    }

    $file = getSessionFile($token);
    if (!is_file($file)) {
        jsonResponse([
            'success' => false,
            'message' => 'Desktop scanner session is unavailable. Open the mobile scanner from the active POS and try again.'
        ], 404);
    }

    $session = readJsonFile($file);
    if (!$session) {
        jsonResponse([
            'success' => false,
            'message' => 'Scanner session data is invalid.'
        ], 500);
    }

    if ((int)($session['expires_at'] ?? 0) < time() || empty($session['active'])) {
        @unlink($file);
        jsonResponse([
            'success' => false,
            'message' => 'Desktop scanner session has expired or stopped.'
        ], 410);
    }

    $session['paired'] = true;
    $session['device'] = substr((string)($_POST['device'] ?? $_GET['device'] ?? 'Mobile Scanner'), 0, 100);
    $session['paired_at'] = time();

    if (!writeJsonFile($file, $session)) {
        jsonResponse([
            'success' => false,
            'message' => 'Unable to save scanner connection.'
        ], 500);
    }

    jsonResponse([
        'success' => true,
        'token' => $token,
        'expires_at' => (int)$session['expires_at'],
        'message' => 'Mobile scanner connected automatically.'
    ]);
}

/* ------------------------------------------------------------
 * PHONE: SEND BARCODE
 * ------------------------------------------------------------ */
if ($action === 'scan') {
    /*
     * Read POST first, then GET. The mobile page sends both values in both
     * places to survive browsers/proxies that may omit POST form fields.
     */
    $tokenValue = trim((string)($_POST['token'] ?? ''));
    if ($tokenValue === '') {
        $tokenValue = trim((string)($_GET['token'] ?? ''));
    }

    $barcodeValue = trim((string)($_POST['barcode'] ?? ''));
    if ($barcodeValue === '') {
        $barcodeValue = trim((string)($_GET['barcode'] ?? ''));
    }

    $token = cleanToken($tokenValue);
    $barcode = $barcodeValue;

    if ($token === '') {
        jsonResponse([
            'success' => false,
            'message' => 'Missing scanner token. The phone is not connected to the active POS. Reconnect automatically.'
        ], 400);
    }

    if (strlen($token) !== 48 && strlen($token) !== 64) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid scanner session token. Reconnect the mobile scanner.'
        ], 400);
    }

    if ($barcode === '') {
        jsonResponse([
            'success' => false,
            'message' => 'Missing barcode. Please point the camera at a barcode and scan again.'
        ], 400);
    }

    if (strlen($barcode) > 250) {
        jsonResponse([
            'success' => false,
            'message' => 'Barcode value is too long.'
        ], 400);
    }

    $file = getSessionFile($token);
    if (!is_file($file)) {
        jsonResponse([
            'success' => false,
            'message' => 'Scanner session has expired or been stopped.'
        ], 410);
    }

    $result = updateJsonFile($file, function (&$data) use ($barcode) {
        if ((int)($data['expires_at'] ?? 0) < time() || empty($data['active'])) {
            return ['expired' => true];
        }

        if (empty($data['paired'])) {
            return ['paired' => false];
        }

        $data['pending_barcode'] = $barcode;
        $data['last_scan_at'] = time();

        return ['queued' => true];
    });

    if (!is_array($result)) {
        jsonResponse([
            'success' => false,
            'message' => 'Unable to queue barcode.'
        ], 500);
    }

    if (!empty($result['expired'])) {
        @unlink($file);
        jsonResponse([
            'success' => false,
            'message' => 'Scanner session has expired or been stopped.'
        ], 410);
    }

    if (empty($result['queued'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Mobile scanner is not connected to the desktop POS.'
        ], 409);
    }

    jsonResponse([
        'success' => true,
        'queued' => true,
        'message' => 'Barcode sent to desktop POS.'
    ]);
}

/* ------------------------------------------------------------
 * DESKTOP: POLL
 * ------------------------------------------------------------ */
if ($action === 'poll') {
    requireDesktopSession();

    $token = cleanToken((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    if (strlen($token) !== 48 && strlen($token) !== 64) {
        jsonResponse([
            'success' => false,
            'message' => 'Missing or invalid scanner token.'
        ], 400);
    }

    if (!sessionBelongsToDesktop($token)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid scanner session. Refresh the desktop POS and reconnect.'
        ], 403);
    }

    $file = getSessionFile($token);
    if (!is_file($file)) {
        unset($_SESSION['mobile_scanner_token']);
        jsonResponse([
            'success' => false,
            'expired' => true,
            'message' => 'Scanner connection has ended.'
        ], 410);
    }

    $result = updateJsonFile($file, function (&$data) {
        if ((int)($data['expires_at'] ?? 0) < time() || empty($data['active'])) {
            return ['expired' => true];
        }

        $barcode = trim((string)($data['pending_barcode'] ?? ''));
        $data['pending_barcode'] = '';

        return [
            'expired' => false,
            'paired' => !empty($data['paired']),
            'barcode' => $barcode,
            'device' => (string)($data['device'] ?? '')
        ];
    });

    if (!is_array($result)) {
        jsonResponse([
            'success' => false,
            'message' => 'Unable to read scanner queue.'
        ], 500);
    }

    if (!empty($result['expired'])) {
        @unlink($file);
        unset($_SESSION['mobile_scanner_token']);
        jsonResponse([
            'success' => false,
            'expired' => true,
            'message' => 'Scanner connection has expired.'
        ], 410);
    }

    jsonResponse([
        'success' => true,
        'paired' => !empty($result['paired']),
        'barcode' => (string)($result['barcode'] ?? ''),
        'device' => (string)($result['device'] ?? '')
    ]);
}

/* ------------------------------------------------------------
 * DESKTOP: STOP
 * ------------------------------------------------------------ */
if ($action === 'stop') {
    requireDesktopSession();

    $token = cleanToken((string)($_POST['token'] ?? $_GET['token'] ?? ''));
    $sessionToken = (string)($_SESSION['mobile_scanner_token'] ?? '');

    if ($token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token)) {
        $file = getSessionFile($token);

        if (is_file($file)) {
            @unlink($file);
        }

        unset($_SESSION['mobile_scanner_token']);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Mobile scanner disconnected.'
    ]);
}

jsonResponse([
    'success' => false,
    'message' => 'Invalid scanner action.'
], 400);
