# Job Offer Module — Complete Refactoring Plan (v2)

> Generated: 2026-02-10 | Symfony 6 | 3 Actors: Admin, Business Partner, Student

> **CRITICAL ROLE FINDING:** The `User.role` column stores `BUSINESS_PARTNER` (confirmed  
> via `Enum\Role::BUSINESS_PARTNER`, `UserCreateType`, `UserEditType`, `base.html.twig`).  
> `User::getRoles()` does `'ROLE_' . strtoupper($this->role)` → the Symfony role is  
> **`ROLE_BUSINESS_PARTNER`**, NOT `ROLE_PARTNER`.  
> All controllers already use `#[IsGranted('ROLE_BUSINESS_PARTNER')]`.

---

# STEP 1 — INVENTORY: Job Offer Files Found

## 1.1 Entities (Doctrine ORM mappings)

| # | File | Description |
|---|------|-------------|
| 1 | `src/Entity/JobOffer.php` (244 lines) | Main entity. Fields: id, title, type (enum), location, description, requirements, status (enum), createdAt, updatedAt, publishedAt, expiresAt, partner (ManyToOne→User), applications (OneToMany→JobApplication). Lifecycle callbacks: PrePersist, PreUpdate. DB indexes on type, status, location, published_at, expires_at. |
| 2 | `src/Entity/JobApplication.php` (119 lines) | Application entity. Fields: id, offer (ManyToOne→JobOffer), student (ManyToOne→User), message, cvFile, status (enum), createdAt. Lifecycle callback: PrePersist. UniqueConstraint on (offer_id, student_id). |

## 1.2 Enums

| # | File | Values |
|---|------|--------|
| 3 | `src/Enum/JobOfferType.php` | INTERNSHIP, APPRENTICESHIP, JOB |
| 4 | `src/Enum/JobOfferStatus.php` | PENDING, ACTIVE, REJECTED, CLOSED |
| 5 | `src/Enum/JobApplicationStatus.php` | SUBMITTED, REVIEWED, ACCEPTED, REJECTED |
| — | `src/Enum/Role.php` | ADMIN, STUDENT, TEACHER, BUSINESS_PARTNER (shared, DO NOT touch) |

## 1.3 Repositories

| # | File | Methods |
|---|------|---------|
| 6 | `src/Repository/JobOfferRepository.php` | `search(?string $q, ?JobOfferType $type, ?string $location, ?JobOfferStatus $status)` — returns `array<JobOffer>`, no pagination |
| 7 | `src/Repository/JobApplicationRepository.php` | **EMPTY** — only auto-generated commented-out boilerplate |

## 1.4 Controllers

| # | File | Guard | Route Prefix | Actions |
|---|------|-------|-------------|---------|
| 8 | `src/Controller/AdminJobOfferController.php` | `#[IsGranted('ROLE_ADMIN')]` | `/admin/job-offer` | list (GET), approve (POST), reject (POST), close (POST), delete (POST) |
| 9 | `src/Controller/Partner/PartnerJobOfferController.php` | `#[IsGranted('ROLE_BUSINESS_PARTNER')]` | `/partner/job-offer` | index (GET), new (GET/POST), edit (GET/POST), close (POST), reopen (POST), delete (POST), applications (GET) |
| 10 | `src/Controller/Partner/PartnerJobApplicationController.php` | `#[IsGranted('ROLE_BUSINESS_PARTNER')]` | `/partner/job-application` | updateStatus (POST) |
| 11 | `src/Controller/JobOfferController.php` | None (class-level) / `#[IsGranted('ROLE_STUDENT')]` on apply | `/job-offer` | index (GET), show (GET), apply (POST) |

## 1.5 Forms

| # | File | Bound entity | Notes |
|---|------|-------------|-------|
| 12 | `src/Form/JobOfferType.php` | `JobOffer` | Class name clashes with `Enum\JobOfferType` (requires `use ... as JobOfferTypeEnum` alias). Constraints in form only, not entity. |
| 13 | `src/Form/JobApplicationType.php` | `JobApplication` | `cvFile` field is `mapped: false` with `Assert\File` constraint. Clean. |

## 1.6 Twig Templates

| # | File | Actor / Purpose |
|---|------|-----------------|
| 14 | `templates/job_offer/index.html.twig` (112 lines) | Student/All — browse active offers (cards grid) |
| 15 | `templates/job_offer/show.html.twig` (175 lines) | Student/All — offer detail + inline apply form |
| 16 | `templates/admin/job_offer/list.html.twig` (170 lines) | Admin — manage all offers (table + filters) |
| 17 | `templates/partner/job_offer/index.html.twig` (161 lines) | Partner — own offers list (table) |
| 18 | `templates/partner/job_offer/new.html.twig` (84 lines) | Partner — create offer form |
| 19 | `templates/partner/job_offer/edit.html.twig` (84 lines) | Partner — edit offer form |
| 20 | `templates/partner/job_offer/applications.html.twig` (167 lines) | Partner — view applications for own offer |

## 1.7 Services

**NONE.** No dedicated service layer exists for Job Offers. All logic is inside controllers.

## 1.8 DTOs / Events / Subscribers

**NONE** related to Job Offers.

## 1.9 Voters / Security

**NONE.** Ownership is enforced via a `private denyAccessUnlessOwner(JobOffer $offer)` method
inside `PartnerJobOfferController` and a similar one in `PartnerJobApplicationController`.

## 1.10 Tests / Fixtures

**NONE.** `tests/` contains only `bootstrap.php`.

## 1.11 Migrations

| # | File | What it does |
|---|------|-------------|
| 21 | `migrations/Version20260204223035.php` | Creates `job_offer` table with indexes |
| 22 | `migrations/Version20260209125707.php` | Adds `published_at`, `expires_at` + indexes on `job_offer`; creates `job_application` table with FK + unique constraint |

