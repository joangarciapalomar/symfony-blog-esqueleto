<?php

namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogController extends AbstractController
{
    #[Route("/blog/buscar/{page}", name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine, Request $request, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm') ?? "";
        $posts = $repository->findByTextPaginated($page, $searchTerm);
        
        
        $recents = $repository->findBy([],['PublishedAt' => 'DESC'], 2);

        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
        ]);
    }

    #[Route("/blog/new", name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();
            $post->setUser($this->getUser());
            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setNumLikes(0);
            $post->setNumComments(0);
            $post->setNumViews(0);
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $file = $form->get('Image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $file->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );

                    $filesystem = new Filesystem();
                    $filesystem->copy(
                        $this->getParameter('images_directory') . '/' . $newFilename,
                        true
                    );
                } catch (FileException $e) {
                    // Handle the exception if something happens during file upload
                }

                // Update the 'file$filename' property to store the PDF file name
                // instead of its contents
                $post->setImage($newFilename);
            }
            $entityManager->flush();

            return $this->redirectToRoute('blog');
        }
        return $this->render(
            'blog/new_post.html.twig',
            array(
                'form' => $form->createView(),
                'post' => $post
            )
        );
    }

    #[Route("/single_post/{slug}/like", name: 'post_like')]
    public function like(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(['Slug' => $slug]);;
        $likes = $post->getNumLikes();
        $post->setNumLikes($likes + 1);
        $entityManager = $doctrine->getManager();
        $entityManager->flush();
        return $this->redirectToRoute('single_post', ['slug' => $post->getSlug()]);

    }

    #[Route("/blog/{page}", name: 'blog')]
    public function index(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAllPaginated($page);
        $recents = $repository->findBy([],['PublishedAt' => 'DESC'], 2);



        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
        ]);
    }


    #[Route("/single_post/{slug}", name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug = 'cambiar'): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(['Slug' => $slug]);

        
        $recents = $repository->findBy([],['PublishedAt' => 'DESC'], 2);

        $form = $this->createForm(CommentFormType::class);
        $formPost = $this->createForm(PostFormType::class, $post);
        $formPost->handleRequest($request);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $postForm = $formPost->getData();

            $comentarios = $postForm->getNumComments();
            $comment->setPost($post);
            $postForm->setNumComments($comentarios + 1);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($postForm);
            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ['slug' => $post->getSlug()]);
        }

        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'commentForm' => $form->createView()
        ]);
    }

}
