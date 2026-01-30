<?php

namespace App\Service\User;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class UserValidationService
{
    private const SIRET_LENGTH = 14;
    private const PHONE_REGEX = '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/';
    private const EMAIL_REGEX = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    private const PASSWORD_MIN_LENGTH = 8;
    private const MAX_ACTIVE_REQUESTS_CLIENT = 5;
    private const MIN_HOURLY_RATE = 10;
    private const MAX_HOURLY_RATE = 100;
    private const MIN_RADIUS = 5;
    private const MAX_RADIUS = 50;

    // Documents autorisés
    private const ALLOWED_DOCUMENT_TYPES = ['pdf', 'jpg', 'jpeg', 'png'];
    private const MAX_DOCUMENT_SIZE = 5242880; // 5MB

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {}

    /**
     * Valide les données d'inscription d'un utilisateur
     */
    public function validateRegistrationData(array $data, string $userType): array
    {
        $errors = [];

        // Validation email
        if (!isset($data['email']) || empty($data['email'])) {
            $errors['email'] = 'L\'email est requis.';
        } elseif (!$this->isValidEmail($data['email'])) {
            $errors['email'] = 'L\'email n\'est pas valide.';
        } elseif ($this->emailExists($data['email'])) {
            $errors['email'] = 'Cet email est déjà utilisé.';
        }

        // Validation mot de passe
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = 'Le mot de passe est requis.';
        } else {
            $passwordErrors = $this->validatePassword($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = $passwordErrors;
            }
        }

        // Validation prénom
        if (!isset($data['firstName']) || empty($data['firstName'])) {
            $errors['firstName'] = 'Le prénom est requis.';
        } elseif (strlen($data['firstName']) < 2) {
            $errors['firstName'] = 'Le prénom doit contenir au moins 2 caractères.';
        }

        // Validation nom
        if (!isset($data['lastName']) || empty($data['lastName'])) {
            $errors['lastName'] = 'Le nom est requis.';
        } elseif (strlen($data['lastName']) < 2) {
            $errors['lastName'] = 'Le nom doit contenir au moins 2 caractères.';
        }

