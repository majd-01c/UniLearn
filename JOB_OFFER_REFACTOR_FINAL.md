# Job Offer Module — Complete Refactoring Plan (Final)

> **Date:** 2026-02-11 | **Symfony 6.x** | **3 Actors:** Admin, Business Partner, Student  
> **Confirmed Roles:** `ROLE_ADMIN`, `ROLE_BUSINESS_PARTNER`, `ROLE_STUDENT`  
> (`User.role` stores `BUSINESS_PARTNER`, `getRoles()` prepends `ROLE_`)

---

## 1. INVENTORY: Job Offer Files Found

### 1.1 Entities (Doctrine ORM)

| # | File | Description |
|---|------|-------------|
| 1 | `src/Entity/JobOffer/JobOffer.php` | Namespace: `App\Entity` (⚠️ mismatch with subfolder). 250 lines. Fields: id, title, type (enum), location, description, requirements, status (enum), createdAt, updatedAt, publishedAt, expiresAt, partner (ManyToOne→User), applications (OneToMany→JobApplication). Lifecycle: PrePersist/PreUpdate. Indexes: type, status, location, published_at, expires_at. |
| 2 | `src/Entity/JobOffer/JobApplication.php` | Namespace: `App\Entity` (⚠️ mismatch). Vich Uploadable. Fields: id, offer (ManyToOne→JobOffer), student (ManyToOne→User), message, cvFile/cvFileName, status (enum), createdAt, updatedAt. UniqueConstraint(offer_id, student_id). |

### 1.2 Enums

| # | File | Values |
|---|------|--------|
| 3 | `src/Enum/JobOfferType.php` | INTERNSHIP, APPRENTICESHIP, JOB |
| 4 | `src/Enum/JobOfferStatus.php` | PENDING, ACTIVE, REJECTED, CLOSED |
| 5 | `src/Enum/JobApplicationStatus.php` | SUBMITTED, REVIEWED, ACCEPTED, REJECTED |
| — | `src/Enum/Role.php` | ADMIN, STUDENT, TEACHER, BUSINESS_PARTNER (**shared — DO NOT touch**) |

### 1.3 Repositories

| # | File | Methods |
|---|------|---------|
| 6 | `src/Repository/JobOfferRepository.php` | `search()`, `searchPaginated()`, `searchAdminPaginated()` — all 3 methods present with Doctrine Paginator |
| 7 | `src/Repository/JobApplicationRepository.php` | `findByOffer()` (with student join), `hasStudentApplied()` (COUNT query) |

### 1.4 Controllers

| # | File | Guard | Route Prefix | Actions |
|---|------|-------|-------------|---------|
| 8 | `src/Controller/Admin/AdminJobOfferController.php` | `#[IsGranted('ROLE_ADMIN')]` | `/admin/job-offer` | list, approve, reject, close, delete |
| 9 | `src/Controller/Partner/PartnerJobOfferController.php` | `#[IsGranted('ROLE_BUSINESS_PARTNER')]` | `/partner/job-offer` | index, new, edit, close, reopen, delete, applications |
| 10 | `src/Controller/Partner/PartnerJobApplicationController.php` | `#[IsGranted('ROLE_BUSINESS_PARTNER')]` | `/partner/job-application` | updateStatus |
| 11 | `src/Controller/Student/StudentJobOfferController.php` | None (class) / `ROLE_STUDENT` on apply | `/job-offer` | index, show, apply |

### 1.5 Services

| # | File | Methods |
|---|------|---------|
| 12 | `src/Service/JobOffer/JobOfferService.php` | `createForPartner()`, `update()`, `changeStatus()`, `delete()`, `getPartnerOffers()` |
| 13 | `src/Service/JobOffer/JobApplicationService.php` | `hasAlreadyApplied()`, `apply()`, `updateStatus()`, `getApplicationsForOffer()` |
| — | `CvUploadService.php` | **Referenced in tests but FILE DOES NOT EXIST** — test instantiates it manually. Dead reference. |

### 1.6 Security / Voters

| # | File | Attributes |
|---|------|------------|
| 14 | `src/Security/Voter/JobOfferVoter.php` | EDIT, DELETE, CLOSE, REOPEN, VIEW_APPLICATIONS. Admin→all, Partner→own only. |

### 1.7 Forms

