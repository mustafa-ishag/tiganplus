<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Persistence;

use EtganERP\Domain\User\User;
use EtganERP\Domain\User\UserRepositoryInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Email;
use EtganERP\Domain\Shared\ValueObjects\Phone;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;
use EtganERP\Domain\User\ValueObjects\FullName;
use EtganERP\Domain\User\ValueObjects\HashedPassword;
use EtganERP\Infrastructure\Database\DatabaseConnection;

/**
 * مستودع المستخدمين
 * User Repository Implementation
 */
final class UserRepository implements UserRepositoryInterface
{
    public function findById(Id $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $data = DatabaseConnection::fetchOne($sql, [$id->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByUsername(Username $username): ?User
    {
        $sql = "SELECT * FROM users WHERE username = ?";
        $data = DatabaseConnection::fetchOne($sql, [$username->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $sql = "SELECT * FROM users WHERE email = ?";
        $data = DatabaseConnection::fetchOne($sql, [$email->value()]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findByRememberToken(string $token): ?User
    {
        $sql = "SELECT * FROM users WHERE remember_token = ? AND status = 'active'";
        $data = DatabaseConnection::fetchOne($sql, [$token]);
        
        return $data ? $this->mapToEntity($data) : null;
    }

    public function save(User $user): void
    {
        $data = $this->mapToArray($user);
        
        if ($this->exists($user->id())) {
            $this->update($user->id(), $data);
        } else {
            $this->insert($data);
        }
    }

    public function delete(User $user): void
    {
        $sql = "DELETE FROM users WHERE id = ?";
        DatabaseConnection::execute($sql, [$user->id()->value()]);
    }

    public function existsByUsername(Username $username): bool
    {
        return DatabaseConnection::exists('users', 'username = ?', [$username->value()]);
    }

    public function existsByEmail(Email $email): bool
    {
        return DatabaseConnection::exists('users', 'email = ?', [$email->value()]);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM users ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByBranchId(Id $branchId): array
    {
        $sql = "SELECT * FROM users WHERE branch_id = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$branchId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByRoleId(Id $roleId): array
    {
        $sql = "SELECT * FROM users WHERE role_id = ? ORDER BY created_at DESC";
        $results = DatabaseConnection::fetchAll($sql, [$roleId->value()]);
        
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function search(string $searchTerm, ?Id $branchId = null, ?Id $roleId = null): array
    {
        $conditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }
        
        if ($branchId) {
            $conditions[] = "branch_id = ?";
            $params[] = $branchId->value();
        }
        
        if ($roleId) {
            $conditions[] = "role_id = ?";
            $params[] = $roleId->value();
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC";
        
        $results = DatabaseConnection::fetchAll($sql, $params);
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function count(): int
    {
        return DatabaseConnection::count('users');
    }

    public function countActive(): int
    {
        return DatabaseConnection::count('users', 'status = ?', ['active']);
    }

    public function nextId(): Id
    {
        // في التطبيق الحقيقي، يمكن استخدام UUID أو تسلسل قاعدة البيانات
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 FROM users";
        $nextId = DatabaseConnection::fetchColumn($sql);
        
        return new Id((int) $nextId);
    }

    private function exists(Id $id): bool
    {
        return DatabaseConnection::exists('users', 'id = ?', [$id->value()]);
    }

    private function insert(array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO users ($columns) VALUES ($placeholders)";
        DatabaseConnection::execute($sql, $data);
    }

    private function update(Id $id, array $data): void
    {
        unset($data['id']); // إزالة المعرف من البيانات
        
        $setClause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $sql = "UPDATE users SET $setClause WHERE id = :id";
        
        $data['id'] = $id->value();
        DatabaseConnection::execute($sql, $data);
    }

    private function mapToEntity(array $data): User
    {
        return new User(
            new Id((int) $data['id']),
            new Username($data['username']),
            new FullName($data['full_name']),
            HashedPassword::fromHashedPassword($data['password']),
            new Id((int) $data['role_id']),
            $data['email'] ? new Email($data['email']) : null,
            $data['phone'] ? new Phone($data['phone']) : null,
            $data['branch_id'] ? new Id((int) $data['branch_id']) : null,
            new Status($data['status']),
            $data['created_at'] ? DateTime::fromString($data['created_at']) : null
        );
    }

    private function mapToArray(User $user): array
    {
        return [
            'id' => $user->id()->value(),
            'username' => $user->username()->value(),
            'full_name' => $user->fullName()->value(),
            'email' => $user->email()?->value(),
            'phone' => $user->phone()?->value(),
            'password' => $user->hashedPassword()->value(),
            'role_id' => $user->roleId()->value(),
            'branch_id' => $user->branchId()?->value(),
            'status' => $user->status()->value(),
            'last_login' => $user->lastLoginAt()?->format(),
            'remember_token' => $user->rememberToken(),
            'created_at' => $user->createdAt()->format(),
            'updated_at' => $user->updatedAt()?->format()
        ];
    }
}
