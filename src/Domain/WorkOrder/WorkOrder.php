<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder;

use EtganERP\Domain\Shared\AggregateRoot;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;
use EtganERP\Domain\WorkOrder\ValueObjects\Department;
use EtganERP\Domain\WorkOrder\ValueObjects\DisbursementStatus;
use EtganERP\Domain\WorkOrder\ValueObjects\Money;
use EtganERP\Domain\WorkOrder\Events\WorkOrderCreated;
use EtganERP\Domain\WorkOrder\Events\WorkOrderUpdated;
use EtganERP\Domain\WorkOrder\Events\WorkOrderStatusChanged;

/**
 * كيان أمر العمل
 * Work Order Entity
 */
final class WorkOrder extends AggregateRoot
{
    private function __construct(
        Id $id,
        private WorkOrderNumber $workOrderNumber,
        private Id $workOrderTypeId,
        private Department $department,
        private ?Id $currentEntityId,
        private Id $branchId,
        private ?string $location,
        private ?DateTime $assignmentDate,
        private ?DateTime $receiptDate,
        private Money $estimatedValue,
        private Money $actualValue,
        private DisbursementStatus $disbursementStatus,
        private ?string $notes,
        private ?Id $extractId,
        private Status $status,
        ?DateTime $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public static function create(
        Id $id,
        WorkOrderNumber $workOrderNumber,
        Id $workOrderTypeId,
        Department $department,
        Id $branchId,
        ?Id $currentEntityId = null,
        ?string $location = null,
        ?DateTime $assignmentDate = null,
        ?DateTime $receiptDate = null,
        ?Money $estimatedValue = null,
        ?string $notes = null
    ): self {
        $workOrder = new self(
            $id,
            $workOrderNumber,
            $workOrderTypeId,
            $department,
            $currentEntityId,
            $branchId,
            $location,
            $assignmentDate,
            $receiptDate,
            $estimatedValue ?? Money::zero(),
            Money::zero(),
            DisbursementStatus::none(),
            $notes,
            null,
            Status::active()
        );

        $workOrder->recordDomainEvent(new WorkOrderCreated($id, $workOrderNumber, $workOrderTypeId, $branchId));

        return $workOrder;
    }

    public static function fromPersistence(
        Id $id,
        WorkOrderNumber $workOrderNumber,
        Id $workOrderTypeId,
        Department $department,
        ?Id $currentEntityId,
        Id $branchId,
        ?DateTime $assignmentDate,
        ?DateTime $receiptDate,
        Money $estimatedValue,
        Money $actualValue,
        DisbursementStatus $disbursementStatus,
        ?string $notes,
        ?Id $extractId,
        Status $status,
        DateTime $createdAt,
        ?DateTime $updatedAt = null
    ): self {
        $workOrder = new self(
            $id,
            $workOrderNumber,
            $workOrderTypeId,
            $department,
            $currentEntityId,
            $branchId,
            $assignmentDate,
            $receiptDate,
            $estimatedValue,
            $actualValue,
            $disbursementStatus,
            $notes,
            $extractId,
            $status,
            $createdAt
        );

        if ($updatedAt) {
            $workOrder->updatedAt = $updatedAt;
        }

        return $workOrder;
    }

    // Getters
    public function workOrderNumber(): WorkOrderNumber
    {
        return $this->workOrderNumber;
    }

    public function workOrderTypeId(): Id
    {
        return $this->workOrderTypeId;
    }

    public function department(): Department
    {
        return $this->department;
    }

    public function currentEntityId(): ?Id
    {
        return $this->currentEntityId;
    }

    public function branchId(): Id
    {
        return $this->branchId;
    }

    public function assignmentDate(): ?DateTime
    {
        return $this->assignmentDate;
    }

    public function receiptDate(): ?DateTime
    {
        return $this->receiptDate;
    }

    public function estimatedValue(): Money
    {
        return $this->estimatedValue;
    }

    public function actualValue(): Money
    {
        return $this->actualValue;
    }

    public function disbursementStatus(): DisbursementStatus
    {
        return $this->disbursementStatus;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function extractId(): ?Id
    {
        return $this->extractId;
    }

    public function status(): Status
    {
        return $this->status;
    }

    // Business Methods
    public function updateBasicInfo(
        Id $workOrderTypeId,
        Department $department,
        ?Id $currentEntityId = null,
        ?DateTime $assignmentDate = null,
        ?DateTime $receiptDate = null,
        ?Money $estimatedValue = null,
        ?string $notes = null
    ): void {
        $this->workOrderTypeId = $workOrderTypeId;
        $this->department = $department;
        $this->currentEntityId = $currentEntityId;
        $this->assignmentDate = $assignmentDate;
        $this->receiptDate = $receiptDate;
        $this->estimatedValue = $estimatedValue ?? Money::zero();
        $this->notes = $notes;
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderUpdated($this->id, $this->workOrderNumber));
    }

    public function updateActualValue(Money $actualValue): void
    {
        $this->actualValue = $actualValue;
        $this->markAsUpdated();
    }

    public function updateEstimatedValue(Money $estimatedValue): void
    {
        $this->estimatedValue = $estimatedValue;
        $this->markAsUpdated();
    }

    public function updateDisbursementStatus(DisbursementStatus $disbursementStatus): void
    {
        $this->disbursementStatus = $disbursementStatus;
        $this->markAsUpdated();
    }

    public function updateDepartment(Department $department): void
    {
        $this->department = $department;
        $this->markAsUpdated();
    }

    public function updateCurrentEntity(?Id $currentEntityId): void
    {
        $this->currentEntityId = $currentEntityId;
        $this->markAsUpdated();
    }

    public function updateAssignmentDate(?DateTime $assignmentDate): void
    {
        $this->assignmentDate = $assignmentDate;
        $this->markAsUpdated();
    }

    public function updateLocation(?string $location): void
    {
        $this->location = $location;
        $this->markAsUpdated();
    }

    public function updateStatus(Status $status): void
    {
        if ($this->status->equals($status)) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = $status;
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderStatusChanged($this->id, $this->workOrderNumber, $oldStatus, $this->status));
    }

    public function assignToExtract(Id $extractId): void
    {
        $this->extractId = $extractId;
        $this->markAsUpdated();
    }

    public function removeFromExtract(): void
    {
        $this->extractId = null;
        $this->markAsUpdated();
    }

    public function complete(): void
    {
        if ($this->status->value() === 'completed') {
            return;
        }

        $oldStatus = $this->status;
        $this->status = new Status('completed');
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderStatusChanged($this->id, $this->workOrderNumber, $oldStatus, $this->status));
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::active();
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderStatusChanged($this->id, $this->workOrderNumber, $oldStatus, $this->status));
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::inactive();
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderStatusChanged($this->id, $this->workOrderNumber, $oldStatus, $this->status));
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCompleted(): bool
    {
        return $this->status->value() === 'completed';
    }

    public function isAssignedToExtract(): bool
    {
        return $this->extractId !== null;
    }
}
