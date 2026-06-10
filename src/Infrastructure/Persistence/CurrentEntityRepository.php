<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\CurrentEntity\CurrentEntity;
use EtganERP\Domain\WorkOrder\ValueObjects\CurrentEntityId;
use EtganERP\Infrastructure\Database\DatabaseConnection;
use Exception;

final class CurrentEntityRepository
{

    public function findAll(): array
    {
        $sql = "SELECT * FROM current_entities ORDER BY name ASC";
        $results = DatabaseConnection::fetchAll($sql);

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM current_entities WHERE is_active = 1 ORDER BY name ASC";
        $results = DatabaseConnection::fetchAll($sql);

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    public function findById(CurrentEntityId $id): ?CurrentEntity
    {
        $sql = "SELECT * FROM current_entities WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);

        return $data ? $this->mapRowToEntity($data) : null;
    }

    public function findByName(string $name): ?CurrentEntity
    {
        $sql = "SELECT * FROM current_entities WHERE name = ?";
        $data = DatabaseConnection::fetchOne($sql, [$name]);

        return $data ? $this->mapRowToEntity($data) : null;
    }

    public function findByCode(string $code): ?CurrentEntity
    {
        $sql = "SELECT * FROM current_entities WHERE code = ?";
        $data = DatabaseConnection::fetchOne($sql, [$code]);

        return $data ? $this->mapRowToEntity($data) : null;
    }

    public function save(CurrentEntity $entity): void
    {
        $sql = "INSERT INTO current_entities (name, code, description, is_active) VALUES (?, ?, ?, ?)";

        DatabaseConnection::execute($sql, [
            $entity->name(),
            $entity->code(),
            $entity->description(),
            $entity->isActive() ? 1 : 0
        ]);
    }

    public function update(CurrentEntity $entity): void
    {
        $sql = "UPDATE current_entities SET name = ?, code = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";

        DatabaseConnection::execute($sql, [
            $entity->name(),
            $entity->code(),
            $entity->description(),
            $entity->isActive() ? 1 : 0,
            $entity->id()->value()
        ]);
    }

    public function delete(CurrentEntityId $id): void
    {
        // التحقق من عدم وجود أوامر عمل مرتبطة
        $checkSql = "SELECT COUNT(*) FROM work_orders WHERE current_entity_id = ?";
        $count = DatabaseConnection::fetchColumn($checkSql, [$id->value()]);

        if ($count > 0) {
            throw new Exception('لا يمكن حذف الجهة الحالية لأنها مرتبطة بأوامر عمل');
        }

        $sql = "DELETE FROM current_entities WHERE id = ?";
        DatabaseConnection::execute($sql, [$id->value()]);
    }

    public function existsByName(string $name, ?CurrentEntityId $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM current_entities WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId->value();
        }

        $count = DatabaseConnection::fetchColumn($sql, $params);
        return $count > 0;
    }

    public function existsByCode(string $code, ?CurrentEntityId $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM current_entities WHERE code = ?";
        $params = [$code];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId->value();
        }

        $count = DatabaseConnection::fetchColumn($sql, $params);
        return $count > 0;
    }

    private function mapRowToEntity(array $row): CurrentEntity
    {
        return new CurrentEntity(
            new CurrentEntityId((int) $row['id']),
            $row['name'],
            $row['code'],
            $row['description'],
            (bool) $row['is_active']
        );
    }
}
