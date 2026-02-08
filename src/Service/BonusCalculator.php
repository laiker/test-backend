<?php

namespace App\Service;

use App\Diagnostics\RuntimeDiagnostics;
use App\Entity\Bonus;
use App\Repository\PartnerRepository;
use App\Repository\SaleRepository;
use App\Repository\BonusRepository;

class BonusCalculator
{
    public function __construct(
        private readonly PartnerRepository $partnerRepository,
        private readonly SaleRepository $saleRepository,
        private readonly BonusRepository $bonusRepository,
    ) {}

    public function calculateForPartners(array $partnerIds, string $period): void
    {
        $partners = $this->partnerRepository->findByIds($partnerIds);
        
        foreach ($partners as $partner) {
            $sales = $this->saleRepository->findCompletedSalesByPartnerId(
                $partner->getId()
            );
            
            $bonusAmount = $this->calculateBonusAmount($sales, $partner->getTier());
            
            $bonus = new Bonus(
                partnerId: $partner->getId(),
                amount: $bonusAmount,
                period: $period,
            );
            
            $this->bonusRepository->save($bonus);
        }
    }

    public function applyMultiplier(float $baseBonus, string $tier): float
    {
        $multipliers = [
            'gold' => 1.5,
            'silver' => 1.2,
            'bronze' => 1.0,
        ];

        $multiplier = $multipliers[$tier] ?? $multipliers['gold'];
        
        return $baseBonus * $multiplier;
    }

    private function calculateBonusAmount(array $sales, string $tier): string
    {
        $totalSales = 0.0;
        
        foreach ($sales as $sale) {
            $totalSales += (float) $sale->getAmount();
        }
        
        $baseBonus = $totalSales * 0.05;
        
        $finalBonus = $this->applyMultiplier($baseBonus, $tier);
        
        return number_format($finalBonus, 2, '.', '');
    }
}
