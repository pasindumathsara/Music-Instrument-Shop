# 🎵 Melody Masters – Music Instrument Shop

A full-stack PHP + MySQL e-commerce application for selling musical instruments, built with procedural MySQLi, vanilla CSS, and HTML.

---

## 🚀 Quick Setup (XAMPP)

### 1. Place Files
Ensure the project is in: `C:\xampp\htdocs\Music-Instrument-Shop\`

### 2. Start XAMPP Services
Open **XAMPP Control Panel** and start:
- ✅ **Apache**
- ✅ **MySQL**

### 3. Import Database
Open **phpMyAdmin** → `http://localhost/phpmyadmin`

1. Click **New** (left sidebar) and create a database named `music_store` (or skip—the SQL script does it automatically)
2. Select `music_store` → click **Import**
3. Upload `database/music_store.sql`
4. Click **Go**

### 4. Open the App
Visit: **http://localhost/Music-Instrument-Shop/**

---

## 🔑 Default Credentials

| Role     | Email                       | Password  |
|----------|-----------------------------|-----------|
| Admin    | admin@melodymasters.com     | admin123  |

> ⚠️ Change the admin password immediately after first login via **My Account**.

---

## 📁 Project Structure

```
Music-Instrument-Shop/
│
├── index.php               # Entry redirect
├── home.php                # Authenticated home (featured products)
├── shop.php                # Public product listing (search + filter)
├── product.php             # Product detail + reviews
├── cart.php                # Session-based shopping cart
├── checkout.php            # Checkout form + order placement
├── order_confirm.php       # Post-checkout confirmation
├── orders.php              # Customer order history + detail
├── account.php             # User profile + password change
├── login.php               # Login (stores user_id, user_name, user_role)
├── register.php            # Registration (auto-login after)
├── logout.php              # Destroys session
│
├── includes/
│   ├── db.php              # MySQLi connection + session + constants
│   ├── functions.php       # All helpers (auth, cart, formatting, etc.)
│   ├── auth.php            # Convenience bootstrapper
│   ├── header.php          # Shared navbar (dynamic login state)
│   └── footer.php          # Shared footer + mobile JS
│
├── admin/
│   ├── dashboard.php       # Stats overview + recent orders
│   ├── manage_products.php # Full product CRUD + image upload
│   ├── manage_orders.php   # Order list + status management
│   ├── manage_users.php    # User list + role toggle + delete
│   └── includes/
│       ├── admin_header.php # Admin sidebar layout header
│       └── admin_footer.php # Admin layout footer
│
├── assets/
│   ├── css/style.css       # Complete design system
│   └── images/             # bg1.jpg, bg2.jpg
│
├── uploads/                # Product image uploads (auto-created)
└── database/
    └── music_store.sql     # Full schema + sample data
```

---

## ⚙️ System Features

### 👥 Authentication
- Secure login with `password_verify()` / `password_hash()`
- Session-based with `user_id`, `user_name`, `user_role`
- Role-based access: `customer` | `admin`
- Auto-login after successful registration

### 🛒 Shopping
- Public product browsing (no login required)
- Search by name/description
- Filter by category
- Sort by price, name, newest
- Session-based cart (add, update, remove, clear)
- Stock validation on checkout

### 📦 Orders
- DB transaction with rollback on failure
- Stock deduction with `FOR UPDATE` lock
- Shipping: **Free** over $100, **$9.99** flat below
- Order status: `pending → paid → processing → shipped → delivered`
- Customer order history + detail view

### ⭐ Reviews
- Only verified purchasers can submit reviews
- One review per product per customer
- Star rating (1–5) + optional comment
- Interactive star picker with hover preview

### ⚙️ Admin Panel
- **Dashboard**: Revenue, orders, products, customers + top sellers
- **Products**: Add/Edit/Delete with image upload (up to 5MB)
- **Orders**: Filter by status, update status per-order
- **Users**: Search, role toggle (admin/customer), delete with self-protection

### 🔒 Security
- Prepared statements everywhere (no raw SQL with user input)
- `htmlspecialchars()` on all output
- Session-based authentication with role guard functions
- File upload type + size validation
- CSRF protection via POST-only for mutations

---

## 🎨 Design System
- **Font**: Poppins (Google Fonts)
- **Primary**: `#0f172a` | **Accent**: `#e11d48`
- Responsive breakpoints: 1024px, 768px, 480px
- Components: navbar, hero, product cards, cart, admin sidebar, alerts, badges, tables

---

## 🔧 Configuration

Edit `includes/db.php` to change database settings:

```php
$host     = "localhost";
$user     = "root";
$password = "";          // Your MySQL password
$database = "music_store";
```

Edit shipping constants in `includes/db.php`:
```php
define('SHIPPING_THRESHOLD', 100.00);  // Free shipping above this
define('SHIPPING_COST',       9.99);   // Flat rate below threshold
```