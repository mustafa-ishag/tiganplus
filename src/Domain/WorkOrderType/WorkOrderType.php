<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrderType;

use EtganERP\Domain\Shared\AggregateRoot;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeDescription;
use EtganERP\Domain\WorkOrderType\Events\WorkOrderTypeCreated;
use EtganERP\Domain\WorkOrderType\Events\WorkOrderTypeUpdated;

/**
 * كيان نوع أمر العمل
 * Work Order Type Entity
 */
final class WorkOrderType extends AggregateRoot
{
    private function __construct(
        Id $id,
        private TypeCode $code,
        private ?TypeDescription $description,
        private Status $status,
        ?DateTime $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public static function create(
        Id $id,
        TypeCode $code,
        ?TypeDescription $description = null
    ): self {
        $workOrderType = new self(
            $id,
            $code,
            $description,
            Status::active()
        );

        $workOrderType->recordDomainEvent(new WorkOrderTypeCreated($id, $code));

        return $workOrderType;
    }

    public static function fromPersistence(
        Id $id,
        TypeCode $code,
        ?TypeDescription $description,
        Status $status,
        DateTime $createdAt,
        ?DateTime $updatedAt = null
    ): self {
        $workOrderType = new self($id, $code, $description, $status, $createdAt);

        if ($updatedAt) {
            $workOrderType->updatedAt = $updatedAt;
        }

        return $workOrderType;
    }

    // Getters
    public function code(): TypeCode
    {
        return $this->code;
    }

    public function description(): ?TypeDescription
    {
        return $this->description;
    }

    public function status(): Status
    {
        return $this->status;
    }

    // Business Methods
    public function updateInfo(
        ?TypeDescription $description = null
    ): void {
        $this->description = $description;
        $this->markAsUpdated();

        $this->recordDomainEvent(new WorkOrderTypeUpdated($this->id, $this->code));
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            return;
        }

        $this->status = Status::active();
        $this->markAsUpdated();
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            return;
        }

        $this->status = Status::inactive();
        $this->markAsUpdated();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canBeDeleted(): bool
    {
        // يمكن حذف نوع أمر العمل إذا كان غير نشط
        // في التطبيق الحقيقي، يجب التحقق من عدم وجود أوامر عمل مرتبطة
        return $this->status->isInactive();
    }
}
