<?php

namespace App\Controller;

use ReflectionClass;
use ReflectionProperty;

use App\DTO\VariantDTO;
use App\DTO\Phase\PhaseDTO;

use App\Entity\Card;
use App\Entity\Phase;
use App\Entity\Variant;
use App\Entity\VariantCards;
use App\Entity\VariantPhases;

use App\Game\CardPile\DeckFactory;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/admin/game")]
final class GameController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route("/create_variant", methods: ["POST"])]
    public function createVariant(#[MapRequestPayload()] VariantDTO $variantDTO): JsonResponse
    {
        $variant = new Variant();
        $variant->setMaxPlayers($variantDTO->maxPlayers);
        $variant->setTableType($variantDTO->tableType->value);
        $variant->setName($variantDTO->name);

        $this->em->persist($variant);
        foreach ($variantDTO->phases as $phaseDTO) {
            $phase = new Phase();
            $phase->setPriority($phaseDTO->priority);
            $phase->setTimeout($phaseDTO->timeout);
            $phase->setType($phaseDTO->getType());

            // We get all keys that are not in the base properties
            $additionnal_properties = $this->getAdditionnalProperties($phaseDTO, PhaseDTO::class);
            if (!empty($additionnal_properties)) {
                $phase->setAdditionnalProperties($additionnal_properties);
            }

            $this->em->persist($phase);

            $variantPhase = new VariantPhases();
            $variantPhase->setVariant($variant);
            $variantPhase->setPhase($phase);
            $this->em->persist($variantPhase);
        }


        $deck = DeckFactory::create($variantDTO->deckRules);
        foreach ($deck->getCards() as $card) {
            $card_entity = Card::fromGameCard($card);
            $this->em->persist($card_entity);

            $variantCard = new VariantCards();
            $variantCard->setCard($card_entity);
            $variantCard->setVariant($variant);
            $this->em->persist($variantCard);
        }

        $this->em->flush();
        return $this->json(["variant" => $variant,], context: ["groups" => ["show_extended_rule", "show_phase", "show_card"]]);
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

    #[Route("/get_all_variants", methods: ["GET"])]
    public function getAllVariants(): JsonResponse
    {
        $variants = $this->em->getRepository(Variant::class)->findAll();
        return $this->json(["variants" => $variants], context: ["groups" => ["show_variant", "show_phase", "show_card"]]);
    }


    #[Route("/delete_variant/{id}", methods: ["DELETE"])]
    public function deleteVariant(int $id): JsonResponse
    {
        $variant = $this->em->getRepository(Variant::class)->findOneBy(["variant_id" => $id]);

        if (empty($variant)) {
            throw $this->createNotFoundException();
        }

        $this->em->remove($variant);
        $this->em->flush();

        return $this->json(["removed" => true, "variant_id" => $id]);
    }
}