        // Validation téléphone (optionnel mais doit être valide si fourni)
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!$this->isValidPhone($data['phone'])) {
                $errors['phone'] = 'Le numéro de téléphone n\'est pas valide.';
            }
        }

        // Validations spécifiques au type d'utilisateur
        if ($userType === 'prestataire') {
            $prestataireErrors = $this->validatePrestataireSpecificData($data);
            $errors = array_merge($errors, $prestataireErrors);
        }

        return $errors;
    }

    /**
     * Valide les données spécifiques au prestataire
     */
    private function validatePrestataireSpecificData(array $data): array
    {
        $errors = [];

        // Validation SIRET
        if (isset($data['siret']) && !empty($data['siret'])) {
            if (!$this->isValidSiret($data['siret'])) {
                $errors['siret'] = 'Le numéro SIRET n\'est pas valide.';
            } elseif ($this->siretExists($data['siret'])) {
                $errors['siret'] = 'Ce numéro SIRET est déjà enregistré.';
            }
        }

        // Validation catégories de service
        if (isset($data['serviceCategories']) && !empty($data['serviceCategories'])) {
            if (!is_array($data['serviceCategories'])) {
                $errors['serviceCategories'] = 'Les catégories de service doivent être un tableau.';
            } elseif (!$this->areValidServiceCategories($data['serviceCategories'])) {
                $errors['serviceCategories'] = 'Une ou plusieurs catégories de service ne sont pas valides.';
            }
        }

        // Validation taux horaire
        if (isset($data['hourlyRate']) && !empty($data['hourlyRate'])) {
            if (!is_numeric($data['hourlyRate'])) {
                $errors['hourlyRate'] = 'Le taux horaire doit être un nombre.';
            } elseif ($data['hourlyRate'] < self::MIN_HOURLY_RATE || $data['hourlyRate'] > self::MAX_HOURLY_RATE) {
                $errors['hourlyRate'] = sprintf(
                    'Le taux horaire doit être entre %d€ et %d€.',
                    self::MIN_HOURLY_RATE,
                    self::MAX_HOURLY_RATE
                );
            }
        }

        // Validation rayon d'intervention
        if (isset($data['radius']) && !empty($data['radius'])) {
            if (!is_numeric($data['radius'])) {
                $errors['radius'] = 'Le rayon d\'intervention doit être un nombre.';
            } elseif ($data['radius'] < self::MIN_RADIUS || $data['radius'] > self::MAX_RADIUS) {
                $errors['radius'] = sprintf(
                    'Le rayon d\'intervention doit être entre %d km et %d km.',
                    self::MIN_RADIUS,
                    self::MAX_RADIUS
                );
            }
        }

        return $errors;
    }

    /**
     * Valide un email
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false 
            && preg_match(self::EMAIL_REGEX, $email);
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists(string $email): bool
    {
        return $this->userRepository->findOneBy(['email' => $email]) !== null;
    }

    /**
     * Valide un numéro de téléphone français
     */
    public function isValidPhone(string $phone): bool
    {
        // Nettoyer le numéro
        $cleanPhone = preg_replace('/[\s.-]/', '', $phone);
        
        return preg_match(self::PHONE_REGEX, $phone) === 1;
    }

    /**
     * Valide un mot de passe
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors[] = sprintf('Le mot de passe doit contenir au moins %d caractères.', self::PASSWORD_MIN_LENGTH);
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre minuscule.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        return $errors;
    }

    /**
     * Valide un numéro SIRET
     */
    public function isValidSiret(string $siret): bool
    {
        // Nettoyer le SIRET
        $siret = preg_replace('/\s/', '', $siret);

        // Vérifier la longueur
        if (strlen($siret) !== self::SIRET_LENGTH) {
            return false;
        }

        // Vérifier que ce sont tous des chiffres
        if (!ctype_digit($siret)) {
            return false;
        }

        // Algorithme de Luhn pour valider le SIRET
        $sum = 0;
        for ($i = 0; $i < self::SIRET_LENGTH; $i++) {
            $digit = (int) $siret[$i];
            
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * Vérifie si un SIRET existe déjà
     */
    public function siretExists(string $siret): bool
    {
        $cleanSiret = preg_replace('/\s/', '', $siret);
        
        $qb = $this->entityManager->createQueryBuilder();
        $count = $qb->select('COUNT(p.id)')
            ->from(Prestataire::class, 'p')
            ->where('p.siret = :siret')
            ->setParameter('siret', $cleanSiret)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Valide les catégories de service
     */
    public function areValidServiceCategories(array $categories): bool
    {
        $validCategories = [
            'nettoyage',
            'repassage',
            'menage_complet',
            'vitres',
            'jardinage',
            'bricolage',
            'garde_enfants',
            'aide_personne_agee',
            'cours_particuliers',
            'autre'
        ];

        foreach ($categories as $category) {
            if (!in_array($category, $validCategories, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valide un document uploadé
     */
    public function validateDocument(array $file): array
    {
        $errors = [];

        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_DOCUMENT_TYPES, true)) {
            $errors[] = sprintf(
                'Le format du document n\'est pas accepté. Formats autorisés: %s',
                implode(', ', self::ALLOWED_DOCUMENT_TYPES)
            );
        }

        // Vérifier la taille
        if ($file['size'] > self::MAX_DOCUMENT_SIZE) {
            $errors[] = sprintf(
                'Le document est trop volumineux. Taille maximale: %d MB',
                self::MAX_DOCUMENT_SIZE / 1048576
            );
        }

        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Une erreur est survenue lors de l\'upload du document.';
        }

        return $errors;
    }

    /**
     * Valide qu'un prestataire peut être approuvé
     */
    public function canApprovePrestataire(Prestataire $prestataire): array
    {
        $errors = [];

        // Vérifier la présence du SIRET
        if (!$prestataire->getSiret()) {
            $errors['siret'] = 'Le numéro SIRET est requis.';
        }

        // Vérifier la présence du KBIS
        if (!$prestataire->getKbis()) {
            $errors['kbis'] = 'Le document KBIS est requis.';
        }

        // Vérifier la présence de l'assurance
        if (!$prestataire->getInsurance()) {
            $errors['insurance'] = 'Le document d\'assurance professionnelle est requis.';
        }

        // Vérifier les catégories de service
        if (!$prestataire->getServiceCategories() || count($prestataire->getServiceCategories()) === 0) {
            $errors['serviceCategories'] = 'Au moins une catégorie de service doit être définie.';
        }

        // Vérifier le taux horaire
        if (!$prestataire->getHourlyRate() || $prestataire->getHourlyRate() < self::MIN_HOURLY_RATE) {
            $errors['hourlyRate'] = 'Le taux horaire doit être défini.';
        }

        // Vérifier le rayon d'intervention
        if (!$prestataire->getRadius() || $prestataire->getRadius() < self::MIN_RADIUS) {
            $errors['radius'] = 'Le rayon d\'intervention doit être défini.';
        }

        // Vérifier que le compte est vérifié
        if (!$prestataire->getIsVerified()) {
            $errors['verification'] = 'L\'email doit être vérifié.';
        }

        return $errors;
    }

    /**
     * Valide qu'un client peut créer une demande de service
     */
    public function canClientCreateRequest(Client $client): array
    {
        $errors = [];

        // Vérifier que le compte est actif
        if (!$client->getIsActive()) {
            $errors['account'] = 'Le compte est désactivé.';
        }

        // Vérifier que l'email est vérifié
        if (!$client->getIsVerified()) {
            $errors['verification'] = 'L\'email doit être vérifié avant de créer une demande.';
        }

        // Vérifier le nombre de demandes actives
        $qb = $this->entityManager->createQueryBuilder();
        $activeRequests = $qb->select('COUNT(sr.id)')
            ->from('App\Entity\Service\ServiceRequest', 'sr')
            ->where('sr.client = :client')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['open', 'quoted'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeRequests >= self::MAX_ACTIVE_REQUESTS_CLIENT) {
            $errors['requests'] = sprintf(
                'Vous ne pouvez pas avoir plus de %d demandes actives.',
                self::MAX_ACTIVE_REQUESTS_CLIENT
            );
        }

        return $errors;
    }

    /**
     * Valide qu'un prestataire peut soumettre un devis
     */
    public function canPrestataireSubmitQuote(Prestataire $prestataire): array
    {
        $errors = [];

        // Vérifier que le compte est actif
        if (!$prestataire->getIsActive()) {
            $errors['account'] = 'Le compte est désactivé.';
        }

        // Vérifier que le prestataire est approuvé
        if (!$prestataire->getIsApproved()) {
            $errors['approval'] = 'Votre compte doit être approuvé pour soumettre des devis.';
        }

        return $errors;
    }

    /**
     * Valide une adresse
     */
    public function validateAddress(array $address): array
    {
        $errors = [];
        $requiredFields = ['street', 'city', 'postalCode', 'country'];

        foreach ($requiredFields as $field) {
            if (!isset($address[$field]) || empty($address[$field])) {
                $errors[$field] = sprintf('Le champ "%s" est requis.', $field);
            }
        }

        // Validation code postal français
        if (isset($address['postalCode']) && isset($address['country']) && $address['country'] === 'FR') {
            if (!preg_match('/^[0-9]{5}$/', $address['postalCode'])) {
                $errors['postalCode'] = 'Le code postal n\'est pas valide.';
            }
        }

        return $errors;
    }

    /**
     * Valide les données de mise à jour du profil
     */
    public function validateProfileUpdate(User $user, array $data): array
    {
        $errors = [];

        // Validation email si modifié
        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            if (!$this->isValidEmail($data['email'])) {
                $errors['email'] = 'L\'email n\'est pas valide.';
            } elseif ($this->emailExists($data['email'])) {
                $errors['email'] = 'Cet email est déjà utilisé.';
            }
        }

        // Validation téléphone si fourni
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!$this->isValidPhone($data['phone'])) {
                $errors['phone'] = 'Le numéro de téléphone n\'est pas valide.';
            }
        }

        // Validations spécifiques au prestataire
        if ($user instanceof Prestataire) {
            if (isset($data['siret']) && $data['siret'] !== $user->getSiret()) {
                if (!$this->isValidSiret($data['siret'])) {
                    $errors['siret'] = 'Le numéro SIRET n\'est pas valide.';
                } elseif ($this->siretExists($data['siret'])) {
                    $errors['siret'] = 'Ce numéro SIRET est déjà enregistré.';
                }
            }

            if (isset($data['hourlyRate'])) {
                if (!is_numeric($data['hourlyRate'])) {
                    $errors['hourlyRate'] = 'Le taux horaire doit être un nombre.';
                } elseif ($data['hourlyRate'] < self::MIN_HOURLY_RATE || $data['hourlyRate'] > self::MAX_HOURLY_RATE) {
                    $errors['hourlyRate'] = sprintf(
                        'Le taux horaire doit être entre %d€ et %d€.',
                        self::MIN_HOURLY_RATE,
                        self::MAX_HOURLY_RATE
                    );
                }
            }

            if (isset($data['radius'])) {
                if (!is_numeric($data['radius'])) {
                    $errors['radius'] = 'Le rayon d\'intervention doit être un nombre.';
                } elseif ($data['radius'] < self::MIN_RADIUS || $data['radius'] > self::MAX_RADIUS) {
                    $errors['radius'] = sprintf(
                        'Le rayon d\'intervention doit être entre %d km et %d km.',
                        self::MIN_RADIUS,
                        self::MAX_RADIUS
                    );
                }
            }

            if (isset($data['serviceCategories'])) {
                if (!is_array($data['serviceCategories'])) {
                    $errors['serviceCategories'] = 'Les catégories de service doivent être un tableau.';
                } elseif (!$this->areValidServiceCategories($data['serviceCategories'])) {
                    $errors['serviceCategories'] = 'Une ou plusieurs catégories de service ne sont pas valides.';
                }
            }
        }

        return $errors;
    }

    /**
     * Valide un changement de mot de passe
     */
    public function validatePasswordChange(string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $errors = [];

        if (empty($currentPassword)) {
            $errors['currentPassword'] = 'Le mot de passe actuel est requis.';
        }

        if (empty($newPassword)) {
            $errors['newPassword'] = 'Le nouveau mot de passe est requis.';
        } else {
            $passwordErrors = $this->validatePassword($newPassword);
            if (!empty($passwordErrors)) {
                $errors['newPassword'] = $passwordErrors;
            }
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirmPassword'] = 'Les mots de passe ne correspondent pas.';
        }

        if ($currentPassword === $newPassword) {
            $errors['newPassword'] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
        }

        return $errors;
    }

    /**
     * Vérifie si un utilisateur peut être supprimé
     */
    public function canDeleteUser(User $user): array
    {
        $errors = [];

        if ($user instanceof Client) {
            // Vérifier les réservations actives
            $qb = $this->entityManager->createQueryBuilder();
            $activeBookings = $qb->select('COUNT(b.id)')
                ->from('App\Entity\Booking\Booking', 'b')
                ->where('b.client = :client')
                ->andWhere('b.status IN (:statuses)')
                ->setParameter('client', $user)
                ->setParameter('statuses', ['scheduled', 'confirmed'])
                ->getQuery()
                ->getSingleScalarResult();

            if ($activeBookings > 0) {
                $errors['bookings'] = 'Impossible de supprimer le compte : des réservations actives existent.';
            }
        } elseif ($user instanceof Prestataire) {
            // Vérifier les réservations à venir
            $qb = $this->entityManager->createQueryBuilder();
            $futureBookings = $qb->select('COUNT(b.id)')
                ->from('App\Entity\Booking\Booking', 'b')
                ->where('b.prestataire = :prestataire')
                ->andWhere('b.scheduledDate > :now')
                ->andWhere('b.status IN (:statuses)')
                ->setParameter('prestataire', $user)
                ->setParameter('now', new \DateTime())
                ->setParameter('statuses', ['scheduled', 'confirmed'])
                ->getQuery()
                ->getSingleScalarResult();

            if ($futureBookings > 0) {
                $errors['bookings'] = 'Impossible de supprimer le compte : des réservations futures existent.';
            }
        }

        return $errors;
    }

    /**
     * Nettoie et formate un numéro de téléphone
     */
    public function formatPhone(string $phone): string
    {
        // Supprimer tous les caractères non numériques sauf le +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Remplacer 00 par +
        if (strpos($cleaned, '00') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        return $cleaned;
    }

    /**
     * Nettoie et formate un SIRET
     */
    public function formatSiret(string $siret): string
    {
        return preg_replace('/\s/', '', $siret);
    }

    /**
     * Vérifie la force d'un mot de passe (score de 0 à 4)
     */
    public function getPasswordStrength(string $password): int
    {
        $strength = 0;

        if (strlen($password) >= 8) $strength++;
        if (strlen($password) >= 12) $strength++;
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;

        return min($strength, 4);
    }

    /**
     * Log les erreurs de validation
     */
    private function logValidationErrors(array $errors, string $context): void
    {
        if (!empty($errors)) {
            $this->logger->warning('Validation errors', [
                'context' => $context,
                'errors' => $errors
            ]);
        }
    }
}