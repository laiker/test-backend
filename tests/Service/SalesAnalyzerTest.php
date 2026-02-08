<?php

namespace App\Tests\Service;

use App\Repository\SaleRepository;
use App\Service\SalesAnalyzer;
use App\Tests\Support\PrettyPhpUnitOutput;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

#[TestDox('Сервис аналитики продаж')]
class SalesAnalyzerTest extends TestCase
{
    use PrettyPhpUnitOutput;
    private const GATE_DOC = 'README.md';

    #[TestDox('Контракт зависимостей соблюдён')]
    public function testSalesAnalyzerDependencyContract(): void
    {
        $this->info('Проверка: сервис аналитики должен иметь корректный DI-контракт');

        $reflection = new ReflectionClass(SalesAnalyzer::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || count($constructor->getParameters()) === 0) {
            $this->fail($this->errorBlock(
                'Не задан контракт внешней зависимости в конструкторе.',
                [
                    'SLO: сервис аналитики должен принимать зависимость через конструктор',
                    'См: ' . self::GATE_DOC . ' → раздел "Модульность и гибкость архитектуры"',
                ]
            ));
        }

        $file = file_get_contents($reflection->getFileName()) ?: '';
        if (str_contains($file, 'Connection::getInstance(') || str_contains($file, 'new PDO(')) {
            $this->fail($this->errorBlock(
                'Сервис аналитики напрямую использует инфраструктурное подключение.',
                [
                    'SLO: бизнес-сервис не должен создавать/получать DB connection напрямую',
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
        $reflection = new ReflectionClass(SalesAnalyzer::class);
        $method = $reflection->getMethod('getTopProducts');
        $returnType = $method->getReturnType();

        if (!$returnType instanceof ReflectionNamedType) {
            $this->fail($this->errorBlock(
                'Нарушен контракт результата аналитики.',
                [
                    'SLO: метод должен иметь явный return type',
                    'Факт: return type не определён явно',
                    'См: ' . self::GATE_DOC . ' → раздел "Строгость контрактов данных"',
                ]
            ));
        }
        if ($returnType->isBuiltin()) {
            $this->fail($this->errorBlock(
                'Нарушен контракт результата аналитики.',
                [
                    'SLO: результат должен быть объектом-контрактом, а не builtin-типом',
                    'Факт: return type = ' . $returnType->getName(),
                    'См: ' . self::GATE_DOC . ' → раздел "Строгость контрактов данных"',
                ]
            ));
        }

        $this->success('Проверка контракта результата аналитики пройдена.');
    }
}
