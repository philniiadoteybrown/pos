<?php
// camera-test.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Philynda Barcode Camera Test</title>

    <!-- LOCAL OFFLINE HTML5 QR CODE LIBRARY -->
    <script src="assets/scripts/html5-qrcode.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 800px;
            margin: auto;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.10);
        }

        h2 {
            margin-top: 0;
            text-align: center;
        }

        #reader {
            width: 100%;
            max-width: 650px;
            margin: 20px auto;
            border: 2px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            background: #f1f3f5;
            margin-top: 15px;
            text-align: center;
            font-weight: bold;
        }

        .barcode-box {
            margin-top: 15px;
        }

        .barcode-box label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .barcode-box input {
            width: 100%;
            padding: 14px;
            font-size: 20px;
            border: 1px solid #ccc;
            border-radius: 7px;
            text-align: center;
        }

        .buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 12px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
        }

        #startBtn {
            background: #198754;
            color: white;
        }

        #stopBtn {
            background: #dc3545;
            color: white;
        }

        #clearBtn {
            background: #6c757d;
            color: white;
        }

        .info {
            margin-top: 20px;
            padding: 15px;
            background: #eef5ff;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        .warning {
            background: #fff3cd;
            color: #664d03;
        }

        .hidden {
            display: none;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <h2>📷 Philynda Barcode Camera Test</h2>

        <div id="reader"></div>

        <div id="status" class="status">
            Camera scanner is ready.
        </div>

        <div class="barcode-box">
            <label for="barcode">Detected Barcode</label>

            <input
                type="text"
                id="barcode"
                placeholder="Barcode will appear here automatically"
                readonly
            >
        </div>

        <div class="buttons">

            <button type="button" id="startBtn" onclick="startScanner()">
                Start Camera
            </button>

            <button type="button" id="stopBtn" onclick="stopScanner()">
                Stop Camera
            </button>

            <button type="button" id="clearBtn" onclick="clearBarcode()">
                Clear
            </button>

        </div>

        <div class="info">

            <strong>How to test:</strong>

            <ol>
                <li>Click <strong>Start Camera</strong>.</li>
                <li>Allow camera permission when your browser asks.</li>
                <li>Hold a barcode in front of the camera.</li>
                <li>Keep the barcode reasonably large and in focus.</li>
                <li>You do <strong>not</strong> need to press a capture button.</li>
                <li>The barcode should be detected automatically.</li>
            </ol>

            <strong>Supported common formats:</strong>
            EAN-13, EAN-8, UPC-A, UPC-E, CODE-128,
            CODE-39, CODE-93, ITF and CODABAR.

        </div>

    </div>

</div>


<script>

let scanner = null;
let scannerRunning = false;
let lastBarcode = "";
let lastScanTime = 0;


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

function setStatus(message, type = "") {

    const status = document.getElementById("status");

    status.className = "status";

    if (type) {
        status.classList.add(type);
    }

    status.textContent = message;
}


/*
|--------------------------------------------------------------------------
| Start Scanner
|--------------------------------------------------------------------------
*/

async function startScanner() {

    if (scannerRunning) {
        return;
    }

    if (typeof Html5Qrcode === "undefined") {

        setStatus(
            "ERROR: html5-qrcode.min.js could not be loaded.",
            "error"
        );

        return;
    }

    setStatus(
        "Starting camera. Please allow camera permission...",
        "warning"
    );

    try {

        scanner = new Html5Qrcode("reader");

        const config = {

            fps: 10,

            qrbox: function(viewfinderWidth, viewfinderHeight) {

                /*
                 * Make the scanning box wide enough for
                 * normal 1D barcodes.
                 */

                let width = Math.floor(viewfinderWidth * 0.90);

                let height = Math.floor(
                    Math.min(
                        180,
                        viewfinderHeight * 0.35
                    )
                );

                return {
                    width: width,
                    height: height
                };
            },

            aspectRatio: 1.777778,

            formatsToSupport: [

                Html5QrcodeSupportedFormats.CODE_128,

                Html5QrcodeSupportedFormats.CODE_39,

                Html5QrcodeSupportedFormats.CODE_93,

                Html5QrcodeSupportedFormats.EAN_13,

                Html5QrcodeSupportedFormats.EAN_8,

                Html5QrcodeSupportedFormats.UPC_A,

                Html5QrcodeSupportedFormats.UPC_E,

                Html5QrcodeSupportedFormats.ITF,

                Html5QrcodeSupportedFormats.CODABAR

            ],

            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }

        };


        /*
         * First try to select the rear/environment camera.
         */

        let cameraId = null;

        try {

            const cameras =
                await Html5Qrcode.getCameras();

            if (cameras && cameras.length > 0) {

                /*
                 * Try to find a rear camera.
                 */

                const rearCamera = cameras.find(function(camera) {

                    const label =
                        (camera.label || "").toLowerCase();

                    return (
                        label.includes("back") ||
                        label.includes("rear") ||
                        label.includes("environment")
                    );

                });

                if (rearCamera) {

                    cameraId = rearCamera.id;

                } else {

                    /*
                     * If no rear camera is identified,
                     * use the first available camera.
                     */

                    cameraId = cameras[0].id;

                }

            }

        } catch (cameraListError) {

            console.log(
                "Could not enumerate cameras:",
                cameraListError
            );

        }


        /*
         * Start camera.
         */

        if (cameraId) {

            await scanner.start(

                cameraId,

                config,

                onBarcodeSuccess,

                onBarcodeError

            );

        } else {

            /*
             * Fall back to environment-facing camera.
             */

            await scanner.start(

                {
                    facingMode: {
                        exact: "environment"
                    }
                },

                config,

                onBarcodeSuccess,

                onBarcodeError

            );

        }


        scannerRunning = true;

        setStatus(
            "Camera is active. Point it at a barcode...",
            ""
        );

    } catch (error) {

        console.error("Camera start error:", error);

        scannerRunning = false;

        let message = "Unable to start the camera.";

        if (error && error.message) {
            message += " " + error.message;
        }

        setStatus(
            message,
            "error"
        );

    }

}


