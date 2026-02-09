<?php

namespace App\Command;

use App\Service\BonusCalculator;
use App\Repository\PartnerRepository;

class CalculateBonusesCommand
{
    public function __construct(
        private readonly BonusCalculator $bonusCalculator,
        private readonly PartnerRepository $partnerRepository,
    ) {}

    public function execute(string $period): int
    {
        echo "Calculating bonuses for period: {$period}\n";
        echo str_repeat('-', 72) . "\n";

        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        echo "Starting memory usage: " . $this->formatBytes($startMemory) . "\n";
        
        $partners = $this->partnerRepository->findActivePartners();
        $totalPartners = count($partners);
        
        echo "Found {$totalPartners} active partners\n";
        echo "Processing...\n";
        
        $processedCount = 0;
        foreach ($partners as $partner) {
            $this->bonusCalculator->calculateForPartners(
                [$partner->getId()],
                $period
            );

            $processedCount++;

            if ($processedCount % 100 === 0) {
                $currentMemory = memory_get_usage();
                echo "\n";
                echo "Processed {$processedCount}/{$totalPartners}";
            }
        }

        $duration = microtime(true) - $startTime;


        echo "\n\n";
        echo "Successfully calculated bonuses for {$processedCount} partners\n";
        echo "Duration: " . number_format($duration, 3, '.', '') . " sec\n";
        echo str_repeat('-', 72) . "\n";

        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
