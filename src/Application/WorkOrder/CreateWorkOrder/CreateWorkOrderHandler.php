<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\CreateWorkOrder;

use EtganERP\Domain\WorkOrder\WorkOrder;
use EtganERP\Domain\WorkOrder\WorkOrderRepositoryInterface;
use EtganERP\Domain\Branch\BranchRepositoryInterface;
use EtganERP\Domain\WorkOrderType\WorkOrderTypeRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\Department;
use EtganERP\Domain\WorkOrder\ValueObjects\Money;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;
use InvalidArgumentException;

/**
 * معالج إنشاء أمر عمل
 * Create Work Order Handler
 */
final class CreateWorkOrderHandler
{
    public function __construct(
        private readonly WorkOrderRepositoryInterface $workOrderRepository,
        private readonly BranchRepositoryInterface $branchRepository,
        private readonly WorkOrderTypeRepositoryInterface $workOrderTypeRepository
    ) {
    }

    public function handle(CreateWorkOrderCommand $command): CreateWorkOrderResponse
    {
        // التحقق من صحة البيانات
        $workOrderTypeId = new Id($command->workOrderTypeId);
        $branchId = new Id($command->branchId);
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

        // التحقق من وجود الفرع
        $branch = $this->branchRepository->findById($branchId);
        if (!$branch) {
            throw new InvalidArgumentException('الفرع غير موجود');
        }

        if (!$branch->isActive()) {
            throw new InvalidArgumentException('الفرع غير نشط');
        }

        // تحويل التواريخ
        $assignmentDate = $command->assignmentDate ? DateTime::fromString($command->assignmentDate) : null;
        $receiptDate = $command->receiptDate ? DateTime::fromString($command->receiptDate) : null;

        // التحقق من صحة التواريخ
        if ($assignmentDate && $receiptDate && $assignmentDate->value() > $receiptDate->value()) {
            throw new InvalidArgumentException('تاريخ التكليف لا يمكن أن يكون بعد تاريخ الاستلام');
        }

        // تحويل القيمة المقدرة
        $estimatedValue = $command->estimatedValue ? new Money($command->estimatedValue) : null;

        // استخدام رقم أمر العمل اليدوي
        $workOrderNumber = new WorkOrderNumber($command->workOrderNumber);

        // التحقق من عدم وجود رقم أمر العمل
        if ($this->workOrderRepository->existsByWorkOrderNumber($workOrderNumber->value())) {
            throw new InvalidArgumentException('رقم أمر العمل موجود بالفعل');
        }

        // إنشاء أمر العمل
        $workOrderId = $this->workOrderRepository->nextId();

        $workOrder = WorkOrder::create(
            $workOrderId,
            $workOrderNumber,
            $workOrderTypeId,
            $department,
            $branchId,
            $currentEntityId,
            $command->location,
            $assignmentDate,
            $receiptDate,
            $estimatedValue,
            $command->notes
        );

        // حفظ أمر العمل
        $this->workOrderRepository->save($workOrder);

        return new CreateWorkOrderResponse(
            $workOrder->id()->value(),
            $workOrder->workOrderNumber()->value(),
            'تم إنشاء أمر العمل بنجاح'
        );
    }
}
