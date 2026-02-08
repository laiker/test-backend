<?php

namespace App\Repository;

use App\Database\Connection;
use App\Diagnostics\RuntimeDiagnostics;
use App\Entity\Partner;
use PDO;

class PartnerRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Connection::getInstance();
    }

    public function findById(int $id): ?Partner
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM partners WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function findByIds(array $ids): array
    {
        RuntimeDiagnostics::increment('partner.find_by_ids.calls');

        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->connection->prepare(
            "SELECT * FROM partners WHERE id IN ({$placeholders})"
        );
        $stmt->execute($ids);

        $partners = [];
        while ($data = $stmt->fetch()) {
            $partners[] = $this->hydrate($data);
        }

        RuntimeDiagnostics::increment('partner.find_by_ids.loaded', count($partners));

        return $partners;
    }

    public function findActivePartners(): array
    {
        RuntimeDiagnostics::increment('partner.find_active.calls');

        $stmt = $this->connection->query(
            'SELECT * FROM partners WHERE active = true ORDER BY id'
        );

        $partners = [];
        while ($data = $stmt->fetch()) {
            $partners[] = $this->hydrate($data);
        }

        RuntimeDiagnostics::increment('partner.find_active.loaded', count($partners));

        return $partners;
    }

    private function hydrate(array $data): Partner
    {
        return new Partner(
            id: (int) $data['id'],
            name: $data['name'],
            tier: $data['tier'],
            active: (bool) $data['active'],
        );
    }
}
