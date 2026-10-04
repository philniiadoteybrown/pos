<?php
/*
 * Remote mobile barcode scanner.
 *
 * The scanner no longer requires a pairing code. When this page is loaded,
 * it checks the local POS scanner-session folder for the currently active
 * desktop session and passes that token into the page. This avoids relying
 * only on a browser AJAX discovery request.
 */

$initialScannerToken = '';
$scannerSessionDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'mobile_scanner_sessions';

if (is_dir($scannerSessionDir)) {
    $files = glob($scannerSessionDir . DIRECTORY_SEPARATOR . '*.json');

    if ($files) {
        $now = time();
        $latestCreated = 0;

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }

            if (empty($data['active'])) {
                continue;
            }

            $expiresAt = (int)($data['expires_at'] ?? 0);
            if ($expiresAt > 0 && $expiresAt < $now) {
                @unlink($file);
                continue;
            }

            $token = preg_replace('/[^a-f0-9]/i', '', (string)($data['token'] ?? ''));
            if (strlen($token) !== 48) {
                continue;
            }

            $createdAt = (int)($data['created_at'] ?? 0);
            if ($createdAt >= $latestCreated) {
                $latestCreated = $createdAt;
                $initialScannerToken = $token;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <title>Mobile Barcode Scanner</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
            background: #111;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
        }

        .scanner-app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .scanner-header {
            padding: 14px 16px;
            background: #000;
            text-align: center;
            flex-shrink: 0;
        }

        .scanner-header h1 {
            margin: 0;
            font-size: 21px;
        }

        .scanner-header p {
            margin: 5px 0 0;
            color: #bdbdbd;
            font-size: 12px;
        }

        .connection-panel {
            width: min(92vw, 440px);
            margin: 18px auto;
            padding: 18px;
            background: #1f1f1f;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.35);
        }

        .connection-panel h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .connection-panel p {
            margin: 0 0 14px;
            color: #c9c9c9;
            font-size: 13px;
            line-height: 1.45;
        }

        .pair-input {
            width: 100%;
            height: 54px;
            border: 1px solid #555;
            border-radius: 8px;
            background: #fff;
            color: #111;
            font-size: 28px;
            letter-spacing: 7px;
            text-align: center;
            font-weight: 800;
            outline: none;
        }

        .pair-input:focus {
            border-color: #0d6efd;
        }

        .connect-btn {
            width: 100%;
            min-height: 50px;
            margin-top: 10px;
            border: 0;
            border-radius: 8px;
            background: #0d6efd;
            color: #fff;
            font-size: 17px;
            font-weight: 700;
        }

        .status {
            margin-top: 12px;
            padding: 10px;
            background: #2a2a2a;
            border-radius: 7px;
            text-align: center;
            font-size: 13px;
        }

        .scanner-area {
            display: none;
            flex: 1;
            flex-direction: column;
            min-height: 0;
        }

        .scanner-status-bar {
            padding: 9px 12px;
            background: #1a1a1a;
            font-size: 13px;
            text-align: center;
        }

        .scanner-camera {
            position: relative;
            flex: 1;
            min-height: 60vh;
            background: #000;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #mobileBarcodeReader {
            width: 100%;
            max-width: 900px;
        }

        #mobileBarcodeReader video {
            width: 100% !important;
            height: auto !important;
            object-fit: cover;
        }


        .last-scan {
            padding: 12px;
            background: #111;
            text-align: center;
            flex-shrink: 0;
        }

        .last-scan-label {
            font-size: 11px;
            color: #999;
            margin-bottom: 3px;
        }

        #lastBarcode {
            font-size: 19px;
            font-weight: 700;
            word-break: break-all;
        }

        .scanner-actions {
            padding: 10px 12px 14px;
            background: #111;
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .scanner-actions button {
            flex: 1;
            min-height: 46px;
            border: 0;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 700;
        }

        .disconnect-btn {
            background: #dc3545;
            color: #fff;
        }

        .scan-again-btn {
            background: #198754;
            color: #fff;
        }

        .exit-btn {
            background: #6c757d;
            color: #fff;
        }

        .hidden {
            display: none !important;
        }

        @media (orientation: landscape) {
            .scanner-camera {
                min-height: 68vh;
            }

        }
    </style>
</head>

<body>

<div class="scanner-app">

    <div class="scanner-header">
        <h1>📱 Mobile Barcode Scanner</h1>
        <p>Scan products and send the barcode directly to the desktop POS.</p>
    </div>

    <div id="connectionPanel" class="connection-panel">

        <h2>Connect to Desktop POS</h2>

        <p>
            This scanner connects automatically to the active POS on this same server.
            No pairing code is required.
        </p>

        <button
            type="button"
            class="connect-btn"
            id="connectButton"
            onclick="autoConnectScanner(true)">
            🔄 Find POS Automatically
        </button>

        <div id="connectionStatus" class="status">
            Looking for an active desktop POS scanner...
        </div>

    </div>

    <div id="scannerArea" class="scanner-area">

        <div id="scannerStatus" class="scanner-status-bar">
            Starting camera...
        </div>

        <div class="scanner-camera">

            <div id="mobileBarcodeReader"></div>


        </div>

        <div class="last-scan">
            <div class="last-scan-label">
                Last barcode sent
            </div>

            <div id="lastBarcode">
                —
            </div>
        </div>

        <div class="scanner-actions">

            <button
                type="button"
                class="scan-again-btn"
                onclick="restartCamera()">
                Restart Camera
            </button>

            <button
                type="button"
                class="disconnect-btn"
                onclick="disconnectScanner()">
                Disconnect
            </button>

            <button
                type="button"
                class="exit-btn"
                onclick="exitScanner()">
                Exit
            </button>

        </div>

    </div>

</div>

<script src="assets/scripts/html5-qrcode.min.js"></script>

<script>
'use strict';

const SCANNER_API = new URL('assets/scripts/mobile_scanner_api.php', window.location.href).toString();
const INITIAL_SCANNER_TOKEN = <?= json_encode($initialScannerToken); ?>;
let mobileScanner = null;
let mobileScannerRunning = false;
let mobileScannerToken = '';
let lastMobileBarcode = '';
let lastMobileBarcodeTime = 0;
let autoConnectTimer = null;
let autoConnectAttempts = 0;
let scanRequestInProgress = false;

function setConnectionStatus(message) {
    const el = document.getElementById('connectionStatus');
    if (el) el.textContent = message;
}

function setScannerStatus(message) {
    const el = document.getElementById('scannerStatus');
    if (el) el.textContent = message;
}

async function apiJson(url, options = {}) {
    const response = await fetch(url, {
        cache: 'no-store',
        ...options
    });

    const text = await response.text();
    let data;

    try {
        data = JSON.parse(text);
    } catch (e) {
        throw new Error('The scanner API did not return JSON. Check the PHP API path and server errors.');
    }

    if (!response.ok && !data.message) {
        data.message = 'Scanner request failed (HTTP ' + response.status + ').';
    }

    return { response, data };
}

async function connectWithToken(token) {
    token = String(token || '').trim();

    if (!token) {
        throw new Error('No scanner session token is available.');
    }

    const connectUrl =
        SCANNER_API +
        '?action=connect&token=' +
        encodeURIComponent(token) +
        '&device=' +
        encodeURIComponent(navigator.userAgent.substring(0, 100)) +
        '&_=' +
        Date.now();

    setConnectionStatus('Desktop POS found. Connecting scanner...');

    // Use a GET request for the connection handshake. The token and device
    // are already present in the URL, which is more reliable on mobile
    // browsers/proxies than relying on a POST body during auto-connect.
    const connectResult = await apiJson(connectUrl, {
        method: 'GET'
    });

    if (!connectResult.response.ok || !connectResult.data.success) {
        const err = new Error(
            connectResult.data.message ||
            ('Automatic connection failed (HTTP ' + connectResult.response.status + ').')
        );
        err.httpStatus = connectResult.response.status;
        throw err;
    }

    mobileScannerToken = String(connectResult.data.token || token).trim();
    sessionStorage.setItem('mobileScannerToken', mobileScannerToken);

    document.getElementById('connectionPanel').style.display = 'none';
    document.getElementById('scannerArea').style.display = 'flex';
    setScannerStatus('Connected automatically. Requesting camera permission...');

    await startCamera();
}

async function autoConnectScanner(manualRetry) {
    clearTimeout(autoConnectTimer);

    const button = document.getElementById('connectButton');
    if (button) button.disabled = true;

    if (manualRetry) {
        autoConnectAttempts = 0;
        setConnectionStatus('Looking for the active desktop POS scanner...');
    }

    try {
        /*
         * Always ask the current server for the active POS session first.
         * This prevents an old token left in the phone browser from being
         * used after the POS has been moved/restarted on another PC.
         */
        let lastError = null;

        const result = await apiJson(
            SCANNER_API + '?action=discover&_=' + Date.now(),
            { method: 'GET' }
        );

        if (result.data && result.data.success && result.data.token) {
            setConnectionStatus('Desktop POS found. Connecting...');
            try {
                await connectWithToken(result.data.token);
                return;
            } catch (error) {
                console.error('SCANNER CONNECT ERROR:', error);
                lastError = error;
            }
        }

        /*
         * If discovery did not return a session, only then try the token
         * embedded in this page. This is useful when the page was generated
         * immediately after the desktop created its session.
         */
        if (INITIAL_SCANNER_TOKEN) {
            try {
                await connectWithToken(INITIAL_SCANNER_TOKEN);
                return;
            } catch (error) {
                lastError = error;
            }
        }

        /*
         * Do not use a stale sessionStorage token as the primary connection.
         * Remove it if it exists; a future successful connection will replace it.
         */
        sessionStorage.removeItem('mobileScannerToken');

        autoConnectAttempts++;

        if (result.response && result.response.status === 404) {
            setConnectionStatus('No active POS scanner yet. Start Mobile Scanner on the desktop POS...');
        } else if (lastError) {
            setConnectionStatus(lastError.message || 'Could not connect to the desktop POS.');
        } else {
            setConnectionStatus(
                (result.data && result.data.message) ||
                'No active POS scanner was found.'
            );
        }

        /* Keep trying indefinitely. The phone can be opened before the PC starts the scanner. */
        autoConnectTimer = setTimeout(function() {
            autoConnectScanner(false);
        }, 2000);

    } catch (error) {
        console.error('AUTO CONNECT ERROR:', error);
        autoConnectAttempts++;

        setConnectionStatus(
            'Could not reach the POS server: ' +
            (error.message || 'Network request failed.') +
            ' Retrying...'
        );

        autoConnectTimer = setTimeout(function() {
            autoConnectScanner(false);
        }, 2000);
    } finally {
        if (button) button.disabled = false;
    }
}

function chooseRearCamera(cameras) {
    if (!Array.isArray(cameras) || cameras.length === 0) return null;

    for (let i = 0; i < cameras.length; i++) {
        const label = String(cameras[i].label || '').toLowerCase();
        if (label.indexOf('back') !== -1 || label.indexOf('rear') !== -1 || label.indexOf('environment') !== -1) {
            return cameras[i];
        }
    }

    return cameras[cameras.length - 1];
}

async function startCamera() {
    if (!mobileScannerToken) {
        mobileScannerToken = sessionStorage.getItem('mobileScannerToken') || '';
    }

    if (!mobileScannerToken) {
        setScannerStatus('No active POS connection. Return to the connection screen and retry.');
        return;
    }

    if (typeof Html5Qrcode === 'undefined') {
        setScannerStatus('Barcode scanner library could not be loaded. Check assets/scripts/html5-qrcode.min.js.');
        return;
    }

    await stopCamera();

    const config = {
        fps: 10,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
            const width = Math.max(180, Math.floor(viewfinderWidth * 0.90));
            const height = Math.max(80, Math.min(150, Math.floor(viewfinderHeight * 0.22)));
            return {
                width: Math.min(width, viewfinderWidth),
                height: Math.min(height, viewfinderHeight)
            };
        },
        // Do not force an aspect ratio. Some Android camera implementations
        // reject an incompatible aspectRatio and report a misleading
        // "source failed to restart" error.
        videoConstraints: {
            facingMode: { ideal: 'environment' }
        }
    };

    if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
        config.formatsToSupport = [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.CODE_93,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.ITF,
            Html5QrcodeSupportedFormats.CODABAR
        ];
    }

    const onScanSuccess = function(decodedText) {
        const barcode = String(decodedText || '').trim();
        if (!barcode) return;

        const now = Date.now();
        if (barcode === lastMobileBarcode && now - lastMobileBarcodeTime < 1500) {
            return;
        }

        lastMobileBarcode = barcode;
        lastMobileBarcodeTime = now;
        sendBarcodeToDesktop(barcode);
    };

    try {
        setScannerStatus('Requesting access to the rear camera...');

        mobileScanner = new Html5Qrcode('mobileBarcodeReader', { verbose: false });

        /*
         * First attempt: let the browser select the environment/rear camera.
         * This avoids relying on camera IDs, which can behave differently
         * between Android phones and browsers.
         */
        await mobileScanner.start(
            { facingMode: { ideal: 'environment' } },
            config,
            onScanSuccess,
            function() {}
        );

        mobileScannerRunning = true;
        setScannerStatus('Connected. Point the camera at a barcode.');
        return;

    } catch (firstError) {
        console.warn('ENVIRONMENT CAMERA START FAILED:', firstError);

        /*
         * Fallback: enumerate cameras after the browser has granted access,
         * then try the available camera IDs one by one.
         */
        try {
            if (mobileScanner) {
                try {
                    await mobileScanner.clear();
                } catch (e) {}
            }

            mobileScanner = new Html5Qrcode('mobileBarcodeReader', { verbose: false });

            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                throw new Error(
                    'The browser did not expose any camera. Check the site camera permission and make sure no other app is using the camera.'
                );
            }

            const orderedCameras = cameras.slice();

            orderedCameras.sort(function(a, b) {
                const al = String(a.label || '').toLowerCase();
                const bl = String(b.label || '').toLowerCase();

                const aRear =
                    al.indexOf('back') !== -1 ||
                    al.indexOf('rear') !== -1 ||
                    al.indexOf('environment') !== -1;

                const bRear =
                    bl.indexOf('back') !== -1 ||
                    bl.indexOf('rear') !== -1 ||
                    bl.indexOf('environment') !== -1;

                return Number(bRear) - Number(aRear);
            });

            let lastCameraError = firstError;

            for (const camera of orderedCameras) {
                try {
                    setScannerStatus('Trying camera ' + (camera.label || 'available camera') + '...');

                    await mobileScanner.start(
                        camera.id,
                        config,
                        onScanSuccess,
                        function() {}
                    );

                    mobileScannerRunning = true;
                    setScannerStatus('Connected. Point the camera at a barcode.');
                    return;

                } catch (cameraError) {
                    console.warn('CAMERA ID START FAILED:', camera.id, cameraError);
                    lastCameraError = cameraError;

                    try {
                        await mobileScanner.stop();
                    } catch (e) {}

                    try {
                        await mobileScanner.clear();
                    } catch (e) {}

                    mobileScanner = new Html5Qrcode('mobileBarcodeReader', { verbose: false });
                }
            }

            throw lastCameraError || new Error('Unable to start any available camera.');

        } catch (fallbackError) {
            console.error('CAMERA START ERROR:', fallbackError);

            const message = String(
                fallbackError && fallbackError.message
                    ? fallbackError.message
                    : fallbackError || 'Unknown camera error'
            );

            setScannerStatus(
                'Unable to start camera: ' + message +
                ' Check camera permission, close other apps using the camera, then tap Restart Camera.'
            );

            try {
                if (mobileScanner) {
                    await mobileScanner.clear();
                }
            } catch (e) {}

            mobileScanner = null;
            mobileScannerRunning = false;
        }
    }
}

