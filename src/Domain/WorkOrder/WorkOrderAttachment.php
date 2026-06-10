<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\FormType;
use EtganERP\Domain\WorkOrder\ValueObjects\AttachmentStatus;
use EtganERP\Domain\WorkOrder\ValueObjects\CompletionCertificateConfirmation;
use InvalidArgumentException;

/**
 * مرفق أمر العمل
 * Work Order Attachment Entity
 */
final class WorkOrderAttachment
{
    private function __construct(
        private readonly Id $id,
        private readonly Id $workOrderId,
        private readonly FormType $formType,
        private AttachmentStatus $status,
        private ?CompletionCertificateConfirmation $completionCertificateConfirmation,
        private ?string $filePath,
        private ?string $originalFilename,
        private ?int $fileSize,
        private ?string $fileType,
        private ?Id $uploadedBy,
        private ?DateTime $uploadedAt,
        private ?string $notes,
        private readonly DateTime $createdAt,
        private ?DateTime $updatedAt = null
    ) {
    }

    public static function create(
        Id $id,
        Id $workOrderId,
        FormType $formType,
        AttachmentStatus $status = null,
        ?CompletionCertificateConfirmation $completionCertificateConfirmation = null,
        ?string $notes = null
    ): self {
        $status = $status ?? AttachmentStatus::notAttached();
        
        // تعيين تأكيد شهادة الإنجاز للنماذج المناسبة
        if ($formType->isCompletionCertificate() && $completionCertificateConfirmation === null) {
            $completionCertificateConfirmation = CompletionCertificateConfirmation::empty();
        }

        return new self(
            $id,
            $workOrderId,
            $formType,
            $status,
            $completionCertificateConfirmation,
            null, // filePath
            null, // originalFilename
            null, // fileSize
            null, // fileType
            null, // uploadedBy
            null, // uploadedAt
            $notes,
            DateTime::now()
        );
    }

    public static function fromPersistence(
        Id $id,
        Id $workOrderId,
        FormType $formType,
        AttachmentStatus $status,
        ?CompletionCertificateConfirmation $completionCertificateConfirmation,
        ?string $filePath,
        ?string $originalFilename,
        ?int $fileSize,
        ?string $fileType,
        ?Id $uploadedBy,
        ?DateTime $uploadedAt,
        ?string $notes,
        DateTime $createdAt,
        ?DateTime $updatedAt
    ): self {
        return new self(
            $id,
            $workOrderId,
            $formType,
            $status,
            $completionCertificateConfirmation,
            $filePath,
            $originalFilename,
            $fileSize,
            $fileType,
            $uploadedBy,
            $uploadedAt,
            $notes,
            $createdAt,
            $updatedAt
        );
    }

    public function updateStatus(AttachmentStatus $status): void
    {
        $this->status = $status;
        $this->markAsUpdated();
    }

    public function updateCompletionCertificateConfirmation(CompletionCertificateConfirmation $confirmation): void
    {
        if (!$this->formType->isCompletionCertificate()) {
            throw new InvalidArgumentException('لا يمكن تحديث تأكيد شهادة الإنجاز إلا لنماذج شهادة الإنجاز');
        }

        $this->completionCertificateConfirmation = $confirmation;
        $this->markAsUpdated();
    }

    public function attachFile(
        string $filePath,
        string $originalFilename,
        int $fileSize,
        string $fileType,
        Id $uploadedBy,
        ?string $notes = null
    ): void {
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
        $this->fileSize = $fileSize;
        $this->fileType = $fileType;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = DateTime::now();
        $this->status = AttachmentStatus::attached();
        
        if ($notes !== null) {
            $this->notes = $notes;
        }
        
        $this->markAsUpdated();
    }

    public function removeFile(): void
    {
        $this->filePath = null;
        $this->originalFilename = null;
        $this->fileSize = null;
        $this->fileType = null;
        $this->uploadedBy = null;
        $this->uploadedAt = null;
        $this->status = AttachmentStatus::notAttached();
        $this->markAsUpdated();
    }

    public function updateNotes(?string $notes): void
    {
        $this->notes = $notes;
        $this->markAsUpdated();
    }

    private function markAsUpdated(): void
    {
        $this->updatedAt = DateTime::now();
    }

    // Getters
    public function id(): Id
    {
        return $this->id;
    }

    public function workOrderId(): Id
    {
        return $this->workOrderId;
    }

    public function formType(): FormType
    {
        return $this->formType;
    }

    public function status(): AttachmentStatus
    {
        return $this->status;
    }

    public function completionCertificateConfirmation(): ?CompletionCertificateConfirmation
    {
        return $this->completionCertificateConfirmation;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function originalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function fileSize(): ?int
    {
        return $this->fileSize;
    }

    public function fileType(): ?string
    {
        return $this->fileType;
    }

    public function uploadedBy(): ?Id
    {
        return $this->uploadedBy;
    }

    public function uploadedAt(): ?DateTime
    {
        return $this->uploadedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    // Helper methods
    public function hasFile(): bool
    {
        return $this->filePath !== null && $this->status->isAttached();
    }

    public function isCompletionCertificate(): bool
    {
        return $this->formType->isCompletionCertificate();
    }

    public function isCompletionCertificateConfirmed(): bool
    {
        return $this->isCompletionCertificate() 
            && $this->completionCertificateConfirmation?->isConfirmed() === true;
    }

    public function getFileSizeFormatted(): string
    {
        if ($this->fileSize === null) {
            return '';
        }

        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
