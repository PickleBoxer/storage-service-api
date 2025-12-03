# Storage Service API

A Laravel 12-based REST API for managing image and video uploads with automatic thumbnail generation. Built for high availability with asynchronous processing to prevent API timeouts.

## Features

- 🔐 **Secure Authentication** - Laravel Sanctum token-based API authentication
- 📤 **Multi-Format Support** - Upload images (JPG, PNG, GIF, WebP) and videos (MP4, MOV, AVI)
- ⚡ **Async Processing** - Immediate HTTP 202 responses with background thumbnail generation
- 🖼️ **Automatic Thumbnails** - 200x200px thumbnails for all media types
- 📊 **Status Tracking** - Real-time processing status via dedicated endpoint
- 👤 **User Isolation** - Users can only access their own media
- 🎯 **Queue-Based Jobs** - Reliable background processing with retry mechanism
- 🧪 **Comprehensive Tests** - Full test coverage with Pest PHP

## Technology Stack

- **Backend:** PHP 8.2+, Laravel 12
- **Database:** SQLite (development), PostgreSQL/MySQL (production)
- **Queue:** Database driver (development), Redis (production recommended)
- **Authentication:** Laravel Sanctum
- **Image Processing:** Intervention Image Laravel
- **Video Processing:** PHP-FFmpeg
- **Testing:** Pest PHP 4.1

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- FFmpeg (for video thumbnail generation)

### Installation

```bash
# 1. Clone repository
git clone https://github.com/PickleBoxer/storage-service-api.git
cd storage-service-api

# 2. Run setup script (installs dependencies, runs migrations, builds assets)
composer setup

# 3. Install media processing packages
composer require intervention/image-laravel php-ffmpeg/php-ffmpeg

# 4. Create storage link
php artisan storage:link

# 5. Start queue worker (required for media processing)
php artisan queue:listen
```

**Note:** This project uses **Laravel Herd** for local development. The application is automatically available at `http://storage-service-api.test` (Herd runs nginx). You must run the queue worker separately (`php artisan queue:listen`) for background media processing.

### Development Commands

```bash
# Start queue worker (required for media processing)
php artisan queue:listen

# Watch logs in real-time
php artisan pail

# Start Vite dev server (if using frontend assets)
npm run dev

# Run tests
composer test

# Format code
./vendor/bin/pint
```

## API Documentation

**Interactive API documentation is available at:** [http://storage-service-api.test/docs/api](http://storage-service-api.test/docs/api)

### Quick Examples

```bash
# Register
curl -X POST http://storage-service-api.test/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://storage-service-api.test/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Get user
curl http://storage-service-api.test/api/user \
  -H "Authorization: Bearer {token}"

# Upload media
curl -X POST http://storage-service-api.test/api/media \
  -H "Authorization: Bearer {token}" \
  -F "file=@image.jpg" \
  -F "title=My Photo"

# List media
curl http://storage-service-api.test/api/media \
  -H "Authorization: Bearer {token}"

# Check status
curl http://storage-service-api.test/api/media/1/status \
  -H "Authorization: Bearer {token}"
```

## Async Processing Flow

```
1. Client uploads file → API stores file → Returns 202 Accepted immediately
2. Background job queued → Queue worker picks up job
3. Job generates thumbnail (image resize or video frame extraction)
4. Database updated with thumbnail path and "completed" status
5. Client polls status endpoint → Receives file URLs when ready
```

## Configuration

### File Size Limits

Edit `app/Http/Requests/StoreMediaRequest.php`:

```php
'file' => [
    'required',
    'file',
    'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi',
    'max:102400', // 100MB max (adjust as needed)
],
```

Also update `php.ini`:

```ini
upload_max_filesize = 100M
post_max_size = 100M
```

### Storage Configuration

Default: Public disk (`storage/app/public`)

For production S3 storage, update `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

### Queue Configuration

Development: Database queue (run `php artisan queue:listen`)

Production: Redis recommended for better performance

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

## Testing

```bash
# Run all tests
composer test

# Run specific test file
php artisan test --filter AuthenticationTest

# Run with coverage
./vendor/bin/pest --coverage
```

**Test Coverage:**
- Authentication endpoints (register, login)
- Media upload validation and file storage
- Background job processing and thumbnail generation
- Status endpoint and authorization
- Error handling and edge cases

## Troubleshooting

### Jobs Not Processing

**Solution:** Ensure queue worker is running:

```bash
php artisan queue:listen
```

### FFmpeg Not Found

**Solution:** Install FFmpeg and add to system PATH:

- **Windows:** Download from [ffmpeg.org](https://ffmpeg.org), add to PATH
- **macOS:** `brew install ffmpeg`
- **Linux:** `sudo apt install ffmpeg`

### Storage Link Issues

**Solution:** Create symbolic link (may require admin rights on Windows):

```bash
php artisan storage:link
```

### File Upload Errors

**Solution:** Check PHP upload limits in `php.ini`:

```ini
upload_max_filesize = 100M
post_max_size = 100M
```

## Security

- All upload endpoints require valid Sanctum API token
- Users can only access their own media files
- File validation prevents unauthorized file types
- Authorization checks prevent cross-user access
- Tokens don't expire by default (configurable)
