<?php

declare(strict_types=1);

namespace EtganERP\Application\Auth\Login;

use EtganERP\Domain\User\UserRepositoryInterface;
use EtganERP\Domain\User\ValueObjects\Username;
use InvalidArgumentException;

/**
 * معالج تسجيل الدخول
 * Login Handler
 */
final class LoginHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function handle(LoginCommand $command): LoginResponse
    {
        // البحث عن المستخدم
        $username = new Username($command->username);
        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            throw new InvalidArgumentException('اسم المستخدم أو كلمة المرور غير صحيحة');
        }

        // التحقق من كلمة المرور
        if (!$user->verifyPassword($command->password)) {
            throw new InvalidArgumentException('اسم المستخدم أو كلمة المرور غير صحيحة');
        }

        // التحقق من حالة المستخدم
        if (!$user->canLogin()) {
            throw new InvalidArgumentException('حسابك غير نشط. يرجى التواصل مع المدير');
        }

        // تسجيل آخر دخول
        $user->recordLogin();

        // إنشاء remember token إذا طُلب
        $rememberToken = null;
        if ($command->rememberMe) {
            $rememberToken = bin2hex(random_bytes(32));
            $user->setRememberToken($rememberToken);
        }

        // حفظ التحديثات
        $this->userRepository->save($user);

        return new LoginResponse(
            $user->id()->value(),
            $user->username()->value(),
            $user->fullName()->value(),
            $user->roleId()->value(),
            $user->branchId()?->value(),
            $rememberToken,
            'تم تسجيل الدخول بنجاح'
        );
    }
}
