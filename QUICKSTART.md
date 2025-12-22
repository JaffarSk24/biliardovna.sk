# 🚀 Quick Start Guide

Get your booking system running in **15 minutes**!

## Step 1: Upload Files (5 min)

### Using FileZilla:

1. **Connect to your server**:
   - Host: `ftp.biliardovna.sk`
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21

2. **Upload all files** to `/www/` or `/public_html/`

3. **Set permissions** (right-click → File permissions):
   - `storage/` → 775
   - `logs/` → 775

## Step 2: Configure Database (3 min)

1. **Create database via phpMyAdmin**:
   - Login to your hosting control panel
   - Open phpMyAdmin
   - Create new database: `biliardovna_db`
   - Create user with all privileges

2. **Configure .env**:
   ```bash
   # Via FileZilla: Download .env.example, rename to .env
   
   # Edit these lines:
   DB_NAME=biliardovna_db
   DB_USER=your_db_user
   DB_PASS=your_db_password
   
   APP_URL=https://biliardovna.sk
   
   # Upload .env back to server
   ```

## Step 3: Install System (2 min)

**Option A - Via Browser**:
1. Visit: `https://biliardovna.sk/install.php`
2. Wait for success message
3. Delete or rename `install.php` for security

**Option B - Via SSH** (if available):
```bash
cd /www
php install.php
rm install.php  # Or rename
```

## Step 4: Test & Secure (5 min)

1. **Test public booking**:
   - Visit: https://biliardovna.sk
   - Try creating a booking
   - Check all languages work

2. **Test admin panel**:
   - Visit: https://biliardovna.sk/admin/login
   - Login: `admin` / `password`
   - See your test booking

3. **⚠️ CHANGE PASSWORD IMMEDIATELY**:
   ```bash
   # Generate new hash:
   php -r "echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);"
   
   # Copy the output
   # Update in phpMyAdmin:
   # Table: admin_users
   # Field: password_hash
   ```

## Done! 🎉

Your booking system is now **LIVE**!

---

## Optional: Quick Integrations (30 min)

### Telegram Notifications (10 min)

1. Message `@BotFather` on Telegram
2. Send: `/newbot` and follow prompts
3. Get your bot token
4. Message your bot
5. Visit: `https://api.telegram.org/bot{TOKEN}/getUpdates`
6. Copy chat_id from response
7. Add to `.env`:
   ```ini
   TELEGRAM_BOT_TOKEN=your_token_here
   TELEGRAM_CHAT_ID=your_chat_id_here
   ```
8. Uncomment code in `src/Services/NotificationService.php` (line ~50)
9. Test with a booking!

### Email Notifications (15 min)

1. Sign up at https://mailgun.com
2. Add domain: `mg.biliardovna.sk`
3. Configure DNS records (via hosting control panel)
4. Get API key from Mailgun dashboard
5. Add to `.env`:
   ```ini
   MAILGUN_DOMAIN=mg.biliardovna.sk
   MAILGUN_API_KEY=your_api_key_here
   ```
6. Uncomment code in `src/Services/NotificationService.php` (line ~80)
7. Test with a booking that includes email!

### Analytics (5 min)

1. Create GA4 property at https://analytics.google.com
2. Get Measurement ID (G-XXXXXXXXXX)
3. Add to `.env`:
   ```ini
   GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
   ```
4. Add tracking code to `src/Views/base.twig` before `</head>`:
   ```html
   <script async src="https://www.googletagmanager.com/gtag/js?id={{ GA_ID }}"></script>
   <script>
     window.dataLayer = window.dataLayer || [];
     function gtag(){dataLayer.push(arguments);}
     gtag('js', new Date());
     gtag('config', '{{ GA_ID }}');
   </script>
   ```

---

## Need More Help?

- **Full Documentation**: See `README.md`
- **Deployment Guide**: See `DEPLOYMENT.md`
- **Integration Guide**: See `INTEGRATIONS.md`
- **Status & Checklist**: See `STATUS.md`

---

## Quick Reference

### Default Admin Access
- URL: https://biliardovna.sk/admin/login
- Username: `admin`
- Password: `password` (CHANGE THIS!)

### Important Files
- Configuration: `.env`
- Database: `install.php` (run once, then remove)
- Public page: `public/index.php`
- Admin panel: `src/Controllers/AdminController.php`

### Directory Structure
```
public/          ← Point domain here
assets/          ← CSS, JS, images
src/             ← PHP code
storage/         ← Cache, uploads (must be writable)
logs/            ← Error logs (must be writable)
```

### Common Commands

**Generate password hash**:
```bash
php -r "echo password_hash('newpassword', PASSWORD_DEFAULT);"
```

**Test database connection**:
```bash
php -r "require 'vendor/autoload.php'; use App\Database\Database; Database::getInstance();"
```

**Clear cache**:
```bash
rm -rf storage/cache/*
```

### Support

If you encounter issues:
1. Check `logs/` directory
2. Set `APP_DEBUG=true` in `.env`
3. Check browser console for JavaScript errors
4. Verify file permissions (775 for storage/logs)
5. Check PHP version is 8.2+

---

**That's it! You're ready to accept bookings! 🎊**
