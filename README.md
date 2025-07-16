# E-Commerce Fresh Farm

A modern, user-friendly e-commerce platform for fresh farm produce. Supports both customer and admin roles, with features for product browsing, cart, checkout, order management, and an admin dashboard.

---

## Features

- User registration, login, and profile management
- Browse products by category (Fruits, Vegetables, Dairy, Meat, Bakery, Organic)
- Shopping cart and secure checkout
- Order history and tracking
- Address and payment method management
- Favorites/wishlist
- Admin dashboard for managing products, orders, users, and transactions
- Email notifications (via PHPMailer)
- Responsive, modern UI/UX

---

## Project Structure

```
E-Commerce-Fresh-Farm/
├── assets/              # Images (products, logos, payment icons)
├── config/              # Database setup scripts
├── css/                 # Stylesheets for various pages
├── dashboard/           # Admin dashboard and management scripts
├── includes/            # Helper scripts (mailer, location data)
├── js/                  # JavaScript for dynamic UI
├── uploads/             # User-uploaded profile images
├── user-dashboard/      # User dashboard, cart, category, settings
├── *.php                # Main PHP scripts (login, signup, etc.)
├── *.sql                # SQL files for database setup
├── composer.json        # PHP dependencies (PHPMailer)
└── README.md            # Project documentation
```

---

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB
- Composer (for PHP dependencies)
- Web server (e.g., Apache, Nginx)

---

## Step-by-Step Setup

### 1. **Clone the Repository**
```bash
git clone <your-repo-url>
cd E-Commerce-Fresh-Farm
```

### 2. **Install PHP Dependencies**
```bash
composer install
```

### 3. **Database Setup**
- **Option A: Import SQL File**
  1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or CLI).
  2. Import `fresh_farm.sql` (recommended) or `database.sql` to create all tables and sample data.

- **Option B: Run Setup Script**
  1. Edit `config/setup_database.php` with your MySQL credentials if needed.
  2. Run the script in your browser or via CLI:
     - Browser: `http://localhost/E-Commerce-Fresh-Farm/config/setup_database.php`
     - CLI: `php config/setup_database.php`

### 4. **Configure Database Connection**
- Edit `connect.php` if your MySQL credentials or database name differ from the defaults:
  ```php
  $host = 'localhost';
  $dbname = 'fresh_farm';
  $username = 'root';
  $password = '';
  ```

### 5. **Set Up File Permissions**
- Ensure the `uploads/` directory is writable for profile image uploads.
- Ensure the web server can write to any directory where users upload files.

### 6. **Configure Web Server**
- Point your web server's document root to the project directory.
- For Apache, enable `mod_rewrite` if you want pretty URLs (optional).

### 7. **Access the Application**
- Open your browser and go to `http://localhost/E-Commerce-Fresh-Farm/`
- Register as a new user or log in as admin (see below).

---

## Default Admin Account
- **Username:** admin
- **Email:** admin@freshfarm.com
- **Password:** Admin@123

(You can change this in the `admins` table after setup.)

---

## Useful Scripts & Files
- `config/database.sql` / `fresh_farm.sql`: Database schema and sample data
- `config/setup_database.php`: Automated database setup
- `composer.json`: PHP dependencies (PHPMailer)
- `connect.php`: Database connection settings

---

## Customization & Tips
- **Product Images:** Add new images to `assets/products/`.
- **Email Sending:** Configure PHPMailer in `includes/mailer.php` for real email sending (SMTP settings).
- **Styling:** Customize CSS in the `css/` directory.
- **Add More Categories:** Update the database and category PHP files in `user-dashboard/category/php/`.

---

## Troubleshooting
- **Database Errors:** Double-check your credentials in `connect.php` and that the database is imported.
- **File Upload Issues:** Ensure `uploads/` is writable by the web server.
- **Email Issues:** Update SMTP settings in `includes/mailer.php`.

---

## License
This project is for educational and demonstration purposes. Please review and adapt for production use.

---

## Credits
- Built with PHP, MySQL, and PHPMailer
- Icons by FontAwesome
- Images and design assets as credited in the `assets/` folder