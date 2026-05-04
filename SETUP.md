# GeoSand Backend Setup

## Authentication Configuration

This Laravel backend uses **Laravel Sanctum with session-based authentication** (SPA authentication) for secure communication with the React frontend.

### Key Features

1. **Session-based Authentication**: Uses cookies instead of tokens for better security with SPA
2. **CORS Configuration**: Properly configured for React frontend communication
3. **Consistent API Responses**: All API endpoints return standardized JSON responses
4. **Comprehensive Error Handling**: Custom exception handlers for API routes

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
# Application
APP_NAME=GeoSand
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_DATABASE=geosand
DB_USERNAME=root
DB_PASSWORD=

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=1440

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173,127.0.0.1:3000,127.0.0.1:5173
SANCTUM_EXPIRATION=1440

# Frontend URL for CORS
FRONTEND_URL=http://localhost:5173
```

### Setup Steps

1. Install dependencies:
```bash
composer install
```

2. Generate application key:
```bash
php artisan key:generate
```

3. Run migrations:
```bash
php artisan migrate
```

4. Start the development server:
```bash
php artisan serve
```

## API Response Structure

All API responses follow this structure:

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

## Authentication Endpoints

### Login
- **POST** `/api/login`
- Body: `{ "email": "user@example.com", "password": "password" }`
- Returns: User data with session cookie

### Logout
- **POST** `/api/logout`
- Requires: Authentication
- Returns: Success message

### Get User
- **GET** `/api/user`
- Requires: Authentication
- Returns: Current user data

## Frontend Integration

### CSRF Cookie

Before making authenticated requests, the frontend must first get a CSRF cookie:

```javascript
// Get CSRF cookie
await axios.get('http://localhost:8000/sanctum/csrf-cookie', {
  withCredentials: true
});

// Then make authenticated requests
await axios.post('http://localhost:8000/api/login', {
  email: 'user@example.com',
  password: 'password'
}, {
  withCredentials: true
});
```

### Axios Configuration

Configure Axios in your React app:

```javascript
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://localhost:8000';
```

## Testing

Run all tests:
```bash
php artisan test
```

Run specific test:
```bash
php artisan test --filter=PasswordHashingPropertyTest
```

### Property-Based Tests

The project includes property-based tests using Eris:

1. **Password Hashing Property Test**: Verifies all passwords are hashed, not stored as plaintext
2. **API JSON Response Property Test**: Ensures all API responses return valid JSON with correct Content-Type

## CORS Configuration

CORS is configured in `config/cors.php` to allow:
- Frontend URLs (localhost:3000, localhost:5173)
- Credentials (cookies)
- All standard HTTP methods
- All headers

## Security Features

1. **Password Hashing**: Automatic bcrypt hashing via Laravel's User model
2. **Session Security**: Secure session cookies with proper expiration
3. **CSRF Protection**: Built-in CSRF protection for state-changing operations
4. **Role-Based Access Control**: Ready for implementation in future tasks
5. **Input Validation**: All requests validated before processing

## Next Steps

- Task 2: Create database migrations and models
- Task 3: Implement complete authentication system with role-based access control
- Task 4+: Implement feature-specific modules (users, quarries, extractions, etc.)
