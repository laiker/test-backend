<?php

namespace App\Tests\Service;

use App\Entity\Bonus;
use App\Entity\Partner;
use App\Entity\Sale;
use App\Repository\BonusRepository;
use App\Repository\PartnerRepository;
use App\Repository\SaleRepository;
use App\Service\BonusCalculator;
use App\Tests\Support\PrettyPhpUnitOutput;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Калькулятор бонусов')]
class BonusCalculatorTest extends TestCase
{
    use PrettyPhpUnitOutput;
    private const GATE_DOC = 'docs/interview-gate-process.md';

    #[TestDox('Скорость расчёта укладывается в SLA')]
    public function testSalesCalculationMustFitTimeBudget(): void
    {
        $this->info('Проверка: расчёт бонусов должен укладываться во временной бюджет');

        $partnersCount = 250;
        $partners = [
            ...array_map(
                static fn (int $id): Partner => new Partner(id: $id, name: 'P' . $id, tier: 'gold', active: true),
                range(1, $partnersCount)
            ),
        ];

        $sales = array_map(
            static fn (int $id): Sale => new Sale(
                id: $id,
                partnerId: $id,
                amount: '100.00',
                productName: 'SKU-' . $id,
                status: 'completed'
            ),
            range(1, $partnersCount)
        );

        $partnerRepository = new class($partners) extends PartnerRepository {
            public function __construct(private array $partners) {}
            public function findByIds(array $ids): array
            {
                return array_values(array_filter(
                    $this->partners,
                    static fn (Partner $partner): bool => in_array($partner->getId(), $ids, true)
                ));
            }
        };

        $saleRepository = new class($sales) extends SaleRepository {
            public int $singleCalls = 0;
            public int $batchCalls = 0;

            public function __construct(private array $sales) {}

            public function findCompletedSalesByPartnerId(int $partnerId): array
            {
                $this->singleCalls++;
                usleep(2000);
                return array_values(array_filter(
                    $this->sales,
                    static fn (Sale $sale): bool => $sale->getPartnerId() === $partnerId
                ));
            }

            public function findCompletedSalesByPartnerIds(array $partnerIds): array
            {
                $this->batchCalls++;

                return array_values(array_filter(
                    $this->sales,
                    static fn (Sale $sale): bool => in_array($sale->getPartnerId(), $partnerIds, true)
                ));
            }
        };

        $bonusRepository = new class extends BonusRepository {
            public array $saved = [];

            public function __construct() {}

            public function save(Bonus $bonus): void
            {
                $this->saved[] = $bonus;
            }
        };

        $calculator = new BonusCalculator($partnerRepository, $saleRepository, $bonusRepository);
        $partnerIds = range(1, $partnersCount);

        $startedAt = hrtime(true);
        $calculator->calculateForPartners($partnerIds, '2026-02');
        $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
        $maxSeconds = 0.30;

        if ($elapsedSeconds > $maxSeconds) {
            $this->fail($this->errorBlock(
                'Нарушен временной бюджет расчёта.',
                [
                    'SLO: длительность <= ' . number_format($maxSeconds, 2, '.', '') . ' sec',
                    'Факт: ' . number_format($elapsedSeconds, 3, '.', '') . ' sec',
                    'См: ' . self::GATE_DOC . ' → раздел "Скорость доступа к данным"',
                ]
            ));
        }

        if (count($bonusRepository->saved) !== $partnersCount) {
            $this->fail($this->errorBlock(
                'Некорректное количество сохранённых бонусов.',
                ["Ожидалось: {$partnersCount}, фактически: " . count($bonusRepository->saved)]
            ));
        }

        self::assertLessThanOrEqual($maxSeconds, $elapsedSeconds);
        $this->success('Проверка временного бюджета расчёта пройдена.');
    }

