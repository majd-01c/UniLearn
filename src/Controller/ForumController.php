<?php

namespace App\Controller;

use App\Entity\ForumCategory;
use App\Entity\ForumReply;
use App\Entity\ForumTopic;
use App\Enum\TopicStatus;
use App\Form\ForumCategoryType;
use App\Form\ForumReplyType;
use App\Form\ForumTopicType;
use App\Repository\ForumCategoryRepository;
use App\Repository\ForumReplyRepository;
use App\Repository\ForumTopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ForumController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumReplyRepository $replyRepository,
        private RequestStack $requestStack
    ) {}

    /**
     * Forum home - list categories and recent topics
     */
    #[Route('', name: 'app_forum')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findAllActive();
        $recentTopics = $this->topicRepository->findRecent(5);
        $unansweredTopics = $this->topicRepository->findUnanswered(5);

        return $this->render('Gestion_Communication/forum/index.html.twig', [
            'categories' => $categories,
            'recentTopics' => $recentTopics,
            'unansweredTopics' => $unansweredTopics,
        ]);
    }

    /**
     * List topics in a category with pagination
     */
    #[Route('/category/{id}', name: 'app_forum_category')]
    public function category(ForumCategory $category, Request $request): Response
    {
        if (!$category->isActive()) {
            throw $this->createNotFoundException('Category not found');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('search');
        $topics = $this->topicRepository->findPaginated($page, 15, $category, $search);

        return $this->render('Gestion_Communication/forum/category.html.twig', [
            'category' => $category,
            'topics' => $topics,
            'page' => $page,
            'totalPages' => ceil(count($topics) / 15),
            'search' => $search,
        ]);
    }

    /**
     * View a topic and its replies
     */
    #[Route('/topic/{id}', name: 'app_forum_topic')]
    public function topic(ForumTopic $topic, Request $request): Response
    {
        // Increment view count only once per session per topic
        $session = $this->requestStack->getSession();
        $viewedTopics = $session->get('viewed_forum_topics', []);
        
        if (!in_array($topic->getId(), $viewedTopics)) {
            $topic->incrementViewCount();
            $this->em->flush();
            $viewedTopics[] = $topic->getId();
            $session->set('viewed_forum_topics', $viewedTopics);
        }

        // Handle reply form
        $reply = new ForumReply();
        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$topic->isLocked()) {
            $user = $this->getUser();
            $reply->setAuthor($user);
            $reply->setTopic($topic);
            
            // Mark as teacher response if user is teacher/admin/trainer
            if ($this->isGranted('ROLE_TEACHER') || $this->isGranted('ROLE_TRAINER') || $this->isGranted('ROLE_ADMIN')) {
                $reply->setIsTeacherResponse(true);
            }

            $topic->updateLastActivity();
            
            $this->em->persist($reply);
            $this->em->flush();

            $this->addFlash('success', 'Reply posted successfully!');
            // Redirect to the new reply with anchor for scroll
            return $this->redirectToRoute('app_forum_topic', [
                'id' => $topic->getId(),
                '_fragment' => 'reply-' . $reply->getId()
            ]);
        }

        // Paginate replies
        $page = max(1, $request->query->getInt('page', 1));
        $replies = $this->replyRepository->findByTopicPaginated($topic, $page, 20);

        return $this->render('Gestion_Communication/forum/topic.html.twig', [
            'topic' => $topic,
            'replies' => $replies,
            'form' => $form,
            'page' => $page,
            'totalPages' => ceil(count($replies) / 20),
        ]);
    }

    /**
     * Create a new topic
     */
    #[Route('/new', name: 'app_forum_new')]
    public function new(Request $request): Response
    {
        $topic = new ForumTopic();
        $form = $this->createForm(ForumTopicType::class, $topic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $topic->setAuthor($this->getUser());
            
            $this->em->persist($topic);
            $this->em->flush();

            $this->addFlash('success', 'Topic created successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        return $this->render('Gestion_Communication/forum/new_topic.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Edit a topic (author only or admin)
     */
    #[Route('/topic/{id}/edit', name: 'app_forum_topic_edit')]
    public function editTopic(ForumTopic $topic, Request $request): Response
    {
        // Check permission: author or admin can edit
        if (!$this->canEditTopic($topic)) {
            throw $this->createAccessDeniedException('You cannot edit this topic.');
        }

        $form = $this->createForm(ForumTopicType::class, $topic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $topic->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Topic updated successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        return $this->render('Gestion_Communication/forum/edit_topic.html.twig', [
            'topic' => $topic,
            'form' => $form,
        ]);
    }

    /**
     * Delete a topic (author only or admin)
     */
    #[Route('/topic/{id}/delete', name: 'app_forum_topic_delete', methods: ['POST'])]
    public function deleteTopic(ForumTopic $topic, Request $request): Response
    {
        // Check permission: author or admin can delete
        if (!$this->canEditTopic($topic)) {
            throw $this->createAccessDeniedException('You cannot delete this topic.');
        }

        if ($this->isCsrfTokenValid('delete-topic-' . $topic->getId(), $request->request->get('_token'))) {
            $categoryId = $topic->getCategory()->getId();
            $this->em->remove($topic);
            $this->em->flush();

            $this->addFlash('success', 'Topic deleted successfully!');
            return $this->redirectToRoute('app_forum_category', ['id' => $categoryId]);
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Toggle a reply as accepted answer
     * Rules:
     * - Topic author: Can accept/unaccept any reply (except their own)
     * - Teacher/Trainer: Can ONLY accept (to help students), but NOT unaccept
     * - Admin: CANNOT accept/unaccept (only moderate via delete)
     */
    #[Route('/reply/{id}/accept', name: 'app_forum_reply_accept', methods: ['POST'])]
    public function acceptReply(ForumReply $reply, Request $request): Response
    {
        $topic = $reply->getTopic();
        $user = $this->getUser();
        $isTopicAuthor = $topic->getAuthor() === $user;
        $isTeacherOrTrainer = $this->isGranted('ROLE_TEACHER') || $this->isGranted('ROLE_TRAINER');
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Admin cannot accept/unaccept - they should only moderate (delete)
        if ($isAdmin && !$isTopicAuthor) {
            $this->addFlash('warning', 'Admins cannot accept/unaccept answers. Use delete to moderate content.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }
        
        // Cannot accept your own reply
        if ($reply->getAuthor() === $user) {
            $this->addFlash('warning', 'You cannot accept your own reply as an answer.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }
        
        // Check if trying to unaccept
        if ($reply->isAccepted()) {
            // Only topic author can unaccept
            if (!$isTopicAuthor) {
                $this->addFlash('warning', 'Only the topic author can remove accepted status from an answer.');
                return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
            }
        }
        
        // Only topic author or teachers/trainers can accept
        if (!$isTopicAuthor && !$isTeacherOrTrainer) {
            throw $this->createAccessDeniedException('You cannot accept answers on this topic.');
        }

        if ($this->isCsrfTokenValid('accept-reply-' . $reply->getId(), $request->request->get('_token'))) {
            // Toggle the accepted status
            if ($reply->isAccepted()) {
                $reply->setIsAccepted(false);
                $this->addFlash('success', 'Answer unmarked as accepted.');
            } else {
                $reply->setIsAccepted(true);
                $this->addFlash('success', 'Answer marked as accepted!');
            }
            
            // Update topic solved status
            $topic->updateSolvedStatus();
            $this->em->flush();
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Toggle pin status of a topic (staff only - teacher/trainer/admin)
     */
    #[Route('/topic/{id}/pin', name: 'app_forum_topic_pin', methods: ['POST'])]
    public function pinTopic(ForumTopic $topic, Request $request): Response
    {
        // Only staff can pin/unpin topics
        if (!$this->isStaff()) {
            throw $this->createAccessDeniedException('Only staff members can pin topics.');
        }
        
        if ($this->isCsrfTokenValid('pin-topic-' . $topic->getId(), $request->request->get('_token'))) {
            $topic->setIsPinned(!$topic->isPinned());
            $this->em->flush();

            $status = $topic->isPinned() ? 'pinned' : 'unpinned';
            $this->addFlash('success', "Topic {$status} successfully!");
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Lock/unlock a topic (staff only - teacher/trainer/admin)
     */
    #[Route('/topic/{id}/lock', name: 'app_forum_topic_lock', methods: ['POST'])]
    public function lockTopic(ForumTopic $topic, Request $request): Response
    {
        // Only staff can lock/unlock topics
        if (!$this->isStaff()) {
            throw $this->createAccessDeniedException('Only staff members can lock topics.');
        }
        
        if ($this->isCsrfTokenValid('lock-topic-' . $topic->getId(), $request->request->get('_token'))) {
            if ($topic->isLocked()) {
                $topic->setStatus(TopicStatus::OPEN);
                $this->addFlash('success', 'Topic unlocked successfully!');
            } else {
                $topic->setStatus(TopicStatus::LOCKED);
                $this->addFlash('success', 'Topic locked successfully!');
            }
            $this->em->flush();
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Edit a reply (author only or admin)
     */
    #[Route('/reply/{id}/edit', name: 'app_forum_reply_edit')]
    public function editReply(ForumReply $reply, Request $request): Response
    {
        // Check permission: author or admin can edit
        if (!$this->canEditReply($reply)) {
            throw $this->createAccessDeniedException('You cannot edit this reply.');
        }

        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reply->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Reply updated successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $reply->getTopic()->getId()]);
        }

        return $this->render('Gestion_Communication/forum/edit_reply.html.twig', [
            'reply' => $reply,
            'form' => $form,
        ]);
    }

    /**
     * Delete a reply (admin only)
     */
    #[Route('/reply/{id}/delete', name: 'app_forum_reply_delete', methods: ['POST'])]
    public function deleteReply(ForumReply $reply, Request $request): Response
    {
        // Check permission: only admin can delete replies
        if (!$this->canDeleteReply($reply)) {
            throw $this->createAccessDeniedException('Only administrators can delete replies.');
        }

        if ($this->isCsrfTokenValid('delete-reply-' . $reply->getId(), $request->request->get('_token'))) {
            $topic = $reply->getTopic();
            $topicId = $topic->getId();
            
            $this->em->remove($reply);
            
            // Update topic solved status after reply removal
            $topic->updateSolvedStatus();
            $this->em->flush();

            $this->addFlash('success', 'Reply deleted successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topicId]);
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $reply->getTopic()->getId()]);
    }

    // ================================
    // ADMIN CATEGORY MANAGEMENT
    // ================================

    /**
     * Admin: List all categories
     */
    #[Route('/admin/categories', name: 'app_forum_admin_categories')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCategories(): Response
    {
        $categories = $this->categoryRepository->findBy([], ['position' => 'ASC']);

        return $this->render('Gestion_Communication/forum/admin/categories.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * Admin: Create new category
     */
    #[Route('/admin/category/new', name: 'app_forum_admin_category_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function newCategory(Request $request): Response
    {
        $category = new ForumCategory();
        $form = $this->createForm(ForumCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Category created successfully!');
            return $this->redirectToRoute('app_forum_admin_categories');
        }

        return $this->render('Gestion_Communication/forum/admin/category_form.html.twig', [
            'form' => $form,
            'category' => null,
        ]);
    }

    /**
     * Admin: Edit category
     */
    #[Route('/admin/category/{id}/edit', name: 'app_forum_admin_category_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editCategory(ForumCategory $category, Request $request): Response
    {
        $form = $this->createForm(ForumCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Category updated successfully!');
            return $this->redirectToRoute('app_forum_admin_categories');
        }

        return $this->render('Gestion_Communication/forum/admin/category_form.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    /**
     * Admin: Delete category
     */
    #[Route('/admin/category/{id}/delete', name: 'app_forum_admin_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(ForumCategory $category, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-category-' . $category->getId(), $request->request->get('_token'))) {
            $this->em->remove($category);
            $this->em->flush();

            $this->addFlash('success', 'Category deleted successfully!');
        }

        return $this->redirectToRoute('app_forum_admin_categories');
    }

    // ================================
    // PERMISSION HELPER METHODS
    // ================================

    /**
     * Check if current user is staff (teacher, trainer, or admin)
     */
    private function isStaff(): bool
    {
        return $this->isGranted('ROLE_TEACHER') 
            || $this->isGranted('ROLE_TRAINER') 
            || $this->isGranted('ROLE_ADMIN');
    }

    /**
     * Check if current user can edit/delete a topic
     * (topic author or admin)
     */
    private function canEditTopic(ForumTopic $topic): bool
    {
        $user = $this->getUser();
        return $topic->getAuthor() === $user || $this->isGranted('ROLE_ADMIN');
    }

    /**
     * Check if current user can edit a reply
     * (reply author only)
     */
    private function canEditReply(ForumReply $reply): bool
    {
        $user = $this->getUser();
        return $reply->getAuthor() === $user;
    }

    /**
     * Check if current user can delete a reply
     * (admin only)
     */
    private function canDeleteReply(ForumReply $reply): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }

    /**
     * Check if current user can accept answers on a topic
     * (topic author, teachers, trainers, or admins)
     */
    private function canAcceptAnswer(ForumTopic $topic): bool
    {
        $user = $this->getUser();
        return $topic->getAuthor() === $user || $this->isStaff();
    }
}
