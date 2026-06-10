<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\Branch\Branch;
use EtganERP\Domain\Branch\BranchRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;
use EtganERP\Domain\Branch\ValueObjects\BranchName;
use EtganERP\Domain\Branch\ValueObjects\BranchDescription;
use EtganERP\Infrastructure\Database\DatabaseConnection;

/**
 * مستودع الفروع
 * Branch Repository Implementation
 */
final class BranchRepository implements BranchRepositoryInterface
{
    public function findById(Id $id): ?Branch
    {
        $sql = "SELECT * FROM branches WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByCode(BranchCode $code): ?Branch
    {
        $sql = "SELECT * FROM branches WHERE code = ?";
        $data = DatabaseConnection::fetchOne($sql, [$code->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function save(Branch $branch): void
    {
        $data = $this->mapToArray($branch);
        
        if ($this->exists($branch->id())) {
            $this->update($branch->id(), $data);
        } else {
            $this->insert($data);
        }
    }

    public function delete(Branch $branch): void
    {
        $sql = "DELETE FROM branches WHERE id = ?";
        DatabaseConnection::execute($sql, [$branch->id()->value()]);
    }

    public function existsByCode(BranchCode $code): bool
    {
        return DatabaseConnection::exists('branches', 'code = ?', [$code->value()]);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM branches ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM branches WHERE status = 'active' ORDER BY name ASC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function search(string $searchTerm): array
    {
        $sql = "SELECT * FROM branches WHERE (code LIKE ? OR name LIKE ? OR notes LIKE ?) ORDER BY name ASC";
        $searchPattern = "%$searchTerm%";
        $results = DatabaseConnection::fetchAll($sql, [$searchPattern, $searchPattern, $searchPattern]);

        return array_map([$this, 'mapToEntity'], $results);
    }

    public function count(): int
    {
        return DatabaseConnection::count('branches');
    }

    public function countActive(): int
    {
        return DatabaseConnection::count('branches', 'status = ?', ['active']);
    }

    public function nextId(): Id
    {
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 FROM branches";
        $nextId = DatabaseConnection::fetchColumn($sql);
        
        return new Id((int) $nextId);
    }

    private function exists(Id $id): bool
    {
        return DatabaseConnection::exists('branches', 'id = ?', [$id->value()]);
    }

    private function insert(array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO branches ($columns) VALUES ($placeholders)";
        DatabaseConnection::execute($sql, $data);
    }

    private function update(Id $id, array $data): void
    {
        unset($data['id']); // إزالة المعرف من البيانات
        
        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $sql = "UPDATE branches SET $setClause WHERE id = :id";
        
        $data['id'] = $id->value();
        DatabaseConnection::execute($sql, $data);
    }

    private function mapToEntity(array $data): Branch
    {
        return Branch::fromPersistence(
            new Id((int) $data['id']),
            new BranchCode($data['code']),
            new BranchName($data['name']),
            $data['notes'] ? new BranchDescription($data['notes']) : null,
            new Status($data['status']),
            DateTime::fromString($data['created_at']),
            $data['updated_at'] ? DateTime::fromString($data['updated_at']) : null
        );
    }

    private function mapToArray(Branch $branch): array
    {
        return [
            'id' => $branch->id()->value(),
            'code' => $branch->code()->value(),
            'name' => $branch->name()->value(),
            'notes' => $branch->description()?->value(),
            'status' => $branch->status()->value(),
            'created_at' => $branch->createdAt()->format(),
            'updated_at' => $branch->updatedAt()?->format()
        ];
    }
}
