<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/informations-legales', name: 'app_legal', defaults: ['document' => 'index'], methods: ['GET'])]
    #[Route('/mentions-legales', name: 'app_legal_notices', defaults: ['document' => 'notices'], methods: ['GET'])]
    #[Route('/cgu', name: 'app_terms', defaults: ['document' => 'terms'], methods: ['GET'])]
    #[Route('/cgv', name: 'app_sales_terms', defaults: ['document' => 'sales'], methods: ['GET'])]
    #[Route('/cookies', name: 'app_cookies', defaults: ['document' => 'cookies'], methods: ['GET'])]
    #[Route('/suppression-donnees', name: 'app_data_deletion', defaults: ['document' => 'deletion'], methods: ['GET'])]
    public function document(Request $request): Response
    {
        $document = $request->attributes->getString('document');
        if (!in_array($document, ['index', 'notices', 'terms', 'sales', 'cookies', 'deletion'], true)) {
            throw $this->createNotFoundException();
        }

        return $this->render('legal/'.$document.'.html.twig', [], new Response(headers: ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store']));
    }
}
