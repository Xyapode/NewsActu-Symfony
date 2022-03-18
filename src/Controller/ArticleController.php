<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commentary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ArticleController extends AbstractController
{
    // #[Route("/voir/{cat_alias}/{article_alias}_{id}", name:"show_article", methods:["GET"])]
    /**
     * @Route("/voir/{cat_alias}/{article_alias}_{id}", name="show_article", methods={"GET"})
     */
    public function showArticle(Article $article, EntityManagerInterface $entityManager): Response
    {

        $commentaries = $entityManager->getRepository(Commentary::class)->findBy(['article' => $article->getId()]);

        return $this->render('article/show_article.html.twig', [
            'article' => $article,
            'commentaries' => $commentaries
        ]);
    }
}