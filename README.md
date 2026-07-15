# Tire

A web-based **Point of Sale (POS) and Inventory Management System** designed for stores that specialize in selling tires and related automotive products.

## Features

* 🛒 Point of Sale (POS) with fast transaction processing
* 📦 Inventory management with real-time stock monitoring
* 🏷️ Product and category management
* 🚗 Vehicle type management for tire compatibility
* 👥 User management with secure authentication
* 📊 Sales reports and transaction history
* 🔔 Low stock and tire expiry notifications
* 📱 QR code and barcode support for faster product lookup
* 💾 Database backup support
* ⚙️ Configurable system settings and business information

## Technology Stack

* **Backend:** PHP
* **Database:** MySQL
* **Frontend:** HTML, CSS, JavaScript, Bootstrap
* **Charts:** Chart.js
* **QR Scanner:** HTML5 QR Code
* **Alerts:** SweetAlert2

## Installation

1. Clone the repository:

```bash
git clone https://github.com/camihoy96/Tire.git
```

2. Move the project to your web server directory (e.g., `htdocs` for XAMPP).

3. Import the database:

```
db/tire.sql
```

4. Configure the database connection in:

```
config.php
```

5. Start Apache and MySQL, then open:

```
http://localhost/Tire
```

## Project Structure

```text
assets/         CSS, JavaScript, images, and libraries
db/             Database schema
includes/       Core classes and reusable components
manage/         Administration and POS modules
ajax/           AJAX request handlers
cron/           Scheduled maintenance scripts
uploads/        Uploaded files and logos
backups/        Database backup files
```

## License

This project is available for educational and personal use. Please review the repository license for usage terms.

---

Developed to provide an efficient, user-friendly, and reliable POS and inventory solution for tire retail businesses.
