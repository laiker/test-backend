<?php

namespace App\Service;

use App\Database\Connection;
use PDO;

class SalesAnalyzer
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Connection::getInstance();
    }

    public function getTopProducts(int $partnerId): array
    {
        $sql = "
            SELECT product_name, SUM(CAST(amount AS DECIMAL)) as total_amount
            FROM sales
            WHERE partner_id = :partnerId
            AND status = 'completed'
            GROUP BY product_name
            ORDER BY total_amount DESC
            LIMIT 10
        ";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['partnerId' => $partnerId]);
        
        return $stmt->fetchAll();
    }

    public function getTotalSalesAmount(int $partnerId): float
    {
        $sql = "
            SELECT SUM(CAST(amount AS DECIMAL)) as total
            FROM sales
            WHERE partner_id = ?
            AND status = 'completed'
        ";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$partnerId]);
        
        $result = $stmt->fetchColumn();
        
        return (float) ($result ?? 0);
    }
}
