"""
Avatar Generation Microservice  v5.0
Uses Hugging Face Stable Diffusion img2img to generate a fully AI-painted
cartoon avatar that looks completely different from the original photo.

Falls back to an enhanced OpenCV cartoon filter when the HF API is unavailable.
"""

import io
import logging
import numpy as np
import cv2
from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.responses import StreamingResponse
from PIL import Image, ImageEnhance
from huggingface_hub import InferenceClient

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# ── Hugging Face configuration ────────────────────────────────────────
HF_TOKEN = "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"

# img2img model – well-supported on the free HF Inference API
HF_MODEL = "stabilityai/stable-diffusion-2-1"

# Prompt engineered for vivid, clean, fully-illustrated cartoon portrait
CARTOON_PROMPT = (
    "cartoon portrait illustration, anime art style, cel-shaded, bold clean outlines, "
    "vibrant saturated colors, studio ghibli inspired, flat color regions, "
    "professional digital art, painterly, high detail, colorful background"
)
NEGATIVE_PROMPT = (
    "photorealistic, photograph, photo, realistic, blurry, grainy, noisy, "
    "dark, low quality, ugly, deformed, extra limbs, watermark, text, logo"
)

# strength=0.90 → 90 % of noise added back, so the result is almost an
# entirely new AI-generated image inspired by the composition/colours.
IMG2IMG_STRENGTH = 0.90

hf_client = InferenceClient(token=HF_TOKEN)

# ── Service setup ────────────────────────────────────────────────────
app = FastAPI(title="Avatar Generator Service – HF AI", version="5.0.0")

MAX_FILE_SIZE = 5 * 1024 * 1024
ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp", "image/gif"}
TARGET_SIZE = (512, 512)   # optimal resolution for SD 2.1


# ── Image pre-processing ─────────────────────────────────────────────

def preprocess(img: Image.Image) -> Image.Image:
    """
    Center-crop to a square, resize to 512×512, and lightly enhance
    contrast & sharpness so SD has a clean input to work from.
    """
    w, h = img.size
    side = min(w, h)
    left = (w - side) // 2
    top  = (h - side) // 2
    img = img.crop((left, top, left + side, top + side))
    img = img.resize(TARGET_SIZE, Image.LANCZOS)

    # Mild contrast boost so colour information is clear for SD
    img = ImageEnhance.Contrast(img).enhance(1.15)
    img = ImageEnhance.Color(img).enhance(1.10)
    return img


# ── OpenCV fallback cartoon ──────────────────────────────────────────

def opencv_cartoon_fallback(img_rgb: np.ndarray) -> np.ndarray:
    """Enhanced OpenCV cartoon used only when the HF API is unreachable."""
    h, w = img_rgb.shape[:2]
    big = cv2.resize(img_rgb, (w * 2, h * 2), interpolation=cv2.INTER_LANCZOS4)
    styled = cv2.stylization(big, sigma_s=60, sigma_r=0.45)
    colour = styled.copy()
    for _ in range(4):
        colour = cv2.bilateralFilter(colour, d=9, sigmaColor=75, sigmaSpace=75)
    hsv = cv2.cvtColor(colour, cv2.COLOR_RGB2HSV).astype(np.float32)
    hsv[:, :, 1] = np.clip(hsv[:, :, 1] * 1.5, 0, 255)
    hsv[:, :, 2] = np.clip(hsv[:, :, 2] * 1.08, 0, 255)
    colour = cv2.cvtColor(hsv.astype(np.uint8), cv2.COLOR_HSV2RGB)
    gray = cv2.cvtColor(big, cv2.COLOR_RGB2GRAY)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    gray = cv2.medianBlur(gray, 5)
    edges = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 17, 6,
    )
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2, 2))
    edges = cv2.morphologyEx(edges, cv2.MORPH_OPEN, kernel)
    edges_3ch = cv2.cvtColor(edges, cv2.COLOR_GRAY2RGB)
    cartoon = cv2.bitwise_and(colour, edges_3ch)
    return cv2.resize(cartoon, (w, h), interpolation=cv2.INTER_AREA)


# ── HF img2img generation ────────────────────────────────────────────

def generate_via_huggingface(source: Image.Image) -> Image.Image:
    """
    Send the pre-processed photo to Stable Diffusion img2img on the
    Hugging Face Inference API and return the cartoon PIL Image.
    """
    logger.info("Sending image to HuggingFace img2img (%s) …", HF_MODEL)
    result: Image.Image = hf_client.image_to_image(
        image=source,
        prompt=CARTOON_PROMPT,
        negative_prompt=NEGATIVE_PROMPT,
        model=HF_MODEL,
        strength=IMG2IMG_STRENGTH,
    )
    logger.info("HuggingFace generation complete.")
    return result


# ── Endpoints ─────────────────────────────────────────────────────────

@app.get("/health")
async def health_check():
    return {
        "status": "ok",
        "engine": "huggingface-sd-img2img",
        "model": HF_MODEL,
        "strength": IMG2IMG_STRENGTH,
    }


@app.post("/generate-avatar")
async def generate_avatar(file: UploadFile = File(...)):
    """
    Generate an AI cartoon avatar from an uploaded photo.
    Accepts: JPG, PNG, WebP, GIF (max 5 MB)
    Returns: PNG image (AI-illustrated cartoon, looks different from original)
    """
    if file.content_type not in ALLOWED_TYPES:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type '{file.content_type}'. Allowed: {', '.join(ALLOWED_TYPES)}",
        )

    contents = await file.read()
    if len(contents) > MAX_FILE_SIZE:
        raise HTTPException(
            status_code=400,
            detail=f"File too large. Maximum: 5 MB.",
        )
    if len(contents) == 0:
        raise HTTPException(status_code=400, detail="Empty file uploaded.")

    try:
        source = Image.open(io.BytesIO(contents)).convert("RGB")
        source = preprocess(source)

        # ── Primary: Hugging Face AI generation ──────────────────────
        try:
            result = generate_via_huggingface(source)
            engine_used = "huggingface-sd-img2img"
        except Exception as hf_err:
            logger.warning("HuggingFace API failed (%s) – falling back to OpenCV.", hf_err)
            arr = opencv_cartoon_fallback(np.array(source))
            result = Image.fromarray(arr)
            engine_used = "opencv-cartoon-fallback"

        # ── Encode and return ─────────────────────────────────────────
        output = io.BytesIO()
        result.save(output, format="PNG", optimize=True)
        output.seek(0)

        logger.info("Avatar generated successfully via %s", engine_used)

        return StreamingResponse(
            output,
            media_type="image/png",
            headers={
                "Content-Disposition": "inline; filename=avatar.png",
                "X-Avatar-Engine": engine_used,
            },
        )

    except HTTPException:
        raise
    except Exception:
        logger.exception("Avatar generation failed")
        raise HTTPException(status_code=500, detail="Avatar generation failed: internal error")
