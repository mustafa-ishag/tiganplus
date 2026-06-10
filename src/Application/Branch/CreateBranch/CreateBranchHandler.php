<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\CreateBranch;

use EtganERP\Domain\Branch\Branch;
use EtganERP\Domain\Branch\BranchRepositoryInterface;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;
use EtganERP\Domain\Branch\ValueObjects\BranchName;
use EtganERP\Domain\Branch\ValueObjects\BranchDescription;
use InvalidArgumentException;

/**
 * معالج إنشاء فرع
 * Create Branch Handler
 */
final class CreateBranchHandler
{
    public function __construct(
        private readonly BranchRepositoryInterface $branchRepository
    ) {
    }

    public function handle(CreateBranchCommand $command): CreateBranchResponse
    {
        // التحقق من صحة البيانات
        $code = new BranchCode($command->code);
        $name = new BranchName($command->name);
        $description = $command->description ? new BranchDescription($command->description) : null;

        // التحقق من عدم وجود رمز الفرع
        if ($this->branchRepository->existsByCode($code)) {
            throw new InvalidArgumentException('رمز الفرع موجود بالفعل');
        }

        // إنشاء الفرع
        $branchId = $this->branchRepository->nextId();
        
        $branch = Branch::create(
            $branchId,
            $code,
            $name,
            $description
        );

        // حفظ الفرع
        $this->branchRepository->save($branch);

        return new CreateBranchResponse(
            $branch->id()->value(),
            $branch->code()->value(),
            $branch->name()->value(),
            'تم إنشاء الفرع بنجاح'
        );
    }
}
