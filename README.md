# Generative API

This microservice provides endpoints to generate content using Google Gemini AI, including text for social media posts, images, audio, and video scripts. It integrates with the Google Generative Language API.

Base route prefix: `/api/v1/marketing/generation`

## Environment Variables

- `GOOGLE_API_KEY` or `GEMINI_API_KEY`: API key for Google Gemini.
- `GEMINI_MODEL` (optional): Default model to use (e.g., `gemini-1.5-flash` for image generation).
- Other config via `config/generative.php` if needed.

## Endpoints

### Generate Facebook Post

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/facebook`
- **Parameters** (JSON body):
  - `prompt` (required if no `contents`, string): Text prompt for generation.
  - `contents` (optional, array): Pre-shaped contents for Gemini API.
  - `tone` (optional, string): Desired tone (e.g., "professional").
  - `length` (optional, string): "short", "medium", or "long".
  - `model` (optional, string): Model to use.
- **Purpose**: Generates text content for a Facebook post.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/facebook" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "Create a post about new product launch", "tone": "excited", "length": "medium"}'
  ```

### Generate Instagram Caption

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/instagram`
- **Parameters** (JSON body):
  - `prompt` (required if no `contents`, string): Text prompt for generation.
  - `contents` (optional, array): Pre-shaped contents for Gemini API.
  - `tone` (optional, string): Desired tone.
  - `length` (optional, string): "short", "medium", or "long".
  - `model` (optional, string): Model to use.
- **Purpose**: Generates a caption for an Instagram post.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/instagram" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "Caption for a beach photo", "tone": "casual"}'
  ```

### Generate Podcast Script

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/podcast`
- **Parameters** (JSON body):
  - `prompt` (required if no `contents`, string): Text prompt for generation.
  - `contents` (optional, array): Pre-shaped contents for Gemini API.
  - `tone` (optional, string): Desired tone.
  - `length` (optional, string): "short", "medium", or "long".
  - `model` (optional, string): Model to use.
- **Purpose**: Generates a podcast script or outline.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/podcast" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "Script for a tech podcast episode", "length": "long"}'
  ```

### Generate Image

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/image`
- **Parameters** (JSON body):
  - `prompt` (required, string): Description of the image to generate (can be in any language, will be translated to English automatically).
  - `sampleCount` / `numberOfImages` (optional, integer, 1-4): Number of images to generate. If omitted the service uses Imagen's default (4). The value will be clamped to the 1..4 range.
  - `aspectRatio` (optional, string): Aspect ratio, e.g., "1:1", "3:4", "4:3", "9:16", "16:9" (default "1:1").
  - `imageSize` (optional, string): Image size, "1K" or "2K" (only for Standard and Ultra models).
  - `personGeneration` (optional, string): "dont_allow", "allow_adult", "allow_all" (default "allow_adult").
  - `imageSize` (optional, string): Image size, "1K" or "2K" (only used with supported Imagen models/tiers). If an unsupported value is provided it will be ignored.

- **Storage & metadata**: Generated images are converted to PNG (when possible) and saved under the service storage path:

  - Files: storage/app/images/<id>.png
  - Metadata: storage/app/images/metadata.json

  Metadata entries include: id, filename, path, original_prompt, model, size (bytes), created_at. The service keeps the 50 most recent images and will delete older files automatically.

- **Purpose**: Generates images using Google's Imagen model, converts them to PNG for consistent storage, saves them to the server, and exposes list/download endpoints.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/image" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "Un atardecer sobre las montañas", "sampleCount": 2, "aspectRatio": "16:9"}'
  ```

### Image listing & download endpoints

- **List images**

  - **Method**: GET
  - **URL**: `/api/v1/marketing/generation/image/list`
  - **Purpose**: Returns up to the latest 50 saved image metadata objects.

  Example:

  ```bash
  curl -X GET "http://localhost:8002/api/v1/marketing/generation/image/list"
  ```

- **Download image**

  - **Method**: GET
  - **URL**: `/api/v1/marketing/generation/image/{id}`
  - **Purpose**: Downloads the PNG file for the requested `id`. The controller sets appropriate headers (Content-Type: image/png and Content-Disposition attachment by default).

  Example:

  ```bash
  curl -X GET "http://localhost:8002/api/v1/marketing/generation/image/QvRzquExD625" -o image.png
  ```

- **Send / download via POST**

  - **Method**: POST
  - **URL**: `/api/v1/marketing/generation/image/send`
  - **Parameters** (JSON body): `id` (string) — returns the image file or metadata for the specified id

  Example:

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/image/send" \
    -H "Content-Type: application/json" \
    -d '{"id":"QvRzquExD625"}' --output image.png
  ```

### Generate Audio

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/audio`
- **Parameters** (JSON body):
  - `prompt` (required, string): Text to convert to speech.
  - `format` (optional, string): Audio format.
  - `size` (optional, string): Audio size/length.
  - `model` (optional, string): Model to use.
- **Purpose**: Generates audio (TTS) from the prompt and saves it on the server.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/audio" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "Hello, this is a test audio"}'
  ```

### List Audios

- **Method**: GET
- **URL**: `/api/v1/marketing/generation/audio/list`
- **Parameters**: None.
- **Purpose**: Returns a list of up to 20 recent generated audio files.

  ```bash
  curl -X GET "http://localhost:8002/api/v1/marketing/generation/audio/list"
  ```

### Send Audio

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/audio/send`
- **Parameters** (JSON body):
  - `id` (required, string): ID of the audio file.
- **Purpose**: Downloads the specified audio file.

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/audio/send" \
    -H "Content-Type: application/json" \
    -d '{"id": "audio-123"}'
  ```

### Download Audio

- **Method**: GET
- **URL**: `/api/v1/marketing/generation/audio/{id}`
- **Parameters**:
  - `id` (path, string): ID of the audio file.
- **Purpose**: Downloads the specified audio file.

  ```bash
  curl -X GET "http://localhost:8002/api/v1/marketing/generation/audio/audio-123"
  ```

### Generate Video

- **Method**: POST
- **URL**: `/api/v1/marketing/generation/video`
- **Parameters** (JSON body):
  - `prompt` (required, string): Description for video script and guidance.
  - `format` (optional, string): Video format.
  - `size` (optional, string): Video size.
  - `model` (optional, string): Model to use.
- **Purpose**: Generates a video script and visual guidance. (Note: To be corrected with official API.)

  ```bash
  curl -X POST "http://localhost:8002/api/v1/marketing/generation/video" \
    -H "Content-Type: application/json" \
    -d '{"prompt": "A short video about AI"}'
  ```

## Notes

- All text generation endpoints support either `prompt` or `contents` for flexibility.
- Audio files are saved on the server and can be listed/downloaded.
- Image generation uses Google's Imagen model; prompts are automatically translated to English using Gemini for compatibility.
- If you encounter errors like "This model only supports text output", ensure your API key supports Imagen and that the model is correctly configured.
- Requires valid Google API key.
- Some features may have additional requirements or limitations.
