<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * نوع النموذج
 * Form Type Value Object
 */
final class FormType
{
    public const EXCAVATION_FORM = 'excavation_form';
    public const PRECISE_DRILLING_FORM = 'precise_drilling_form';
    public const DEMOLITION_FORM = 'demolition_form';
    public const F1_FORM = 'f1_form';
    public const COMPLETION_CERTIFICATE = 'completion_certificate';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $formType): void
    {
        $validTypes = [
            self::EXCAVATION_FORM,
            self::PRECISE_DRILLING_FORM,
            self::DEMOLITION_FORM,
            self::F1_FORM,
            self::COMPLETION_CERTIFICATE
        ];

        if (!in_array($formType, $validTypes, true)) {
            throw new InvalidArgumentException('نوع نموذج غير صحيح: ' . $formType);
        }
    }

    public static function excavationForm(): self
    {
        return new self(self::EXCAVATION_FORM);
    }

    public static function preciseDrillingForm(): self
    {
        return new self(self::PRECISE_DRILLING_FORM);
    }

    public static function demolitionForm(): self
    {
        return new self(self::DEMOLITION_FORM);
    }

    public static function f1Form(): self
    {
        return new self(self::F1_FORM);
    }

    public static function completionCertificate(): self
    {
        return new self(self::COMPLETION_CERTIFICATE);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isExcavationForm(): bool
    {
        return $this->value === self::EXCAVATION_FORM;
    }

    public function isPreciseDrillingForm(): bool
    {
        return $this->value === self::PRECISE_DRILLING_FORM;
    }

    public function isDemolitionForm(): bool
    {
        return $this->value === self::DEMOLITION_FORM;
    }

    public function isF1Form(): bool
    {
        return $this->value === self::F1_FORM;
    }

    public function isCompletionCertificate(): bool
    {
        return $this->value === self::COMPLETION_CERTIFICATE;
    }

    public function equals(FormType $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::EXCAVATION_FORM => 'نموذج الكشط',
            self::PRECISE_DRILLING_FORM => 'نموذج الحفر الدقيق',
            self::DEMOLITION_FORM => 'نموذج التخريد',
            self::F1_FORM => 'نموذج F1',
            self::COMPLETION_CERTIFICATE => 'شهادة الإنجاز',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