| # | File | Bound Entity |
|---|------|-------------|
| 15 | `src/Form/JobOfferFormType.php` | JobOffer — with inline constraints (duplicating entity constraints) |
| 16 | `src/Form/JobApplicationFormType.php` | JobApplication — message + cvFile (VichFileType) |

### 1.8 Twig Templates

| # | File | Actor |
|---|------|-------|
| 17 | `templates/Gestion_Job_Offre/job_offer/index.html.twig` | Student — browse active offers (card grid) |
| 18 | `templates/Gestion_Job_Offre/job_offer/show.html.twig` | Student — detail + inline apply form |
| 19 | `templates/Gestion_Job_Offre/job_offer/_offer_status_badge.html.twig` | Shared partial |
| 20 | `templates/Gestion_Job_Offre/job_offer/_application_status_badge.html.twig` | Shared partial |
| 21 | `templates/Gestion_Job_Offre/job_offer/_pagination.html.twig` | Shared partial |
| 22 | `templates/Gestion_Job_Offre/partner/job_offer/index.html.twig` | Partner — own offers list |
| 23 | `templates/Gestion_Job_Offre/partner/job_offer/new.html.twig` | Partner — create form |
| 24 | `templates/Gestion_Job_Offre/partner/job_offer/edit.html.twig` | Partner — edit form |
| 25 | `templates/Gestion_Job_Offre/partner/job_offer/applications.html.twig` | Partner — applications list |
| 26 | `templates/Gestion_user/admin/job_offer/list.html.twig` | Admin — manage all (⚠️ in wrong folder!) |

### 1.9 Tests

| # | File | Coverage |
|---|------|----------|
| 27 | `tests/Service/JobOfferServiceTest.php` | Unit tests for create, changeStatus, delete |
| 28 | `tests/Service/JobApplicationServiceTest.php` | Unit tests for apply (success, inactive, duplicate). ⚠️ Imports non-existent `CvUploadService`. |
| 29 | `tests/Security/JobOfferVoterTest.php` | Unit tests for voter: admin/partner/student |

### 1.10 Config

| # | File | Relevant |
|---|------|----------|
| 30 | `config/packages/security.yaml` | `access_control: ^/admin → ROLE_ADMIN`, `^/partner → ROLE_BUSINESS_PARTNER`. Role hierarchy defined. |
| 31 | `config/packages/vich_uploader.yaml` | `cv_files` mapping configured |

### 1.11 Migrations

| # | File |
|---|------|
| 32 | `migrations/Version20260204223035.php` |
| 33 | `migrations/Version20260209125707.php` |

---

## 2. FEATURE MAP TABLE

