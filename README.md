# Invoice Management System

A modern, mobile-friendly invoice management application with OTP authentication for creating, editing, viewing, and managing invoices. Perfect for small businesses and freelancers.

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🔐 **OTP Login** | Secure one-time password authentication |
| 📝 **Create Invoices** | Create new invoices with customer details and line items |
| ✏️ **Edit Invoices** | Modify existing invoices and update details |
| 👁️ **View Invoices** | View detailed invoice information |
| 🗑️ **Delete Invoices** | Remove unwanted invoices |
| 📄 **Export to PDF** | Generate and download PDF versions |
| 🔗 **Share Invoices** | Create shareable public links |
| 📱 **Responsive Design** | Works perfectly on phones, tablets, and desktops |
| 🔢 **Auto Numbering** | Automatic invoice number generation (INV-2026-00001) |
| 💰 **Flexible Discounts** | Item-level and invoice-level discounts |
| 📊 **Tax Calculation** | Automatic VAT/tax calculation (15%) |

## ✅ Authentication & Testing

**OTP (One-Time Password) Login System**
- Login via OTP code sent to email
- Test credentials available for all environments (localhost and deployed):
  - **Email**: `test@invoyce.com`
  - **OTP Code**: `123456`
- On production (cPanel), OTP codes are emailed via PHP `mail()` function
- On localhost, OTP code is shown in debug message for convenience

## 📋 Requirements

- **PHP** 7.4 or higher with `mail()` support (for production OTP emails)
- **MySQL** 5.7 or higher
- **Web Server** Apache, Nginx, or built-in PHP server
- **Modern Browser** (Chrome, Firefox, Safari, Edge)

## 🚀 Quick Start

### 1. Setup Database

Create the database and run all SQL to create tables:


```sql
CREATE DATABASE IF NOT EXISTS invoice_app;
USE invoice_app;

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(20),
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(20) UNIQUE,
  customer_id INT NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_percent DECIMAL(5,2) DEFAULT 0.00,
  discount_amount DECIMAL(10,2) DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('draft', 'sent', 'paid', 'overdue') DEFAULT 'draft',
  share_token VARCHAR(64) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_customer (customer_id),
  INDEX idx_status (status),
  INDEX idx_date (invoice_date),
  INDEX idx_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_percent DECIMAL(5,2) DEFAULT 0.00,
  discount_amount DECIMAL(10,2) DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  code VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Configure Database

Edit `config/db.php`:

```php
<?php
$host = "localhost";
$db   = "invoice_app";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);
```

### 4. Run the Application

Using PHP's built-in server:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/login.html` in your browser to login.

Use test credentials:
- **Email**: `test@invoyce.com`
- **OTP**: `123456`

## 🔐 OTP Login Setup

The application uses OTP (One-Time Password) authentication. Users login with their email and receive a code.

### Test on Localhost

1. Open `http://localhost:8000/login.html`
2. Enter `test@invoyce.com`
3. Click "Request OTP"
4. A debug message shows the code: `123456`
5. Enter the code and click "Verify OTP"
6. You'll be logged in and redirected to the dashboard

### Test on Deployed Server (cPanel, etc.)

