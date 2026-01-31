<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Annotation pour valider un numéro SIRET français
 * 
 * Le SIRET (Système d'Identification du Répertoire des Établissements) 
 * est un numéro de 14 chiffres qui identifie de manière unique un établissement en France.
 * 
 * Structure :
 * - 9 premiers chiffres : SIREN (identifiant de l'entreprise)
 * - 5 derniers chiffres : NIC (identifiant de l'établissement)
 * 
 * Validation :
 * - Doit contenir exactement 14 chiffres
 * - Doit respecter l'algorithme de Luhn pour les 14 chiffres
 * 
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ValidSiret extends Constraint
{
    public string $message = 'Le numéro SIRET "{{ value }}" n\'est pas valide.';
    public string $invalidFormatMessage = 'Le numéro SIRET doit contenir exactement 14 chiffres.';
    public string $invalidChecksumMessage = 'Le numéro SIRET "{{ value }}" ne respecte pas l\'algorithme de validation.';
    public string $notActiveMessage = 'Le numéro SIRET "{{ value }}" n\'est pas actif ou n\'existe pas dans le répertoire SIRENE.';
    
    /**
     * Si true, vérifie également que le SIRET existe dans la base SIRENE de l'INSEE
     * Nécessite une connexion à l'API SIRENE
     */
    public bool $checkExistence = false;
    
    /**
     * Si true, vérifie que l'établissement est actif (non fermé)
     * Nécessite checkExistence = true
     */
    public bool $checkActive = false;

    // ❌ SUPPRIMÉ : public array $groups = [];
    // ❌ SUPPRIMÉ : public mixed $payload = null;
    // Ces propriétés existent déjà dans Symfony\Component\Validator\Constraint

    public function __construct(
        array $options = null,
        string $message = null,
        string $invalidFormatMessage = null,
        string $invalidChecksumMessage = null,
        string $notActiveMessage = null,
        bool $checkExistence = null,
        bool $checkActive = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options);
        
        $this->message = $message ?? $this->message;
        $this->invalidFormatMessage = $invalidFormatMessage ?? $this->invalidFormatMessage;
        $this->invalidChecksumMessage = $invalidChecksumMessage ?? $this->invalidChecksumMessage;
        $this->notActiveMessage = $notActiveMessage ?? $this->notActiveMessage;
        $this->checkExistence = $checkExistence ?? $this->checkExistence;
        $this->checkActive = $checkActive ?? $this->checkActive;
        
        // ✅ CORRECTION : Utiliser les propriétés héritées
        if ($groups !== null) {
            $this->groups = $groups;
        }
        if ($payload !== null) {
            $this->payload = $payload;
        }
    }
}