async function sendBarcodeToDesktop(barcode) {
    barcode = String(barcode || '').trim();

    if (!barcode) {
        setScannerStatus('Barcode was empty. Please scan again.');
        return;
    }

    if (scanRequestInProgress) return;

    /*
     * Use the live token established by connectWithToken(). Do not silently
     * trust an old browser token. If the live token is missing, rediscover
     * the current POS session before trying to send the barcode.
     */
    if (!mobileScannerToken || (mobileScannerToken.length !== 48 && mobileScannerToken.length !== 64)) {
        mobileScannerToken = '';
        sessionStorage.removeItem('mobileScannerToken');

        setScannerStatus('Desktop scanner connection is missing. Reconnecting...');

        try {
            await autoConnectScanner(false);
        } catch (error) {
            console.error('AUTO RECONNECT BEFORE SCAN ERROR:', error);
        }

        if (!mobileScannerToken || (mobileScannerToken.length !== 48 && mobileScannerToken.length !== 64)) {
            setScannerStatus('Could not reconnect to the desktop POS. Tap Find POS Automatically.');
            return;
        }
    }

    scanRequestInProgress = true;

    const token = String(mobileScannerToken).trim();
    const lastBarcode = document.getElementById('lastBarcode');
    if (lastBarcode) lastBarcode.textContent = barcode;
    setScannerStatus('Sending barcode...');

    try {
        /*
         * Send the token and barcode in BOTH POST and query string.
         * This protects against mobile-browser/proxy environments that
         * occasionally omit application/x-www-form-urlencoded POST fields.
         */
        const scanUrl =
            SCANNER_API +
            '?action=scan' +
            '&token=' + encodeURIComponent(token) +
            '&barcode=' + encodeURIComponent(barcode) +
            '&_=' + Date.now();

        const result = await apiJson(scanUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'scan',
                token: token,
                barcode: barcode
            })
        });

        console.log('MOBILE BARCODE SEND RESPONSE:', result.response.status, result.data);

        if (!result.response.ok || !result.data.success) {
            /*
             * A bad/expired session should cause a fresh automatic discovery.
             */
            if (
                result.response.status === 400 ||
                result.response.status === 404 ||
                result.response.status === 409 ||
                result.response.status === 410
            ) {
                mobileScannerToken = '';
                sessionStorage.removeItem('mobileScannerToken');

                await stopCamera();

                document.getElementById('scannerArea').style.display = 'none';
                document.getElementById('connectionPanel').style.display = 'block';

                setConnectionStatus('Desktop scanner connection ended. Reconnecting...');
                autoConnectScanner(false);
            } else {
                setScannerStatus(
                    result.data.message ||
                    'Barcode could not be sent to the desktop POS.'
                );
            }

            return;
        }

        setScannerStatus('Barcode sent. Ready for the next scan.');

    } catch (error) {
        console.error('SEND BARCODE ERROR:', error);
        setScannerStatus(
            'Could not reach the desktop POS: ' +
            (error.message || 'Network request failed.')
        );
    } finally {
        scanRequestInProgress = false;
    }
}

