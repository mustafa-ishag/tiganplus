<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\CreateBranch;

/**
 * استجابة إنشاء فرع
 * Create Branch Response
 */
final class CreateBranchResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $message
    ) {
    }
}
