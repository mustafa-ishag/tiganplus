<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\UpdateWorkOrderType;

use EtganERP\Domain\WorkOrderType\WorkOrderTypeRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeDescription;
use EtganERP\Domain\Shared\ValueObjects\Status;
use InvalidArgumentException;

/**
 * معالج تحديث نوع أمر عمل
 * Update Work Order Type Handler
 */
final class UpdateWorkOrderTypeHandler
{
    public function __construct(
        private readonly WorkOrderTypeRepositoryInterface $workOrderTypeRepository
    ) {
    }

    public function handle(UpdateWorkOrderTypeCommand $command): UpdateWorkOrderTypeResponse
    {
        // البحث عن نوع أمر العمل
        $typeId = new Id($command->id);
        $workOrderType = $this->workOrderTypeRepository->findById($typeId);

        if (!$workOrderType) {
            throw new InvalidArgumentException('نوع أمر العمل غير موجود');
        }

        // التحقق من صحة البيانات
        $description = $command->description ? new TypeDescription($command->description) : null;
        $status = new Status($command->status);

        // تحديث نوع أمر العمل
        $workOrderType->updateInfo($description);

        // تحديث الحالة
        if ($status->isActive()) {
            $workOrderType->activate();
        } else {
            $workOrderType->deactivate();
        }

        // حفظ التحديثات
        $this->workOrderTypeRepository->save($workOrderType);

        return new UpdateWorkOrderTypeResponse(
            $workOrderType->id()->value(),
            $workOrderType->code()->value(),
            'تم تحديث نوع أمر العمل بنجاح'
        );
    }
}
