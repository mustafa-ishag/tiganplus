<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\CreateWorkOrderType;

use EtganERP\Domain\WorkOrderType\WorkOrderType;
use EtganERP\Domain\WorkOrderType\WorkOrderTypeRepositoryInterface;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeDescription;
use EtganERP\Domain\Shared\ValueObjects\Status;
use InvalidArgumentException;

/**
 * معالج إنشاء نوع أمر عمل
 * Create Work Order Type Handler
 */
final class CreateWorkOrderTypeHandler
{
    public function __construct(
        private readonly WorkOrderTypeRepositoryInterface $workOrderTypeRepository
    ) {
    }

    public function handle(CreateWorkOrderTypeCommand $command): CreateWorkOrderTypeResponse
    {
        // التحقق من صحة البيانات
        $code = new TypeCode($command->code);
        $description = $command->description ? new TypeDescription($command->description) : null;
        $status = new Status($command->status);

        // التحقق من عدم وجود كود نوع أمر العمل
        if ($this->workOrderTypeRepository->existsByCode($code)) {
            throw new InvalidArgumentException('كود نوع أمر العمل موجود بالفعل');
        }

        // إنشاء نوع أمر العمل
        $typeId = $this->workOrderTypeRepository->nextId();

        $workOrderType = WorkOrderType::create(
            $typeId,
            $code,
            $description
        );

        // تعيين الحالة
        if ($status->isActive()) {
            $workOrderType->activate();
        } else {
            $workOrderType->deactivate();
        }

        // حفظ نوع أمر العمل
        $this->workOrderTypeRepository->save($workOrderType);

        return new CreateWorkOrderTypeResponse(
            $workOrderType->id()->value(),
            $workOrderType->code()->value(),
            'تم إنشاء نوع أمر العمل بنجاح'
        );
    }
}
