<?php

namespace App\Controller;

use App\Game\Card\Card;
use App\Game\CardPile\Deck;

use App\DTO\DeckGenerationDTO;
use App\Enum\DeckGenerationTypeEnum;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/deck")]
final class DeckController extends AbstractController
{
    #[Route("/generate")]
    public function generateDeck(#[MapRequestPayload()] DeckGenerationDTO $deckDTO): JsonResponse
    {
        $deck = new Deck(maxSize: $deckDTO->maxSize, noDuplicate: $deckDTO->noDuplicate);

        if ($deckDTO->generationType === DeckGenerationTypeEnum::AUTOMATIC) {
            $card_gen_config = $deckDTO->cardGenerationConfig;

            if (empty($card_gen_config)) {
                throw new HttpException(403, "Cannot generate a deck without a card generation config provided !");
            }

            foreach ($card_gen_config->ranks as $i => $rank) {
                $weight = $i;
                foreach ($card_gen_config->symbols as $symbol) {
                    $card = new Card($rank, $symbol, $weight + 0);
                    $deck->pushCard($card);
                }
            }
        } else {
            $cards = $deckDTO->cards;
            foreach ($cards as $card) {
                $real_card = new Card($card->getRank(), $card->getSymbol(), $card->getWeight());
                $deck->pushCard($real_card);
            }
        }

        return $this->json(["deck" => $deck]);
    }

    #[Route("/shuffle")]
    public function shuffleDeck(#[MapRequestPayload()] Deck $deck): JsonResponse
    {
        $deck->shuffle();
        return $this->json(["deck" => $deck]);
    }

    #[Route("/pop/{number}")]
    public function popDeck(int $number, #[MapRequestPayload()] Deck $deck): JsonResponse
    {
        $cards = [];
        for ($i = 0; $i < $number; $i++) {
            $cards[] = $deck->pop();
        }

        return $this->json(["cards" => $cards]);
    }
}
