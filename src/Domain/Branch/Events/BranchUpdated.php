<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\Events;

use EtganERP\Domain\Shared\DomainEventInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;

/**
 * حدث تحديث فرع
 * Branch Updated Event
 */
final class BranchUpdated implements DomainEventInterface
{
    public function __construct(
        private readonly Id $branchId,
        private readonly BranchCode $code
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

    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
