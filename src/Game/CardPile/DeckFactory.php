<?php

namespace App\Game\CardPile;

use App\DTO\DeckGenerationDTO;

use App\Enum\CardRankEnum;
use App\Enum\CardSymbolEnum;
use App\Enum\DeckGenerationTypeEnum;

use App\Game\Card\Card;

use Exception;

class DeckFactory
{
    private DeckGenerationDTO $deckGenerationDTO;

    public function __construct(DeckGenerationDTO $deckGenerationDTO)
    {
        $this->deckGenerationDTO = $deckGenerationDTO;
    }

    public function newDeck(): Deck
    {
        return static::create($this->deckGenerationDTO);
    }

    public static function create(DeckGenerationDTO $deckDTO): Deck
    {
        $deck = new Deck(maxSize: $deckDTO->maxSize, noDuplicate: $deckDTO->noDuplicate);

        if ($deckDTO->generationType === DeckGenerationTypeEnum::AUTOMATIC) {
            $card_gen_config = $deckDTO->cardGenerationConfig;

            if (empty($card_gen_config)) {
                throw new Exception("Cannot generate a deck without a card generation config provided !");
            }

            foreach ($card_gen_config->ranks as $i => $rank) {
                foreach ($card_gen_config->symbols as $symbol) {
                    $card = new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::tryFrom($symbol));
                    $deck->pushCard($card);
                }
            }
        } else {
            $cards = $deckDTO->cards;
            foreach ($cards as $card) {
                $real_card = new Card(CardRankEnum::tryFrom($card->getRank()), CardSymbolEnum::tryFrom($card->getSymbol()));
                $deck->pushCard($real_card);
            }
        }

        return $deck;
    }
}