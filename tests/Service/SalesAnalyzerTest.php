<?php

namespace App\Tests\Service;

use App\Repository\SaleRepository;
use App\Service\SalesAnalyzer;
use App\Tests\Support\PrettyPhpUnitOutput;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[TestDox('Сервис аналитики продаж')]
class SalesAnalyzerTest extends TestCase
{
    use PrettyPhpUnitOutput;
    private const GATE_DOC = 'docs/interview-gate-process.md';

    #[TestDox('Контракт зависимостей соблюдён')]
    public function testSalesAnalyzerDependencyContract(): void
    {
        $this->info('Проверка: сервис аналитики должен иметь корректный DI-контракт');

        $reflection = new ReflectionClass(SalesAnalyzer::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $this->fail($this->errorBlock(
                'Не найден конструктор зависимостей.',
                [
                    'SLO: у сервиса должен быть явный конструктор с dependency injection',
                    'См: ' . self::GATE_DOC . ' → раздел "Модульность и гибкость архитектуры"',
                ]
            ));
        }

        $parameters = $constructor->getParameters();
        if (count($parameters) !== 1) {
            $this->fail($this->errorBlock(
                'Нарушен контракт зависимостей сервиса.',
                [
                    'SLO: количество зависимостей = 1',
                    'Факт: ' . count($parameters),
                    'См: ' . self::GATE_DOC . ' → раздел "Модульность и гибкость архитектуры"',
                ]
            ));
        }

        $parameter = $parameters[0];
        if ($parameter->getType() === null) {
            $this->fail($this->errorBlock(
                'Тип зависимости не определён.',
                [
                    'SLO: зависимость должна быть строго типизирована',
                    'См: ' . self::GATE_DOC . ' → раздел "Модульность и гибкость архитектуры"',
                ]
            ));
        }

        $dependencyType = (string) $parameter->getType();

        if (!interface_exists($dependencyType)) {
            $this->fail($this->errorBlock(
                'Сервис зависит от конкретной реализации вместо абстракции.',
                [
                    'SLO: зависимость аналитики должна быть контрактом',
                    'Факт: ' . $dependencyType,
                    'См: ' . self::GATE_DOC . ' → раздел "Модульность и гибкость архитектуры"',
                ]
            ));
        }

        $this->success('Проверка DI-контракта аналитики пройдена.');
    }

    #[TestDox('Контракт выходных данных соблюдён')]
    public function testAnalyticsOutputContract(): void
    {
        $this->info('Проверка: аналитика должна возвращать объект контракта');

        $saleRepository = new class extends SaleRepository {
            public function __construct() {}

            public function findTopProductsByPartnerId(int $partnerId, int $limit): array
            {
                return [
                    ['product_name' => 'A', 'total_amount' => '500.00'],
                    ['product_name' => 'B', 'total_amount' => '200.00'],
                ];
            }

            public function findCompletedSalesByPartnerId(int $partnerId): array
            {
                return [];
            }
        };

        $analyzer = new SalesAnalyzer($saleRepository);
        $topProducts = $analyzer->getTopProducts(1);

        if (!is_object($topProducts)) {
            $this->fail($this->errorBlock(
                'Нарушен контракт результата аналитики.',
                [
                    'SLO: результат должен быть объектом-сущностью',
                    'Факт: получен тип ' . get_debug_type($topProducts),
                    'См: ' . self::GATE_DOC . ' → раздел "Строгость контрактов данных"',
                ]
            ));
        }

        $resultClass = get_class($topProducts);
        if (!str_starts_with($resultClass, 'App\\Entity\\')) {
            $this->fail($this->errorBlock(
                'Объект результата не соответствует domain-контракту.',
                [
                    'SLO: контракт результата должен быть domain entity',
                    'Факт: ' . $resultClass,
                    'См: ' . self::GATE_DOC . ' → раздел "Строгость контрактов данных"',
                ]
            ));
        }

        $this->success('Проверка контракта результата аналитики пройдена.');
    }
}
