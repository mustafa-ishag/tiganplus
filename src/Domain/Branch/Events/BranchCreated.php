<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\Events;

use EtganERP\Domain\Shared\DomainEventInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;
use EtganERP\Domain\Branch\ValueObjects\BranchName;

/**
 * حدث إنشاء فرع
 * Branch Created Event
 */
final class BranchCreated implements DomainEventInterface
{
    public function __construct(
        private readonly Id $branchId,
        private readonly BranchCode $code,
        private readonly BranchName $name
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

    public function name(): BranchName
    {
        return $this->name;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