| # | File/Path | Responsibility | Problems Found | Suggested Action |
|---|-----------|---------------|----------------|------------------|
| 1 | `src/Entity/JobOffer/JobOffer.php` | Job Offer entity | ⚠️ **Namespace `App\Entity` doesn't match subfolder `Entity/JobOffer/`** — autoload won't break (PSR-4 maps `App\` → `src/`) but a class in `Entity/JobOffer/` with namespace `App\Entity` works only because it's the root namespace. Confusing. | Keep file in subfolder, keep namespace `App\Entity` — this is fine for Symfony conventions. Alternatively flatten to `src/Entity/JobOffer.php`. |
| 2 | `src/Entity/JobOffer/JobApplication.php` | Job Application entity | Same namespace vs folder issue. Uses `Vich\Uploadable` directly — good. | Same as above. |
| 3 | `src/Repository/JobOfferRepository.php` | Query builder for offers | ✅ Has 3 methods with pagination — well structured. Minor: `search()` method is unused (superseded by `searchPaginated()`). | Remove dead `search()` method. |
| 4 | `src/Repository/JobApplicationRepository.php` | Query builder for applications | Has `findByOffer()` and `hasStudentApplied()`, but service uses `findOneBy()` instead of `hasStudentApplied()`. | Refactor service to use `hasStudentApplied()` for efficiency (COUNT vs full hydration). |
| 5 | `src/Controller/Admin/AdminJobOfferController.php` | Admin CRUD for offers | ✅ Clean, thin controller. Uses service layer and CSRF. No Voter (relies on `#[IsGranted('ROLE_ADMIN')]`). | Good as-is. Could use Voter for consistency but `ROLE_ADMIN` is sufficient. |
| 6 | `src/Controller/Partner/PartnerJobOfferController.php` | Partner CRUD for offers | ✅ Uses Voter for ownership checks. Clean. | Good as-is. |
| 7 | `src/Controller/Partner/PartnerJobApplicationController.php` | Update application status | ✅ Clean. | Good as-is. |
| 8 | `src/Controller/Student/StudentJobOfferController.php` | Browse/apply | ✅ Clean. Creates form in `show()` — acceptable. | Good as-is. |
| 9 | `src/Service/JobOffer/JobOfferService.php` | Offer business logic | ✅ Clean service layer. `final` class. | Good. |
| 10 | `src/Service/JobOffer/JobApplicationService.php` | Application business logic | ⚠️ Uses `findOneBy()` for duplicate check instead of optimized `hasStudentApplied()`. | Switch to repo's COUNT-based method. |
| 11 | `src/Security/Voter/JobOfferVoter.php` | Object-level authorization | ✅ Comprehensive. Covers 5 attributes. | Good as-is. |
| 12 | `src/Form/JobOfferFormType.php` | Offer creation form | ⚠️ **Duplicated constraints** — form adds `NotBlank`/`Length` that already exist on entity. | Remove form-level constraints, rely on entity validation. |
| 13 | `src/Form/JobApplicationFormType.php` | Application form | ✅ Clean. VichFileType for CV. | Good as-is. |
| 14 | `templates/Gestion_user/admin/job_offer/list.html.twig` | Admin list view | ⚠️ **Wrong folder** — admin job offer template is under `Gestion_user` instead of `Gestion_Job_Offre`. | Move to `templates/Gestion_Job_Offre/admin/job_offer/list.html.twig`. |
| 15 | `templates/Gestion_Job_Offre/**` | All other templates | ✅ Well organized. Partials (`_badge`, `_pagination`) are reusable. | Good structure. |
| 16 | `tests/Service/JobApplicationServiceTest.php` | Application service tests | ⚠️ **References non-existent `CvUploadService`** — will throw error if constructor changed. | Fix test to match actual service constructor signature. |
| 17 | `CvUploadService.php` | CV upload handling | ❌ **File doesn't exist** but referenced in test. App uses VichUploaderBundle instead. | Remove dead reference from test. Not needed since Vich handles uploads. |
| 18 | `src/Repository/JobOfferRepository.php` `search()` | Dead method | ⚠️ `search()` is never called anywhere — `searchPaginated()` replaced it. | Delete the unused method. |

---

## 3. RECOMMENDED ARCHITECTURE

### Chosen: **Option A — Classic Symfony Feature Folders** (with module-scoped subfolders)

**Justification:** The codebase already follows this pattern (entities in `Entity/JobOffer/`, controllers in `Controller/Admin/`, `Controller/Partner/`, `Controller/Student/`, services in `Service/JobOffer/`). A DDD option would require a massive namespace change that would break teammates' imports and bring no practical benefit for a feature of this size. A hybrid approach is overkill.

**The good news:** Your codebase is already 90% correctly structured after the previous refactoring effort. The remaining work is cleanup, not restructuring.

### Current vs Target Folder Tree

```
src/
├── Controller/
│   ├── Admin/
│   │   └── AdminJobOfferController.php          ✅ KEEP (already correct)
│   ├── Partner/
│   │   ├── PartnerJobOfferController.php         ✅ KEEP
│   │   └── PartnerJobApplicationController.php   ✅ KEEP
│   └── Student/
│       └── StudentJobOfferController.php         ✅ KEEP
│
├── Entity/
│   └── JobOffer/
│       ├── JobOffer.php                          ✅ KEEP (namespace App\Entity)
│       └── JobApplication.php                    ✅ KEEP
│
├── Enum/
│   ├── JobOfferType.php                          ✅ KEEP
│   ├── JobOfferStatus.php                        ✅ KEEP
│   └── JobApplicationStatus.php                  ✅ KEEP
│
├── Form/
│   ├── JobOfferFormType.php                      🔧 FIX: remove duplicate constraints
│   └── JobApplicationFormType.php                ✅ KEEP
│
├── Repository/
│   ├── JobOfferRepository.php                    🔧 FIX: remove dead search() method
│   └── JobApplicationRepository.php              ✅ KEEP
│
├── Security/
│   └── Voter/
│       └── JobOfferVoter.php                     ✅ KEEP
│
└── Service/
    └── JobOffer/
        ├── JobOfferService.php                   ✅ KEEP
        └── JobApplicationService.php             🔧 FIX: use hasStudentApplied()

templates/
└── Gestion_Job_Offre/
    ├── admin/
    │   └── job_offer/
    │       └── list.html.twig                    🔧 MOVE from Gestion_user/admin/
    ├── partner/
    │   └── job_offer/
    │       ├── index.html.twig                   ✅ KEEP
    │       ├── new.html.twig                     ✅ KEEP
    │       ├── edit.html.twig                    ✅ KEEP
    │       └── applications.html.twig            ✅ KEEP
    └── job_offer/
        ├── index.html.twig                       ✅ KEEP
        ├── show.html.twig                        ✅ KEEP
        ├── _offer_status_badge.html.twig         ✅ KEEP
        ├── _application_status_badge.html.twig   ✅ KEEP
        └── _pagination.html.twig                 ✅ KEEP

tests/
├── Service/
│   ├── JobOfferServiceTest.php                   ✅ KEEP
│   └── JobApplicationServiceTest.php             🔧 FIX: remove CvUploadService ref
└── Security/
    └── JobOfferVoterTest.php                     ✅ KEEP
```

---

## 4. PERMISSIONS MATRIX + SECURITY RULES

### 4A. Permissions Matrix (Actor × Action)

| Action | ROLE_ADMIN | ROLE_BUSINESS_PARTNER | ROLE_STUDENT |
|--------|:----------:|:---------------------:|:------------:|
| **List all offers (admin view)** | ✅ | ❌ | ❌ |
| **Approve offer** | ✅ | ❌ | ❌ |
| **Reject offer** | ✅ | ❌ | ❌ |
| **Close any offer** | ✅ | ❌ | ❌ |
| **Delete any offer** | ✅ | ❌ | ❌ |
| **Create own offer** | ❌ | ✅ | ❌ |
| **Edit own offer** | ❌ | ✅ (Voter) | ❌ |
| **Close own offer** | ❌ | ✅ (Voter) | ❌ |
| **Reopen own offer** | ❌ | ✅ (Voter) | ❌ |
| **Delete own offer** | ❌ | ✅ (Voter) | ❌ |
| **View own applications** | ❌ | ✅ (Voter) | ❌ |
| **Update application status** | ❌ | ✅ (Voter) | ❌ |
| **Browse active offers** | ✅ | ✅ | ✅ |
| **View offer details** | ✅ | ✅ | ✅ |
| **Apply to offer** | ❌ | ❌ | ✅ |

### 4B. Security Configuration (Already Correct)

```yaml
# config/packages/security.yaml (ALREADY IN PLACE — no changes needed)
access_control:
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/partner, roles: ROLE_BUSINESS_PARTNER }
    - { path: ^/profile, roles: ROLE_USER }
    - { path: ^/, roles: ROLE_USER }
```

**Assessment:** The current setup is correct:
- `/admin/job-offer/*` → covered by `^/admin` + `#[IsGranted('ROLE_ADMIN')]`  
- `/partner/job-offer/*` → covered by `^/partner` + `#[IsGranted('ROLE_BUSINESS_PARTNER')]`  
- `/job-offer/*` → accessible by any `ROLE_USER` (students browse), `apply` further restricted by `#[IsGranted('ROLE_STUDENT')]`

### 4C. Voter (Already Correct)

`JobOfferVoter` handles 5 attributes for object-level ownership checks:
- `JOB_OFFER_EDIT`, `JOB_OFFER_DELETE`, `JOB_OFFER_CLOSE`, `JOB_OFFER_REOPEN`, `JOB_OFFER_VIEW_APPLICATIONS`
- Admin → always granted
- Partner → only if `$offer->getPartner() === $user`
- Everyone else → denied

**No additional voters needed.** The current setup is complete and correct.

---

## 5. REFACTOR PLAN — 5 Safe Commits

> **Key finding:** The codebase is already well-structured from a prior refactoring effort. The remaining work is cleanup, not major restructuring. Each commit is low-risk.

---

### Commit 1: Move admin template + fix template reference
**What:** Move the admin job offer list template from the wrong folder to the correct one.  
**Why:** `list.html.twig` is under `Gestion_user/admin/` but belongs under `Gestion_Job_Offre/admin/`.  
**Risk:** Low — only 1 template + 1 controller render path.

**Steps:**

```bash
# 1. Create target directory
mkdir -p templates/Gestion_Job_Offre/admin/job_offer

# 2. Move the file
mv templates/Gestion_user/admin/job_offer/list.html.twig templates/Gestion_Job_Offre/admin/job_offer/list.html.twig

# 3. Remove empty directory (if no other files)
rmdir templates/Gestion_user/admin/job_offer 2>/dev/null
```

**File changed:** `src/Controller/Admin/AdminJobOfferController.php`

```diff
- return $this->render('Gestion_user/admin/job_offer/list.html.twig', [
+ return $this->render('Gestion_Job_Offre/admin/job_offer/list.html.twig', [
```

**Verify:**
```bash
php bin/console cache:clear
php bin/console router:match /admin/job-offer
# Visit /admin/job-offer in browser — should render correctly
```

**Commit message:**
```
refactor(job-offer): move admin template to Gestion_Job_Offre folder

Moves list.html.twig from templates/Gestion_user/admin/job_offer/
to templates/Gestion_Job_Offre/admin/job_offer/ for consistency.
Updates controller render path.
```

---

### Commit 2: Clean up services (use optimized repo method + remove dead code)
**What:** 
1. `JobApplicationService::hasAlreadyApplied()` — use `$repo->hasStudentApplied()` (COUNT query) instead of `findOneBy()` (full hydration).
2. `JobOfferRepository` — remove unused `search()` method.  
**Why:** Performance (avoid hydrating a full entity for a boolean check) + dead code removal.  
**Risk:** Very low — behavior unchanged, only internal optimization.

**File 1:** `src/Service/JobOffer/JobApplicationService.php`

```diff
  public function hasAlreadyApplied(JobOffer $offer, User $student): bool
  {
-     return $this->applicationRepository->findOneBy([
-         'offer' => $offer,
-         'student' => $student,
-     ]) !== null;
+     return $this->applicationRepository->hasStudentApplied($offer, $student);
  }
```

**File 2:** `src/Repository/JobOfferRepository.php`

```diff
  // DELETE the entire search() method (lines 31-70 approximately)
- /**
-  * Search job offers with filters
-  * ...
-  */
- public function search(
-     ?string $q,
-     ?JobOfferType $type,
-     ?string $location,
-     ?JobOfferStatus $status = JobOfferStatus::ACTIVE
- ): array {
-     // ... entire method body ...
- }
```

**Verify:**
```bash
php bin/console cache:clear
php bin/phpunit tests/Service/JobApplicationServiceTest.php
php bin/phpunit tests/Service/JobOfferServiceTest.php
# Visit /job-offer and /partner/job-offer — both should work
```

**Commit message:**
```
refactor(job-offer): optimize hasAlreadyApplied + remove dead search()

- Use COUNT-based hasStudentApplied() instead of full entity hydration
- Remove unused search() method from JobOfferRepository (superseded by searchPaginated)
```

---

### Commit 3: Form validation cleanup (remove duplicate constraints)
**What:** Remove form-level `Assert` constraints from `JobOfferFormType` that duplicate entity-level constraints.  
**Why:** Single source of truth for validation. Entity already has `#[Assert\NotBlank]`, `#[Assert\Length]`. Form constraints are redundant and can cause confusing double-validation error messages.  
**Risk:** Low — entity constraints are identical. Validation behavior unchanged.

**File:** `src/Form/JobOfferFormType.php`

```diff
  ->add('title', TextType::class, [
      'label' => 'Job Title',
      'required' => true,
      'attr' => [
          'class' => 'form-control',
          'placeholder' => 'e.g., Software Developer Intern',
      ],
-     'constraints' => [
-         new Assert\NotBlank(['message' => 'Job title is required']),
-         new Assert\Length(['max' => 255]),
-     ],
  ])
  ->add('type', EnumType::class, [
      'label' => 'Job Type',
      'class' => JobOfferTypeEnum::class,
      'required' => true,
      'attr' => ['class' => 'form-select'],
      'choice_label' => fn($choice) => ucfirst(strtolower(str_replace('_', ' ', $choice->value))),
-     'constraints' => [
-         new Assert\NotBlank(['message' => 'Job type is required']),
-     ],
  ])
  ->add('location', TextType::class, [
      'label' => 'Location',
      'required' => false,
      'attr' => [
          'class' => 'form-control',
          'placeholder' => 'e.g., Paris, France or Remote',
      ],
-     'constraints' => [
-         new Assert\Length(['max' => 255]),
-     ],
  ])
  ->add('description', TextareaType::class, [
      'label' => 'Job Description',
      'required' => true,
      'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Describe the job responsibilities...',
          'rows' => 8,
      ],
-     'constraints' => [
-         new Assert\NotBlank(['message' => 'Job description is required']),
-     ],
  ])
```

Also remove the unused `use Symfony\Component\Validator\Constraints as Assert;` import.

**Verify:**
```bash
php bin/console cache:clear
# Visit /partner/job-offer/new → submit empty form → should still show validation errors from entity
# Visit /partner/job-offer/{id}/edit → same test
```

**Commit message:**
```
refactor(job-offer): remove duplicate form constraints

Entity already has NotBlank/Length constraints — form-level duplicates
caused double validation. Single source of truth is now the entity.
```

---

### Commit 4: Fix broken test + add partner offers pagination
**What:**
1. Fix `JobApplicationServiceTest` — remove `CvUploadService` reference (file doesn't exist; app uses VichUploaderBundle).
2. Add pagination to partner offers listing (currently loads ALL partner offers without limit).

**Why:** Test will fail if run. Partner with many offers gets no pagination.  
**Risk:** Low — test fix is straightforward, pagination is additive.

**File 1:** `tests/Service/JobApplicationServiceTest.php`

```diff
- use App\Service\JobOffer\CvUploadService;
- use Symfony\Component\String\Slugger\AsciiSlugger;

  protected function setUp(): void
  {
      $this->em = $this->createMock(EntityManagerInterface::class);
      $this->repo = $this->createMock(JobApplicationRepository::class);
-     // CvUploadService is final, so instantiate with real dependencies (won't be called in these tests)
-     $cvService = new CvUploadService(new AsciiSlugger(), sys_get_temp_dir());
-     $this->service = new JobApplicationService($this->em, $this->repo, $cvService);
+     $this->service = new JobApplicationService($this->em, $this->repo);
  }
```

**File 2:** `src/Repository/JobOfferRepository.php` — add method:

```php
/**
 * Paginated list of offers for a specific partner
 *
 * @return Paginator<JobOffer>
 */
public function findByPartnerPaginated(
    User $partner,
    int $page = 1,
    int $limit = 20,
): Paginator {
    $qb = $this->createQueryBuilder('o')
        ->andWhere('o.partner = :partner')
        ->setParameter('partner', $partner)
        ->orderBy('o.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    return new Paginator($qb->getQuery(), fetchJoinCollection: true);
}
```

(Add `use App\Entity\User;` import at the top of the repository.)

**File 3:** `src/Service/JobOffer/JobOfferService.php` — update method:

```diff
- /** @return JobOffer[] */
- public function getPartnerOffers(User $partner): array
- {
-     return $this->jobOfferRepository->findBy(
-         ['partner' => $partner],
-         ['createdAt' => 'DESC']
-     );
- }
+ /**
+  * @return Paginator<JobOffer>
+  */
+ public function getPartnerOffersPaginated(User $partner, int $page = 1, int $limit = 20): Paginator
+ {
+     return $this->jobOfferRepository->findByPartnerPaginated($partner, $page, $limit);
+ }
```

(Add `use Doctrine\ORM\Tools\Pagination\Paginator;` import.)

**File 4:** `src/Controller/Partner/PartnerJobOfferController.php` — update index:

```diff
- #[Route('', name: 'app_partner_job_offer_index', methods: ['GET'])]
- public function index(): Response
+ #[Route('', name: 'app_partner_job_offer_index', methods: ['GET'])]
+ public function index(Request $request): Response
  {
      /** @var \App\Entity\User $user */
      $user = $this->getUser();
+     $page = max(1, $request->query->getInt('page', 1));
+     $limit = 20;
  
-     $offers = $this->jobOfferService->getPartnerOffers($user);
+     $paginator = $this->jobOfferService->getPartnerOffersPaginated($user, $page, $limit);
+     $totalItems = count($paginator);
+     $totalPages = (int) ceil($totalItems / $limit);
  
      return $this->render('Gestion_Job_Offre/partner/job_offer/index.html.twig', [
-         'offers' => $offers,
+         'offers' => $paginator,
+         'currentPage' => $page,
+         'totalPages' => $totalPages,
+         'totalItems' => $totalItems,
      ]);
  }
```

**File 5:** `templates/Gestion_Job_Offre/partner/job_offer/index.html.twig` — add pagination at bottom:

```twig
{# Add before the closing </div> of the container, after the "Total" line #}
{% include 'Gestion_Job_Offre/job_offer/_pagination.html.twig' with {
    currentPage: currentPage,
    totalPages: totalPages,
    route: 'app_partner_job_offer_index',
    routeParams: {}
} only %}
```

**Verify:**
```bash
php bin/phpunit
php bin/console cache:clear
# Visit /partner/job-offer — should show paginated list
```

**Commit message:**
```
fix(job-offer): fix broken test + add partner pagination

- Remove non-existent CvUploadService from test constructor
- Add findByPartnerPaginated() to repository
- Partner job offer list now paginated (20/page)
```

---

### Commit 5: Add missing critical tests
**What:** Add tests that cover the remaining untested paths.  
**Why:** Complete minimum test coverage for the Job Offer module.

**File 1:** `tests/Service/JobOfferServiceTest.php` — add:

```php
public function testUpdateFlushes(): void
{
    $this->em->expects($this->once())->method('flush');
    $offer = new JobOffer();
    $this->service->update($offer);
}

public function testGetPartnerOffersPaginatedDelegates(): void
{
    $partner = new User();
    $partner->setRole('BUSINESS_PARTNER');

    $paginator = $this->createMock(\Doctrine\ORM\Tools\Pagination\Paginator::class);
    $this->repo->expects($this->once())
        ->method('findByPartnerPaginated')
        ->with($partner, 1, 20)
        ->willReturn($paginator);

    $result = $this->service->getPartnerOffersPaginated($partner);
    $this->assertSame($paginator, $result);
}
```

**File 2:** `tests/Service/JobApplicationServiceTest.php` — add:

```php
public function testUpdateStatusChangesStatus(): void
{
    $this->em->expects($this->once())->method('flush');
    
    $application = new JobApplication();
    $application->setStatus(JobApplicationStatus::SUBMITTED);
    
    $this->service->updateStatus($application, JobApplicationStatus::ACCEPTED);
    
    $this->assertSame(JobApplicationStatus::ACCEPTED, $application->getStatus());
}
```

**File 3:** `tests/Security/JobOfferVoterTest.php` — add:

```php
public function testPartnerCanViewOwnApplications(): void
{
    $partner = new User();
    $partner->setRole('BUSINESS_PARTNER');
    $offer = new JobOffer();
    $offer->setPartner($partner);
    $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());
    
    $this->assertSame(
        VoterInterface::ACCESS_GRANTED,
        $this->voter->vote($token, $offer, [JobOfferVoter::VIEW_APPLICATIONS])
    );
}

public function testPartnerCannotViewOtherApplications(): void
{
    $partner = new User();
    $partner->setRole('BUSINESS_PARTNER');
    $other = new User();
    $other->setRole('BUSINESS_PARTNER');
    $offer = new JobOffer();
    $offer->setPartner($other);
    $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());
    
    $this->assertSame(
        VoterInterface::ACCESS_DENIED,
        $this->voter->vote($token, $offer, [JobOfferVoter::VIEW_APPLICATIONS])
    );
}
```

**Verify:**
```bash
php bin/phpunit
# All tests should pass. Check coverage:
php bin/phpunit --coverage-text --filter Job
```

**Commit message:**
```
test(job-offer): add missing unit tests for services and voter

Covers: update(), getPartnerOffersPaginated(), updateStatus(),
VIEW_APPLICATIONS voter checks.
```

---

## 6. CODE QUALITY CHECKLIST

### Doctrine Optimization
- [x] **N+1 prevention:** `searchAdminPaginated()` already uses `leftJoin('o.partner', 'p')->addSelect('p')`.
- [x] **N+1 prevention:** `findByOffer()` uses `leftJoin('a.student', 's')->addSelect('s')`.
- [ ] **⚠️ Partner index N+1:** `offer.applications|length` in partner template triggers lazy-load. Fix: add `->leftJoin('o.applications', 'a')->addSelect('COUNT(a.id) as appCount')` or load the count via a sub-query. Alternatively do `->addSelect('SIZE(o.applications) as HIDDEN appCount')` but that's DQL-specific.
- [x] **Indexes:** Columns `type`, `status`, `location`, `published_at`, `expires_at` are indexed.
- [ ] **⚠️ Missing index:** `job_application.student_id` — used in `hasStudentApplied()` + `findOneBy()`. Doctrine adds FK indexes by default, but verify with `SHOW INDEX FROM job_application`.
- [x] **Pagination:** All list views use Doctrine Paginator (admin, student). Partner needs Commit 4 above.

### Form Validation
- [ ] **Single source of truth** — After Commit 3, all constraints live on the entity only.
- [x] **CSRF protection** — All POST actions validate CSRF tokens.
- [x] **File upload** — VichUploader handles file naming, size limits (5MB), MIME type validation.

### PHP-CS-Fixer Recommended Config
```php
// .php-cs-fixer.dist.php (create at project root)
<?php
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_order' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
```

```bash
# Run:
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix
```

### PHPStan Config
```neon
# phpstan.neon (create at project root)
parameters:
    level: 6
    paths:
        - src/Controller/Admin/AdminJobOfferController.php
        - src/Controller/Partner/
        - src/Controller/Student/StudentJobOfferController.php
        - src/Entity/JobOffer/
        - src/Enum/JobOfferType.php
        - src/Enum/JobOfferStatus.php
        - src/Enum/JobApplicationStatus.php
        - src/Form/JobOfferFormType.php
        - src/Form/JobApplicationFormType.php
        - src/Repository/JobOfferRepository.php
        - src/Repository/JobApplicationRepository.php
        - src/Security/Voter/JobOfferVoter.php
        - src/Service/JobOffer/
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
```

```bash
vendor/bin/phpstan analyse
```

### Naming Conventions
- [x] Controllers: `{Actor}{Feature}Controller` (AdminJobOffer, PartnerJobOffer, StudentJobOffer)
- [x] Services: `{Feature}Service` in `Service/JobOffer/` namespace
- [x] Forms: `{Feature}FormType`
- [x] Voter: `{Feature}Voter`
- [x] Routes: `{actor}_{feature}_{action}` (e.g., `app_partner_job_offer_index`)
- [ ] **⚠️ Inconsistency:** Admin routes don't have `app_` prefix: `admin_job_offer_list` vs `app_partner_job_offer_index`. Minor but worth noting.

### Translation Keys
Currently all strings are hardcoded in English. For i18n readiness:
```
# translations/messages.en.yaml (future)
job_offer.title: "Job Offers"
job_offer.create: "Create Job Offer"
job_offer.status.active: "Active"
job_offer.status.pending: "Pending"
# ... etc
```
Not blocking — add when i18n is required.

### Error Handling + Flash Messages
- [x] All state-changing actions wrapped in try/catch with flash messages
- [x] CSRF validation on all POST actions
- [x] `LogicException` for business rule violations (already applied, offer not active)
- [x] Consistent pattern: success → `addFlash('success', ...)`, error → `addFlash('error', ...)`

### Service Layering & DI Best Practices
- [x] Services are `final` classes
- [x] Constructor injection with `private readonly`
- [x] EntityManager injected into services, not controllers
- [x] Controllers only call services, never EntityManager directly
- [x] Voter is standalone, no service dependencies needed
- [x] Repository methods are properly typed with PHPDoc generics

### Summary of What Remains to Do

| Priority | Task | Commit |
|----------|------|--------|
| 🔴 High | Move admin template to correct folder | Commit 1 |
| 🔴 High | Fix broken test (CvUploadService) | Commit 4 |
| 🟡 Medium | Remove duplicate form constraints | Commit 3 |
| 🟡 Medium | Use optimized hasStudentApplied() | Commit 2 |
| 🟡 Medium | Remove dead search() method | Commit 2 |
| 🟡 Medium | Add partner pagination | Commit 4 |
| 🟢 Low | Add missing tests | Commit 5 |
| 🟢 Low | Fix N+1 on partner offer.applications count | Post-refactor |
| 🟢 Low | Normalize admin route prefix (`app_admin_*`) | Post-refactor |

---

## Quick Command Reference

```bash
# After each commit:
php bin/console cache:clear
php bin/console doctrine:schema:validate
php bin/console debug:router | grep job
php bin/phpunit

# If routes break:
php bin/console debug:router --show-controllers | grep -i job

# If templates break:
php bin/console lint:twig templates/Gestion_Job_Offre/
```
