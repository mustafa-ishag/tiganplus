<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\WorkOrder\WorkOrder;
use EtganERP\Domain\WorkOrder\WorkOrderRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;
use EtganERP\Domain\WorkOrder\ValueObjects\Department;
use EtganERP\Domain\WorkOrder\ValueObjects\DisbursementStatus;
use EtganERP\Domain\WorkOrder\ValueObjects\Money;
use EtganERP\Infrastructure\Database\DatabaseConnection;

/**
 * مستودع أوامر العمل
 * Work Order Repository Implementation
 */
final class WorkOrderRepository implements WorkOrderRepositoryInterface
{
    public function findById(Id $id): ?WorkOrder
    {
        $sql = "SELECT * FROM work_orders WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByNumber(WorkOrderNumber $number): ?WorkOrder
    {
        $sql = "SELECT * FROM work_orders WHERE work_order_number = ?";
        $data = DatabaseConnection::fetchOne($sql, [$number->value()]);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function save(WorkOrder $workOrder): void
    {
        $data = $this->mapToArray($workOrder);
        
        if ($this->exists($workOrder->id())) {
            $this->update($workOrder->id(), $data);
        } else {
            $this->insert($data);
        }
    }

    public function delete(WorkOrder $workOrder): void
    {
        $sql = "DELETE FROM work_orders WHERE id = ?";
        DatabaseConnection::execute($sql, [$workOrder->id()->value()]);
    }

    public function existsByNumber(WorkOrderNumber $number): bool
    {
        return DatabaseConnection::exists('work_orders', 'work_order_number = ?', [$number->value()]);
    }

    public function existsByWorkOrderNumber(string $workOrderNumber): bool
    {
        return DatabaseConnection::exists('work_orders', 'work_order_number = ?', [$workOrderNumber]);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM work_orders ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM work_orders WHERE status = 'active' ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByBranch(Id $branchId): array
    {
        $sql = "SELECT * FROM work_orders WHERE branch_id = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$branchId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByType(Id $workOrderTypeId): array
    {
        $sql = "SELECT * FROM work_orders WHERE work_order_type_id = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$workOrderTypeId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByDepartment(string $department): array
    {
        $sql = "SELECT * FROM work_orders WHERE department = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$department]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findUnassignedToExtract(): array
    {
        $sql = "SELECT * FROM work_orders WHERE extract_id IS NULL AND status = 'active' ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByExtract(Id $extractId): array
    {
        $sql = "SELECT * FROM work_orders WHERE extract_id = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$extractId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function search(string $searchTerm): array
    {
        $sql = "SELECT * FROM work_orders WHERE (work_order_number LIKE ? OR notes LIKE ?) ORDER BY created_at DESC";
        $searchPattern = "%$searchTerm%";
        $results = DatabaseConnection::fetchAll($sql, [$searchPattern, $searchPattern]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function advancedSearch(array $criteria): array
    {
        $conditions = [];
        $params = [];

        if (!empty($criteria['branch_id'])) {
            $conditions[] = 'branch_id = ?';
            $params[] = $criteria['branch_id'];
        }

        if (!empty($criteria['work_order_type_id'])) {
            $conditions[] = 'work_order_type_id = ?';
            $params[] = $criteria['work_order_type_id'];
        }

        if (!empty($criteria['department'])) {
            $conditions[] = 'department = ?';
            $params[] = $criteria['department'];
        }

        if (!empty($criteria['status'])) {
            $conditions[] = 'status = ?';
            $params[] = $criteria['status'];
        }

        if (!empty($criteria['disbursement_status'])) {
            $conditions[] = 'disbursement_status = ?';
            $params[] = $criteria['disbursement_status'];
        }

        if (!empty($criteria['date_from'])) {
            $conditions[] = 'created_at >= ?';
            $params[] = $criteria['date_from'];
        }

        if (!empty($criteria['date_to'])) {
            $conditions[] = 'created_at <= ?';
            $params[] = $criteria['date_to'];
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT * FROM work_orders $whereClause ORDER BY created_at DESC";
        
        $results = DatabaseConnection::fetchAll($sql, $params);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function count(): int
    {
        return DatabaseConnection::count('work_orders');
    }

    public function countActive(): int
    {
        return DatabaseConnection::count('work_orders', 'status = ?', ['active']);
    }

    public function countByBranch(Id $branchId): int
    {
        return DatabaseConnection::count('work_orders', 'branch_id = ?', [$branchId->value()]);
    }

    public function nextId(): Id
    {
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 FROM work_orders";
        $nextId = DatabaseConnection::fetchColumn($sql);
        
        return new Id((int) $nextId);
    }

    public function generateWorkOrderNumber(string $branchCode): WorkOrderNumber
    {
        $currentYear = date('y'); // آخر رقمين من السنة
        $prefix = strtoupper($branchCode) . $currentYear;
        
        // البحث عن آخر رقم أمر عمل للفرع في السنة الحالية
        $sql = "SELECT work_order_number FROM work_orders 
                WHERE work_order_number LIKE ? 
                ORDER BY work_order_number DESC 
                LIMIT 1";
        
        $lastNumber = DatabaseConnection::fetchColumn($sql, [$prefix . '%']);
        
        if ($lastNumber) {
            // استخراج الرقم التسلسلي وزيادته
            $sequentialPart = (int) substr($lastNumber, -4);
            $newSequential = $sequentialPart + 1;
        } else {
            // أول أمر عمل للفرع في السنة
            $newSequential = 1;
        }
        
        // تكوين رقم أمر العمل الجديد
        $newNumber = $prefix . str_pad((string) $newSequential, 4, '0', STR_PAD_LEFT);
        
        return new WorkOrderNumber($newNumber);
    }

    private function exists(Id $id): bool
    {
        return DatabaseConnection::exists('work_orders', 'id = ?', [$id->value()]);
    }

    private function insert(array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO work_orders ($columns) VALUES ($placeholders)";
        DatabaseConnection::execute($sql, $data);
    }

    private function update(Id $id, array $data): void
    {
        unset($data['id']); // إزالة المعرف من البيانات

        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $sql = "UPDATE work_orders SET $setClause WHERE id = :id";

        $data['id'] = $id->value();
        DatabaseConnection::execute($sql, $data);
    }

    private function mapToEntity(array $data): WorkOrder
    {
        return WorkOrder::fromPersistence(
            new Id((int) $data['id']),
            new WorkOrderNumber($data['work_order_number']),
            new Id((int) $data['work_order_type_id']),
            new Department($data['department']),
            $data['current_entity_id'] ? new Id((int) $data['current_entity_id']) : null,
            new Id((int) $data['branch_id']),
            $data['assignment_date'] ? DateTime::fromString($data['assignment_date']) : null,
            $data['receipt_date'] ? DateTime::fromString($data['receipt_date']) : null,
            new Money((float) $data['estimated_value']),
            new Money((float) $data['actual_value']),
            new DisbursementStatus($data['disbursement_status']),
            $data['notes'],
            $data['extract_id'] ? new Id((int) $data['extract_id']) : null,
            new Status($data['status']),
            DateTime::fromString($data['created_at']),
            $data['updated_at'] ? DateTime::fromString($data['updated_at']) : null
        );
    }

    private function mapToArray(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id()->value(),
            'work_order_number' => $workOrder->workOrderNumber()->value(),
            'work_order_type_id' => $workOrder->workOrderTypeId()->value(),
            'department' => $workOrder->department()->value(),
            'current_entity_id' => $workOrder->currentEntityId()?->value(),
            'branch_id' => $workOrder->branchId()->value(),
            'assignment_date' => $workOrder->assignmentDate()?->format('Y-m-d'),
            'receipt_date' => $workOrder->receiptDate()?->format('Y-m-d'),
            'estimated_value' => $workOrder->estimatedValue()->amount(),
            'actual_value' => $workOrder->actualValue()->amount(),
            'disbursement_status' => $workOrder->disbursementStatus()->value(),
            'notes' => $workOrder->notes(),
            'extract_id' => $workOrder->extractId()?->value(),
            'status' => $workOrder->status()->value(),
            'created_at' => $workOrder->createdAt()->format(),
            'updated_at' => $workOrder->updatedAt()?->format()
        ];
    }
}
