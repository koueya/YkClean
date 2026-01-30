<?php

namespace App\Security;

use App\Entity\User\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Custom User Provider for authentication
 * Handles user loading by email and user refresh for security context
 */
class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Load user by identifier (email in this case)
     * Called during authentication process
     *
     * @throws UserNotFoundException if user is not found or not active
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
        }

        // Check if user account is active
        if (!$user->isActive()) {
            throw new UserNotFoundException('User account is deactivated.');
        }

        // Check if user email is verified (optional, depending on your requirements)
        if (!$user->isVerified()) {
            throw new UserNotFoundException('Email address is not verified.');
        }

        return $user;
    }

    /**
     * Refreshes the user after being reloaded from the session.
     * When a user is logged in, at the beginning of each request, 
     * the User object is loaded from the session and then this method is called.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        // Reload the user from database
        $reloadedUser = $this->userRepository->find($user->getId());

        if (!$reloadedUser) {
            throw new UserNotFoundException(sprintf('User with id "%s" not found.', $user->getId()));
        }

        // Check if user is still active
        if (!$reloadedUser->isActive()) {
            throw new UserNotFoundException('User account has been deactivated.');
        }

        return $reloadedUser;
    }

    /**
     * Tells Symfony whether this provider supports the given user class
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrade password encoding when needed
     * This method can be used to upgrade password hashes
     */
    public function upgradePassword(UserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->userRepository->save($user, true);
    }
}