## 1.12 Configuration

| # | File | Relevant entry |
|---|------|----------------|
| 23 | `config/services.yaml` line 8 | `cv_files_directory: '%kernel.project_dir%/public/uploads/cv'` |
| 24 | `config/packages/security.yaml` lines 49-53 | `access_control: ^/admin → ROLE_ADMIN` exists. **No `^/partner` rule** — relies entirely on `#[IsGranted]`. |

## 1.13 Related External References

- `templates/base.html.twig` lines 95, 105, 113 — sidebar links to `app_job_offer_index` and `app_partner_job_offer_index`
- `templates/home/index.html.twig` lines 90, 159 — homepage links to `app_job_offer_index`
- `src/Entity/User.php` lines 115-118, 459-483 — `$jobOffers` and `$jobApplications` collections

---

# STEP 2 — FEATURE MAP TABLE

| # | File/Path | Responsibility | Problems Found | Suggested Destination |
|---|-----------|---------------|----------------|-----------------------|
| 1 | `src/Controller/AdminJobOfferController.php` | Admin: list all offers + approve/reject/close/delete | **Fat controller**: raw QueryBuilder in `list()` (should be in repository). Duplicate CSRF+try/catch pattern in every action (4×). No service layer. Namespace `App\Controller` but should be `App\Controller\Admin`. | `src/Controller/Admin/AdminJobOfferController.php` (namespace fix) → delegate to `JobOfferService` |
| 2 | `src/Controller/Partner/PartnerJobOfferController.php` | Partner: CRUD + close/reopen/view-apps | **Fat controller**: manual ownership check via private `denyAccessUnlessOwner()` (no Voter). Manual `setPublishedAt`, `setUpdatedAt`, `setStatus`. Duplicate CSRF+try/catch. Injects `JobApplicationRepository` but only uses it in `applications()`. | Keep location, add Voter, delegate to `JobOfferService` |
| 3 | `src/Controller/Partner/PartnerJobApplicationController.php` | Partner: update application status | Ownership check duplicated from #2. No service. Otherwise clean. | Keep location, delegate to `JobApplicationService`, use Voter |
| 4 | `src/Controller/JobOfferController.php` | Student: browse + show + apply | **God-method `apply()`** (~70 lines): file upload, entity creation, persistence, duplicate check all inline. Uses `$this->getParameter('cv_files_directory')` instead of injected service. Class name generic (`JobOfferController`). | Rename to `src/Controller/Student/StudentJobOfferController.php`, extract logic to services |
| 5 | `src/Repository/JobOfferRepository.php` | Search offers | Returns `array` (no pagination). Missing `findByPartner()`, no `createAdminQueryBuilder()`. | Add pagination-ready `QueryBuilder` methods |
| 6 | `src/Repository/JobApplicationRepository.php` | Nothing | **Completely empty** — just boilerplate comments. | Add `findByOffer()`, `hasStudentApplied()` |
| 7 | `src/Form/JobOfferType.php` | Offer create/edit form | Class name `JobOfferType` **clashes** with `Enum\JobOfferType` (requires aliasing). Constraints in form duplicate what should be on entity. | Rename to `JobOfferFormType` |
| 8 | `src/Form/JobApplicationType.php` | Application form | Minor: no filter form exists for admin/student lists. | Rename to `JobApplicationFormType` for consistency |
| 9 | `src/Entity/JobOffer.php` | Doctrine entity | Good lifecycle callbacks. **Missing** `#[Assert\...]` constraints — validation relies solely on form. | Add entity constraints |
| 10 | `src/Entity/JobApplication.php` | Doctrine entity | Same: **no entity-level validation**. | Add entity constraints |
| 11 | `templates/admin/job_offer/list.html.twig` | Admin view | **No pagination**. Status badge block duplicated (same 4-line if/elseif block in 5 templates). Inline `style="display:inline;"` on 4 forms. | Extract `_status_badge.html.twig`, add pagination |
| 12 | `templates/job_offer/index.html.twig` | Student browse | **No pagination**. Flash messages block duplicated. | Add pagination |
| 13 | `templates/job_offer/show.html.twig` | Student detail | Status badge duplicated. | Use partial |
| 14 | `templates/partner/job_offer/index.html.twig` | Partner list | N+1: `offer.applications\|length` triggers lazy-load per row. Status badge duplicated. Inline styles. | Use `EXTRA_LAZY` or count subquery, use partial |
| 15 | `templates/partner/job_offer/applications.html.twig` | Partner apps view | Application status badge block (4-case if/elseif) is also duplicated. | Extract `_application_status_badge.html.twig` |
| 16 | `config/packages/security.yaml` | Access control | No `^/partner` access_control rule. Defense-in-depth relies only on `#[IsGranted]` attributes. No `role_hierarchy`. | Add `^/partner → ROLE_BUSINESS_PARTNER` rule + `role_hierarchy` |

---

# STEP 3 — ARCHITECTURE RECOMMENDATION

## Choice: **Option A — Classic Symfony Folders** ✅

### Why Option A (not B or C)?

| Option | Pros | Cons | Verdict |
|--------|------|------|---------|
| **A) Classic Symfony** | Standard PSR-4 `src/Controller/`, `src/Service/`, etc. Your teammates already know this. Symfony autowiring works out of the box. Easy onboarding. | Feature files scattered across multiple dirs. | ✅ **Best for a team project where not everyone is a DDD expert** |
| B) Full DDD | Clean domain boundaries, ports/adapters | Overkill for a university project. Breaks Symfony conventions. Teammates must learn DDD layering. Autowiring requires custom config. | ❌ |
| C) Hybrid feature module | Self-contained `src/JobOffer/` directory | Non-standard for Symfony. Requires custom autoload config. Confuses Doctrine entity mapping. Teammates will have different patterns per module. | ❌ |

