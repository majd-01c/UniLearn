/**
 * UniLearn Face ID — handles both enrollment and verification.
 *
 * Globals expected (set in the Twig template):
 *   FACE_API_MODEL_URL  – path to model weight files
 *   MODE                – 'enroll' | 'verify' | 'login'
 *   CSRF_TOKEN          – CSRF token string
 *   ENROLL_URL          – (enroll mode) POST endpoint
 *   VERIFY_URL          – (verify/login mode) POST endpoint
 *   attemptsLeft        – (verify/login mode) remaining attempts
 */

(function () {
    'use strict';

    // ────────────────────────────────────────────────────────────────────
    // DOM refs (may or may not exist depending on MODE)
    // ────────────────────────────────────────────────────────────────────
    const $loading     = document.getElementById('step-loading');
    const $capture     = document.getElementById('step-capture');
    const $success     = document.getElementById('step-success');       // enroll only
    const $error       = document.getElementById('step-error');         // enroll only
    const $errorMsg    = document.getElementById('error-message');      // enroll only
    const $video       = document.getElementById('video');
    const $overlay     = document.getElementById('overlay');
    const $cameraSec   = document.getElementById('camera-section');
    const $uploadSec   = document.getElementById('upload-section');
    const $captureStatus = document.getElementById('capture-status');
    const $captureProg = document.getElementById('capture-progress');   // enroll only
    const $modelProg   = document.getElementById('model-progress');
    const $uploadInput = document.getElementById('face-upload');
    const $uploadPreviews = document.getElementById('upload-previews');
    const $btnProcess  = document.getElementById('btn-process-uploads');
    const $uploadStatus= document.getElementById('upload-status');
    const $consent     = document.getElementById('consent-check');      // enroll only
    const $btnEnroll   = document.getElementById('btn-enroll');         // enroll only
    const $btnCapture  = document.getElementById('btn-capture');        // verify only
    const $verifyResult= document.getElementById('verify-result');      // verify only
    const $verifySpinner = document.getElementById('verify-spinner');   // verify only
    const $verifySuccess = document.getElementById('verify-success');   // verify only
    const $verifyFail  = document.getElementById('verify-fail');        // verify only
    const $verifyFailMsg = document.getElementById('verify-fail-msg'); // verify only
    const $btnRetry    = document.getElementById('btn-retry');          // verify only
    const $attemptsEl  = document.getElementById('attempts-left');      // verify only

    // Step labels (enroll)
    const $step1Label = document.getElementById('step-1-label');
    const $step2Label = document.getElementById('step-2-label');
    const $step3Label = document.getElementById('step-3-label');

    let collectedDescriptors = [];
    let stream = null;
    const ENROLL_FRAMES = 5;

    // ────────────────────────────────────────────────────────────────────
    // 1. Load face-api models
    // ────────────────────────────────────────────────────────────────────
    async function loadModels() {
        const models = [
            faceapi.nets.ssdMobilenetv1,
            faceapi.nets.faceLandmark68Net,
            faceapi.nets.faceRecognitionNet,
        ];
        let loaded = 0;
        for (const net of models) {
            await net.loadFromUri(FACE_API_MODEL_URL);
            loaded++;
            if ($modelProg) $modelProg.style.width = Math.round((loaded / models.length) * 100) + '%';
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // 2. Start camera or fall back to upload
    // ────────────────────────────────────────────────────────────────────
    async function initCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
            });
            $video.srcObject = stream;
            await new Promise(r => { $video.onloadedmetadata = r; });
            $overlay.width = $video.videoWidth;
            $overlay.height = $video.videoHeight;
            return true;
        } catch (e) {
            console.warn('Camera unavailable:', e);
            return false;
        }
    }

    function showUploadFallback() {
        $cameraSec.classList.add('d-none');
        $uploadSec.classList.remove('d-none');
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // 3. Extract descriptor from a video frame
    // ────────────────────────────────────────────────────────────────────
    async function detectFromVideo() {
        const detection = await faceapi
            .detectSingleFace($video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        return detection;
    }

    // Draw bounding box on overlay
    function drawDetection(detection) {
        const ctx = $overlay.getContext('2d');
        ctx.clearRect(0, 0, $overlay.width, $overlay.height);
        if (!detection) return;
        const box = detection.detection.box;
        ctx.strokeStyle = '#00d97e';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
    }

    // ────────────────────────────────────────────────────────────────────
    // 4. ENROLL — auto-capture 5 frames
    // ────────────────────────────────────────────────────────────────────
    async function runEnrollCapture() {
        $captureStatus.innerHTML = '<i class="bi bi-camera-video"></i> Detecting face…';
        collectedDescriptors = [];

        while (collectedDescriptors.length < ENROLL_FRAMES) {
            const det = await detectFromVideo();
            if (det) {
                drawDetection(det);
                collectedDescriptors.push(Array.from(det.descriptor));
                const n = collectedDescriptors.length;
                $captureStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i> Captured frame ' + n + '/' + ENROLL_FRAMES;
                if ($captureProg) $captureProg.style.width = (n / ENROLL_FRAMES * 100) + '%';
                // small pause between captures for variation
                await sleep(600);
            } else {
                drawDetection(null);
                $captureStatus.innerHTML = '<i class="bi bi-eye-slash text-warning"></i> No face detected – hold still…';
                await sleep(300);
            }
        }

        stopCamera();
        $captureStatus.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> All frames captured!';

        // Show enroll button
        $btnEnroll.classList.remove('d-none');
        updateEnrollButton();
    }

    function updateEnrollButton() {
        if ($btnEnroll && $consent) {
            $btnEnroll.disabled = !(collectedDescriptors.length >= 1 && $consent.checked);
        }
    }

    async function submitEnrollment() {
        $btnEnroll.disabled = true;
        $btnEnroll.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';

        try {
            const resp = await fetch(ENROLL_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: JSON.stringify({
                    descriptors: collectedDescriptors,
                    consent: true,
                    _token: CSRF_TOKEN,
                }),
            });
            const data = await resp.json();
            if (resp.ok && data.success) {
                showStep('success');
            } else {
                showError(data.error || 'Unknown error.');
            }
        } catch (e) {
            showError('Network error: ' + e.message);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // 5. VERIFY — capture one frame and send
    // ────────────────────────────────────────────────────────────────────
    async function runVerifyCapture() {
        $captureStatus.innerHTML = '<i class="bi bi-camera-video text-primary"></i> Detecting face…';

        // Continuous detection loop — show bounding box, enable capture button once detected
        let currentDetection = null;
        const detectLoop = async () => {
            if (!stream) return;
            const det = await detectFromVideo();
            drawDetection(det);
            if (det) {
                currentDetection = det;
                $captureStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i> Face detected — click Capture to verify';
                $btnCapture.classList.remove('d-none');
            } else {
                currentDetection = null;
                $captureStatus.innerHTML = '<i class="bi bi-eye-slash text-warning"></i> No face detected…';
            }
            if (stream) requestAnimationFrame(detectLoop);
        };
        detectLoop();

        // Capture button
        $btnCapture.addEventListener('click', async () => {
            if (!currentDetection) return;
            $btnCapture.disabled = true;
            await submitVerification(Array.from(currentDetection.descriptor));
        });
    }

    async function submitVerification(descriptor) {
        // Show spinner — handle both camera and upload UI states
        if ($capture) $capture.classList.add('d-none');
        if ($verifyResult) {
            $verifyResult.classList.remove('d-none');
            if ($verifySpinner) $verifySpinner.classList.remove('d-none');
            if ($verifySuccess) $verifySuccess.classList.add('d-none');
            if ($verifyFail) $verifyFail.classList.add('d-none');
        }

        try {
            const resp = await fetch(VERIFY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: JSON.stringify({ descriptor: descriptor, _token: CSRF_TOKEN }),
            });
            const data = await resp.json();
            if ($verifySpinner) $verifySpinner.classList.add('d-none');

            if (data.match) {
                if ($verifySuccess) $verifySuccess.classList.remove('d-none');
                // Show welcome message if provided (face login mode)
                const $welcomeMsg = document.getElementById('welcome-msg');
                if ($welcomeMsg && data.message) {
                    $welcomeMsg.textContent = data.message;
                }
                stopCamera();
                setTimeout(() => {
                    window.location.href = data.redirect || '/';
                }, 1200);
            } else {
                if (typeof attemptsLeft !== 'undefined') {
                    attemptsLeft = data.attemptsLeft;
                    if ($attemptsEl) $attemptsEl.textContent = attemptsLeft;
                }
                if ($verifyFail) $verifyFail.classList.remove('d-none');
                if ($verifyFailMsg) $verifyFailMsg.textContent = data.message || 'Face not recognized.';
                if (data.attemptsLeft <= 0 && data.redirect) {
                    setTimeout(() => { window.location.href = data.redirect; }, 2000);
                }
            }
        } catch (e) {
            if ($verifySpinner) $verifySpinner.classList.add('d-none');
            if ($verifyFail) $verifyFail.classList.remove('d-none');
            if ($verifyFailMsg) $verifyFailMsg.textContent = 'Network error: ' + e.message;
        }
    }

    // Retry button (verify mode)
    if ($btnRetry) {
        $btnRetry.addEventListener('click', () => {
            if ($verifyResult) $verifyResult.classList.add('d-none');
            if ($verifyFail) $verifyFail.classList.add('d-none');
            if ($capture) $capture.classList.remove('d-none');
            if ($btnCapture) $btnCapture.disabled = false;
            // Re-enable upload button too
            if ($btnProcess) $btnProcess.disabled = false;
            if ($uploadInput) $uploadInput.value = '';
            if ($uploadPreviews) $uploadPreviews.innerHTML = '';
            if ($uploadStatus) $uploadStatus.innerHTML = '';
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // 6. Upload fallback — process uploaded images
    // ────────────────────────────────────────────────────────────────────
    if ($uploadInput) {
        $uploadInput.addEventListener('change', () => {
            $uploadPreviews.innerHTML = '';
            const files = $uploadInput.files;
            if (files.length > 0) {
                const limit = MODE === 'enroll' ? 5 : 1;
                for (let i = 0; i < Math.min(files.length, limit); i++) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(files[i]);
                    img.className = 'rounded border';
                    img.style.width = '80px';
                    img.style.height = '80px';
                    img.style.objectFit = 'cover';
                    $uploadPreviews.appendChild(img);
                }
                $btnProcess.disabled = false;
            } else {
                $btnProcess.disabled = true;
            }
        });
    }

    if ($btnProcess) {
        $btnProcess.addEventListener('click', async () => {
            $btnProcess.disabled = true;
            const files = $uploadInput.files;
            const limit = MODE === 'enroll' ? 5 : 1;
            const descs = [];

            for (let i = 0; i < Math.min(files.length, limit); i++) {
                $uploadStatus.innerHTML = '<span class="text-primary">Processing image ' + (i + 1) + '/' + Math.min(files.length, limit) + '...</span>';
                const img = await faceapi.bufferToImage(files[i]);
                const det = await faceapi
                    .detectSingleFace(img, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                if (det) {
                    descs.push(Array.from(det.descriptor));
                } else {
                    $uploadStatus.innerHTML = '<span class="text-warning">No face detected in image ' + (i + 1) + ' — skipped.</span>';
                    await sleep(800);
                }
            }

            if (descs.length === 0) {
                $uploadStatus.innerHTML = '<span class="text-danger">No faces detected in any of the uploaded images. Please try again with clearer photos.</span>';
                $btnProcess.disabled = false;
                return;
            }

            if (MODE === 'enroll') {
                collectedDescriptors = descs;
                $uploadStatus.innerHTML = '<span class="text-success">' + descs.length + ' face(s) processed successfully!</span>';
                $btnEnroll.classList.remove('d-none');
                if ($captureProg) $captureProg.style.width = (descs.length / ENROLL_FRAMES * 100) + '%';
                updateEnrollButton();
            } else {
                // verify mode — show status then send first descriptor
                $uploadStatus.innerHTML = '<span class="text-primary"><span class="spinner-border spinner-border-sm me-1"></span> Verifying your face...</span>';
                try {
                    await submitVerification(descs[0]);
                } catch (e) {
                    $uploadStatus.innerHTML = '<span class="text-danger">Verification error: ' + e.message + '</span>';
                }
            }
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────
    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    function showStep(name) {
        [$loading, $capture, $success, $error].forEach(el => {
            if (el) el.classList.add('d-none');
        });
        const el = document.getElementById('step-' + name);
        if (el) el.classList.remove('d-none');

        // Update step labels (enroll)
        if (name === 'capture' && $step2Label) {
            $step2Label.querySelector('.badge').className = 'badge bg-primary rounded-pill';
        }
        if ((name === 'success') && $step3Label) {
            $step2Label.querySelector('.badge').className = 'badge bg-success rounded-pill';
            $step3Label.querySelector('.badge').className = 'badge bg-success rounded-pill';
        }
    }

    function showError(msg) {
        showStep('error');
        if ($errorMsg) $errorMsg.textContent = msg;
    }

    // ────────────────────────────────────────────────────────────────────
    // INIT
    // ────────────────────────────────────────────────────────────────────
    async function init() {
        try {
            await loadModels();
        } catch (e) {
            showError('Failed to load face detection models: ' + e.message);
            return;
        }

        showStep('capture');

        const hasCam = await initCamera();
        if (!hasCam) {
            showUploadFallback();
        } else {
            if (MODE === 'enroll') {
                runEnrollCapture();
            } else {
                runVerifyCapture();
            }
        }
    }

    // Consent checkbox (enroll)
    if ($consent) {
        $consent.addEventListener('change', updateEnrollButton);
    }
    // Enroll button (enroll)
    if ($btnEnroll) {
        $btnEnroll.addEventListener('click', submitEnrollment);
    }

    // Start once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
