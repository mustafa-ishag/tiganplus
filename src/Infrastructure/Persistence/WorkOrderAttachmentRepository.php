<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\WorkOrder\WorkOrderAttachment;
use EtganERP\Domain\WorkOrder\WorkOrderAttachmentRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\FormType;
use EtganERP\Domain\WorkOrder\ValueObjects\AttachmentStatus;
use EtganERP\Domain\WorkOrder\ValueObjects\CompletionCertificateConfirmation;
use EtganERP\Infrastructure\Database\DatabaseConnection;

/**
 * مستودع مرفقات أوامر العمل
 * Work Order Attachment Repository Implementation
 */
final class WorkOrderAttachmentRepository implements WorkOrderAttachmentRepositoryInterface
{
    public function findById(Id $id): ?WorkOrderAttachment
    {
        $sql = "SELECT * FROM work_order_attachments WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByWorkOrderAndFormType(Id $workOrderId, FormType $formType): ?WorkOrderAttachment
    {
        $sql = "SELECT * FROM work_order_attachments WHERE work_order_id = ? AND form_type = ?";
        $data = DatabaseConnection::fetchOne($sql, [$workOrderId->value(), $formType->value()]);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByWorkOrder(Id $workOrderId): array
    {
        $sql = "SELECT * FROM work_order_attachments WHERE work_order_id = ? ORDER BY form_type ASC";
        $results = DatabaseConnection::fetchAll($sql, [$workOrderId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function save(WorkOrderAttachment $attachment): void
    {
        $data = $this->mapToArray($attachment);
        
        if ($this->exists($attachment->workOrderId(), $attachment->formType())) {
            $this->update($attachment->id(), $data);
        } else {
            $this->insert($data);
        }
    }

    public function delete(WorkOrderAttachment $attachment): void
    {
        $sql = "DELETE FROM work_order_attachments WHERE id = ?";
        DatabaseConnection::execute($sql, [$attachment->id()->value()]);
    }

    public function exists(Id $workOrderId, FormType $formType): bool
    {
        return DatabaseConnection::exists(
            'work_order_attachments', 
            'work_order_id = ? AND form_type = ?', 
            [$workOrderId->value(), $formType->value()]
        );
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM work_order_attachments ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function search(string $searchTerm): array
    {
        $sql = "SELECT * FROM work_order_attachments WHERE (original_filename LIKE ? OR notes LIKE ?) ORDER BY created_at DESC";
        $searchPattern = "%$searchTerm%";
        $results = DatabaseConnection::fetchAll($sql, [$searchPattern, $searchPattern]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByStatus(string $status): array
    {
        $sql = "SELECT * FROM work_order_attachments WHERE status = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$status]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findConfirmedCompletionCertificates(): array
    {
        $sql = "SELECT * FROM work_order_attachments 
                WHERE form_type = 'completion_certificate' 
                AND completion_certificate_confirmation = 'confirmed' 
                ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findUnconfirmedCompletionCertificates(): array
    {
        $sql = "SELECT * FROM work_order_attachments 
                WHERE form_type = 'completion_certificate' 
                AND (completion_certificate_confirmation IS NULL OR completion_certificate_confirmation != 'confirmed')
                ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByUploadedBy(Id $userId): array
    {
        $sql = "SELECT * FROM work_order_attachments WHERE uploaded_by = ? ORDER BY uploaded_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$userId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function count(): int
    {
        return DatabaseConnection::count('work_order_attachments');
    }

    public function countByWorkOrder(Id $workOrderId): int
    {
        return DatabaseConnection::count('work_order_attachments', 'work_order_id = ?', [$workOrderId->value()]);
    }

    public function nextId(): Id
    {
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 FROM work_order_attachments";
        $nextId = DatabaseConnection::fetchColumn($sql);
        
        return new Id((int) $nextId);
    }

    public function createDefaultFormsForWorkOrder(Id $workOrderId): void
    {
        $formTypes = [
            FormType::EXCAVATION_FORM,
            FormType::PRECISE_DRILLING_FORM,
            FormType::DEMOLITION_FORM,
            FormType::F1_FORM,
            FormType::COMPLETION_CERTIFICATE
        ];

        foreach ($formTypes as $formTypeValue) {
            $formType = new FormType($formTypeValue);
            
            if (!$this->exists($workOrderId, $formType)) {
                $attachment = WorkOrderAttachment::create(
                    $this->nextId(),
                    $workOrderId,
                    $formType
                );
                
                $this->save($attachment);
            }
        }
    }

    private function insert(array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO work_order_attachments ($columns) VALUES ($placeholders)";
        DatabaseConnection::execute($sql, $data);
    }

    private function update(Id $id, array $data): void
    {
        unset($data['id']); // إزالة المعرف من البيانات

        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $sql = "UPDATE work_order_attachments SET $setClause WHERE id = :id";

        $data['id'] = $id->value();
        DatabaseConnection::execute($sql, $data);
    }

    private function mapToEntity(array $data): WorkOrderAttachment
    {
        return WorkOrderAttachment::fromPersistence(
            new Id((int) $data['id']),
            new Id((int) $data['work_order_id']),
            new FormType($data['form_type']),
            new AttachmentStatus($data['status']),
            $data['completion_certificate_confirmation'] ?
                new CompletionCertificateConfirmation($data['completion_certificate_confirmation']) : null,
            $data['file_path'],
            $data['original_filename'],
            $data['file_size'] ? (int) $data['file_size'] : null,
            $data['file_type'],
            $data['uploaded_by'] ? new Id((int) $data['uploaded_by']) : null,
            $data['uploaded_at'] ? DateTime::fromString($data['uploaded_at']) : null,
            $data['notes'],
            DateTime::fromString($data['created_at']),
            $data['updated_at'] ? DateTime::fromString($data['updated_at']) : null
        );
    }

    private function mapToArray(WorkOrderAttachment $attachment): array
    {
        return [
            'id' => $attachment->id()->value(),
            'work_order_id' => $attachment->workOrderId()->value(),
            'form_type' => $attachment->formType()->value(),
            'status' => $attachment->status()->value(),
            'completion_certificate_confirmation' => $attachment->completionCertificateConfirmation()?->value(),
            'file_path' => $attachment->filePath(),
            'original_filename' => $attachment->originalFilename(),
            'file_size' => $attachment->fileSize(),
            'file_type' => $attachment->fileType(),
            'uploaded_by' => $attachment->uploadedBy()?->value(),
            'uploaded_at' => $attachment->uploadedAt()?->format(),
            'notes' => $attachment->notes(),
            'created_at' => $attachment->createdAt()->format(),
            'updated_at' => $attachment->updatedAt()?->format()
        ];
    }
}
