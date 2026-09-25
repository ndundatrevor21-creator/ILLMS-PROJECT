# LendingSystem

A PHP and MySQL lending management system for borrowers, lenders, and administrators.

## Project Structure

- `index.php` - Application entry page.
- `database/lms_tr.sql` - Exported MySQL/MariaDB database file.
- `config/database.php` - Shared database connection and application database helpers.
- `config/setup.php` - Creates the required application tables.
- `assets/css/` - Stylesheets.
- `assets/js/` - JavaScript files.
- `admin/` - Administrator actions and dashboard data endpoints.
- `api/` - Application API endpoints.
- `includes/` - Shared headers, footers, and access helpers.

## Requirements

- XAMPP with Apache and MySQL/MariaDB.
- PHP 8.0 or newer.
- MySQLi PHP extension enabled.
- A web browser.

## Installation With XAMPP

1. Copy or clone this project into:

   `C:\xampp\htdocs\LendingSystem`

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Create a database named `lms_tr` in phpMyAdmin.
4. Import the exported database file:

   `database/lms_tr.sql`

   In phpMyAdmin, select the `lms_tr` database, choose **Import**, select the SQL file, and run the import.

5. Copy `.env.example` to `.env`.
6. Open `.env` and set the local MySQL credentials:

   ```text
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_USERNAME=your_database_username
   DB_PASSWORD=your_database_password
   DB_DATABASE=lms_tr
   ```

7. Open the application at:

   `http://localhost/LendingSystem/`

## Alternative PHP Development Server

From the project folder, run `start-server.bat` or execute:

```text
php -S localhost:8000
```

Then open:

`http://localhost:8000`

## Database Export Location

The exported database is located in the `database` folder:

`database/lms_tr.sql`

This file contains the database schema and exported records from the local development database. Remove password hashes, personal information, and other private records before publishing it publicly.



## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for the full license text.

