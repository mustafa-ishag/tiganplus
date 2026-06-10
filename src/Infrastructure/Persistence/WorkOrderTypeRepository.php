<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\WorkOrderType\WorkOrderType;
use EtganERP\Domain\WorkOrderType\WorkOrderTypeRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeDescription;
use EtganERP\Infrastructure\Database\DatabaseConnection;

/**
 * مستودع أنواع أوامر العمل
 * Work Order Type Repository Implementation
 */
final class WorkOrderTypeRepository implements WorkOrderTypeRepositoryInterface
{
    public function findById(Id $id): ?WorkOrderType
    {
        $sql = "SELECT * FROM work_order_types WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByCode(TypeCode $code): ?WorkOrderType
    {
        $sql = "SELECT * FROM work_order_types WHERE type_code = ?";
        $data = DatabaseConnection::fetchOne($sql, [$code->value()]);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function save(WorkOrderType $workOrderType): void
    {
        $data = $this->mapToArray($workOrderType);
        
        if ($this->exists($workOrderType->id())) {
            $this->update($workOrderType->id(), $data);
        } else {
            $this->insert($data);
        }
    }

    public function delete(WorkOrderType $workOrderType): void
    {
        $sql = "DELETE FROM work_order_types WHERE id = ?";
        DatabaseConnection::execute($sql, [$workOrderType->id()->value()]);
    }

    public function existsByCode(TypeCode $code): bool
    {
        return DatabaseConnection::exists('work_order_types', 'type_code = ?', [$code->value()]);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM work_order_types ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM work_order_types WHERE status = 'active' ORDER BY type_code ASC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function search(string $searchTerm): array
    {
        $sql = "SELECT * FROM work_order_types WHERE (type_code LIKE ? OR description LIKE ?) ORDER BY type_code ASC";
        $searchPattern = "%$searchTerm%";
        $results = DatabaseConnection::fetchAll($sql, [$searchPattern, $searchPattern]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function count(): int
    {
        return DatabaseConnection::count('work_order_types');
    }

    public function countActive(): int
    {
        return DatabaseConnection::count('work_order_types', 'status = ?', ['active']);
    }

    public function nextId(): Id
    {
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 FROM work_order_types";
        $nextId = DatabaseConnection::fetchColumn($sql);
        
        return new Id((int) $nextId);
    }

    private function exists(Id $id): bool
    {
        return DatabaseConnection::exists('work_order_types', 'id = ?', [$id->value()]);
    }

    private function insert(array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO work_order_types ($columns) VALUES ($placeholders)";
        DatabaseConnection::execute($sql, $data);
    }

    private function update(Id $id, array $data): void
    {
        unset($data['id']); // إزالة المعرف من البيانات
        
        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $sql = "UPDATE work_order_types SET $setClause WHERE id = :id";
        
        $data['id'] = $id->value();
        DatabaseConnection::execute($sql, $data);
    }

    private function mapToEntity(array $data): WorkOrderType
    {
        return WorkOrderType::fromPersistence(
            new Id((int) $data['id']),
            new TypeCode($data['type_code']),
            $data['description'] ? new TypeDescription($data['description']) : null,
            new Status($data['status']),
            DateTime::fromString($data['created_at']),
            $data['updated_at'] ? DateTime::fromString($data['updated_at']) : null
        );
    }

    private function mapToArray(WorkOrderType $workOrderType): array
    {
        return [
            'id' => $workOrderType->id()->value(),
            'type_code' => $workOrderType->code()->value(),
            'description' => $workOrderType->description()?->value(),
            'status' => $workOrderType->status()->value(),
            'created_at' => $workOrderType->createdAt()->format(),
            'updated_at' => $workOrderType->updatedAt()?->format()
        ];
    }
}
