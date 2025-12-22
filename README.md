# Biliardovna.sk Booking System

A custom booking management system for Biliardovna.sk, built with PHP 8.2, Twig template engine, and MySQL.

## 🏗 Project Structure

The project follows a standard MVC architecture with a secure public entry point.

```
biliardovna-booking/
├── public/                 # Web server document root
│   ├── index.php           # Application entry point
│   └── assets/             # CSS, JS, Images
├── src/                    # Application source code
│   ├── Controllers/        # Request handlers (Admin, Booking, Page, etc.)
│   ├── Models/             # Database models
│   ├── Repositories/       # Data access layer (ContentRepository)
│   ├── Services/           # Business logic services
│   └── routes.php          # Route definitions
├── templates/              # Twig templates (.twig)
├── config/                 # Configuration files
├── scripts/                # Utility scripts (install, verify)
└── vendor/                 # Composer dependencies
```

## 🚀 Installation & Setup

### Prerequisites
- PHP >= 8.2
- MySQL / MariaDB
- Composer

### 1. Setup Environment
Copy the example environment file and configure your database credentials:
```bash
cp .env.example .env
# Edit .env and set DB_HOST, DB_NAME, DB_USER, DB_PASS
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Initialize Database
Run the installation script to create tables and seed initial data:
```bash
php scripts/install.php
```

### 4. Verify Installation
Run the verification script to ensure everything is configured correctly:
```bash
php scripts/verify.php
```

## 🛠 Usage

- **Public Access**: Point your web server (Nginx/Apache) to the `public/` directory.
- **Admin Panel**: Accessible at `/admin/login`. Default credentials are provided by the install script (change immediately!).

## ⚙️ Features

- **Booking System**: Check availability, calculate prices dynamicall based on time/day, and manage reservations.
- **Admin Dashboard**: Manage bookings, pricing, holidays, and promo codes.
- **Webhook Integration**: Telegram webhook handler for booking notifications (configured in `src/Controllers/TelegramController.php`).
- **Multilingual**: Support for multiple languages (SK, EN, etc.).

## 🔒 Security

- **Public Entry Point**: Application logic is outside the web root.
- **Environment config**: Sensitive credentials stored in `.env`.
- **Session capabilities**: Secure session handling.

## 📄 License
Proprietary software for Biliardovna.sk.
