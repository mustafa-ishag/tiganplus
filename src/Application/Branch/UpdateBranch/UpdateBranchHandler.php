<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\UpdateBranch;

use EtganERP\Domain\Branch\BranchRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Branch\ValueObjects\BranchName;
use EtganERP\Domain\Branch\ValueObjects\BranchDescription;
use InvalidArgumentException;

/**
 * معالج تحديث فرع
 * Update Branch Handler
 */
final class UpdateBranchHandler
{
    public function __construct(
        private readonly BranchRepositoryInterface $branchRepository
    ) {
    }

    public function handle(UpdateBranchCommand $command): UpdateBranchResponse
    {
        // البحث عن الفرع
        $branchId = new Id($command->id);
        $branch = $this->branchRepository->findById($branchId);

        if (!$branch) {
            throw new InvalidArgumentException('الفرع غير موجود');
        }

        // التحقق من صحة البيانات
        $name = new BranchName($command->name);
        $description = $command->description ? new BranchDescription($command->description) : null;

        // تحديث الفرع
        $branch->updateInfo($name, $description);

        // حفظ التحديثات
        $this->branchRepository->save($branch);

        return new UpdateBranchResponse(
            $branch->id()->value(),
            $branch->code()->value(),
            $branch->name()->value(),
            'تم تحديث الفرع بنجاح'
        );
    }
}
