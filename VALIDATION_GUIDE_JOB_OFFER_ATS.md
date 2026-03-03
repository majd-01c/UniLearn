# 🎯 VALIDATION GUIDE - JOB OFFER & ATS SYSTEM

**Project:** UniLearn - Job Offer Management & ATS System  
**Validation Date:** February 27, 2026  
**Your Part:** Job Offer & ATS System Implementation

---

## 🏗️ SYSTEM ARCHITECTURE OVERVIEW

### **Technology Stack**
- **Framework:** Symfony 6.4
- **PHP Version:** >= 8.1
- **Database:** MySQL with Doctrine ORM
- **Architecture:** Service-Oriented Architecture (SOA)
- **Design Pattern:** MVC (Model-View-Controller)

### **Core Features Built**
- ✅ Job Offer Management System
- ✅ Applicant Tracking System (ATS)
- ✅ AI-Powered CV Analysis
- ✅ Automated Candidate Scoring
- ✅ Multi-Role System (Student/Partner/Admin)
- ✅ PDF CV Processing
- ✅ Secure File Upload System

---

## 📁 SYMFONY FOLDER STRUCTURE & PURPOSE

### **`src/` Directory - Main Application Code**

#### **`src/Controller/`** - HTTP Request Handlers
- **`JobOfferController.php`** - Main controller for job offer operations
  - Public job listing and search
  - Job details display
  - Application submission
  - Partner job management
  - Admin approval workflow

#### **`src/Entity/`** - Database Models
- **`JobOffer/JobOffer.php`** - Job offer entity
  - Job details (title, type, location, description)
  - ATS requirements (required/preferred skills)
  - Status management (DRAFT, PENDING, ACTIVE, EXPIRED)
- **`JobOffer/JobApplication.php`** - Application entity
  - CV file storage
  - ATS scoring results
  - Application status tracking
- **`JobOffer/CustomSkill.php`** - Partner-specific skills

#### **`src/Service/JobOffer/`** - Business Logic Services
- **`ATSScoringService.php`** - Core ATS functionality
  - 100-point scoring algorithm
  - Gemini AI integration
  - Skills matching logic
- **`CVParserService.php`** - PDF processing
  - Text extraction from CVs
  - Content cleaning and formatting
- **`JobApplicationService.php`** - Application management
- **`JobOfferService.php`** - Job offer business logic
- **`SkillsProvider.php`** - Skills database and categorization

#### **`src/Repository/`** - Database Query Methods
- Custom database queries
- Search and filter functionality
- Pagination support

#### **`src/Form/`** - Form Handling
- Job offer creation/editing forms
- Application submission forms
- Validation rules

### **`config/` Directory - Configuration**

#### **`config/packages/`** - Bundle Configurations
- **`doctrine.yaml`** - Database ORM configuration
- **`security.yaml`** - Authentication & authorization
- **`vich_uploader.yaml`** - File upload configuration
- **`mailer.yaml`** - Email system setup

#### **`config/services.yaml`** - Service Container
```yaml
parameters:
    gemini_api_key: '%env(GEMINI_API_KEY)%'
    cv_upload_directory: '%kernel.project_dir%/public/uploads/cv'

services:
    App\Service\JobOffer\ATSScoringService:
        arguments:
            $cvUploadDirectory: '%cv_upload_directory%'
            $geminiApiKey: '%gemini_api_key%'
```

### **`templates/` Directory - User Interface**
- **`Gestion_Job_Offre/`** - Job offer templates
  - Job listing views
  - Application forms
  - Status displays
  - Admin panels

### **`migrations/` Directory - Database Changes**
- Database schema evolution
- Version controlled database changes
- Automated deployment support

### **`public/uploads/`** - File Storage**
- **`cv/`** - CV file storage
- **`profiles/`** - Profile photos

---

## 🔌 APIs & INTEGRATIONS

### **1. Google Gemini AI API**

**Purpose:** Intelligent CV analysis and candidate scoring

**Integration Details:**
- **API Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models`
- **Primary Model:** `gemini-2.5-flash`
- **Fallback Models:** `gemini-2.0-flash`, `gemini-2.0-flash-lite`
- **Authentication:** API Key based
- **Configuration:** Environment variable `GEMINI_API_KEY`

**Implementation Location:**
```php
// src/Service/JobOffer/ATSScoringService.php
private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
private const PRIMARY_MODEL = 'gemini-2.5-flash';
```

**What it does:**
- Analyzes CV content for skills extraction
- Evaluates education levels
- Assesses experience years
- Language proficiency analysis
- Intelligent matching with job requirements

### **2. PDF Parser Library**

**Library:** `smalot/pdfparser`

**Purpose:** Extract text content from PDF CVs

**Installation:**
```bash
composer require smalot/pdfparser
```

**Implementation:**
```php
// src/Service/JobOffer/CVParserService.php
use Smalot\PdfParser\Parser;

