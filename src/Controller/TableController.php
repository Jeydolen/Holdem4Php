<?php

namespace App\Controller;

use App\DTO\VariantSearchDTO;

use App\Repository\TableRepository;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/table")]
final class TableController extends AbstractController
{
    public function __construct(
        private TableRepository $tableRepository,
    ) {
    }

    #[Route("/get_all_tables")]
    public function getAllTables(
        #[MapRequestPayload()] VariantSearchDTO $variant
    ): JsonResponse {
        $criterias = [];

        if (!empty($variant->tableType)) {
            $criterias["table_type"] = $variant->tableType->value;
        }

        if (!empty($variant->name)) {
            $criterias["name"] = $variant->name;
        }

        if (!empty($variant->maxPlayers)) {
            $criterias["max_players"] = $variant->maxPlayers;
        }

        if (!empty($variant->bettingType)) {
            $criterias["betting_type"] = $variant->bettingType->name;
        }

        $tables = $this->tableRepository->findByVariantCriterias($criterias, limit: 15);
        return $this->json(["tables" => $tables], context: ["groups" => ["show_table", "show_variant", "show_table_stake"]]);
    }
}