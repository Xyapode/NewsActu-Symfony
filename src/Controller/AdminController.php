<?php

namespace App\Controller;

use DateTime;
use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\User;
use App\Form\ArticleFormType;
use App\Form\CategoryFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{

    // #[Route("/tableau-de-bord", name:"show_dashboard", methods:["GET"])]
    
    /**
     * @Route("/tableau-de-bord", name="show_dashboard", methods={"GET"})
     * // IsGranted("ROLE_ADMIN")
     */
    public function showDashboard(EntityManagerInterface $entityManager): Response
    {
        /*
        * try/catch fait partie nativement de PHP
        * Cela a été créé pour gérer les class Exception (erreur)
        * On se sert d'un try/catch lorsqu'on utilise des méthodes (fonctions) QUI LANCENT (throw) une Exception
        * Si la méthode lance l'erreur pendant son exécution, alors l'Exception sera 'attrapée' (catch) Le code dans les accolades du catch sera alors exécuté.
        */
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }
        catch(AccessDeniedException $exception){

            $this->addFlash('warning', 'Cette partie du site est réservée aux administrateurs.');
            return $this->redirectToRoute('default_home');
        }

        $articles = $entityManager->getRepository(Article::class)->findAll();
        $categories = $entityManager->getRepository(Categorie::class)->findAll();
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/show_dashboard.html.twig', [
            'articles' => $articles,
            'categories' => $categories,    
            'users' => $users    
        ]);
    }

    // #[Route("/creer-un-article", name:"create_article", methods:["GET|POST"])]
    /**
     * @Route("/creer-un-article", name="create_article", methods={"GET|POST"})
     */
    public function createArticle(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $article = new Article();

        $form = $this->createForm(ArticleFormType::class, $article)->handleRequest($request);

        // Traitement du formulaire
        if($form->isSubmitted() && $form->isValid()){

            // Pour accéder à une valeur d'un input de $form, on fait :
                // $form->get('title')->getData()

            // Setting des propriétés non mappées dans le formulaire
            $article->setAlias($slugger->slug($article->getTitle() ) );
            $article->setCreatedAt(new DateTime());
            $article->setUpdatedAt(new DateTime());
            
            // Association d'un auteur à un article
            // $this->getUser() retourne un objet de type UserInterface
            $article->setAuthor($this->getUser());

            // Variabilisation du fichier 'photo' uploadé
            $file = $form->get('photo')->getData();

            // if ($file === true)
            // Si un fichier est uploadé (depuis le formulaire)
            if($file) {
                // Maintenant il s'agit de reconstruire le nom du fichier pour le sécuriser

                // 1ère étape : On déconstruit le nom du fichier et on variabilise
                $extension = '.' . $file->guessExtension();
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                // Assainissement du nom de fichier (du filename)
                // $safeFilename = $slugger->slug($originalFilename);
                $safeFilename = $article->getAlias();

                // 2ème étape : On reconstruit le nom du fichier maintenant qu'il est safe
                // uniqid() est une fonction native de PHP, elle permet d'ajouter une valeur numérique (id) unique et auto-générée
                $newFilename = $safeFilename . '_' . uniqid() . $extension;

                // try/catch fait partie de PHP nativement
                try {

                    // On a configuré un paramètre "uploads_dir" dans le fichier services.yaml
                        // Ce param contient le chemin absolu de notre dossier d'upload de photo
                    $file->move($this->getParameter('uploads_dir'), $newFilename);

                    // On set le NOM de la photo, pas le CHEMIN
                    $article->setPhoto($newFilename);

                } catch (FileException $exception) {

                } // END catch()
            } // END if($file)

            $entityManager->persist($article);
            $entityManager->flush();

            // Ici on ajoute un message qu'on affichera en twig
            $this->addFlash('success', 'Bravo, votre article est bien en ligne !');

            return $this->redirectToRoute('show_dashboard');
        } // END if($form)

        return $this->render('admin/form/form_article.html.twig',[
            'form' => $form->createView()
        ]);
    }

    // L'action est exécuté 2 fois et accessible par 2 méthodes (GET|POST)
    // #[Route("/modifier-un-article/{id}", name:"update_article", methods:["GET|POST"])]
    /**
     * @Route("/modifier-un-article/{id}", name="update_article", methods={"GET|POST"})
     */
    public function updateArticle(Article $article, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger)
    {
        # Condition ternaire : $article->getPhoto() ?? ''
            # => est égal à : isset($article->getPhoto()) ? $article->getPhoto() : '' ;
        $originalPhoto = $article->getPhoto() ?? '' ;
        // 1er tour en méthode GET
        $form = $this->createForm(ArticleFormType::class, $article, [
            'photo' => $originalPhoto
        ])->handleRequest($request);

        // 2ème tour de l'action en méthode POST
        if($form->isSubmitted() && $form->isValid()) {

            $article->setAlias($slugger->slug($article->getTitle()));
            $article->setUpdatedAt(new DateTime());

            $file = $form->get('photo')->getData();

            if($file) {
                $extension = '.' . $file->guessExtension();
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $article->getAlias();
                $newFilename = $safeFilename . '_' . uniqid() . $extension;

                try {

                    $file->move($this->getParameter('uploads_dir'), $newFilename);
                    $article->setPhoto($newFilename);

                } catch (FileException $exception) {
                    # code à exécuter si une erreur est attrapé

                } // END catch()

            } else {
                $article->setPhoto($originalPhoto);
            } // END if($file)

            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', "L'article " . $article->getTitle() . " a bien été modifié !");

            return $this->redirectToRoute("show_dashboard");
        } // END if($form)

        # On rend la vue pour la méthode GET
        return $this->render('admin/form/form_article.html.twig', [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }

    // #[Route("/archiver-un-article/{id}", name:"soft_delete_article", methods:["GET"])]
    /**
     * @Route("/archiver-un-article/{id}", name="soft_delete_article", methods={"GET"})
     */
    public function softDeleteArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        # On set la propriété deletedAt pour archiver l'article. De l'autre côté, on affichera les articles où deletedAt === null
        $article->setDeletedAt(new DateTime());

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash('success', "L'article " . $article->getTitle() . " a bien été archivé");

        return $this->redirectToRoute('show_dashboard');
    }

    // #[Route("/supprimer-un-article/{id}", name:"hard_delete_article", methods:["GET"])]
    /**
     * @Route("/supprimer-un-article/{id}", name="hard_delete_article", methods={"GET"})
     */
    public function hardDeleteArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        # Cette méthode supprime une ligne en BDD
        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash('success', "L'article " . $article->getTitle() . " a bien été supprimé de la base de données");

        return $this->redirectToRoute('show_dashboard');
    }

    // #[Route("/restaurer-un-article/{id}", name:"restore_article", methods:["GET"])]
    /**
     * @Route("/restaurer-un-article/{id}", name="restore_article", methods={"GET"})
     */
    public function restoreArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $article->setDeletedAt();

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash('success', "L'article " . $article->getTitle() . " a bien été restauré");

        return $this->redirectToRoute("show_dashboard");
    }

    /**
     * @Route("/creer-une-categorie", name="create_category", methods={"GET|POST"})
     */
    public function createCategory(EntityManagerInterface $entityManager, Request $request, SluggerInterface $slugger): Response
    {
        $category = new Categorie;

        $form = $this->createForm(CategoryFormType::class, $category)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $category->setAlias($slugger->slug($category->getName()));
            // Il y a une autre façon de procéder pour setter ces propriétés de Categorie
                # => Voir Categorie Entity __construct()
            // $category->setCreatedAt(new DateTime());
            // $category->setUpdatedAt(new DateTime());

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', "Vous avez ajouté la catégorie " . $category->getName() ." avec succès.");
            return $this->redirectToRoute('show_dashboard');
        }

        return $this->render('admin/form/form_category.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/modifier-une-categorie/{id}", name="update_category", methods={"GET|POST"})
     */
    public function updateCategory(Categorie $category, Request $request, SluggerInterface $slugger, EntityManagerInterface $entityManager): Response
    {
        // Variabilisation de l'ancien nom de la catégorie pour le addFlash()
        $oldCategoryName = $category->getName();

        $form = $this->createForm(CategoryFormType::class, $category)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $category->setAlias($slugger->slug($category->getName()));
            $category->setUpdatedAt((new DateTime()));

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', "Vous avez modifié la catégorie " . $oldCategoryName ." avec succès.");
            return $this->redirectToRoute('show_dashboard');
        }

        return $this->render('admin/form/form_category.html.twig', [
            'form' => $form->createView(),
            'category' => $category
        ]);
    }

    /**
     * @Route("/archiver-une-categorie_{id}", name="soft_delete_category", methods={"GET"})
     */
    public function softDeleteCategory(Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $categorie->setDeletedAt(new DateTime());

        $entityManager->persist($categorie);
        $entityManager->flush();

        $this->addFlash('success', "La catégorie " . $categorie->getName() . " a bien été archivée.");
        return $this->redirectToRoute('show_dashboard');
    }

    /**
     * @Route("/restaurer-une-categorie_{id}", name="restore_category", methods={"GET"})
     */
    public function restoreCategory(Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $categorie->setDeletedAt(null);

        $entityManager->persist($categorie);
        $entityManager->flush();

        $this->addFlash('success', "La catégorie " . $categorie->getName() . " a bien été restaurée.");
        return $this->redirectToRoute('show_dashboard');
    }

    /**
     * @Route("/supprimer-une-categorie_{id}", name="hard_delete_category", methods={"GET"})
     */
    public function hardDeleteCategory(Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($categorie);
        $entityManager->flush();

        $this->addFlash('success', "La catégorie " . $categorie->getName() . " a bien été supprimée définitivement de la base de données.");
        return $this->redirectToRoute('show_dashboard');
    }

} // END class
