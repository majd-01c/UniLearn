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
        private ForumReplyRepository $replyRepository
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
        // Increment view count
        $topic->incrementViewCount();
        $this->em->flush();

        // Handle reply form
        $reply = new ForumReply();
        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$topic->isLocked()) {
            $user = $this->getUser();
            $reply->setAuthor($user);
            $reply->setTopic($topic);
            
            // Mark as teacher response if user is teacher/admin
            if (in_array($user->getRole(), ['TEACHER', 'TRAINER', 'ADMIN'])) {
                $reply->setIsTeacherResponse(true);
            }

            $topic->updateLastActivity();
            
            $this->em->persist($reply);
            $this->em->flush();

            $this->addFlash('success', 'Reply posted successfully!');
            return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
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
        $user = $this->getUser();
        if ($topic->getAuthor() !== $user && $user->getRole() !== 'ADMIN') {
            throw $this->createAccessDeniedException();
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
        $user = $this->getUser();
        if ($topic->getAuthor() !== $user && $user->getRole() !== 'ADMIN') {
            throw $this->createAccessDeniedException();
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
     * Mark a reply as accepted answer (topic author or teacher/admin)
     */
    #[Route('/reply/{id}/accept', name: 'app_forum_reply_accept', methods: ['POST'])]
    public function acceptReply(ForumReply $reply, Request $request): Response
    {
        $topic = $reply->getTopic();
        $user = $this->getUser();
        
        // Only topic author, teachers, or admins can accept answers
        if ($topic->getAuthor() !== $user && !in_array($user->getRole(), ['TEACHER', 'TRAINER', 'ADMIN'])) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('accept-reply-' . $reply->getId(), $request->request->get('_token'))) {
            $topic->setAcceptedAnswer($reply);
            $this->em->flush();

            $this->addFlash('success', 'Answer marked as accepted!');
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Toggle pin status of a topic (teacher/admin only)
     */
    #[Route('/topic/{id}/pin', name: 'app_forum_topic_pin', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER')]
    public function pinTopic(ForumTopic $topic, Request $request): Response
    {
        if ($this->isCsrfTokenValid('pin-topic-' . $topic->getId(), $request->request->get('_token'))) {
            $topic->setIsPinned(!$topic->isPinned());
            $this->em->flush();

            $status = $topic->isPinned() ? 'pinned' : 'unpinned';
            $this->addFlash('success', "Topic {$status} successfully!");
        }

        return $this->redirectToRoute('app_forum_topic', ['id' => $topic->getId()]);
    }

    /**
     * Lock/unlock a topic (teacher/admin only)
     */
    #[Route('/topic/{id}/lock', name: 'app_forum_topic_lock', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER')]
    public function lockTopic(ForumTopic $topic, Request $request): Response
    {
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
        $user = $this->getUser();
        if ($reply->getAuthor() !== $user && $user->getRole() !== 'ADMIN') {
            throw $this->createAccessDeniedException();
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
     * Delete a reply (author only or admin)
     */
    #[Route('/reply/{id}/delete', name: 'app_forum_reply_delete', methods: ['POST'])]
    public function deleteReply(ForumReply $reply, Request $request): Response
    {
        $user = $this->getUser();
        if ($reply->getAuthor() !== $user && $user->getRole() !== 'ADMIN') {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-reply-' . $reply->getId(), $request->request->get('_token'))) {
            $topicId = $reply->getTopic()->getId();
            
            // If this was the accepted answer, unset it
            if ($reply->isAccepted()) {
                $reply->getTopic()->setAcceptedAnswer(null);
                $reply->getTopic()->setStatus(TopicStatus::OPEN);
            }
            
            $this->em->remove($reply);
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
}