1. Open `login.html` on your domain
2. Enter `test@invoyce.com`
3. Click "Request OTP"
4. Check your email for the OTP code (uses server's `mail()` function)
5. Enter the code and verify
6. Access your dashboard

### Production Use (Real Users)

TODO

## 📁 Project Structure

```
invoyce/
├── login.html              # OTP Login page
├── index.html              # Dashboard - List all invoices (protected)
├── create_invoice.html     # Create new invoice (protected)
├── view_invoice.html       # View invoice details (protected)
├── edit_invoice.html       # Edit existing invoice (protected)
├── config/
│   └── db.php             # Database configuration
├── api/
│   ├── auth.php           # OTP authentication (request & verify)
│   ├── invoices.php       # Main invoice API (create, read, update, delete)
│   ├── customers.php      # Customer management
│   ├── invoices_list.php  # List all invoices
│   ├── delete_invoice.php # Delete invoice endpoint
│   ├── export_pdf.php     # PDF export
│   └── share_invoice.php  # Public invoice view
├── assets/
│   ├── styles.css         # Main stylesheet (responsive, modern design)
│   └── app.js             # Frontend logic (auth guard, invoice forms)
├── sql/
│   ├── add_invoice_number.sql  # Add invoice_number column migration
│   └── add_auth_tables.sql     # Create users & otp_codes tables
├── tools/
│   ├── extract_pdf.py     # PDF extraction tool (internal)
│   └── extract_images.py  # Image extraction tool (internal)
└── README.md              # This file - setup & usage guide
```

## 🎯 Usage

### 1. Login
1. Go to `/login.html`
2. Enter email (use `test@invoyce.com` for testing)
3. Click "Request OTP"
4. Check email (or see debug code on localhost)
5. Enter OTP code and verify
6. You're logged in and redirected to dashboard

### 2. Creating an Invoice
1. Click "Create Invoice" on dashboard or nav
2. Select or add a customer
3. Set invoice and due dates
4. Add line items with descriptions, quantities, and prices
5. Set item-level or invoice-level discounts
6. Review totals (auto-calculated with 15% VAT)
7. Save invoice

### 3. Editing an Invoice
1. Go to dashboard and click "Edit" on any invoice
2. Modify customer, dates, items, or discounts
3. Totals update automatically
4. Save changes

### 4. Viewing an Invoice
1. Click "View" on the dashboard
2. See full details, customer info, and line items
3. Options to edit, delete, export PDF, or generate share link

### 5. Sharing an Invoice
1. Open invoice and click "Get Share Link"
2. Share the URL with customer
3. Customer can view invoice without login (public link)

### 6. Logout
1. Click your email in the top-right nav
2. Click "Logout"
3. You're logged out and redirected to login page

## 🔧 API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| POST | `/api/invoices.php?action=create_invoice` | Create invoice |
| GET | `/api/invoices.php?action=get_invoice&id=1` | Get invoice details |
| POST | `/api/invoices.php?action=update_invoice` | Update invoice |
| POST | `/api/invoices.php?action=delete_invoice` | Delete invoice |
| GET | `/api/invoices_list.php` | List all invoices |
| GET/POST | `/api/customers.php` | Get/create customers |

## 📱 Mobile Features

✅ Fully responsive design for all devices
✅ Touch-friendly buttons and inputs
✅ Optimized form layouts for mobile
✅ Hidden columns on small screens
✅ 16px font size (prevents iOS zoom)
✅ 48px minimum button height

## 🐛 Troubleshooting

### Login Issues

**"OTP sent to email" but I don't receive it**
- ✓ On localhost: code is shown in debug alert (not emailed)
- ✓ On production: ensure `mail()` is configured on your server
- ✓ Check email spam folder
- ✓ Verify cPanel mail settings if hosted

**Test credentials not working**
- ✓ Ensure `sql/add_auth_tables.sql` has been run
- ✓ Try email: `test@invoyce.com` and OTP: `123456`
- ✓ Reload the page if it seems stuck

### Database Connection Error
- ✓ Check `config/db.php` credentials match your MySQL setup
- ✓ Verify MySQL is running (`mysql -u root` should connect)
- ✓ Confirm database exists: `mysql -u root -e "SHOW DATABASES"`
- ✓ Ensure all migrations have been run

### Missing Tables
Run all migrations:
```bash
mysql -u root invoice_app < sql/add_invoice_number.sql
mysql -u root invoice_app < sql/add_auth_tables.sql
```

### Page Redirects to Login
- ✓ You must login first (OTP required)
- ✓ All pages except `login.html` require authentication
- ✓ Clear browser storage: `localStorage.removeItem('invoyce_user')`

### Page Not Found (404)
- ✓ Ensure all files are in correct directories
- ✓ Check web server is serving the app root
- ✓ Verify file permissions (644 for files, 755 for folders)

### Buttons Not Working
- ✓ Check browser console for JavaScript errors (F12 → Console)
- ✓ Ensure `assets/app.js` is loading
- ✓ Clear browser cache (Ctrl+Shift+Delete)
- ✓ Try incognito/private browsing mode

## ⚙️ Configuration

### Changing Tax Rate

Edit `assets/app.js`, find the `recalc()` function:

```javascript
const tax = subtotal * 0.15;  // Change 0.15 to desired rate (e.g., 0.10 for 10%)
```

### Changing Currency Symbol

Edit currency symbol in:
- `assets/app.js` (search for `R` in display functions)
- `view_invoice.html` (search for `R` in totals display)

Replace `R` with your currency (e.g., `$`, `€`, `£`, `¥`)

### Changing Email For OTP Sender

Edit `api/auth.php`, find the email sending section:

```php
$headers = "From: admin@dreyerventures\r\n" .
           "Reply-To: admin@dreyerventures\r\n";
```

Replace with your email address.

### Customizing OTP Expiry Time

Edit `api/auth.php`, find OTP generation:

```php
$expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
// Change '+10 minutes' to desired timeframe
```

## 🔐 Security Notes

⚠️ **Important**: Production deployment checklist:

### Authentication & Access
- ✅ OTP login system implemented
- ✅ Session storage via localStorage (can be improved to server sessions)
- ✓ Add authorization checks (users can only see their own invoices)
- ✓ Implement server-side session validation

### Network & Transport
- ✓ Use HTTPS (SSL certificate required)
- ✓ Update domain in `api/auth.php` for email sender
- ✓ Configure proper mail() or SMTP for email delivery
- ✓ Add CORS headers if API is accessed from different domain

### Data & Validation
- ✅ All inputs are validated and sanitized
- ✅ PDO prepared statements prevent SQL injection
- ✓ Add rate limiting on OTP requests (prevent brute force)
- ✓ Add audit logging for invoice changes
- ✓ Implement IP-based rate limiting

### Environment
- ✓ Set `display_errors = Off` in production `php.ini`
- ✓ Store database credentials securely (not in repo)
- ✓ Use environment variables for sensitive config
- ✓ Keep PHP and MySQL updated
- ✓ Regular database backups

## 📄 License

MIT License - Free to use and modify for personal or commercial projects.

## 💡 Future Enhancements

- [ ] Payment gateway integration (Stripe, PayPal)
- [ ] Email invoice delivery to customers
- [ ] Recurring/subscription invoices
- [ ] Invoice templates (different styles/layouts)
- [ ] Analytics and financial reports
- [ ] Multi-currency support
- [ ] Advanced search and filtering
- [ ] Invoice reminders (automatic emails)
- [ ] Multi-user teams (shared access)
- [ ] Two-factor authentication (2FA)
- [ ] Webhook support for integrations
- [ ] Mobile app (iOS/Android)