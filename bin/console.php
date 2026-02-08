<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Command\CalculateBonusesCommand;
use App\Service\BonusCalculator;
use App\Repository\PartnerRepository;
use App\Repository\SaleRepository;
use App\Repository\BonusRepository;

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'calculate-bonuses':
        $period = $argv[2] ?? date('Y-m');
        
        $command = new CalculateBonusesCommand(
            new BonusCalculator(
                new PartnerRepository(),
                new SaleRepository(),
                new BonusRepository()
            ),
            new PartnerRepository()
        );
        
        exit($command->execute($period));
        
    case 'help':
    default:
        echo "Available commands:\n";
        echo "  php bin/console.php calculate-bonuses [period]\n";
        echo "  php bin/console.php help\n";
        exit(0);
}
