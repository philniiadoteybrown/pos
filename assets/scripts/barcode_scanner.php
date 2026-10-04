<!-- Local phone-camera barcode scanner -->
<script src="assets/scripts/html5-qrcode.min.js"></script>

<style>
#barcodeScannerOverlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0,0,0,.96);
    color: #fff;
}

#barcodeScannerOverlay .barcode-scanner-panel {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
}

#barcodeScannerOverlay .barcode-scanner-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 15px;
    background: #111;
}

#barcodeScannerOverlay .barcode-scanner-title {
    font-size: 18px;
    font-weight: 700;
}

#barcodeScannerOverlay .barcode-scanner-close {
    border: 0;
    background: #dc3545;
    color: #fff;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 16px;
}

#barcode-reader {
    flex: 1;
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

#barcodeScannerStatus {
    min-height: 48px;
    padding: 10px 15px;
    text-align: center;
    background: #111;
    font-size: 14px;
}

@media (max-width: 575.98px) {
    #barcodeScannerOverlay .barcode-scanner-header {
        padding: 10px;
    }

    #barcodeScannerOverlay .barcode-scanner-title {
        font-size: 16px;
    }
}
</style>

<div id="barcodeScannerOverlay">
    <div class="barcode-scanner-panel">
        <div class="barcode-scanner-header">
            <div class="barcode-scanner-title">📷 Scan Product Barcode</div>
            <button type="button" class="barcode-scanner-close" onclick="closeBarcodeScanner()">
                Close
            </button>
        </div>

        <div id="barcode-reader"></div>

        <div id="barcodeScannerStatus">
            Point the phone camera at the barcode.
        </div>
    </div>
</div>

<script>
let barcodeScanner = null;
let barcodeScannerRunning = false;
let barcodeScannerCallback = null;
let barcodeLastCode = '';
let barcodeLastTime = 0;

function openBarcodeScanner(callback) {
    barcodeScannerCallback = callback;
    barcodeLastCode = '';
    barcodeLastTime = 0;

    const overlay = document.getElementById('barcodeScannerOverlay');
    const status = document.getElementById('barcodeScannerStatus');

    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
    status.textContent = 'Starting phone camera...';

    setTimeout(startBarcodeScanner, 100);
}

async function startBarcodeScanner() {
    const status = document.getElementById('barcodeScannerStatus');

    try {
        if (!window.Html5Qrcode) {
            throw new Error('Local barcode scanner library was not loaded.');
        }

        if (barcodeScannerRunning) {
            return;
        }

        document.getElementById('barcode-reader').innerHTML = '';

        barcodeScanner = new Html5Qrcode('barcode-reader');

        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            throw new Error('No camera was detected on this device.');
        }

        // Prefer the rear/environment camera on phones.
        let selectedCamera = cameras.find(camera => {
            const label = (camera.label || '').toLowerCase();
            return label.includes('back') ||
                   label.includes('rear') ||
                   label.includes('environment');
        });

        if (!selectedCamera) {
            selectedCamera = cameras[cameras.length - 1];
        }

        const formats = [
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

        const config = {
            fps: 15,
            formatsToSupport: formats,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                return {
                    width: Math.floor(viewfinderWidth * 0.88),
                    height: Math.floor(viewfinderHeight * 0.45)
                };
            },
            aspectRatio: 1.7777778,
            disableFlip: false
        };

        await barcodeScanner.start(
            selectedCamera.id,
            config,
            function(decodedText) {
                handleBarcodeDetected(decodedText);
            },
            function() {
                // Normal: scanner keeps looking until a barcode is found.
            }
        );

        barcodeScannerRunning = true;
        status.textContent = 'Camera active — point it at a barcode.';

    } catch (error) {
        console.error('Barcode scanner error:', error);
        status.textContent = 'Camera error: ' + error.message;
    }
}

function handleBarcodeDetected(code) {
    code = String(code || '').trim();

    if (!code) {
        return;
    }

    const now = Date.now();

    // Prevent the same barcode from being returned repeatedly.
    if (code === barcodeLastCode && (now - barcodeLastTime) < 2500) {
        return;
    }

    barcodeLastCode = code;
    barcodeLastTime = now;

    const callback = barcodeScannerCallback;

    // Stop immediately after a successful scan.
    closeBarcodeScanner();

    if (typeof callback === 'function') {
        callback(code);
    }
}

async function closeBarcodeScanner() {
    const overlay = document.getElementById('barcodeScannerOverlay');

    if (barcodeScanner && barcodeScannerRunning) {
        try {
            await barcodeScanner.stop();
        } catch (error) {
            console.warn('Scanner stop:', error);
        }
    }

    barcodeScannerRunning = false;
    barcodeScanner = null;
    barcodeScannerCallback = null;

    if (overlay) {
        overlay.style.display = 'none';
    }

    document.body.style.overflow = '';
}

window.addEventListener('pagehide', function() {
    if (barcodeScanner && barcodeScannerRunning) {
        barcodeScanner.stop().catch(() => {});
    }
});
</script>
