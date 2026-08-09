<?php

namespace App\Controller;

use Exception;
use ReflectionClass;
use ReflectionProperty;

use App\DTO\StakeDTO;
use App\DTO\VariantDTO;
use App\DTO\Phase\PhaseDTO;
use App\DTO\UpdateStakesDTO;

use App\Entity\Card;
use App\Entity\Phase;
use App\Entity\Stake;
use App\Entity\Variant;
use App\Entity\VariantCards;
use App\Entity\VariantPhases;

use App\Repository\VariantRepository;

use App\Game\CardPile\DeckFactory;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/admin/variant")]
class VariantController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private VariantRepository $variantRepository)
    {
    }

    #[Route("/get_all", methods: ["GET"])]
    public function getAllVariants(): JsonResponse
    {
        $variants = $this->variantRepository->findAll();
        return $this->json(["variants" => $variants], context: [
            "groups" => [
                "show_variant",
                "show_phase",
                "show_stake",
                "show_card"
            ]
        ]);
    }


    private function getVariantEntity(int $id): Variant
    {
        $variant = $this->variantRepository->findOneBy(["variant_id" => $id]);

        if (empty($variant)) {
            throw $this->createNotFoundException();
        }

        return $variant;
    }

    #[Route("/get/{id}", methods: ["GET"])]
    public function getVariant(int $id): JsonResponse
    {
        $variant = $this->getVariantEntity($id);

        return $this->json(["variant" => $variant], context: [
            "groups" => [
                "show_variant",
                "show_phase",
                "show_stake",
                "show_card"
            ]
        ]);
    }

    #[Route("/add", methods: ["POST"])]
    public function createVariant(#[MapRequestPayload()] VariantDTO $variantDTO): JsonResponse
    {
        try {
            $this->em->beginTransaction();
            $variant = new Variant();
            $this->setVariantEntity($variantDTO, $variant);

            // Add stake
            $this->addStake($variant, $variantDTO->stake);

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json(["variant" => $variant,], context: ["groups" => ["show_extended_variant", "show_phase", "show_card"]]);
    }

    private function setVariantEntity(VariantDTO $variantDTO, Variant $variant): Variant
    {
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

        return $variant;
    }

    private function addStake(Variant $variant, StakeDTO $stakeDTO): void
    {
        if ($stakeDTO->minBuyIn > $stakeDTO->maxBuyIn) {
            throw new Exception("Minimum buy in can't exceed maximum buy in");
        }

        $stake = new Stake();
        $stake->setMinBuyIn($stakeDTO->minBuyIn);
        $stake->setMaxBuyIn($stakeDTO->maxBuyIn);
        $stake->setVariant($variant);
        $this->em->persist($stake);
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



    #[Route("/delete/{id}", methods: ["DELETE"])]
    public function deleteVariant(int $id): JsonResponse
    {
        try {
            $this->em->beginTransaction();

            $variant = $this->getVariantEntity($id);
            $this->em->remove($variant);

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }
        return $this->json(["removed" => true, "variant_id" => $id]);
    }

    #[Route("/update/{id}", methods: ["POST"])]
    public function updateVariant(int $id, #[MapRequestPayload()] VariantDTO $variantDTO): JsonResponse
    {
        try {
            $this->em->beginTransaction();

            $variant = $this->getVariantEntity($id);

            // Don't forget to clear previous cards and phases to not duplicate them
            $variant->getVariantCards()->clear();
            $variant->getVariantPhases()->clear();

            $this->setVariantEntity($variantDTO, $variant);

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json(["variant" => $variant,], context: ["groups" => ["show_extended_variant", "show_phase", "show_card"]]);
    }

    #[Route("/update_stakes/{id}", methods: ["POST"])]
    public function updateStakes(int $id, #[MapRequestPayload()] UpdateStakesDTO $updateStakesDTO): JsonResponse
    {
        try {
            $this->em->beginTransaction();

            $variant = $this->getVariantEntity($id);

            $variant->getStakes()->clear();

            foreach ($updateStakesDTO->stakes as $stake) {
                $this->addStake($variant, $stake);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json(["variant" => $variant,], context: ["groups" => ["show_extended_variant", "show_phase", "show_card"]]);

    }
}