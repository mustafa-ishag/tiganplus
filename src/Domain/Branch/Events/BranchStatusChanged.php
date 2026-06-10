<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\Events;

use EtganERP\Domain\Shared\DomainEventInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;

/**
 * حدث تغيير حالة فرع
 * Branch Status Changed Event
 */
final class BranchStatusChanged implements DomainEventInterface
{
    public function __construct(
        private readonly Id $branchId,
        private readonly BranchCode $code,
        private readonly Status $oldStatus,
        private readonly Status $newStatus
    ) {
    }

    public function branchId(): Id
    {
        return $this->branchId;
    }

    public function code(): BranchCode
    {
        return $this->code;
    }

    public function oldStatus(): Status
    {
        return $this->oldStatus;
    }

    public function newStatus(): Status
    {
        return $this->newStatus;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
