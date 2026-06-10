<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\UpdateWorkOrder;

use EtganERP\Domain\WorkOrder\WorkOrderRepositoryInterface;
use EtganERP\Domain\WorkOrderType\WorkOrderTypeRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\Department;
use EtganERP\Domain\WorkOrder\ValueObjects\DisbursementStatus;
use EtganERP\Domain\WorkOrder\ValueObjects\Money;
use InvalidArgumentException;

/**
 * معالج تحديث أمر عمل
 * Update Work Order Handler
 */
final class UpdateWorkOrderHandler
{
    public function __construct(
        private readonly WorkOrderRepositoryInterface $workOrderRepository,
        private readonly WorkOrderTypeRepositoryInterface $workOrderTypeRepository
    ) {
    }

    public function handle(UpdateWorkOrderCommand $command): UpdateWorkOrderResponse
    {
        // البحث عن أمر العمل
        $workOrderId = new Id($command->id);
        $workOrder = $this->workOrderRepository->findById($workOrderId);

        if (!$workOrder) {
            throw new InvalidArgumentException('أمر العمل غير موجود');
        }

        // التحقق من صحة البيانات
        $workOrderTypeId = new Id($command->workOrderTypeId);
        $department = new Department($command->department);
        $currentEntityId = $command->currentEntityId ? new Id($command->currentEntityId) : null;

        // التحقق من وجود نوع أمر العمل
        $workOrderType = $this->workOrderTypeRepository->findById($workOrderTypeId);
        if (!$workOrderType) {
            throw new InvalidArgumentException('نوع أمر العمل غير موجود');
        }

        if (!$workOrderType->isActive()) {
            throw new InvalidArgumentException('نوع أمر العمل غير نشط');
        }

        // تحويل التواريخ
        $assignmentDate = $command->assignmentDate ? DateTime::fromString($command->assignmentDate) : null;
        $receiptDate = $command->receiptDate ? DateTime::fromString($command->receiptDate) : null;

        // التحقق من صحة التواريخ
        if ($assignmentDate && $receiptDate && $assignmentDate->value() > $receiptDate->value()) {
            throw new InvalidArgumentException('تاريخ التكليف لا يمكن أن يكون بعد تاريخ الاستلام');
        }

        // تحويل القيم المالية
        $estimatedValue = $command->estimatedValue ? new Money($command->estimatedValue) : null;

        // تحديث المعلومات الأساسية
        $workOrder->updateBasicInfo(
            $workOrderTypeId,
            $department,
            $currentEntityId,
            $assignmentDate,
            $receiptDate,
            $estimatedValue,
            $command->notes
        );

        // تحديث القيمة الفعلية إذا تم توفيرها
        if ($command->actualValue !== null) {
            $actualValue = new Money($command->actualValue);
            $workOrder->updateActualValue($actualValue);
        }

        // تحديث حالة الصرف إذا تم توفيرها
        if ($command->disbursementStatus !== null) {
            $disbursementStatus = new DisbursementStatus($command->disbursementStatus);
            $workOrder->updateDisbursementStatus($disbursementStatus);
        }

        // حفظ التحديثات
        $this->workOrderRepository->save($workOrder);

        return new UpdateWorkOrderResponse(
            $workOrder->id()->value(),
            $workOrder->workOrderNumber()->value(),
            'تم تحديث أمر العمل بنجاح'
        );
    }
}