**Justification:** This is a group project with multiple teammates building different modules. **You need predictability more than purity.** Classic Symfony structure means:
- No custom `services.yaml` exclusions
- Doctrine auto-scans `src/Entity/` without extra config
- Any Symfony developer can navigate the codebase
- Actor separation is achieved via sub-namespaces (`Controller/Admin/`, `Controller/Partner/`, `Controller/Student/`)

### Final Folder Tree

```
src/
├── Controller/
│   ├── Admin/
│   │   └── AdminJobOfferController.php          # MOVED from src/Controller/
│   ├── Partner/
│   │   ├── PartnerJobOfferController.php         # KEEP (already here)
│   │   └── PartnerJobApplicationController.php   # KEEP (already here)
│   └── Student/
│       └── StudentJobOfferController.php         # RENAMED from JobOfferController.php
│
├── Entity/                                        # NO CHANGES
│   ├── JobOffer.php                               # + add Assert constraints
│   └── JobApplication.php                         # + add Assert constraints
│
├── Enum/                                          # NO CHANGES
│   ├── JobOfferType.php
│   ├── JobOfferStatus.php
│   └── JobApplicationStatus.php
│
├── Repository/                                    # ENHANCE
│   ├── JobOfferRepository.php                     # + add admin QB, partner QB methods
│   └── JobApplicationRepository.php               # + add findByOffer(), hasStudentApplied()
│
├── Service/
│   └── JobOffer/
│       ├── JobOfferService.php                    # NEW: create, update, changeStatus, delete
│       ├── JobApplicationService.php              # NEW: apply, updateStatus, hasApplied
│       └── CvUploadService.php                    # NEW: file upload handling
│
├── Security/
│   └── Voter/
│       └── JobOfferVoter.php                      # NEW: ownership + role-based access
│
├── Form/
│   ├── JobOfferFormType.php                       # RENAMED from JobOfferType.php
│   └── JobApplicationFormType.php                 # RENAMED from JobApplicationType.php
│
templates/
├── job_offer/
│   ├── _offer_status_badge.html.twig              # NEW: reusable partial
│   ├── _application_status_badge.html.twig        # NEW: reusable partial
│   ├── index.html.twig                            # ENHANCE: + pagination
│   └── show.html.twig                             # ENHANCE: use partial
│
├── admin/
│   └── job_offer/
│       └── list.html.twig                         # ENHANCE: use partial, + pagination
│
├── partner/
│   └── job_offer/
│       ├── index.html.twig                        # ENHANCE: use partial
│       ├── new.html.twig                          # NO CHANGES
│       ├── edit.html.twig                         # ENHANCE: use partial
│       └── applications.html.twig                 # ENHANCE: use partial
│
tests/
├── Service/
│   ├── JobOfferServiceTest.php                    # NEW
│   └── JobApplicationServiceTest.php              # NEW
├── Security/
│   └── JobOfferVoterTest.php                      # NEW
└── Controller/
    ├── AdminJobOfferControllerTest.php            # NEW
    └── StudentJobOfferControllerTest.php          # NEW
```

---

# STEP 4 — AUTHORIZATION BOUNDARIES

## 4.1 Permissions Matrix (Actor × Action)

| Action | Admin (`ROLE_ADMIN`) | Partner (`ROLE_BUSINESS_PARTNER`) | Student (`ROLE_STUDENT`) |
|--------|:--------------------:|:---------------------------------:|:------------------------:|
| List ALL offers (admin view, any status) | ✅ | ❌ | ❌ |
| Filter by status/type/partner/location (admin) | ✅ | ❌ | ❌ |
| Approve offer (PENDING → ACTIVE) | ✅ | ❌ | ❌ |
| Reject offer (PENDING/ACTIVE → REJECTED) | ✅ | ❌ | ❌ |
| Close offer (admin, any offer) | ✅ | ❌ | ❌ |
| Delete offer (admin, any offer) | ✅ | ❌ | ❌ |
| Create new offer | ❌ | ✅ | ❌ |
| Edit own offer | ❌ | ✅ (owner only) | ❌ |
| Close own offer | ❌ | ✅ (owner only) | ❌ |
| Reopen own offer (CLOSED → ACTIVE) | ❌ | ✅ (owner only) | ❌ |
| Delete own offer | ❌ | ✅ (owner only) | ❌ |
| View own offer's applications | ❌ | ✅ (owner only) | ❌ |
| Update application status | ❌ | ✅ (offer owner) | ❌ |
| Browse ACTIVE offers | ✅ | ✅ | ✅ |
| View offer details | ✅ | ✅ | ✅ |
| Apply to ACTIVE offer (once per offer) | ❌ | ❌ | ✅ |

## 4.2 Recommended `security.yaml` Changes

```yaml
security:
    # ADD role hierarchy (currently absent)
    role_hierarchy:
        ROLE_ADMIN: [ROLE_USER]
        ROLE_BUSINESS_PARTNER: [ROLE_USER]
        ROLE_STUDENT: [ROLE_USER]

    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/partner, roles: ROLE_BUSINESS_PARTNER }     # ← ADD (defense-in-depth)
        - { path: ^/job-offer/\d+/apply$, roles: ROLE_STUDENT } # ← ADD (belt + suspenders)
        - { path: ^/profile, roles: ROLE_USER }
        - { path: ^/, roles: ROLE_USER }
```

## 4.3 Voter: `JobOfferVoter`

