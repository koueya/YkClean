<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * ✅ SOLUTION ALTERNATIVE 2 : Override de formatValue() avec visibilité protected
 */
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

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $siret = (string) $value;
        $siret = $this->cleanSiret($siret);

        if (!$this->isValidFormat($siret)) {
            $this->context->buildViolation($constraint->invalidFormatMessage)
                ->setParameter('{{ value }}', $this->formatValue($value)) // ✅ Utilise la méthode overridée
                ->setCode('SIRET_INVALID_FORMAT')
                ->addViolation();
            return;
        }

        if (!$this->isValidLuhn($siret)) {
            $this->context->buildViolation($constraint->invalidChecksumMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode('SIRET_INVALID_CHECKSUM')
                ->addViolation();
            return;
        }

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

    private function cleanSiret(string $siret): string
    {
        return preg_replace('/[^0-9]/', '', $siret);
    }

    private function isValidFormat(string $siret): bool
    {
        return preg_match('/^[0-9]{14}$/', $siret) === 1;
    }

    private function isValidLuhn(string $siret): bool
    {
        $sum = 0;
        $length = strlen($siret);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $siret[$i];

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
                
                if ($checkActive && isset($data['etablissement']['etatAdministratifEtablissement'])) {
                    $etat = $data['etablissement']['etatAdministratifEtablissement'];
                    
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
            if ($this->logger) {
                $this->logger->warning('Erreur lors de la vérification du SIRET via l\'API SIRENE', [
                    'siret' => $siret,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return true;
        }

        return false;
    }

    /**
     * Override de la méthode parent pour formater spécifiquement les SIRET
     * 
     * ✅ CHANGÉ DE private À protected pour respecter la hiérarchie
     */
    protected function formatValue(mixed $value, int $format = 0): string
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

        // Fallback sur la méthode parent pour les autres types
        return parent::formatValue($value, $format);
    }
}