public function extractTextFromPdf(string $filePath): ?string
{
    $parser = new Parser();
    $pdf = $parser->parseFile($filePath);
    return $pdf->getText();
}
```

---

## 📦 BUNDLES INSTALLED & CONFIGURED

### **Core Bundles**

#### **1. VichUploaderBundle**
**Purpose:** Secure file upload handling

**Installation:**
```bash
composer require vich/uploader-bundle
```

**Configuration:** `config/packages/vich_uploader.yaml`
```yaml
vich_uploader:
    mappings:
        cv_files:
            uri_prefix: /uploads/cv
            upload_destination: '%kernel.project_dir%/public/uploads/cv'
            namer: Vich\UploaderBundle\Naming\SmartUniqueNamer
```

**Features:**
- Secure file validation
- Unique file naming
- Automatic file organization
- Integration with Doctrine entities

#### **2. DoctrineBundle**
**Purpose:** Database ORM and management

**Features:**
- Entity mapping
- Database migrations
- Query optimization
- Relationship management

#### **3. SecurityBundle**
**Purpose:** Authentication and authorization

**Configuration:** Role-based access control
- `ROLE_STUDENT` - Can apply to jobs
- `ROLE_PARTNER` - Can create job offers
- `ROLE_ADMIN` - Can approve offers

#### **4. HttpClient Component**
**Purpose:** External API communication

**Usage:** Gemini AI API calls
```bash
composer require symfony/http-client
```

---

## ⚙️ ATS SCORING ALGORITHM

### **Scoring Breakdown (100 Points Total)**

```php
// src/Service/JobOffer/ATSScoringService.php
private const WEIGHT_REQUIRED_SKILLS = 40;    // 40 points max
private const WEIGHT_PREFERRED_SKILLS = 15;   // 15 points max
private const WEIGHT_EDUCATION = 20;          // 20 points max
private const WEIGHT_EXPERIENCE = 15;         // 15 points max
private const WEIGHT_LANGUAGES = 10;          // 10 points max
```

### **Skills Categories**
- **Programming Languages:** PHP, Python, Java, JavaScript, etc.
- **Frontend Technologies:** React, Vue.js, Angular, HTML, CSS
- **Backend Frameworks:** Symfony, Laravel, Django, Spring Boot
- **Databases:** MySQL, PostgreSQL, MongoDB, Redis
- **DevOps & Cloud:** Docker, Kubernetes, AWS, Azure, Git
- **Mobile Development:** Android, iOS, React Native, Flutter

---

## 🔄 COMPLETE SYSTEM WORKFLOW

### **1. Partner Workflow**
```
1. Partner Login
   ↓
2. Create Job Offer Form
   ↓
3. Set ATS Criteria
   - Required Skills
   - Preferred Skills
   - Education Requirements
   - Experience Level
   ↓
4. Submit for Admin Approval
   ↓
5. Admin Reviews & Approves
   ↓
6. Job Goes Live
```

### **2. Student Application Workflow**
```
1. Browse Active Job Offers
   ↓
2. View Job Details
   ↓
3. Submit Application + CV Upload
   ↓
4. CV Processing Pipeline:
   a) File Upload (VichUploader)
   b) PDF Text Extraction (PdfParser)
   c) AI Analysis (Gemini API)
   d) ATS Scoring Calculation
   ↓
5. Application Stored with Score
   ↓
