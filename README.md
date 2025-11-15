<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:


This project is a Laravel application skeleton that provides an intermediary API to work with the Google Gemini generative API.

Endpoints added (api/v1/marketing/*):
- POST /api/v1/marketing/facebook — generate a Facebook post text using input prompt.
- POST /api/v1/marketing/instagram — generate an Instagram caption using input prompt.
- POST /api/v1/marketing/podcast — generate a podcast script/outline using input prompt.
- POST /api/v1/marketing/image — generate an image from prompt.
- POST /api/v1/marketing/generation/audio — generate audio from prompt (TTS) and save it on the server.
- GET  /api/v1/marketing/generation/audio/list — return a list of generated audios (up to 20 recent audio files).
- POST /api/v1/marketing/generation/audio/send — send/download a generated audio by ID (body: `{"id": "<audio-id>"}`).
- POST /api/v1/marketing/video — generate video script and visual guidance from prompt.

Configuration:
- Set GOOGLE_API_KEY in your .env or set config/generative.php to supply the API key.
 - Optionally set GEMINI_MODEL in your .env to select the default Gemini model used by the app (e.g. 'gemini-2.5-flash'). You can override per-request using the `model` parameter.
 - You can set either `GOOGLE_API_KEY` or `GEMINI_API_KEY` in your `.env` (the app will accept both); `GEMINI_API_KEY` is an alias for the same key used in some examples.

How requests work:
- Each text endpoint accepts a JSON body with property `prompt` (required). Optional properties like `tone` and `length` are also accepted.
- The service wraps the user prompt with a usage-specific wrapper before sending to Google Gemini, e.g., "Act as a professional social media content writer..."
- Responses return a JSON structure with `success`, `status` and `payload` containing the Gemini API response.

Example (Facebook):

POST /api/v1/marketing/facebook
{
	"prompt": "Write about a new vegan cafe opening in town",
	"tone": "friendly",
	"length": "short"
}

This will call the Google Gemini API on your behalf and return the generated text inside `payload`.

Direct Gemini curl example (use GEMINI_MODEL from env):
```
curl "https://generativelanguage.googleapis.com/v1beta/models/${GEMINI_MODEL}:generateContent" \
	-H "x-goog-api-key: $GOOGLE_API_KEY" \
	-H 'Content-Type: application/json' \
	-X POST \
	-d '{
		"contents": [
			{
				"parts": [
					{
						"text": "Explain how AI works in a few words"
					}
				]
			}
		]
	}'
```

Example of calling this app's endpoint and overriding the model in the request body:
```bash
curl -X POST "http://127.0.0.1:8000/api/v1/marketing/facebook" \
	-H "Content-Type: application/json" \
	-d '{
		"prompt": "Announce our new vegan coffee shop this weekend.",
		"model": "gemini-2.5-flash",
		"tone": "friendly",
		"length": "short"
	}'

Example TTS request for the audio endpoint (use the TTS model and select a voice):

```bash
curl "http://127.0.0.1:8000/api/v1/marketing/generation/audio" \
	-H "Content-Type: application/json" \
	-H "Accept: application/json" \
	-d '{
		"prompt": "Say cheerfully: Have a wonderful day!",
		"model": "gemini-2.5-flash-preview-tts",
		"voice": "Kore"
}'
```

If you'd like to run the Google endpoint directly (this is what the service does under the hood), you can use this template. The request returns inline base64 PCM which you can decode and convert to WAV with ffmpeg:

```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent" \
	-H "x-goog-api-key: $GEMINI_API_KEY" \
	-H "Content-Type: application/json" \
	-X POST \
	-d '{
				"contents": [{
					"parts":[{
						"text": "Say cheerfully: Have a wonderful day!"
					}]
				}],
				"generationConfig": {
					"responseModalities": ["AUDIO"],
					"speechConfig": {
						"voiceConfig": {
							"prebuiltVoiceConfig": {
								"voiceName": "Kore"
							}
						}
					}
				},
				"model": "gemini-2.5-flash-preview-tts"
		}' | jq -r '.candidates[0].content.parts[0].inlineData.data' | \
					base64 --decode > out.pcm

# Convert to WAV using ffmpeg: PCM signed 16-bit, 24000 Hz, mono
ffmpeg -f s16le -ar 24000 -ac 1 -i out.pcm out.wav
```
```

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
