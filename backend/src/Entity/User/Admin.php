<?php
// src/Entity/User/Admin.php

namespace App\Entity\User;

use App\Repository\AdminRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User\User;
#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(name: 'admins')]
class Admin extends User
{
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $department = null; // Service/département

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $permissions = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isSuperAdmin = false;

    public function __construct()
    {
        parent::__construct();
        $this->addRole('ROLE_ADMIN');
    }

    // Getters and Setters

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): self
    {
        $this->department = $department;
        return $this;
    }

    public function getPermissions(): ?array
    {
        return $this->permissions ?? [];
    }

    public function setPermissions(?array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function addPermission(string $permission): self
    {
        $permissions = $this->getPermissions();
        if (!in_array($permission, $permissions, true)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
        }
        return $this;
    }

    public function removePermission(string $permission): self
    {
        $permissions = $this->getPermissions();
        $key = array_search($permission, $permissions, true);
        if ($key !== false) {
            unset($permissions[$key]);
            $this->permissions = array_values($permissions);
        }
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function setIsSuperAdmin(bool $isSuperAdmin): self
    {
        $this->isSuperAdmin = $isSuperAdmin;
        
        if ($isSuperAdmin) {
            $this->addRole('ROLE_SUPER_ADMIN');
        }
        
        return $this;
    }
}