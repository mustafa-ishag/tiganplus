<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch;

use EtganERP\Domain\Shared\AggregateRoot;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;
use EtganERP\Domain\Branch\ValueObjects\BranchName;
use EtganERP\Domain\Branch\ValueObjects\BranchDescription;
use EtganERP\Domain\Branch\Events\BranchCreated;
use EtganERP\Domain\Branch\Events\BranchUpdated;
use EtganERP\Domain\Branch\Events\BranchStatusChanged;

/**
 * كيان الفرع
 * Branch Entity
 */
final class Branch extends AggregateRoot
{
    private function __construct(
        Id $id,
        private BranchCode $code,
        private BranchName $name,
        private ?BranchDescription $description,
        private Status $status,
        ?DateTime $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public static function create(
        Id $id,
        BranchCode $code,
        BranchName $name,
        ?BranchDescription $description = null
    ): self {
        $branch = new self(
            $id,
            $code,
            $name,
            $description,
            Status::active()
        );

        $branch->recordDomainEvent(new BranchCreated($id, $code, $name));

        return $branch;
    }

    public static function fromPersistence(
        Id $id,
        BranchCode $code,
        BranchName $name,
        ?BranchDescription $description,
        Status $status,
        DateTime $createdAt,
        ?DateTime $updatedAt = null
    ): self {
        $branch = new self($id, $code, $name, $description, $status, $createdAt);
        
        if ($updatedAt) {
            $branch->updatedAt = $updatedAt;
        }

        return $branch;
    }

    // Getters
    public function code(): BranchCode
    {
        return $this->code;
    }

    public function name(): BranchName
    {
        return $this->name;
    }

    public function description(): ?BranchDescription
    {
        return $this->description;
    }

    public function status(): Status
    {
        return $this->status;
    }

    // Business Methods
    public function updateInfo(
        BranchName $name,
        ?BranchDescription $description = null
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->markAsUpdated();

        $this->recordDomainEvent(new BranchUpdated($this->id, $this->code));
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::active();
        $this->markAsUpdated();

        $this->recordDomainEvent(new BranchStatusChanged($this->id, $this->code, $oldStatus, $this->status));
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::inactive();
        $this->markAsUpdated();

        $this->recordDomainEvent(new BranchStatusChanged($this->id, $this->code, $oldStatus, $this->status));
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canBeDeleted(): bool
    {
        // يمكن حذف الفرع إذا كان غير نشط
        // في التطبيق الحقيقي، يجب التحقق من عدم وجود مستخدمين أو أوامر عمل مرتبطة
        return $this->status->isInactive();
    }
}
