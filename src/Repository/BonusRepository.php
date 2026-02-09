<?php

namespace App\Repository;

use App\Database\Connection;
use App\Entity\Bonus;
use DateTimeImmutable;
use PDO;

class BonusRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Connection::getInstance();
    }

    public function save(Bonus $bonus): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO bonuses (partner_id, amount, period, calculated_at)
             VALUES (:partner_id, :amount, :period, :calculated_at)'
        );

        $stmt->execute([
            'partner_id' => $bonus->getPartnerId(),
            'amount' => $bonus->getAmount(),
            'period' => $bonus->getPeriod(),
            'calculated_at' => $bonus->getCalculatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