    #[TestDox('Невалидные tier безопасно обрабатываются')]
    public function testInvalidTierMustBeRejected(): void
    {
        $this->info('Проверка: невалидные данные tier должны обрабатываться безопасно');

        $calculator = $this->createCalculator();

        $invalidTiers = ['invalid', '', '0'];
        $results = [];

        foreach ($invalidTiers as $tier) {
            $results[$tier] = $calculator->applyMultiplier(100.0, $tier);
        }

        if ($results !== ['invalid' => 100.0, '' => 100.0, '0' => 100.0]) {
            $this->fail($this->errorBlock(
                'Нарушена безопасность обработки невалидных данных.',
                [
                    'SLO: невалидные tier должны нормализоваться к безопасному значению (x1.0).',
                    'Фактические результаты: ' . json_encode($results, JSON_UNESCAPED_UNICODE),
                    'См: ' . self::GATE_DOC . ' → раздел "Чистота входных данных (Data Integrity)"',
                ]
            ));
        }

        self::assertSame(['invalid' => 100.0, '' => 100.0, '0' => 100.0], $results);
        $this->success('Проверка безопасной обработки невалидных tier пройдена.');
    }

    #[TestDox('Базовый расчёт бонусов корректен')]
    public function testBusinessCalculationMustBeCorrect(): void
    {
        $this->info('Проверка: базовая бизнес-логика расчёта не должна ломаться при оптимизациях');

        $partners = [
            new Partner(id: 1, name: 'P1', tier: 'gold', active: true),
            new Partner(id: 2, name: 'P2', tier: 'silver', active: true),
        ];

        $sales = [
            new Sale(id: 1, partnerId: 1, amount: '20000.00', productName: 'A', status: 'completed'),
            new Sale(id: 2, partnerId: 1, amount: '20000.00', productName: 'B', status: 'completed'),
            new Sale(id: 3, partnerId: 2, amount: '52500.00', productName: 'C', status: 'completed'),
        ];

        $partnerRepository = new class($partners) extends PartnerRepository {
            public function __construct(private array $partners) {}

            public function findByIds(array $ids): array
            {
                return array_values(array_filter(
                    $this->partners,
                    static fn (Partner $partner): bool => in_array($partner->getId(), $ids, true)
                ));
            }
        };

        $saleRepository = new class($sales) extends SaleRepository {
            public function __construct(private array $sales) {}

            public function findCompletedSalesByPartnerId(int $partnerId): array
            {
                return array_values(array_filter(
                    $this->sales,
                    static fn (Sale $sale): bool => $sale->getPartnerId() === $partnerId
                ));
            }
        };

        $bonusRepository = new class extends BonusRepository {
            public array $saved = [];

            public function __construct() {}

            public function save(Bonus $bonus): void
            {
                $this->saved[] = $bonus;
            }
        };

        $calculator = new BonusCalculator($partnerRepository, $saleRepository, $bonusRepository);
        $calculator->calculateForPartners([1, 2], '2026-02');

        $amounts = array_map(static fn (Bonus $bonus): float => (float) $bonus->getAmount(), $bonusRepository->saved);

        foreach ($amounts as $amount) {
            if ($amount <= 0) {
                $this->fail($this->errorBlock(
                    'Нарушена корректность бизнес-расчёта бонусов.',
                    [
                        'Все бонусы должны быть больше 0',
                        'Фактические суммы: ' . implode(', ', array_map(
                            static fn (float $amount): string => number_format($amount, 2, '.', ''),
                            $amounts
                        )),
                        'См: ' . self::GATE_DOC . ' → раздел "Бизнес-процесс"',
                    ]
                ));
            }
            self::assertGreaterThan(0, $amount);
        }

        $this->success('Бизнес-расчёт бонусов корректен.');
    }

    private function createCalculator(): BonusCalculator
    {
        $partnerRepository = new class extends PartnerRepository {
            public function __construct() {}
        };

        $saleRepository = new class extends SaleRepository {
            public function __construct() {}
        };

        $bonusRepository = new class extends BonusRepository {
            public function __construct() {}
        };

        return new BonusCalculator($partnerRepository, $saleRepository, $bonusRepository);
    }
}
