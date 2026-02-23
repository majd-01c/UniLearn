"""
Avatar Generation Microservice
Uses OpenCV cartoon filters to generate stylized avatars
from uploaded profile photos.  Runs in < 1 second on CPU.
"""

import io
import logging
import numpy as np
import cv2
from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.responses import StreamingResponse
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Avatar Generator Service", version="3.0.0")

MAX_FILE_SIZE = 5 * 1024 * 1024  # 5MB
ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp", "image/gif"}


# ── Cartoon filter pipeline ──────────────────────────────────────────

def cartoonize(img_rgb: np.ndarray) -> np.ndarray:
    """
    Apply a cartoon / comic-book effect using OpenCV:
      1. Bilateral filter to smooth colours while keeping edges
      2. Adaptive-threshold edge mask
      3. Colour quantization via K-means
      4. Combine smoothed colours + edges
    """
    h, w = img_rgb.shape[:2]

    # --- 1. Edge mask ---------------------------------------------------
    gray = cv2.cvtColor(img_rgb, cv2.COLOR_RGB2GRAY)
    gray = cv2.medianBlur(gray, 5)
    edges = cv2.adaptiveThreshold(
        gray, 255,
        cv2.ADAPTIVE_THRESH_MEAN_C,
        cv2.THRESH_BINARY,
        blockSize=9,
        C=2,
    )

    # --- 2. Smooth colours with bilateral filter (repeat for stronger) --
    colour = img_rgb.copy()
    for _ in range(3):
        colour = cv2.bilateralFilter(colour, d=9, sigmaColor=75, sigmaSpace=75)

    # --- 3. Colour quantization (K-means, K=12) -------------------------
    data = np.float32(colour.reshape(-1, 3))
    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 20, 1.0)
    _, labels, centres = cv2.kmeans(
        data, 12, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS,
    )
    centres = np.uint8(centres)
    quantised = centres[labels.flatten()].reshape(colour.shape)

    # --- 4. Combine: quantised colours masked by edges ------------------
    edges_3ch = cv2.cvtColor(edges, cv2.COLOR_GRAY2RGB)
    cartoon = cv2.bitwise_and(quantised, edges_3ch)

    # Boost saturation slightly for a vivid cartoon look
    hsv = cv2.cvtColor(cartoon, cv2.COLOR_RGB2HSV).astype(np.float32)
    hsv[:, :, 1] = np.clip(hsv[:, :, 1] * 1.3, 0, 255)
    hsv = hsv.astype(np.uint8)
    cartoon = cv2.cvtColor(hsv, cv2.COLOR_HSV2RGB)

    return cartoon


# ── Endpoints ─────────────────────────────────────────────────────────

@app.get("/health")
async def health_check():
    return {
        "status": "ok",
        "engine": "opencv-cartoon",
    }


@app.post("/generate-avatar")
async def generate_avatar(file: UploadFile = File(...)):
    """
    Generate a cartoon avatar from an uploaded photo.
    Accepts: JPG, PNG, WebP, GIF (max 5 MB)
    Returns: PNG image
    """
    # Validate content type
    if file.content_type not in ALLOWED_TYPES:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type '{file.content_type}'. Allowed: {', '.join(ALLOWED_TYPES)}",
        )

    # Read and validate size
    contents = await file.read()
    if len(contents) > MAX_FILE_SIZE:
        raise HTTPException(
            status_code=400,
            detail=f"File too large ({len(contents)} bytes). Maximum: {MAX_FILE_SIZE} bytes (5 MB)",
        )
    if len(contents) == 0:
        raise HTTPException(status_code=400, detail="Empty file uploaded")

    try:
        # Open and prepare the image
        input_image = Image.open(io.BytesIO(contents)).convert("RGB")

        # Resize to 512×512 for consistency
        input_image = input_image.resize((512, 512), Image.LANCZOS)

        # Convert to numpy array for OpenCV processing
        img_array = np.array(input_image)

        # Apply cartoon effect
        cartoon_array = cartoonize(img_array)

        # Convert back to PIL Image
        result = Image.fromarray(cartoon_array)

        # Convert to PNG bytes
        output = io.BytesIO()
        result.save(output, format="PNG", optimize=True)
        output.seek(0)

        logger.info("Avatar generated successfully")

        return StreamingResponse(
            output,
            media_type="image/png",
            headers={"Content-Disposition": "inline; filename=avatar.png"},
        )

    except Exception as e:
        logger.exception("Avatar generation failed")
        raise HTTPException(status_code=500, detail="Avatar generation failed: internal error")
