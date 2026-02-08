<?php

namespace App\Tests\Command;

use App\Command\CalculateBonusesCommand;
use App\Entity\Partner;
use App\Repository\PartnerRepository;
use App\Service\BonusCalculator;
use App\Tests\Support\PrettyPhpUnitOutput;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Команда расчёта бонусов')]
class CalculateBonusesCommandTest extends TestCase
{
    use PrettyPhpUnitOutput;
    private const GATE_DOC = 'README.md';

    #[TestDox('Потребление памяти укладывается в SLA')]
    public function testPartnerLoadingMustBeMemorySafe(): void
    {
        $this->info('Проверка: загрузка партнёров должна быть memory-safe');

        $partnersCount = 1200;
        $payload = str_repeat('X', 8192);

        $bonusCalculator = new class extends BonusCalculator {
            public int $calls = 0;

            public function __construct() {}

            public function calculateForPartners(array $partnerIds, string $period): void
            {
                $this->calls++;
            }
        };

        $partnerRepository = new class($partnersCount, $payload) extends PartnerRepository {
            public function __construct(
                private int $partnersCount,
                private string $payload,
            ) {}

            public function findActivePartners(): array
            {
                $partners = [];
                for ($index = 1; $index <= $this->partnersCount; $index++) {
                    $partners[] = new Partner(
                        id: $index,
                        name: 'P' . $index . '-' . $this->payload,
                        tier: 'gold',
                        active: true
                    );
                }

                return $partners;
            }

            public function findActivePartnersIterator(): \Generator
            {
                for ($index = 1; $index <= $this->partnersCount; $index++) {
                    yield new Partner(
                        id: $index,
                        name: 'P' . $index . '-' . $this->payload,
                        tier: 'gold',
                        active: true
                    );
                }
            }
        };

        $command = new CalculateBonusesCommand($bonusCalculator, $partnerRepository);
        $maxPeakGrowthBytes = 6 * 1024 * 1024;

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $baselineUsage = memory_get_usage(true);

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $exitCode = $command->execute('2026-02');
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
        $peakGrowthBytes = memory_get_peak_usage(true) - $baselineUsage;

        if ($exitCode !== 0) {
            $this->fail($this->errorBlock(
                'Команда завершилась с ошибкой.',
                ["Ожидаемый код: 0, фактический: {$exitCode}"]
            ));
        }

        if ($bonusCalculator->calls !== $partnersCount) {
            $this->fail($this->errorBlock(
                'Обработано не то количество партнёров.',
                ["Ожидалось вызовов калькулятора: {$partnersCount}, фактически: {$bonusCalculator->calls}"]
            ));
        }

        if ($peakGrowthBytes > $maxPeakGrowthBytes) {
            $this->fail($this->errorBlock(
                'Нарушен memory-safe SLO для загрузки партнёров.',
                [
                    'SLO: пиковый рост памяти <= 6.00 MB',
                    'Факт: ' . number_format($peakGrowthBytes / 1024 / 1024, 2, '.', '') . ' MB',
                    'См: ' . self::GATE_DOC . ' → раздел "Эффективность использования ресурсов"',
                ]
            ));
        }

        self::assertLessThanOrEqual($maxPeakGrowthBytes, $peakGrowthBytes);
        $this->success('Проверка memory budget пройдена.');
    }
}