**Full implementation — `src/Security/Voter/JobOfferVoter.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\JobOffer;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class JobOfferVoter extends Voter
{
    public const EDIT   = 'JOB_OFFER_EDIT';
    public const DELETE = 'JOB_OFFER_DELETE';
    public const CLOSE  = 'JOB_OFFER_CLOSE';
    public const REOPEN = 'JOB_OFFER_REOPEN';
    public const VIEW_APPLICATIONS = 'JOB_OFFER_VIEW_APPLICATIONS';

    private const ATTRIBUTES = [
        self::EDIT,
        self::DELETE,
        self::CLOSE,
        self::REOPEN,
        self::VIEW_APPLICATIONS,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof JobOffer;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var JobOffer $offer */
        $offer = $subject;

        // Admin can do everything on any offer
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Partner can manage only their own offers
        if (in_array('ROLE_BUSINESS_PARTNER', $user->getRoles(), true)) {
            return $offer->getPartner() === $user;
        }

        return false;
    }
}
```

**Usage in controllers (replaces `denyAccessUnlessOwner()`):**
```php
// Before (PartnerJobOfferController):
$this->denyAccessUnlessOwner($offer);

// After:
$this->denyAccessUnlessGranted(JobOfferVoter::EDIT, $offer);
```

---

# STEP 5 — REFACTOR PLAN: 5 SAFE COMMITS

---

## COMMIT 1: Move/rename files + fix namespaces (NO logic changes)

### What & Why
- Move `AdminJobOfferController` from `App\Controller` to `App\Controller\Admin` (actor separation)
- Rename `JobOfferController` → `StudentJobOfferController` under `App\Controller\Student` (clarify actor)
- Rename `JobOfferType` form → `JobOfferFormType` (eliminate clash with `Enum\JobOfferType`)
- Rename `JobApplicationType` form → `JobApplicationFormType` (consistency)
- Update all `namespace` and `use` statements
- Zero logic changes — purely structural

### Terminal commands

```powershell
# Create directories
New-Item -ItemType Directory -Force -Path src\Controller\Admin
New-Item -ItemType Directory -Force -Path src\Controller\Student

# Move AdminJobOfferController into Admin/
git mv src/Controller/AdminJobOfferController.php src/Controller/Admin/AdminJobOfferController.php

# Move + rename student controller
git mv src/Controller/JobOfferController.php src/Controller/Student/StudentJobOfferController.php

# Rename form types
git mv src/Form/JobOfferType.php src/Form/JobOfferFormType.php
git mv src/Form/JobApplicationType.php src/Form/JobApplicationFormType.php

# Clear cache
php bin/console cache:clear
```

### Exact code changes per file

**File: `src/Controller/Admin/AdminJobOfferController.php`**
```diff
-namespace App\Controller;
+namespace App\Controller\Admin;
```
(line 3 — only namespace changes, everything else stays identical)

**File: `src/Controller/Student/StudentJobOfferController.php`**
```diff
-namespace App\Controller;
+namespace App\Controller\Student;

-use App\Form\JobApplicationType;
+use App\Form\JobApplicationFormType;

-class JobOfferController extends AbstractController
+class StudentJobOfferController extends AbstractController
```
And inside `show()`:
```diff
-$form = $this->createForm(JobApplicationType::class, $application, [
+$form = $this->createForm(JobApplicationFormType::class, $application, [
```
And inside `apply()`:
```diff
-$form = $this->createForm(JobApplicationType::class, $application);
+$form = $this->createForm(JobApplicationFormType::class, $application);
```

**File: `src/Form/JobOfferFormType.php`**
```diff
-class JobOfferType extends AbstractType
+class JobOfferFormType extends AbstractType
```

**File: `src/Form/JobApplicationFormType.php`**
```diff
-class JobApplicationType extends AbstractType
+class JobApplicationFormType extends AbstractType
```

**File: `src/Controller/Partner/PartnerJobOfferController.php`**
```diff
-use App\Form\JobOfferType;
+use App\Form\JobOfferFormType;
```
And in `new()` and `edit()`:
```diff
-$form = $this->createForm(JobOfferType::class, $offer);
+$form = $this->createForm(JobOfferFormType::class, $offer);
```

### Commit
```powershell
git add -A
git commit -m "refactor(job-offer): move controllers to actor folders, rename forms to avoid enum clash

- AdminJobOfferController → App\Controller\Admin
- JobOfferController → App\Controller\Student\StudentJobOfferController
- JobOfferType (form) → JobOfferFormType
- JobApplicationType (form) → JobApplicationFormType
- Updated all namespace and use statements
- No logic changes"
```

---

## COMMIT 2: Extract services & slim controllers

