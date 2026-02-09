# UniLearn - University Learning Management System

A comprehensive LMS application built with Symfony 6.4, featuring user management and modular course organization.

## 🎓 Features Implemented

### ✅ Authentication & Security
- Login system with form authentication
- Password hashing using Symfony PasswordHasher
- Remember me functionality
- CSRF protection
- Role-based access control (ADMIN, TEACHER, STUDENT, PARTNER)

### ✅ User Management (Full CRUD)
- Add new users with comprehensive form
- List all users with profile pictures
- Role assignment and status management
- File upload for profile pictures
- Skills management (stored as JSON array)
- Email verification fields
- User activation/deactivation

### ✅ Dashboard & Navigation
- Bootstrap 5 responsive design
- Navigation bar with all modules
- Quick access cards to all features
- Flash messages for user feedback

### ✅ Placeholder Modules (Coming Soon)
- Programme Management (Programme → Module → Course → Contenu)
- Classe Management
- Event Management
- Job Offer Management

## 📋 Database Schema

### User Entity Fields
- `id`: Auto-increment primary key
- `role`: ENUM (ADMIN, TEACHER, STUDENT, PARTNER) - Default: STUDENT
- `email`: Unique, required
- `password`: Hashed, required (min 6 chars)
- `isActive`: Boolean, default true
- `name`: String, nullable
- `phone`: String, nullable
- `profilePic`: String (filename), nullable
- `location`: String, nullable
- `skills`: JSON array, nullable
- `about`: Text, nullable
- `isVerified`: Boolean, default false
- `needsVerification`: Boolean, default true
- `emailVerifiedAt`: DateTime, nullable
- `emailVerificationCode`: String, nullable
- `codeExpiryDate`: DateTime, nullable

## 🚀 Getting Started

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Composer
- Docker (for the database container)

### Installation Steps

1. **Database is already running** (unilearn-database-1)

2. **Migrations are already applied**
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

3. **Start Symfony Server**
   ```bash
   symfony server:start
   # or
   php -S localhost:8000 -t public/
   ```

4. **Access the Application**
   - URL: http://localhost:8000/login
   - Email: admin@unilearn.com
   - Password: admin123

## 📁 Project Structure

```
src/
├── Controller/
│   ├── SecurityController.php      # Login/Logout
│   ├── HomeController.php          # Dashboard
│   ├── UserController.php          # User CRUD
│   ├── ProgrammeController.php     # Programme module
│   ├── ClasseController.php        # Classe module
│   ├── EventController.php         # Event module
│   └── JobOfferController.php      # Job Offer module
├── Entity/
│   └── User.php                    # User entity with security interfaces
├── Form/
│   └── UserType.php                # User creation form
└── Repository/
    └── UserRepository.php          # User repository

templates/
├── base.html.twig                  # Base layout with Bootstrap 5
├── auth/
│   └── login.html.twig            # Login page
├── home/
│   └── index.html.twig            # Dashboard
├── user/
│   ├── index.html.twig            # User list
│   └── new.html.twig              # Add user form
├── programme/
│   ├── index.html.twig            # Placeholder
│   ├── modules.html.twig          # Placeholder
│   ├── courses.html.twig          # Placeholder
│   └── contenus.html.twig         # Placeholder
├── classe/
│   └── index.html.twig            # Placeholder
├── event/
│   └── index.html.twig            # Placeholder
└── job_offer/
    └── index.html.twig            # Placeholder

config/packages/
└── security.yaml                   # Security configuration
```

## 🔐 Routes

### Public Routes
- `GET /login` - Login page

### Protected Routes (ROLE_USER)
- `GET /` - Dashboard (app_home)
- `GET /programme` - Programme index
- `GET /programme/modules` - Modules
- `GET /programme/courses` - Courses
- `GET /programme/contenus` - Contenus
- `GET /classe` - Classe
- `GET /event` - Event
- `GET /job-offer` - Job Offer
- `GET /logout` - Logout

### Admin Routes (ROLE_ADMIN)
- `GET /users` - List users
- `GET /users/new` - Add user form
- `POST /users/new` - Create user

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

## 🔑 Default Admin Account

```
Email: admin@unilearn.com
Password: admin123
Role: ADMIN
```

## ⚙️ Configuration

### Security (config/packages/security.yaml)
- Form login authentication
- User provider: Entity-based (User::email)
- Password hasher: Auto (bcrypt)
- Remember me: 1 week
- Access control:
  - `/login` - Public
  - `/users/*` - ROLE_ADMIN
  - `/*` - ROLE_USER

## 📝 Creating New Users

1. Login as admin
2. Navigate to Users → Add User
3. Fill in the form:
   - **Required**: Role, Email, Password
   - **Optional**: Name, Phone, Location, About, Skills, Profile Picture
   - **Status**: isActive, isVerified, needsVerification
   - **Verification**: Email verification fields
4. Submit the form
5. User is created with hashed password

## 🛠️ Development Commands

```bash
# Clear cache
php bin/console cache:clear

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

## 🎯 Next Steps

To expand the application:

1. **Programme Module**: Create entities for Programme, Module, Course, Contenu
2. **Classe Module**: Create entity for Classe with relationships
3. **Event Module**: Create event management with calendar
4. **Job Offer Module**: Create job posting system
5. **User Roles**: Implement role-based UI filtering
6. **Email Verification**: Implement email sending
7. **API**: Add REST API endpoints
8. **Advanced Features**: 
   - User profile editing
   - Password reset
   - Two-factor authentication
   - Activity logs

## 🐛 Troubleshooting

### Login Issues
- Verify database connection
- Check if user exists and is active
- Clear cache: `php bin/console cache:clear`

### File Upload Issues
- Ensure `public/uploads/profiles/` directory exists and is writable
- Check file size and type restrictions

### Migration Issues
- Check database connection in `.env`
- Verify migrations table exists
- Run: `php bin/console doctrine:migrations:status`

## 📄 License

This project is built for educational purposes.

---

**Built with Symfony 6.4 | Bootstrap 5 | MySQL 8**