async function restartCamera() {
    setScannerStatus('Restarting camera...');
    await startCamera();
}

async function stopCamera() {
    if (!mobileScanner) return;

    try {
        if (mobileScannerRunning) {
            await mobileScanner.stop();
        }
    } catch (error) {
        console.warn('CAMERA STOP WARNING:', error);
    } finally {
        mobileScannerRunning = false;
        try {
            await mobileScanner.clear();
        } catch (error) {}
        mobileScanner = null;
    }
}

function exitScanner() {
    clearTimeout(autoConnectTimer);

    /*
     * Stop the camera before leaving the scanner page.
     * The desktop POS scanner session is intentionally left running so
     * returning to the dashboard/POS can continue using it.
     */
    stopCamera().catch(function(error) {
        console.warn('EXIT CAMERA STOP WARNING:', error);
    }).finally(function() {
        window.location.href = 'index.php';
    });
}

async function disconnectScanner() {
    clearTimeout(autoConnectTimer);
    await stopCamera();

    mobileScannerToken = '';
    sessionStorage.removeItem('mobileScannerToken');
    lastMobileBarcode = '';
    lastMobileBarcodeTime = 0;

    document.getElementById('scannerArea').style.display = 'none';
    document.getElementById('connectionPanel').style.display = 'block';
    setConnectionStatus('Disconnected. Tap Find POS Automatically to reconnect.');
}

window.addEventListener('DOMContentLoaded', function() {
    autoConnectScanner(false);
});

window.addEventListener('beforeunload', function() {
    try {
        if (mobileScanner && mobileScannerRunning) {
            mobileScanner.stop();
        }
    } catch (error) {}
});
</script>

</body>
</html>