6. Partner Reviews Applications
```

### **3. ATS Processing Pipeline**
```
CV Upload → PDF Parse → Text Clean → AI Analysis → Score Calculation → Ranking
```

**Detailed ATS Process:**
1. **File Validation** - Check file type and size
2. **Text Extraction** - Extract content from PDF
3. **Content Cleaning** - Remove formatting artifacts
4. **AI Analysis** - Send to Gemini API for intelligent parsing
5. **Skills Extraction** - Identify technical and soft skills
6. **Experience Calculation** - Determine years of experience
7. **Education Assessment** - Evaluate academic background
8. **Language Proficiency** - Assess language skills
9. **Score Calculation** - Apply weighted scoring algorithm
10. **Storage** - Save results to database

---

## 🛣️ ROUTING STRUCTURE

### **Public Routes**
- `GET /job-offers` - Job listing with search/filter
- `GET /job-offers/{id}` - Job details page

### **Student Routes (Authentication Required)**
- `POST /job-offers/{id}/apply` - Submit application

### **Partner Routes (Role: PARTNER)**
- `GET /partner/job-offers` - Manage own job offers
- `POST /partner/job-offers/create` - Create new job offer
- `PUT /partner/job-offers/{id}/edit` - Edit job offer

### **Admin Routes (Role: ADMIN)**
- `GET /admin/job-offers/pending` - Review pending offers
- `POST /admin/job-offers/{id}/approve` - Approve job offer
- `POST /admin/job-offers/{id}/reject` - Reject job offer

---

## 🔐 SECURITY IMPLEMENTATION

### **Authentication System**
- **Provider:** Entity-based user provider
- **Method:** Form login with CSRF protection
- **Password Hashing:** Automatic password hashing
- **Session Management:** Secure session handling

### **Authorization**
- **Security Voters** - Custom authorization logic
- **Role Hierarchy** - ADMIN > PARTNER > STUDENT
- **Route Protection** - Controller-level security attributes

### **File Security**
- **Upload Validation** - File type and size restrictions
- **Secure Storage** - Files stored outside web root where possible
- **Access Control** - Authenticated access only

---

## 📊 DATABASE SCHEMA

### **Key Tables**
- **`user`** - User accounts (students, partners, admins)
- **`job_offer`** - Job postings with ATS criteria
- **`job_application`** - Student applications with scores
- **`custom_skill`** - Partner-specific skills

### **Relationships**
- User ↔ JobOffer (Partner creates offers)
- User ↔ JobApplication (Student applies)
- JobOffer ↔ JobApplication (One-to-many)

---

## 🔧 ENVIRONMENT SETUP

### **Required Environment Variables**
```bash
DATABASE_URL="mysql://user:password@localhost/unilearn"
MAILER_DSN="smtp://localhost:1025"
GEMINI_API_KEY="your-gemini-api-key"
AVATAR_SERVICE_URL="http://localhost:5000"
```

### **Installation Steps**
```bash
# Install dependencies
composer install

# Setup database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Load fixtures (if available)
php bin/console doctrine:fixtures:load

# Clear cache
php bin/console cache:clear
```

---

## 🎯 KEY TALKING POINTS FOR VALIDATION

### **Technical Excellence**
- **"Service-Oriented Architecture"** - Clean separation of concerns
- **"AI-Powered ATS"** - Modern intelligent candidate scoring
- **"Secure File Handling"** - Professional upload management
- **"Scalable Design"** - Modular and extensible architecture

### **API Integration Expertise**
- **"RESTful API Integration"** - Gemini AI for CV analysis
- **"Error Handling & Fallbacks"** - Multiple model support
- **"Environment-Based Configuration"** - Secure credential management
- **"HTTP Client Best Practices"** - Proper API communication

### **Symfony Mastery**
- **"Bundle Integration"** - VichUploader, Doctrine, Security
- **"Custom Services"** - Business logic encapsulation
- **"Repository Pattern"** - Clean data access layer
- **"Form Handling"** - Robust form validation

### **Business Value**
- **"Automated Screening"** - Reduces manual review time
- **"Intelligent Matching"** - Better candidate-job fit
- **"Scalable Solution"** - Handles high application volumes
- **"User Experience"** - Simplified application process

---

## 📈 PERFORMANCE CONSIDERATIONS

### **Optimizations Implemented**
- **Database Indexing** - Strategic indexes on search fields
- **Pagination** - Efficient large dataset handling
- **Caching** - Symfony cache for frequent queries
- **File Storage** - Organized upload directory structure

### **Scalability Features**
- **Service Abstraction** - Easy to swap implementations
- **API Rate Limiting** - Handles external API constraints
- **Background Processing** - Ready for queue implementation
- **Modular Design** - Independent feature development

---

## 🧪 TESTING STRATEGY

### **Test Files Present**
- `test_job_offer_workflow.py` - End-to-end workflow testing
- `test_job_offer_workflow_pytest.py` - Automated test suite

### **Testing Coverage**
- **Unit Tests** - Service layer testing
- **Integration Tests** - API and database testing
- **End-to-End Tests** - Complete user workflows
- **Security Tests** - Authorization and validation

---

## 🚀 DEPLOYMENT READY

### **Production Considerations**
- **Environment Variables** - Secure configuration management
- **Database Migrations** - Version-controlled schema changes
- **Asset Management** - Optimized frontend resources
- **Error Handling** - Comprehensive exception management
- **Logging** - Structured application logs

---

**Good luck with your validation! You've built a sophisticated, AI-powered ATS system with modern architecture and best practices.** 🎯✨