### What & Why
Create 3 services to extract ALL business logic from controllers:
1. `JobOfferService` — offer lifecycle (create, update, changeStatus, delete)
2. `JobApplicationService` — apply, update status, check duplicates
3. `CvUploadService` — file upload (currently inline in controller's `apply()`)

This eliminates duplicate code (CSRF+try/catch pattern repeated 9 times across 3 controllers), makes logic testable, and enforces SRP.

### New files to create

**File: `src/Service/JobOffer/JobOfferService.php`**
```php
<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferStatus;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JobOfferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JobOfferRepository $jobOfferRepository,
    ) {
    }

    public function createForPartner(JobOffer $offer, User $partner): void
    {
        $offer->setPartner($partner);
        $offer->setStatus(JobOfferStatus::ACTIVE);

        if ($offer->getPublishedAt() === null) {
            $offer->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->persist($offer);
        $this->em->flush();
    }

    public function update(JobOffer $offer): void
    {
        // updatedAt is set by PreUpdate lifecycle callback
        $this->em->flush();
    }

    public function changeStatus(JobOffer $offer, JobOfferStatus $newStatus): void
    {
        $offer->setStatus($newStatus);

        if ($newStatus === JobOfferStatus::ACTIVE && $offer->getPublishedAt() === null) {
            $offer->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    public function delete(JobOffer $offer): void
    {
        $this->em->remove($offer);
        $this->em->flush();
    }

    /** @return JobOffer[] */
    public function getPartnerOffers(User $partner): array
    {
        return $this->jobOfferRepository->findBy(
            ['partner' => $partner],
            ['createdAt' => 'DESC']
        );
    }
}
```

**File: `src/Service/JobOffer/CvUploadService.php`**
```php
<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CvUploadService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly string $cvFilesDirectory,
    ) {
    }

    /**
     * @throws FileException
     */
    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->cvFilesDirectory, $newFilename);

        return $newFilename;
    }
}
```

**File: `src/Service/JobOffer/JobApplicationService.php`**
```php
<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Repository\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JobApplicationRepository $applicationRepository,
        private readonly CvUploadService $cvUploadService,
    ) {
    }

    public function hasAlreadyApplied(JobOffer $offer, User $student): bool
    {
        return $this->applicationRepository->findOneBy([
            'offer' => $offer,
            'student' => $student,
        ]) !== null;
    }

    /**
     * @throws \LogicException if offer not active or student already applied
     */
    public function apply(
        JobApplication $application,
        JobOffer $offer,
        User $student,
        ?UploadedFile $cvFile = null,
    ): void {
        if ($offer->getStatus() !== JobOfferStatus::ACTIVE) {
            throw new \LogicException('This job offer is no longer accepting applications.');
        }

        if ($this->hasAlreadyApplied($offer, $student)) {
            throw new \LogicException('You have already applied to this job offer.');
        }

        if ($cvFile !== null) {
            $filename = $this->cvUploadService->upload($cvFile);
            $application->setCvFile($filename);
        }

        $application->setOffer($offer);
        $application->setStudent($student);
        $application->setStatus(JobApplicationStatus::SUBMITTED);

        $this->em->persist($application);
        $this->em->flush();
    }

    public function updateStatus(JobApplication $application, JobApplicationStatus $status): void
    {
        $application->setStatus($status);
        $this->em->flush();
    }

    /** @return JobApplication[] */
    public function getApplicationsForOffer(JobOffer $offer): array
    {
        return $this->applicationRepository->findBy(
            ['offer' => $offer],
            ['createdAt' => 'DESC']
        );
    }
}
```

### Wire `CvUploadService` in `config/services.yaml`

Add at the end of `services.yaml`:
```yaml
    App\Service\JobOffer\CvUploadService:
        arguments:
            $cvFilesDirectory: '%cv_files_directory%'
```

### Slimmed controllers (exact diffs)

**`AdminJobOfferController` — replace EntityManager with service:**
```diff
 public function __construct(
-    private EntityManagerInterface $entityManager,
-    private JobOfferRepository $jobOfferRepository
+    private readonly JobOfferRepository $jobOfferRepository,
+    private readonly JobOfferService $jobOfferService,
 ) {
 }
```

Each approve/reject/close/delete action becomes:
```php
// Example: approve() — from ~20 lines to ~8
public function approve(Request $request, JobOffer $offer): Response
{
    if ($this->isCsrfTokenValid('approve-' . $offer->getId(), $request->request->get('_token'))) {
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
        $this->addFlash('success', 'Job offer approved successfully.');
    } else {
        $this->addFlash('error', 'Invalid CSRF token.');
    }

    return $this->redirectToRoute('admin_job_offer_list');
}
```

**`PartnerJobOfferController` — inject services:**
```diff
 public function __construct(
-    private EntityManagerInterface $entityManager,
-    private JobOfferRepository $jobOfferRepository,
-    private JobApplicationRepository $jobApplicationRepository
+    private readonly JobOfferService $jobOfferService,
+    private readonly JobApplicationService $applicationService,
 ) {
 }
```

**`StudentJobOfferController` — inject services:**
```diff
 public function __construct(
-    private EntityManagerInterface $entityManager,
-    private JobOfferRepository $jobOfferRepository,
-    private JobApplicationRepository $jobApplicationRepository
+    private readonly JobOfferRepository $jobOfferRepository,
+    private readonly JobApplicationService $applicationService,
 ) {
 }
```

The `apply()` method shrinks from 70 lines to:
```php
public function apply(Request $request, JobOffer $offer): Response
{
    /** @var User $user */
    $user = $this->getUser();

    $application = new JobApplication();
    $form = $this->createForm(JobApplicationFormType::class, $application);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        try {
            $cvFile = $form->get('cvFile')->getData();
            $this->applicationService->apply($application, $offer, $user, $cvFile);
            $this->addFlash('success', 'Your application has been submitted successfully.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }
    } else {
        $this->addFlash('error', 'Please correct the errors in your application.');
    }

    return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
}
```

### Commit
```powershell
php bin/console cache:clear
git add -A
git commit -m "refactor(job-offer): extract service layer, slim all controllers

- NEW: JobOfferService (create, update, changeStatus, delete)
- NEW: JobApplicationService (apply, updateStatus, hasApplied)
- NEW: CvUploadService (file upload extracted from controller)
- Controllers now only: parse request → call service → flash + redirect
- Wired CvUploadService in services.yaml"
```

---

## COMMIT 3: Validation + forms cleanup + Voter

### What & Why
1. Add `#[Assert\...]` constraints to entities (single source of truth)
2. Create `JobOfferVoter` (replace manual `denyAccessUnlessOwner()`)
3. Add useful methods to `JobApplicationRepository`
4. Update `security.yaml` (add `role_hierarchy` + `^/partner` rule)

### Entity constraint additions

**`src/Entity/JobOffer.php` — add `use` + constraints:**
```diff
+use Symfony\Component\Validator\Constraints as Assert;

 #[ORM\Entity(repositoryClass: JobOfferRepository::class)]
 ...
 class JobOffer
 {
+    #[Assert\NotBlank(message: 'Title is required.')]
+    #[Assert\Length(max: 255)]
     #[ORM\Column(length: 255)]
     private ?string $title = null;

+    #[Assert\NotNull(message: 'Job type is required.')]
     #[ORM\Column(type: 'string', enumType: JobOfferType::class)]
     private ?JobOfferType $type = null;

+    #[Assert\Length(max: 255)]
     #[ORM\Column(length: 255, nullable: true)]
     private ?string $location = null;

+    #[Assert\NotBlank(message: 'Description is required.')]
     #[ORM\Column(type: Types::TEXT)]
     private ?string $description = null;
```

**`src/Entity/JobApplication.php` — add `use` + constraints:**
```diff
+use Symfony\Component\Validator\Constraints as Assert;

+    #[Assert\Length(max: 5000)]
     #[ORM\Column(type: Types::TEXT, nullable: true)]
     private ?string $message = null;
```

### Create `src/Security/Voter/JobOfferVoter.php`

(Full code provided in Step 4.3 above — copy as-is)

### Replace ownership checks in `PartnerJobOfferController`

```diff
-use App\Entity\JobOffer; // if not using Voter constants import
+use App\Security\Voter\JobOfferVoter;

 // In each action that had $this->denyAccessUnlessOwner($offer):
-$this->denyAccessUnlessOwner($offer);
+$this->denyAccessUnlessGranted(JobOfferVoter::EDIT, $offer);

 // Remove the private method entirely:
-private function denyAccessUnlessOwner(JobOffer $offer): void { ... }
```

Same pattern for `PartnerJobApplicationController`:
```diff
+use App\Security\Voter\JobOfferVoter;

-$this->denyAccessUnlessOwner($application);
+$this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $application->getOffer());

-private function denyAccessUnlessOwner(JobApplication $application): void { ... }
```

### Enhance `JobApplicationRepository`

```php
// Add to src/Repository/JobApplicationRepository.php:

/** @return JobApplication[] */
public function findByOffer(JobOffer $offer): array
{
    return $this->createQueryBuilder('a')
        ->andWhere('a.offer = :offer')
        ->setParameter('offer', $offer)
        ->leftJoin('a.student', 's')
        ->addSelect('s')
        ->orderBy('a.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}

public function hasStudentApplied(JobOffer $offer, User $student): bool
{
    return (bool) $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->andWhere('a.offer = :offer')
        ->andWhere('a.student = :student')
        ->setParameter('offer', $offer)
        ->setParameter('student', $student)
        ->getQuery()
        ->getSingleScalarResult();
}
```

### Update `security.yaml`

```diff
+    role_hierarchy:
+        ROLE_ADMIN: [ROLE_USER]
+        ROLE_BUSINESS_PARTNER: [ROLE_USER]
+        ROLE_STUDENT: [ROLE_USER]
+
     access_control:
         - { path: ^/login, roles: PUBLIC_ACCESS }
         - { path: ^/admin, roles: ROLE_ADMIN }
+        - { path: ^/partner, roles: ROLE_BUSINESS_PARTNER }
         - { path: ^/profile, roles: ROLE_USER }
         - { path: ^/, roles: ROLE_USER }
```

### Commit
```powershell
php bin/console cache:clear
git add -A
git commit -m "refactor(job-offer): add entity constraints, create JobOfferVoter, enhance repository

- Added Assert constraints on JobOffer (title, type, description) and JobApplication (message length)
- NEW: JobOfferVoter with EDIT/DELETE/CLOSE/REOPEN/VIEW_APPLICATIONS
- Replaced manual denyAccessUnlessOwner() with denyAccessUnlessGranted()
- Enhanced JobApplicationRepository: findByOffer(), hasStudentApplied()
- Updated security.yaml: role_hierarchy + ^/partner access_control"
```

---

## COMMIT 4: Templates normalization + partials + pagination

### What & Why
1. Extract duplicated status badge blocks into reusable Twig partials
2. Add pagination (using Doctrine's built-in `Paginator` — no extra bundle needed)
3. Remove inline `style=""` attributes

### Create template partials

**`templates/job_offer/_offer_status_badge.html.twig`:**
```twig
{# Usage: {% include 'job_offer/_offer_status_badge.html.twig' with {status: offer.status} %} #}
{% if status.value == 'ACTIVE' %}
    <span class="badge bg-success">Active</span>
{% elseif status.value == 'PENDING' %}
    <span class="badge bg-warning text-dark">Pending</span>
{% elseif status.value == 'CLOSED' %}
    <span class="badge bg-secondary">Closed</span>
{% elseif status.value == 'REJECTED' %}
    <span class="badge bg-danger">Rejected</span>
{% endif %}
```

**`templates/job_offer/_application_status_badge.html.twig`:**
```twig
{# Usage: {% include 'job_offer/_application_status_badge.html.twig' with {status: application.status} %} #}
{% if status.value == 'SUBMITTED' %}
    <span class="badge bg-info">Submitted</span>
{% elseif status.value == 'REVIEWED' %}
    <span class="badge bg-warning text-dark">Reviewed</span>
{% elseif status.value == 'ACCEPTED' %}
    <span class="badge bg-success">Accepted</span>
{% elseif status.value == 'REJECTED' %}
    <span class="badge bg-danger">Rejected</span>
{% endif %}
```

### Add pagination to `JobOfferRepository`

```php
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Returns paginated search results
 */
public function searchPaginated(
    ?string $q,
    ?JobOfferType $type,
    ?string $location,
    ?JobOfferStatus $status = JobOfferStatus::ACTIVE,
    int $page = 1,
    int $limit = 12,
): Paginator {
    $qb = $this->createQueryBuilder('o');

    if ($status !== null) {
        $qb->andWhere('o.status = :status')->setParameter('status', $status);
    }
    if ($q !== null && $q !== '') {
        $qb->andWhere('o.title LIKE :query OR o.description LIKE :query')
            ->setParameter('query', '%' . $q . '%');
    }
    if ($type !== null) {
        $qb->andWhere('o.type = :type')->setParameter('type', $type);
    }
    if ($location !== null && $location !== '') {
        $qb->andWhere('o.location LIKE :location')
            ->setParameter('location', '%' . $location . '%');
    }

    $qb->orderBy('o.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    return new Paginator($qb->getQuery(), fetchJoinCollection: true);
}
```

### Replace badge blocks in templates

In each template where you see the 4-line if/elseif status block, replace with:
```twig
{% include 'job_offer/_offer_status_badge.html.twig' with {status: offer.status} only %}
```

For application status:
```twig
{% include 'job_offer/_application_status_badge.html.twig' with {status: application.status} only %}
```

### Commit
```powershell
php bin/console cache:clear
git add -A
git commit -m "refactor(job-offer): extract template partials, add pagination, clean up styles

- NEW: _offer_status_badge.html.twig (replaces 5 duplicated blocks)
- NEW: _application_status_badge.html.twig (replaces 1 duplicated block)
- Added searchPaginated() to JobOfferRepository
- Replaced inline style= attributes with Bootstrap classes
- Templates now use {% include %} for status badges"
```

---

## COMMIT 5: Minimum critical tests

### What & Why
Zero tests currently exist. Add unit tests for services + voter to cover critical paths.

### Test files to create

**`tests/Service/JobOfferServiceTest.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\JobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class JobOfferServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private JobOfferRepository $repo;
    private JobOfferService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(JobOfferRepository::class);
        $this->service = new JobOfferService($this->em, $this->repo);
    }

    public function testCreateForPartnerSetsStatusPartnerAndPublishedAt(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setTitle('Test Offer');
        $offer->setDescription('Description');
        $offer->setType(JobOfferType::INTERNSHIP);

        $partner = new User();
        $this->service->createForPartner($offer, $partner);

        $this->assertSame($partner, $offer->getPartner());
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());
    }

    public function testChangeStatusToActive(): void
    {
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::PENDING);

        $this->service->changeStatus($offer, JobOfferStatus::ACTIVE);

        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());
    }

    public function testChangeStatusToClosed(): void
    {
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);

        $this->service->changeStatus($offer, JobOfferStatus::CLOSED);

        $this->assertSame(JobOfferStatus::CLOSED, $offer->getStatus());
    }

    public function testDelete(): void
    {
        $offer = new JobOffer();

        $this->em->expects($this->once())->method('remove')->with($offer);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($offer);
    }
}
```

**`tests/Service/JobApplicationServiceTest.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobApplicationRepository;
use App\Service\JobOffer\CvUploadService;
use App\Service\JobOffer\JobApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class JobApplicationServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private JobApplicationRepository $repo;
    private CvUploadService $cvService;
    private JobApplicationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(JobApplicationRepository::class);
        $this->cvService = $this->createMock(CvUploadService::class);
        $this->service = new JobApplicationService($this->em, $this->repo, $this->cvService);
    }

    public function testApplySuccess(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);
        $offer->setType(JobOfferType::JOB);
        $offer->setTitle('Test');
        $offer->setDescription('Desc');

        $student = new User();

        $this->repo->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $application = new JobApplication();
        $this->service->apply($application, $offer, $student);

        $this->assertSame($offer, $application->getOffer());
        $this->assertSame($student, $application->getStudent());
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
    }

    public function testApplyThrowsWhenOfferNotActive(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::CLOSED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no longer accepting');

        $this->service->apply(new JobApplication(), $offer, new User());
    }

    public function testApplyThrowsOnDuplicate(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);

        $this->repo->method('findOneBy')->willReturn(new JobApplication());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already applied');

        $this->service->apply(new JobApplication(), $offer, new User());
    }
}
```

**`tests/Security/JobOfferVoterTest.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Security\Voter\JobOfferVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class JobOfferVoterTest extends TestCase
{
    private JobOfferVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new JobOfferVoter();
    }

    public function testAdminCanEditAnyOffer(): void
    {
        $admin = new User();
        $admin->setRole('ADMIN');

        $offer = new JobOffer();
        $offer->setPartner(new User()); // different user

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testPartnerCanEditOwnOffer(): void
    {
        $partner = new User();
        $partner->setRole('BUSINESS_PARTNER');

        $offer = new JobOffer();
        $offer->setPartner($partner);

        $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testPartnerCannotEditOtherOffer(): void
    {
        $partner = new User();
        $partner->setRole('BUSINESS_PARTNER');

        $otherPartner = new User();
        $otherPartner->setRole('BUSINESS_PARTNER');

        $offer = new JobOffer();
        $offer->setPartner($otherPartner);

        $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testStudentCannotEditOffer(): void
    {
        $student = new User();
        $student->setRole('STUDENT');

        $offer = new JobOffer();
        $offer->setPartner(new User());

        $token = new UsernamePasswordToken($student, 'main', $student->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }
}
```

### Run tests
```powershell
php bin/phpunit tests/Service/JobOfferServiceTest.php
php bin/phpunit tests/Service/JobApplicationServiceTest.php
php bin/phpunit tests/Security/JobOfferVoterTest.php
```

### Commit
```powershell
git add -A
git commit -m "test(job-offer): add unit tests for services and voter

- JobOfferServiceTest: create, changeStatus, delete (4 tests)
- JobApplicationServiceTest: apply success, not-active, duplicate (3 tests)
- JobOfferVoterTest: admin/partner-own/partner-other/student (4 tests)
- 11 tests total covering critical job-offer paths"
```

---

# STEP 6 — CODE QUALITY CHECKLIST

## 6.1 Doctrine Query Optimization

| Issue | Current State | Fix Required |
|-------|---------------|-------------|
| N+1 on `partner` in admin list | ✅ OK — uses `leftJoin('o.partner', 'p')->addSelect('p')` | None |
| N+1 on `applications` count in partner list | 🔴 Template does `offer.applications\|length` → lazy-load all applications per row | Change `OneToMany` to `fetch: 'EXTRA_LAZY'` so `.count()` uses `COUNT()` query, OR add a count subquery in repository |
| N+1 on `student` in applications list | 🔴 Template accesses `application.student.profile.fullName` | Add `leftJoin('a.student', 's')->addSelect('s')` in `findByOffer()` — already done in Commit 3 repository enhancement |
| Missing pagination | 🔴 All lists load ALL records | Add `searchPaginated()` — done in Commit 4 |
| Indexes | ✅ Good — type, status, location, published_at, expires_at already indexed | None |

**Recommended EXTRA_LAZY addition:**
```diff
 // src/Entity/JobOffer.php
-#[ORM\OneToMany(targetEntity: JobApplication::class, mappedBy: 'offer', orphanRemoval: true, cascade: ['persist', 'remove'])]
+#[ORM\OneToMany(targetEntity: JobApplication::class, mappedBy: 'offer', orphanRemoval: true, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
 private Collection $applications;
```
This makes `$offer->getApplications()->count()` execute `SELECT COUNT(*)` instead of hydrating all applications.

## 6.2 Pagination Strategy

| View | Items per page | Method |
|------|---------------|--------|
| Student browse (`/job-offer`) | 12 | `searchPaginated()` |
| Admin list (`/admin/job-offer`) | 25 | Admin-specific paginated QB |
| Partner offers (`/partner/job-offer`) | 15 | `findBy()` → switch to paginated |
| Partner applications | 20 | `findByOffer()` → paginate |

Use Doctrine's built-in `Paginator` (no extra bundle required). Template pagination links can use a simple macro or partial.

## 6.3 Validation Strategy

| Layer | What belongs here |
|-------|-------------------|
| Entity (`#[Assert\...]`) | `NotBlank`, `NotNull`, `Length`, `Type` — business rules that apply regardless of input source |
| Form Type (`constraints` option) | `Assert\File` (upload-specific), presentation-level constraints that may differ per form context |
| Service layer | Business logic validation: "is offer active?", "has student already applied?" → throw `\LogicException` |

**Rule:** Entity is single source of truth. Forms may add constraints but never be the ONLY place constraints exist.

## 6.4 Error Handling + Flash Messages

| Current Problem | Fix |
|----------------|-----|
| Generic `catch (\Exception $e)` with `$e->getMessage()` exposed to user | Use specific exceptions (`\LogicException`, `FileException`). Log full exception, show generic message to user. |
| Hardcoded strings throughout | Consider translation keys: `job_offer.flash.approved`, `job_offer.flash.error.csrf_invalid` |
| No logging | Add `LoggerInterface` to services, log errors at `error` level |

**Pattern for controllers after refactoring:**
```php
try {
    $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
    $this->addFlash('success', 'Job offer approved successfully.');
} catch (\LogicException $e) {
    $this->addFlash('error', $e->getMessage());
}
```

## 6.5 CS Fixer + PHPStan

**`.php-cs-fixer.dist.php`:**
```php
<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'final_class' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
```

**`phpstan.neon`:**
```neon
parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - src/Kernel.php
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
```

**Install commands:**
```powershell
composer require --dev friendsofphp/php-cs-fixer phpstan/phpstan phpstan/phpstan-symfony
php vendor/bin/php-cs-fixer fix --dry-run --diff
php vendor/bin/phpstan analyse
```

## 6.6 Naming Conventions

| Current | Recommended | Why |
|---------|------------|-----|
| `JobOfferType` (form) | `JobOfferFormType` | Avoids clash with `Enum\JobOfferType` |
| `JobApplicationType` (form) | `JobApplicationFormType` | Consistency with above |
| `JobOfferController` | `StudentJobOfferController` | Clarifies which actor's controller |
| `AdminJobOfferController` (in root `Controller/`) | `Admin\AdminJobOfferController` | Actor sub-namespace |
| Route `admin_job_offer_list` | Keep or rename to `app_admin_job_offer_list` | Optional prefix consistency |

## 6.7 Service Layering & DI Best Practices

| Rule | Current Status | After Refactor |
|------|---------------|----------------|
| Controllers call only services, never EntityManager | 🔴 All controllers inject EM | ✅ Controllers inject only services |
| Services are `final` with `readonly` constructor args | 🔴 N/A | ✅ All 3 new services are `final` |
| Repositories handle queries only | ✅ OK | ✅ Enhanced with useful methods |
| Voters handle authorization | 🔴 Manual private method | ✅ `JobOfferVoter` |
| File upload in dedicated service | 🔴 Inline in controller | ✅ `CvUploadService` |
| Parameters injected via DI, not `getParameter()` | 🔴 `$this->getParameter('cv_files_directory')` | ✅ Injected into `CvUploadService` constructor |
| Entity constraints are source of truth | 🔴 Constraints only in forms | ✅ Entity `#[Assert\...]` attributes |

---

# SUMMARY — Execution Order

| Commit | Duration | Risk | Impact |
|--------|----------|------|--------|
| 1. Move/rename files + namespaces | 15 min | Very Low (no logic) | Structural clarity |
| 2. Extract 3 services, slim 4 controllers | 45 min | Medium | Biggest quality improvement |
| 3. Entity constraints + Voter + repository | 30 min | Low | Security + validation hardening |
| 4. Template partials + pagination | 30 min | Low | DRY templates + performance |
| 5. Unit tests (11 tests) | 45 min | None | Safety net for future changes |

**Total: ~2.5–3 hours for a clean, testable, scalable Job Offer module.**