/*
|--------------------------------------------------------------------------
| Barcode successfully detected
|--------------------------------------------------------------------------
*/

function onBarcodeSuccess(decodedText, decodedResult) {

    const now = Date.now();

    /*
     * Prevent the same barcode from being
     * processed repeatedly while it remains
     * in front of the camera.
     */

    if (
        decodedText === lastBarcode &&
        (now - lastScanTime) < 2000
    ) {
        return;
    }

    lastBarcode = decodedText;
    lastScanTime = now;


    document.getElementById("barcode").value =
        decodedText;


    setStatus(
        "✓ BARCODE DETECTED: " + decodedText,
        "success"
    );


    console.log(
        "Barcode detected:",
        decodedText
    );

    console.log(
        "Scan result:",
        decodedResult
    );


    /*
     * Stop after successful scan.
     *
     * This prevents the same product from
     * being scanned dozens of times.
     */

    setTimeout(function() {

        stopScanner();

    }, 500);

}


/*
|--------------------------------------------------------------------------
| Barcode scanning errors
|--------------------------------------------------------------------------
*/

function onBarcodeError(errorMessage) {

    /*
     * Do not display every failed frame.
     *
     * A barcode scanner normally produces
     * many "not found" messages while searching.
     */

    // console.log("Scanning:", errorMessage);

}


/*
|--------------------------------------------------------------------------
| Stop Scanner
|--------------------------------------------------------------------------
*/

async function stopScanner() {

    if (!scanner) {
        return;
    }

    try {

        if (scannerRunning) {

            await scanner.stop();

        }

    } catch (error) {

        console.log(
            "Scanner stop error:",
            error
        );

    }


    try {

        scanner.clear();

    } catch (error) {

        console.log(
            "Scanner clear error:",
            error
        );

    }


    scanner = null;

    scannerRunning = false;


    setStatus(
        "Camera stopped.",
        ""
    );

}


/*
|--------------------------------------------------------------------------
| Clear Barcode
|--------------------------------------------------------------------------
*/

function clearBarcode() {

    document.getElementById("barcode").value = "";

    lastBarcode = "";
    lastScanTime = 0;

    setStatus(
        "Ready to scan.",
        ""
    );

}


/*
|--------------------------------------------------------------------------
| Page cleanup
|--------------------------------------------------------------------------
*/

window.addEventListener(
    "beforeunload",
    function() {

        if (scanner) {

            try {
                scanner.stop();
            } catch (e) {}

        }

    }
);

</script>

</body>
</html>