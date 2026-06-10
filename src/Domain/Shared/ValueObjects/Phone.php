<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * رقم الهاتف
 * Phone Number Value Object
 */
final class Phone
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $this->normalize($value);
    }

    private function validate(string $phone): void
    {
        if (empty($phone)) {
            throw new InvalidArgumentException('رقم الهاتف لا يمكن أن يكون فارغاً');
        }

        // إزالة المسافات والرموز
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
            throw new InvalidArgumentException('رقم الهاتف يجب أن يكون بين 10 و 15 رقماً');
        }

        // التحقق من الأرقام السعودية
        if (preg_match('/^(05|5)\d{8}$/', $cleanPhone) || 
            preg_match('/^\+966(5)\d{8}$/', $cleanPhone) ||
            preg_match('/^966(5)\d{8}$/', $cleanPhone)) {
            return;
        }

        // التحقق من الأرقام الدولية
        if (!preg_match('/^(\+?\d{10,15})$/', $cleanPhone)) {
            throw new InvalidArgumentException('رقم الهاتف غير صحيح');
        }
    }

    private function normalize(string $phone): string
    {
        // إزالة المسافات والرموز
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // تحويل الأرقام السعودية للصيغة الدولية
        if (preg_match('/^05(\d{8})$/', $cleanPhone, $matches)) {
            return '+9665' . $matches[1];
        }
        
        if (preg_match('/^5(\d{8})$/', $cleanPhone, $matches)) {
            return '+9665' . $matches[1];
        }
        
        if (preg_match('/^9665(\d{8})$/', $cleanPhone, $matches)) {
            return '+9665' . $matches[1];
        }
        
        // إضافة + إذا لم تكن موجودة
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . $cleanPhone;
        }
        
        return $cleanPhone;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Phone $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
