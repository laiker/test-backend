<?php

namespace App\Repository;

use App\Database\Connection;
use App\Entity\Sale;
use DateTimeImmutable;
use PDO;

class SaleRepository
{
    private ?PDO $connection = null;

    public function __construct()
    {
        $this->connection = Connection::getInstance();
    }

    public function findCompletedSalesByPartnerId(int $partnerId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM sales
             WHERE partner_id = :partner_id
             AND status = 'completed'
             ORDER BY created_at DESC"
        );
        $stmt->execute(['partner_id' => $partnerId]);

        $sales = [];
        while ($data = $stmt->fetch()) {
            $sales[] = $this->hydrate($data);
        }

        return $sales;
    }

    public function findCompletedSalesByPartnerIds(array $partnerIds): array
    {
        if (empty($partnerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT * FROM sales
             WHERE partner_id IN ({$placeholders})
             AND status = 'completed'
             ORDER BY partner_id, created_at DESC"
        );
        $stmt->execute($partnerIds);

        $sales = [];
        while ($data = $stmt->fetch()) {
            $sales[] = $this->hydrate($data);
        }

        return $sales;
    }

    private function hydrate(array $data): Sale
    {
        return new Sale(
            id: (int) $data['id'],
            partnerId: (int) $data['partner_id'],
            amount: $data['amount'],
            productName: $data['product_name'],
            status: $data['status'],
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }
}
