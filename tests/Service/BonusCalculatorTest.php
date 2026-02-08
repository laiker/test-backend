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
    private const GATE_DOC = 'README.md';

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

    #[TestDox('Невалидные tier отклоняются')]
    public function testInvalidTierMustBeRejected(): void
    {
        $this->info("Проверка: невалидные tier должны приводить к отказу");

        $calculator = $this->createCalculator();

        $invalidTiers = ['invalid', '', '0'];
        $accepted = [];

        foreach ($invalidTiers as $tier) {
            try {
                $calculator->applyMultiplier(100.0, $tier);
                $accepted[] = $tier;
            } catch (\InvalidArgumentException) {
            }
        }

        if ($accepted !== []) {
            $this->fail($this->errorBlock(
                'Нарушен SLO валидации tier.',
                [
                    'Ожидалось отклонение всех невалидных tier.',
                    'Фактически приняты: ' . implode(', ', $accepted),
                    'См: ' . self::GATE_DOC . ' → раздел "Чистота входных данных (Data Integrity)"',
                ]
            ));
        }

        self::assertSame([], $accepted);
        $this->success('Проверка валидации tier пройдена.');
    }

    #[TestDox('Базовый расчёт бонусов корректен')]
    public function testBusinessCalculationMustBeCorrect(): void
    {
        $this->info('Проверка: базовая бизнес-логика расчёта не должна ломаться при оптимизациях');

        $partners = [
            new Partner(id: 1, name: 'P1', tier: 'gold', active: true),
            new Partner(id: 2, name: 'P2', tier: 'bronze', active: true),
        ];

        $sales = [
            new Sale(id: 1, partnerId: 1, amount: '100.00', productName: 'A', status: 'completed'),
            new Sale(id: 2, partnerId: 1, amount: '100.00', productName: 'B', status: 'completed'),
            new Sale(id: 3, partnerId: 2, amount: '100.00', productName: 'C', status: 'completed'),
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
        sort($amounts);

        if ($amounts !== [5.0, 15.0]) {
            $this->fail($this->errorBlock(
                'Нарушена корректность бизнес-расчёта бонусов.',
                [
                    'Ожидаемые суммы: 5.00 и 15.00',
                    'Фактические суммы: ' . implode(', ', array_map(
                        static fn (float $amount): string => number_format($amount, 2, '.', ''),
                        $amounts
                    )),
                    'См: ' . self::GATE_DOC . ' → раздел "Бизнес-процесс"',
                ]
            ));
        }

        self::assertSame([5.0, 15.0], $amounts);
        $this->success('Бизнес-расчёт бонусов корректен.');
    }

    #[TestDox('Агрегация продаж через замыкание корректна')]
    public function testClosureBasedAggregationMustBeCorrect(): void
    {
        $this->info('Проверка: суммирование продаж через анонимную функцию не должно терять значения');

        $partners = [
            new Partner(id: 7, name: 'Closure Partner', tier: 'bronze', active: true),
        ];

        $sales = [
            new Sale(id: 1, partnerId: 7, amount: '70.00', productName: 'A', status: 'completed'),
            new Sale(id: 2, partnerId: 7, amount: '30.00', productName: 'B', status: 'completed'),
        ];

        $partnerRepository = new class($partners) extends PartnerRepository {
            public function __construct(private array $partners) {}

            public function findByIds(array $ids): array
            {
                return $this->partners;
            }
        };

        $saleRepository = new class($sales) extends SaleRepository {
            public function __construct(private array $sales) {}

            public function findCompletedSalesByPartnerId(int $partnerId): array
            {
                return $this->sales;
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
        $calculator->calculateForPartners([7], '2026-02');

        $actualAmount = $bonusRepository->saved[0]->getAmount() ?? '0.00';
        if ($actualAmount !== '5.00') {
            $this->fail($this->errorBlock(
                'Нарушена корректность суммирования через замыкание.',
                [
                    'Ожидаемый бонус: 5.00 (100.00 * 5% * bronze)',
                    'Факт: ' . $actualAmount,
                    'См: README.md → раздел "Бизнес-процесс"',
                ]
            ));
        }

        self::assertSame('5.00', $actualAmount);
        $this->success('Суммирование через замыкание работает корректно.');
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
