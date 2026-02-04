# Invoice Management System

A modern, mobile-friendly invoice management application for creating, editing, viewing, and managing invoices. Perfect for small businesses and freelancers.

## ✨ Features

| Feature | Description |
|---------|-------------|
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

## 📋 Requirements

- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- **Web Server** Apache, Nginx, or built-in PHP server
- **Modern Browser** (Chrome, Firefox, Safari, Edge)

## 🚀 Quick Start

### 1. Setup Database

Run this SQL to create tables:

```sql
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

### 3. Run the Application

Using PHP's built-in server:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## 📁 Project Structure

```
invoyce/
├── index.html              # Dashboard - List all invoices
├── create_invoice.html     # Create new invoice
├── view_invoice.html       # View invoice details
├── edit_invoice.html       # Edit existing invoice
├── config/
│   └── db.php             # Database configuration
├── api/
│   ├── invoices.php       # Main invoice API (create, read, update, delete)
│   ├── customers.php      # Customer management
│   ├── invoices_list.php  # List all invoices
│   ├── delete_invoice.php # Delete invoice endpoint
│   ├── export_pdf.php     # PDF export
│   └── share_invoice.php  # Public invoice view
├── assets/
│   ├── styles.css         # Main stylesheet (responsive)
│   └── app.js             # Frontend logic
├── sql/
│   └── add_invoice_number.sql  # Migration file
└── MIGRATION.md            # Database migration guide
```

## 🎯 Usage

### Creating an Invoice
1. Click "Create New Invoice" on dashboard
2. Select or add a customer
3. Add line items with descriptions, quantities, and prices
4. Set discounts if needed
5. Review totals and save

### Editing an Invoice
1. Go to dashboard and click "Edit" on any invoice
2. Modify customer, dates, items, or discounts
3. Save changes

### Viewing an Invoice
1. Click "View" on the dashboard
2. See full details, customer info, and line items
3. Options to edit, delete, export PDF, or generate share link

### Sharing an Invoice
1. Open invoice and click "Get Share Link"
2. Share the URL with customer
3. Customer can view invoice without authentication

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

### Database Connection Error
- ✓ Check `config/db.php` credentials
- ✓ Verify MySQL is running
- ✓ Confirm database exists

### Missing invoice_number Column
Run this migration:
```sql
ALTER TABLE invoices ADD COLUMN invoice_number VARCHAR(20) UNIQUE AFTER id;
CREATE INDEX idx_invoice_number ON invoices(invoice_number);
```

Or run:
```bash
mysql -u root invoice_app < sql/add_invoice_number.sql
```

### Page Not Found (404)
- ✓ Ensure all files are in correct directories
- ✓ Check web server is serving files
- ✓ Verify file permissions

### Buttons Not Working
- ✓ Check browser console for JavaScript errors
- ✓ Ensure `assets/app.js` is loading
- ✓ Clear browser cache

## ⚙️ Configuration

### Changing Tax Rate

Edit `assets/app.js`, find `recalc()` function:

```javascript
const tax = subtotal * 0.15;  // Change 0.15 to your rate
```

### Changing Currency Symbol

Edit `assets/app.js` and `view_invoice.html`:

Replace `R` with your currency symbol (e.g., `$`, `€`, `£`)

## 🔐 Security Notes

⚠️ **Important**: This app has NO authentication. For production use:

- ✓ Add user authentication (login system)
- ✓ Add authorization checks (who can see what)
- ✓ Use HTTPS (SSL certificate)
- ✓ Sanitize all inputs
- ✓ Add CORS headers
- ✓ Implement rate limiting
- ✓ Add audit logging

## 📄 License

MIT License - Free to use and modify for personal or commercial projects.

## 💡 Future Enhancements

- [ ] User authentication and multi-user support
- [ ] Payment gateway integration
- [ ] Email sending for invoices
- [ ] Recurring invoices
- [ ] Invoice templates
- [ ] Analytics and reports
- [ ] Multi-currency support
- [ ] Advanced search and filtering