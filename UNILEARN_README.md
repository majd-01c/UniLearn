# UniLearn - University Learning Management System

A comprehensive LMS application built with Symfony 6.4, featuring user management and modular course organization.

## 🎓 Features Implemented

### ✅ Authentication & Security
- Login system with form authentication
- Password hashing using Symfony PasswordHasher
- Remember me functionality
- CSRF protection
- Role-based access control (ADMIN, TEACHER, STUDENT, PARTNER)

## 🚀 Getting Started

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Composer
- Docker (for the database container)

### Installation Steps

```bash
# 1. Install dependencies
composer install

# 2. Start the database container
docker compose up -d

# 3. Configure database (if needed)
# Edit .env file with database connection:
# DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app"

# 4. Run database migrations
php bin/console doctrine:migrations:migrate

# 5. Create admin user
php bin/console app:create-admin

# 6. Clear cache
php bin/console cache:clear

# 7. Start the server
symfony serve
```

### Database Configuration

| Setting       | Value        |
|---------------|--------------|
| Database Name | app          |
| Username      | app          |
| Password      | !ChangeMe!   |

### Access the Application
- **URL:** http://localhost:8000/login
- **Email:** admin@unilearn.com
- **Password:** admin123

## 🔑 Default Admin Account

```
Email: admin@unilearn.com
Password: admin123
Role: ADMIN
```

## 🎨 User Interface

### Bootstrap 5 Components Used
- Navbar with dropdowns
- Cards for dashboard widgets
- Forms with validation
- Tables for user listing
- Alerts for flash messages
- Badges for status indicators
- Icons (Bootstrap Icons)

## 📤 File Upload

Profile pictures are stored in:
```
public/uploads/profiles/
```

Accepted formats: JPG, JPEG, PNG, GIF (max 2MB)

## 🛠️ Development Commands

```bash
# Clear cache
php bin/console cache:clear

# Reload autoload & clear cache
composer dump-autoload && php bin/console cache:clear

# Create migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate

# Create controller
php bin/console make:controller

# Create entity
php bin/console make:entity

# List routes
php bin/console debug:router
```

## 📊 Database Commands

```bash
# View users
docker exec unilearn-database-1 mysql -uapp -p'!ChangeMe!' -D app -e "SELECT * FROM user;"

# Reset database (careful!)
php bin/console doctrine:schema:drop --force
php bin/console doctrine:migrations:migrate
```

## 🐛 Troubleshooting

### Login Issues
- Verify database connection
- Check if user exists and is active
- Clear cache: `php bin/console cache:clear`

### File Upload Issues
- Ensure `public/uploads/profiles/` directory exists and is writable
- Check file size and type restrictions

### Migration Issues

**Check migration status:**
```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
```

**Validate schema before migrating:**
```bash
php bin/console doctrine:schema:validate
```

**If you get DUPLICATE COLUMN / TABLE ERROR:**

Sometimes migration is marked as NOT executed but the DB already contains the changes. In that case, mark it as executed instead:
```bash
php bin/console doctrine:migrations:version <migration_number> --add
```

**Execute a specific migration:**
```bash
php bin/console doctrine:migrations:execute <migration_number> --up
```

### Docker Issues

**Reset Docker volume (if database is corrupted):**
```bash
docker-compose down -v
docker-compose up -d
```

### Complete Database Reset

If the database is in a bad state, you can reset everything:
```bash
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

**Alternative: Force schema update and sync migrations:**
```bash
php bin/console doctrine:schema:update --force
php bin/console doctrine:migrations:version --add --all
```

## 📄 License

This project is built for educational purposes.

---

## 📰 Recent Updates

### ✅ Forum/Community Improvements (Feb 16, 2026)

**Fixed Bugs:**
- ✓ Scroll position now preserved on page refresh
- ✓ Redirects to newly posted reply with smooth scroll
- ✓ Fixed pagination anchor navigation
- ✓ Added Previous/Next buttons to pagination

**New Features:**
- ✓ Smooth scrolling with highlight animation
- ✓ Loading states on form submission
- ✓ Improved hover effects and transitions
- ✓ Better mobile responsiveness

See [FORUM_IMPROVEMENTS.md](FORUM_IMPROVEMENTS.md) for detailed documentation.

---

**Built with Symfony 6.4 | Bootstrap 5 | MySQL 8**

### Check if Database is Synced with Entities 
php bin/console doctrine:schema:validate

