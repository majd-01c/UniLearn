# UniLearn – University Learning Management System

## Overview

This project was developed as part of the **PIDEV – 3rd Year Engineering Program** at **Esprit School of Engineering** (Academic Year 2025–2026).

UniLearn is a full-stack web application designed  for universities. It enables administrators, teachers, students, and business partners to manage academic programs, courses, evaluations, job offers, events, and real-time communication — all within a single unified platform.

## Features

- **User Management** – Role-based access control (Admin, Teacher, Student, Business Partner) with profile management and Face ID authentication
- **Academic Programs** – Hierarchical program builder (Program → Module → Course → Content) with class management
- **Evaluation System** – Quizzes with multiple question types, AI-powered answer evaluation, grade tracking, and semester results
- **Communication** – Forum with AI-assisted topic suggestions (Gemini API),  chat 
- **Job Offers** – ATS scoring system, CV parsing with AI, motivation letter management, skill matching
- **Events** – Event creation, participation management, calendar integration
- **AI Integration** – Gemini API for forum assistance, Groq API for academic recommendations, AI content detection
- **Video Meetings** – Jitsi-based virtual classrooms for live sessions
- **Face ID** – Biometric face verification for secure login using face-api.js
- **Avatar Generator** – Python FastAPI microservice for generating user avatars
- **Email Notifications** – Automated welcome emails, verification codes, password reset via Google SMTP
- **Multilingual** – Internationalization support (English, French, Arabic)

## Tech Stack

### Frontend

- **Twig** – Symfony templating engine
- **Bootstrap 5** – Responsive UI framework with Bootstrap Icons
- **Stimulus** (Symfony UX) – JavaScript controller framework
- **Turbo** (Symfony UX) – SPA-like navigation without full page reloads
- **face-api.js** – Client-side face detection and recognition

### Backend

- **PHP 8.1+** – Server-side language
- **Symfony 6.4** – PHP framework (MVC architecture)
- **Doctrine ORM 3** – Database abstraction and entity management
- **MySQL 8.0** – Relational database
- **Python FastAPI** – Avatar generation microservice
- **Gemini API** – AI-powered forum suggestions and content analysis
- **Groq API** – AI-powered academic recommendations

### DevOps & Tools

- **Docker** – Containerized database environment
- **Composer** – PHP dependency manager
- **PHPStan** (Level 6) – Static analysis for code quality
- **PHPUnit** – Unit and integration testing
- **Symfony Mailer** – Email service (Google SMTP)

## Architecture

```
unilearn/
├── src/
│   ├── Controller/          # Route handlers (Admin, Student, Teacher, Partner, Forum, etc.)
│   ├── Entity/              # Doctrine ORM entities
│   │   ├── Communication/   # Forum topics, comments, reactions, chat messages
│   │   ├── Evaluation/      # Grades, assessments, reclamations, schedules
│   │   ├── Events/          # Events, participations
│   │   ├── JobOffer/        # Job offers, applications, skills
│   │   ├── Program/         # Programs, modules, courses, quizzes, classes
│   │   └── User/            # Users, profiles, face verification logs
│   ├── Service/             # Business logic (AI, mailer, scoring, etc.)
│   ├── Repository/          # Database queries
│   ├── Form/                # Symfony form types
│   ├── Enum/                # Status enumerations
│   ├── Security/            # Voters and access control
│   └── EventSubscriber/     # Locale and event listeners
├── templates/               # Twig templates organized by module
│   ├── Gestion_Communication/
│   ├── Gestion_Evaluation/
│   ├── Gestion_Evenement/
│   ├── Gestion_Job_Offre/
│   ├── Gestion_Program/
│   └── Gestion_user/
├── avatar_service/          # Python FastAPI microservice
├── migrations/              # Doctrine database migrations
├── tests/                   # PHPUnit tests & PHPStan analysis
├── assets/                  # Frontend JS/CSS (Stimulus controllers)
└── public/                  # Web root (index.php, uploads, face-api models)
```

## Contributors

| Name | Module | Role |
|------|--------|------|
| *Alaa salem* | Gestion User | Full-Stack Developer |
| *Majd Labidi* | Gestion Communication | Full-Stack Developer |
| *khalil fekih* | Gestion Evaluation | Full-Stack Developer |
| *Haroun chaabane* | Gestion Program | Full-Stack Developer |
| *Dhia Amri* | Gestion Job Offre | Full-Stack Developer |


> *Replace with actual team member names.*

## Academic Context

Developed at **Esprit School of Engineering – Tunisia**
PIDEV – 3A | 2025–2026

- **Degree:** Engineering in Computer Science
- **Course:** PIDEV (Projet Intégré de Développement)
- **Year:** 3rd Year (3A)
- **Academic Year:** 2025–2026


## Getting Started

### Prerequisites

- PHP 8.1+
- MySQL 8.0+
- Composer
- Docker
- Node.js (for asset building)
- Python 3.10+ (for avatar service)

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/your-org/unilearn.git
cd unilearn

# 2. Install PHP dependencies
composer install

# 3. Start the database container
docker compose up -d

# 4. Run database migrations
php bin/console doctrine:migrations:migrate

# 5. Create default users
php bin/console app:create-access-users

# 6. Start the Symfony server
symfony serve
```

### Default Accounts

| Email | Password | Role |
|-------|----------|------|
| admin@unilearn.com | admin123 | ADMIN |
| student1@unilearn.com | student123 | STUDENT |
| student2@unilearn.com | student123 | STUDENT |
| teacher@unilearn.com | teacher123 | TEACHER |
| partner@unilearn.com | partner123 | BUSINESS_PARTNER |

### Access

- **Application:** http://localhost:8000

## Analytics & Project Indicators

### Production Indicators

| Indicator | Value |
|-----------|-------|
| Total public repositories | 1 |
| Average commits per project | *See GitHub Insights* |

### Quality Indicators

| Indicator | Status |
|-----------|--------|
| Structured README | ✅ Yes |
| Conforming topics | ✅ Yes |

### Impact Indicators

| Indicator | Value |
|-----------|-------|
| Total stars | ![GitHub Stars](https://img.shields.io/github/stars/your-org/unilearn?style=social) |
| Total forks | ![GitHub Forks](https://img.shields.io/github/forks/your-org/unilearn?style=social) |
| Clones | *Available via GitHub Traffic* |

> Update `your-org/unilearn` in the badge URLs with your actual GitHub repository path.

## Acknowledgments

- **Esprit School of Engineering** for providing the academic framework and guidance
- **Symfony** open-source community
- **Google Gemini API** and **Groq API** for AI capabilities
- **Jitsi Meet** for open-source video conferencing
- **face-api.js** for browser-based face recognition
- All open-source libraries and tools that made this project possible
