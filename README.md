# 🚀 BackboneJobs - Quick Installation Guide

## Prerequisites Check
Before starting, ensure you have:
- ✅ PHP 7.4 or higher installed
- ✅ MySQL 5.7+ or MariaDB 10.3+
- ✅ Apache/Nginx web server
- ✅ phpMyAdmin (optional but recommended)

---

## 📦 Step-by-Step Installation

### 1️⃣ Extract Files
Extract the BackboneJobs folder to your web server directory:

**XAMPP (Windows):**
```
C:\xampp\htdocs\bbjobs\
```

**WAMP (Windows):**
```
C:\wamp\www\bbjobs\
```

**Linux/Mac:**
```
/var/www/html/bbjobs/
```

### 2️⃣ Create Database

**Option A: Using phpMyAdmin (Recommended)**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" in the left sidebar
3. Database name: `backbonejobs`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

**Option B: Using MySQL Command Line**
```sql
CREATE DATABASE backbonejobs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3️⃣ Import Database Schema

**Using phpMyAdmin:**
1. Click on `backbonejobs` database
2. Go to "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"
5. Wait for success message

**Using MySQL Command Line:**
```bash
mysql -u root -p backbonejobs < database/schema.sql
```

### 4️⃣ Import Sample Data (Optional but Recommended)

This adds 15 sample jobs, 5 employers, and 5 job seekers for testing:

**Using phpMyAdmin:**
1. Still in `backbonejobs` database
2. Go to "Import" tab
3. Choose file: `database/sample_data.sql`
4. Click "Go"

**Using MySQL Command Line:**
```bash
mysql -u root -p backbonejobs < database/sample_data.sql
```

### 5️⃣ Configure Database Connection

Open `api/config.php` and update these lines:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password (blank for XAMPP)
define('DB_NAME', 'backbonejobs');
```

Update the site URL:
```php
define('SITE_URL', 'http://localhost/bbjobs/');
```

### 6️⃣ Set Directory Permissions

**Windows:**
Create the uploads folder in your project root:
```
bbjobs\uploads\
```

**Linux/Mac:**
```bash
cd /var/www/html/bbjobs
mkdir -p uploads/{resumes,photos,documents}
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

### 7️⃣ Start Your Server

**XAMPP:**
1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL

**WAMP:**
1. Start WAMP
2. Wait for green icon

**Linux/Mac:**
```bash
sudo service apache2 start
sudo service mysql start
```

### 8️⃣ Access the Application

Open your browser and go to:
```
http://localhost/bbjobs/
```

---

## 🔑 Default Login Credentials

### Admin Panel
- **URL:** `http://localhost/bbjobs/admin-login.html`
- **Username:** `admin`
- **Password:** `admin123`

⚠️ **IMPORTANT:** Change this password immediately after first login!

### Sample Employers (If you imported sample_data.sql)
All sample employers use password: `password123`

- **Email:** `hr@prestigegroup.com`
- **Email:** `careers@dlf.com`
- **Email:** `jobs@godrej.com`
- **Email:** `hr@brigade.com`
- **Email:** `recruitment@phoenix.com`

### Sample Job Seekers (If you imported sample_data.sql)
All sample users use password: `password123`

- **Email:** `ramesh.verma@email.com`
- **Email:** `suresh.y@email.com`
- **Email:** `kavita.d@email.com`
- **Email:** `mohan.k@email.com`
- **Email:** `anjali.s@email.com`

---

## ✅ Verify Installation

1. **Homepage:** Should display with hero section and features
2. **Browse Jobs:** Should show 15 sample jobs (if sample data imported)
3. **Register:** Should allow new user registration
4. **Login:** Should work with sample credentials
5. **Apply for Job:** Should work after login
6. **Admin Panel:** Should be accessible

---

## 🐛 Troubleshooting

### Database Connection Error
**Error:** "Database connection failed"
**Solution:**
- Check MySQL is running
- Verify credentials in `api/config.php`
- Ensure database `backbonejobs` exists

### Blank Page / PHP Errors
**Solution:**
- Enable error display temporarily:
  - Open `php.ini`
  - Set `display_errors = On`
  - Restart Apache

### File Upload Not Working
**Solution:**
- Check `uploads/` folder exists
- Verify folder permissions (755 on Linux)
- Check PHP `upload_max_filesize` in php.ini

### No Jobs Showing
**Solution:**
- Import sample data: `database/sample_data.sql`
- Or register as employer and post jobs manually
- Check browser console for JavaScript errors

### CSS/JS Not Loading
**Solution:**
- Clear browser cache (Ctrl+Shift+R)
- Check file paths in HTML files
- Ensure `css/` and `js/` folders exist

---

## 📁 File Structure Checklist

Ensure you have all these files:

```
bbjobs/
├── index.html
├── jobs.html
├── job-details.html
├── apply.html
├── login.html
├── register.html
├── dashboard.html
├── css/
│   ├── style.css
│   ├── auth.css
│   ├── dashboard.css
│   ├── jobs.css
│   └── job-details.css
├── js/
│   ├── main.js
│   ├── auth.js
│   ├── dashboard.js
│   ├── jobs.js
│   └── job-details.js
├── api/
│   ├── config.php
│   ├── jobs.php
│   ├── stats.php
│   ├── applications.php
│   ├── saved-jobs.php
│   └── auth/
│       ├── login.php
│       └── register.php
├── database/
│   ├── schema.sql
│   └── sample_data.sql
└── uploads/
    ├── resumes/
    ├── photos/
    └── documents/
```

---

## 🎯 Next Steps

1. ✅ Change default admin password
2. ✅ Test job posting as employer
3. ✅ Test job application as job seeker
4. ✅ Customize colors in `css/style.css`
5. ✅ Update contact information in footer
6. ✅ Add your company logo
7. ✅ Configure email settings (optional)

---

## 🆘 Need Help?

**Common Issues:**
- Check browser console (F12) for JavaScript errors
- Check PHP error logs in XAMPP/WAMP logs folder
- Verify all API files have `.php` extension
- Ensure MySQL service is running

**Still Stuck?**
- Review error messages carefully
- Check that all files were extracted properly
- Verify database connection settings
- Make sure you imported both schema.sql and sample_data.sql

---

## 🎉 Success!

If you can:
- ✅ See the homepage with jobs
- ✅ Register a new account
- ✅ Login successfully
- ✅ Apply for a job

**Congratulations!** BackboneJobs is successfully installed and running!

---

**Built with ❤️ for support staff across India**
