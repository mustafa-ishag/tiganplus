<?php

declare(strict_types=1);

namespace EtganERP\Domain\User;

use EtganERP\Domain\Shared\AggregateRoot;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Email;
use EtganERP\Domain\Shared\ValueObjects\Phone;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;
use EtganERP\Domain\User\ValueObjects\FullName;
use EtganERP\Domain\User\ValueObjects\HashedPassword;
use EtganERP\Domain\User\Events\UserCreated;
use EtganERP\Domain\User\Events\UserUpdated;
use EtganERP\Domain\User\Events\UserPasswordChanged;
use EtganERP\Domain\User\Events\UserStatusChanged;
use InvalidArgumentException;

/**
 * كيان المستخدم
 * User Entity
 */
final class User extends AggregateRoot
{
    private Username $username;
    private FullName $fullName;
    private ?Email $email;
    private ?Phone $phone;
    private HashedPassword $password;
    private Id $roleId;
    private ?Id $branchId;
    private Status $status;
    private ?DateTime $lastLoginAt;
    private ?string $rememberToken;

    public function __construct(
        Id $id,
        Username $username,
        FullName $fullName,
        HashedPassword $password,
        Id $roleId,
        ?Email $email = null,
        ?Phone $phone = null,
        ?Id $branchId = null,
        ?Status $status = null,
        ?DateTime $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
        
        $this->username = $username;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
        $this->password = $password;
        $this->roleId = $roleId;
        $this->branchId = $branchId;
        $this->status = $status ?? Status::active();
        $this->lastLoginAt = null;
        $this->rememberToken = null;

        $this->recordDomainEvent(new UserCreated($this->id, $this->username, $this->fullName));
    }

    public static function create(
        Id $id,
        Username $username,
        FullName $fullName,
        string $plainPassword,
        Id $roleId,
        ?Email $email = null,
        ?Phone $phone = null,
        ?Id $branchId = null
    ): self {
        $hashedPassword = HashedPassword::fromPlainPassword($plainPassword);
        
        return new self(
            $id,
            $username,
            $fullName,
            $hashedPassword,
            $roleId,
            $email,
            $phone,
            $branchId
        );
    }

    // Getters
    public function username(): Username
    {
        return $this->username;
    }

    public function fullName(): FullName
    {
        return $this->fullName;
    }

    public function email(): ?Email
    {
        return $this->email;
    }

    public function phone(): ?Phone
    {
        return $this->phone;
    }

    public function roleId(): Id
    {
        return $this->roleId;
    }

    public function branchId(): ?Id
    {
        return $this->branchId;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function lastLoginAt(): ?DateTime
    {
        return $this->lastLoginAt;
    }

    public function rememberToken(): ?string
    {
        return $this->rememberToken;
    }

    // Business Methods
    public function updateProfile(
        FullName $fullName,
        ?Email $email = null,
        ?Phone $phone = null
    ): void {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserUpdated($this->id, $this->username));
    }

    public function changePassword(string $currentPassword, string $newPassword): void
    {
        if (!$this->password->verify($currentPassword)) {
            throw new InvalidArgumentException('كلمة المرور الحالية غير صحيحة');
        }

        $this->password = HashedPassword::fromPlainPassword($newPassword);
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserPasswordChanged($this->id, $this->username));
    }

    public function resetPassword(string $newPassword): void
    {
        $this->password = HashedPassword::fromPlainPassword($newPassword);
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserPasswordChanged($this->id, $this->username));
    }

    public function verifyPassword(string $password): bool
    {
        return $this->password->verify($password);
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::active();
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserStatusChanged($this->id, $this->username, $oldStatus, $this->status));
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::inactive();
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserStatusChanged($this->id, $this->username, $oldStatus, $this->status));
    }

    public function suspend(): void
    {
        if ($this->status->isSuspended()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::suspended();
        $this->markAsUpdated();

        $this->recordDomainEvent(new UserStatusChanged($this->id, $this->username, $oldStatus, $this->status));
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = DateTime::now();
        $this->markAsUpdated();
    }

    public function setRememberToken(string $token): void
    {
        $this->rememberToken = $token;
        $this->markAsUpdated();
    }

    public function clearRememberToken(): void
    {
        $this->rememberToken = null;
        $this->markAsUpdated();
    }

    public function assignToRole(Id $roleId): void
    {
        $this->roleId = $roleId;
        $this->markAsUpdated();
    }

    public function assignToBranch(?Id $branchId): void
    {
        $this->branchId = $branchId;
        $this->markAsUpdated();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canLogin(): bool
    {
        return $this->status->isActive();
    }

    public function hashedPassword(): HashedPassword
    {
        return $this->password;
    }
}
