<?php

namespace App\Controller;

use App\Entity\ForumCategory;
use App\Entity\ForumComment;
use App\Entity\ForumCommentReaction;
use App\Entity\ForumTopic;
use App\Enum\TopicStatus;
use App\Form\ForumCategoryType;
use App\Form\ForumCommentType;
use App\Form\ForumTopicType;
use App\Repository\ForumCategoryRepository;
use App\Repository\ForumCommentRepository;
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
        private ForumCommentRepository $commentRepository,
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
     * View a topic and its comments
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

        // Handle comment form
        $comment = new ForumComment();
        $form = $this->createForm(ForumCommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$topic->isLocked()) {
            $user = $this->getUser();
            $comment->setAuthor($user);
            $comment->setTopic($topic);
            
            // Mark as teacher response if user is teacher/admin/trainer
            if ($this->isGranted('ROLE_TEACHER') || $this->isGranted('ROLE_TRAINER') || $this->isGranted('ROLE_ADMIN')) {
                $comment->setIsTeacherResponse(true);
            }

            $topic->updateLastActivity();
            
            $this->em->persist($comment);
            $this->em->flush();

            $this->addFlash('success', 'Comment posted successfully!');
            // Redirect to the new comment with anchor for scroll
            return $this->redirectToRoute('app_forum_topic', [
                'id' => $topic->getId(),
                '_fragment' => 'comment-' . $comment->getId()
            ]);
        }

        // Paginate comments (top-level only)
        $page = max(1, $request->query->getInt('page', 1));
        $comments = $this->commentRepository->findByTopicPaginated($topic, $page, 20);

        return $this->render('Gestion_Communication/forum/topic.html.twig', [
            'topic' => $topic,
            'comments' => $comments,
            'form' => $form,
            'page' => $page,
            'totalPages' => ceil(count($comments) / 20),
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
     * Toggle a comment as accepted answer
     * Rules:
     * - Topic author: Can accept/unaccept any comment (except their own)
     * - Teacher/Trainer: Can ONLY accept (to help students), but NOT unaccept
     * - Admin: CANNOT accept/unaccept (only moderate via delete)
     */
    #[Route('/comment/{id}/accept', name: 'app_forum_comment_accept', methods: ['POST'])]
    public function acceptComment(ForumComment $comment, Request $request): Response
    {
        $topic = $comment->getTopic();
        $user = $this->getUser();
        $isTopicAuthor = $topic->getAuthor() === $user;
        $isTeacherOrTrainer = $this->isGranted('ROLE_TEACHER') || $this->isGranted('ROLE_TRAINER');
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Admin cannot accept/unaccept - they should only moderate (delete)
        if ($isAdmin && !$isTopicAuthor) {
            $this->addFlash('warning', 'Admins cannot accept/unaccept answers. Use delete to moderate content.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }
        
        // Cannot accept your own comment
        if ($comment->getAuthor() === $user) {
            $this->addFlash('warning', 'You cannot accept your own comment as an answer.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }
        
        // Check if trying to unaccept
        if ($comment->isAccepted()) {
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

        if ($this->isCsrfTokenValid('accept-comment-' . $comment->getId(), $request->request->get('_token'))) {
            // Toggle the accepted status
            if ($comment->isAccepted()) {
                $comment->setIsAccepted(false);
                $this->addFlash('success', 'Answer unmarked as accepted.');
            } else {
                $comment->setIsAccepted(true);
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
     * Edit a comment (author only or admin)
     */
    #[Route('/comment/{id}/edit', name: 'app_forum_comment_edit')]
    public function editComment(ForumComment $comment, Request $request): Response
    {
        // Check permission: author or admin can edit
        if (!$this->canEditComment($comment)) {
            throw $this->createAccessDeniedException('You cannot edit this comment.');
        }

        $form = $this->createForm(ForumCommentType::class, $comment, ['is_reply' => $comment->isReply()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Comment updated successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $comment->getTopic()->getId()]);
        }

        return $this->render('Gestion_Communication/forum/edit_comment.html.twig', [
            'comment' => $comment,
            'form' => $form,
        ]);
    }

    /**
     * Delete a comment (admin only)
     */
    #[Route('/comment/{id}/delete', name: 'app_forum_comment_delete', methods: ['POST'])]
    public function deleteComment(ForumComment $comment, Request $request): Response
    {
        // Check permission: only admin can delete comments
        if (!$this->canDeleteComment($comment)) {
            throw $this->createAccessDeniedException('Only administrators can delete comments.');
        }

        if ($this->isCsrfTokenValid('delete-comment-' . $comment->getId(), $request->request->get('_token'))) {
            $topic = $comment->getTopic();
            $topicId = $topic->getId();
            
            $this->em->remove($comment);
            
            // Update topic solved status after comment removal
            $topic->updateSolvedStatus();
            $this->em->flush();

            $this->addFlash('success', 'Comment deleted successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topicId]);
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $comment->getTopic()->getId()]);
    }

    /**
     * Reply to a comment
     */
    #[Route('/comment/{id}/reply', name: 'app_forum_comment_reply', methods: ['POST'])]
    public function replyToComment(ForumComment $parentComment, Request $request): Response
    {
        $topic = $parentComment->getTopic();
        
        if ($topic->isLocked()) {
            $this->addFlash('warning', 'This topic is locked. No new replies can be added.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        // Get content from the manual form
        $formData = $request->request->all('forum_comment_type');
        $content = $formData['content'] ?? '';
        $submittedToken = $formData['_token'] ?? '';

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('forum_comment_type', $submittedToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        // Validate content
        $content = trim($content);
        if (empty($content)) {
            $this->addFlash('error', 'Reply content cannot be empty.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        if (strlen($content) < 3) {
            $this->addFlash('error', 'Reply must be at least 3 characters long.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        if (strlen($content) > 5000) {
            $this->addFlash('error', 'Reply cannot exceed 5000 characters.');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
        }

        // Create the reply
        $reply = new ForumComment();
        $reply->setContent($content);
        $reply->setAuthor($this->getUser());
        $reply->setTopic($topic);
        $reply->setParent($parentComment);
        
        // Mark as teacher response if user is teacher/admin/trainer
        if ($this->isGranted('ROLE_TEACHER') || $this->isGranted('ROLE_TRAINER') || $this->isGranted('ROLE_ADMIN')) {
            $reply->setIsTeacherResponse(true);
        }

        $topic->updateLastActivity();
        
        $this->em->persist($reply);
        $this->em->flush();

        $this->addFlash('success', 'Reply posted successfully!');
        return $this->redirectToRoute('app_forum_topic', [
            'id' => $topic->getId(),
            '_fragment' => 'comment-' . $reply->getId()
        ]);
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
    // COMMENT REACTIONS (LIKE/DISLIKE)
    // ================================

    /**
     * Handle like/dislike reaction on a comment
     */
    #[Route('/comment/{id}/react/{type}', name: 'app_forum_comment_react', methods: ['POST'])]
    public function reactToComment(
        ForumComment $comment,
        string $type,
        Request $request
    ): Response {
        // Validate type
        if (!in_array($type, ['like', 'dislike'])) {
            throw $this->createNotFoundException('Invalid reaction type');
        }

        // CSRF token validation
        if (!$this->isCsrfTokenValid('react-comment-' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token');
            return $this->redirectToRoute('app_forum_topic', ['id' => $comment->getTopic()->getId()]);
        }

        $user = $this->getUser();
        $reactionRepo = $this->em->getRepository(ForumCommentReaction::class);
        
        // Check if user already has a reaction
        $existingReaction = $reactionRepo->findUserReaction($user, $comment);

        if ($existingReaction) {
            // If same type, remove it (toggle off)
            if ($existingReaction->getType() === $type) {
                $this->em->remove($existingReaction);
            } else {
                // If different type, update it
                $existingReaction->setType($type);
            }
        } else {
            // Create new reaction
            $reaction = new ForumCommentReaction();
            $reaction->setUser($user);
            $reaction->setComment($comment);
            $reaction->setType($type);
            $this->em->persist($reaction);
        }

        $this->em->flush();

        // Redirect back to the topic with anchor to comment
        return $this->redirectToRoute('app_forum_topic', [
            'id' => $comment->getTopic()->getId(),
            '_fragment' => 'comment-' . $comment->getId()
        ]);
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
     * Check if current user can edit a comment
     * (comment author only)
     */
    private function canEditComment(ForumComment $comment): bool
    {
        $user = $this->getUser();
        return $comment->getAuthor() === $user;
    }

    /**
     * Check if current user can delete a comment
     * (admin only)
     */
    private function canDeleteComment(ForumComment $comment): bool
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
