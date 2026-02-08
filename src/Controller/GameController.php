<?php

namespace App\Controller;

use ReflectionClass;
use ReflectionProperty;

use App\DTO\TableRulesDTO;
use App\DTO\Phase\PhaseDTO;

use App\Entity\Card;
use App\Entity\Phase;
use App\Entity\TableRules;

use App\Game\CardPile\DeckFactory;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/game")]
final class GameController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer
    ) {
    }

    #[Route("/create_table_rules", methods: ["POST"])]
    public function createTableRules(#[MapRequestPayload()] TableRulesDTO $tableRulesDTO): JsonResponse
    {
        $table_rule = new TableRules();
        $table_rule->setMaxPlayers($tableRulesDTO->maxPlayers);
        $table_rule->setTableType($tableRulesDTO->tableType->value);

        $this->em->persist($table_rule);
        foreach ($tableRulesDTO->phases as $phaseDTO) {
            $phase = new Phase();
            $phase->setTableRules($table_rule);
            $phase->setPriority($phaseDTO->priority);
            $phase->setTimeout($phaseDTO->timeout);
            $phase->setType($phaseDTO->getType());

            // We get all keys that are not in the base properties
            $additionnal_properties = $this->getAdditionnalProperties($phaseDTO, PhaseDTO::class);
            if (!empty($additionnal_properties)) {
                $phase->setAdditionnalProperties($additionnal_properties);
            }

            $this->em->persist($phase);
        }

        $deck = DeckFactory::create($tableRulesDTO->deckRules);
        foreach ($deck->getCards() as $card) {
            $card_entity = Card::fromGameCard($card);
            $this->em->persist($card_entity);
            $table_rule->addCard($card_entity);
        }

        $this->em->flush();
        return $this->json(["rule" => $table_rule,], context: ["groups" => ["show_extended_rule", "show_phase", "show_card"]]);
    }

    private function getAdditionnalProperties(object $object, object|string $baseObject): array
    {
        $object_reflection = new ReflectionClass($object);
        $base_object_reflection = new ReflectionClass($baseObject);

        $object_props = $object_reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $base_object_props = $base_object_reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $obj_prop_names = array_map(fn($p) => $p->getName(), $object_props);
        $base_obj_prop_names = array_map(fn($p) => $p->getName(), $base_object_props);

        $additionnal_properties = array_values(array_diff($obj_prop_names, $base_obj_prop_names));

        $result = [];
        foreach ($additionnal_properties as $prop) {
            $result[$prop] = $object->$prop;
        }
        return $result;
    }

    #[Route("/get_all_rules", methods: ["GET"])]
    public function getAllRules(): JsonResponse
    {
        $rules = $this->em->getRepository(TableRules::class)->findAll();
        return $this->json(["rules" => $rules], context: ["groups" => ["show_extended_rule", "show_phase", "show_card"]]);
    }


    #[Route("/delete_rule/{id}", methods: ["DELETE"])]

    public function deleteRule(int $id): JsonResponse
    {
        $rule = $this->em->getRepository(TableRules::class)->findOneBy(["id" => $id]);

        if (empty($rule)) {
            throw $this->createNotFoundException();
        }

        $this->em->remove($rule);
        $this->em->flush();

        return $this->json(["removed" => true, "rule_id" => $id]);
    }
}
