<?php

declare(strict_types=1);

namespace EtganERP\Application\User\CreateUser;

use EtganERP\Domain\User\User;
use EtganERP\Domain\User\UserRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Email;
use EtganERP\Domain\Shared\ValueObjects\Phone;
use EtganERP\Domain\User\ValueObjects\Username;
use EtganERP\Domain\User\ValueObjects\FullName;
use InvalidArgumentException;

/**
 * معالج إنشاء مستخدم
 * Create User Handler
 */
final class CreateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function handle(CreateUserCommand $command): CreateUserResponse
    {
        // التحقق من صحة البيانات
        $username = new Username($command->username);
        $fullName = new FullName($command->fullName);
        $roleId = new Id($command->roleId);
        
        $email = $command->email ? new Email($command->email) : null;
        $phone = $command->phone ? new Phone($command->phone) : null;
        $branchId = $command->branchId ? new Id($command->branchId) : null;

        // التحقق من عدم وجود اسم المستخدم
        if ($this->userRepository->existsByUsername($username)) {
            throw new InvalidArgumentException('اسم المستخدم موجود بالفعل');
        }

        // التحقق من عدم وجود البريد الإلكتروني
        if ($email && $this->userRepository->existsByEmail($email)) {
            throw new InvalidArgumentException('البريد الإلكتروني موجود بالفعل');
        }

        // إنشاء المستخدم
        $userId = $this->userRepository->nextId();
        
        $user = User::create(
            $userId,
            $username,
            $fullName,
            $command->password,
            $roleId,
            $email,
            $phone,
            $branchId
        );

        // حفظ المستخدم
        $this->userRepository->save($user);

        return new CreateUserResponse(
            $user->id()->value(),
            $user->username()->value(),
            $user->fullName()->value(),
            'تم إنشاء المستخدم بنجاح'
        );
    }
}
