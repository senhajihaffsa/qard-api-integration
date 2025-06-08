<?php
// src/Controller/CompanyController.php

namespace App\Controller;

use App\Service\QardClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends AbstractController
{
    private QardClient $qardClient;

    public function __construct(QardClient $qardClient)
    {
        $this->qardClient = $qardClient;
    }

    /**
     * @Route("/companies", name="companies_index")
     */
    public function index(): Response
    {
        $sirenList = [
            '834816985' => 'QARD.',
            '839836608' => 'ARIA',
            '852379890' => 'MANSA GROUP',
            '832872436' => 'ALGOAN',
            '813414620' => 'YELLOAN',
            '830256558' => 'AVANSEO',
        ];

        $companies = [];

        foreach ($sirenList as $siren => $name) {
            $userData = $this->qardClient->createLegalUser($name, $siren);

            if ($userData && isset($userData['id']) && $this->isValidUuid($userData['id'])) {
                $profile = $this->qardClient->getCompanyProfile($userData['id']);

                $companies[] = [
                    'name' => $name,
                    'siren' => $siren,
                    'userId' => $userData['id'],
                    'redirect_url' => $userData['redirect_url'] ?? '#',
                    'created_at' => $profile['creation_date'] ?? 'Profil indisponible',
                    'status' => $profile['legal']['status'] ?? 'Non communiqué',
                ];
            } else {
                $this->addFlash('warning', "User ID invalide pour {$name} / {$siren}");
            }
        }

        return $this->render('company/index.html.twig', [
            'companies' => $companies
        ]);
    }

    private function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }
}