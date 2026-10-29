<?php

declare(strict_types=1);

use App\Exception\DomainException;
use App\Service\CardService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Seeders\Seeder;

/**
 * PANs de teste públicos. O seeder passa pelo CardService, então o número
 * vai ao cofre e só BIN, final, bandeira e token ficam no MySQL.
 */
class DatabaseSeeder extends Seeder
{
    public $withinTransaction = false;

    public function run(): void
    {
        $service = ApplicationContext::getContainer()->get(CardService::class);

        $cards = [
            ['Ada Lovelace', '4111111111111111', 12, 2030],
            ['Grace Hopper', '5555555555554444', 11, 2030],
            ['Alan Turing', '2221000000000009', 10, 2030],
            ['Katherine Johnson', '378282246310005', 9, 2030],
            ['Margaret Hamilton', '6362970000457013', 8, 2030],
            ['Evelyn Boyd', '4011780000000006', 7, 2030],
            ['Radia Perlman', '6062825624254001', 6, 2030],
        ];

        foreach ($cards as [$holder, $number, $month, $year]) {
            try {
                $card = $service->create($holder, $number, $month, $year);
                echo sprintf(
                    "Cartão %s %s %s\n",
                    $card['brand'],
                    $card['masked_number'],
                    $card['token']
                );
            } catch (DomainException $exception) {
                if ($exception->status !== 409) {
                    throw $exception;
                }
                echo "Cartão de {$holder} já existia. Seguindo.\n";
            }
        }
    }
}
