<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ValidSiretValidator extends ConstraintValidator
{
    private const SIRENE_API_URL = 'https://api.insee.fr/entreprises/sirene/V3/siret';
    
    public function __construct(
        private ?HttpClientInterface $httpClient = null,
        private ?LoggerInterface $logger = null,
        private ?string $inseeApiToken = null
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSiret) {
            throw new UnexpectedTypeException($constraint, ValidSiret::class);
        }

        // Valeur nulle ou vide autorisée (utiliser NotBlank si requis)
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Convertir en string et nettoyer
        $siret = (string) $value;
        $siret = $this->cleanSiret($siret);

        // Vérifier le format (14 chiffres exactement)
        if (!$this->isValidFormat($siret)) {
            $this->context->buildViolation($constraint->invalidFormatMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode('SIRET_INVALID_FORMAT')
                ->addViolation();
            return;
        }

        // Vérifier l'algorithme de Luhn
        if (!$this->isValidLuhn($siret)) {
            $this->context->buildViolation($constraint->invalidChecksumMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode('SIRET_INVALID_CHECKSUM')
                ->addViolation();
            return;
        }

        // Vérification optionnelle de l'existence dans la base SIRENE
        if ($constraint->checkExistence && $this->httpClient && $this->inseeApiToken) {
            $exists = $this->checkSiretExists($siret, $constraint->checkActive);
            
            if ($exists === false) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode('SIRET_NOT_FOUND')
                    ->addViolation();
                return;
            }

            if ($exists === 'inactive' && $constraint->checkActive) {
                $this->context->buildViolation($constraint->notActiveMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode('SIRET_NOT_ACTIVE')
                    ->addViolation();
                return;
            }
        }
    }

    /**
     * Nettoie le SIRET en supprimant les espaces et caractères spéciaux
     */
    private function cleanSiret(string $siret): string
    {
        return preg_replace('/[^0-9]/', '', $siret);
    }

    /**
     * Vérifie que le SIRET a le bon format (14 chiffres)
     */
    private function isValidFormat(string $siret): bool
    {
        return preg_match('/^[0-9]{14}$/', $siret) === 1;
    }

    /**
     * Vérifie le SIRET avec l'algorithme de Luhn
     * 
     * L'algorithme de Luhn est utilisé pour valider les numéros SIRET.
     * Il fonctionne comme suit :
     * 1. On prend les chiffres du SIRET de gauche à droite
     * 2. On multiplie par 2 les chiffres de rang pair (en partant de 1)
     * 3. Si le résultat est > 9, on soustrait 9
     * 4. On additionne tous les chiffres
     * 5. Le total doit être divisible par 10
     */
    private function isValidLuhn(string $siret): bool
    {
        $sum = 0;
        $length = strlen($siret);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $siret[$i];

            // Les positions paires (index impair car on commence à 0) sont doublées
            if ($i % 2 === 1) {
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
     * Vérifie l'existence du SIRET dans la base SIRENE de l'INSEE
     * 
     * @return bool|string true si existe et actif, 'inactive' si existe mais inactif, false si n'existe pas
     */
    private function checkSiretExists(string $siret, bool $checkActive): bool|string
    {
        try {
            $response = $this->httpClient->request('GET', self::SIRENE_API_URL . '/' . $siret, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->inseeApiToken,
                    'Accept' => 'application/json',
                ],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                // Vérifier si l'établissement est actif
                if ($checkActive && isset($data['etablissement']['etatAdministratifEtablissement'])) {
                    $etat = $data['etablissement']['etatAdministratifEtablissement'];
                    
                    // 'A' = Actif, 'F' = Fermé
                    if ($etat !== 'A') {
                        return 'inactive';
                    }
                }
                
                return true;
            }

            if ($response->getStatusCode() === 404) {
                return false;
            }

        } catch (\Exception $e) {
            // En cas d'erreur de l'API, on log mais on ne bloque pas la validation
            if ($this->logger) {
                $this->logger->warning('Erreur lors de la vérification du SIRET via l\'API SIRENE', [
                    'siret' => $siret,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // On considère que le SIRET est valide si l'API n'est pas disponible
            // Car on a déjà validé le format et l'algorithme de Luhn
            return true;
        }

        return false;
    }

    /**
     * Formate la valeur pour l'affichage dans les messages d'erreur
     */
    private function formatValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            $siret = $this->cleanSiret((string) $value);
            
            // Formater le SIRET pour l'affichage (XXX XXX XXX XXXXX)
            if (strlen($siret) === 14) {
                return substr($siret, 0, 3) . ' ' . 
                       substr($siret, 3, 3) . ' ' . 
                       substr($siret, 6, 3) . ' ' . 
                       substr($siret, 9, 5);
            }
            
            return $siret;
        }

        return (string) $value;
    }
}