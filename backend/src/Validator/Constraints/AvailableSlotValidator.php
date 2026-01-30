<?php

namespace App\Validator;

use App\Entity\Planning\AvailableSlot;
use App\Repository\Planning\AvailableSlotRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validateur pour les créneaux disponibles (AvailableSlot)
 * Vérifie les règles métier complexes
 */
class AvailableSlotValidator extends ConstraintValidator
{
    public function __construct(
        private AvailableSlotRepository $availableSlotRepository
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableSlotConstraint) {
            throw new UnexpectedTypeException($constraint, AvailableSlotConstraint::class);
        }

        if (!$value instanceof AvailableSlot) {
            throw new UnexpectedValueException($value, AvailableSlot::class);
        }

        // Valider les horaires
        $this->validateTimeRange($value);

        // Valider qu'il n'y a pas de chevauchement
        $this->validateNoOverlap($value);

        // Valider la date (pas dans le passé)
        $this->validateDateNotPast($value);

        // Valider la durée minimale
        $this->validateMinimumDuration($value);

        // Valider la capacité
        $this->validateCapacity($value);

        // Valider le prix personnalisé si défini
        $this->validateCustomPrice($value);

        // Valider la cohérence du blocage
        $this->validateBlockedState($value);

        // Valider la géolocalisation si définie
        $this->validateLocation($value);
    }

    /**
     * Valide que l'heure de fin est après l'heure de début
     */
    private function validateTimeRange(AvailableSlot $slot): void
    {
        $startTime = $slot->getStartTime();
        $endTime = $slot->getEndTime();

        if (!$startTime || !$endTime) {
            return;
        }

        $start = new \DateTime($startTime->format('H:i:s'));
        $end = new \DateTime($endTime->format('H:i:s'));

        if ($end <= $start) {
            $this->context->buildViolation('L\'heure de fin doit être après l\'heure de début.')
                ->atPath('endTime')
                ->setCode('INVALID_TIME_RANGE')
                ->addViolation();
        }
    }

    /**
     * Valide qu'il n'y a pas de chevauchement avec d'autres créneaux du même prestataire
     */
    private function validateNoOverlap(AvailableSlot $slot): void
    {
        $prestataire = $slot->getPrestataire();
        $date = $slot->getDate();
        $startTime = $slot->getStartTime();
        $endTime = $slot->getEndTime();

        if (!$prestataire || !$date || !$startTime || !$endTime) {
            return;
        }

        // Récupérer tous les créneaux du prestataire pour cette date
        $existingSlots = $this->availableSlotRepository->findByPrestataireAndDate(
            $prestataire,
            $date
        );

        foreach ($existingSlots as $existingSlot) {
            // Ne pas se comparer avec soi-même
            if ($slot->getId() && $existingSlot->getId() === $slot->getId()) {
                continue;
            }

            // Vérifier le chevauchement
            if ($slot->overlapsWith($existingSlot)) {
                $this->context->buildViolation(
                    'Ce créneau chevauche un créneau existant de {{ startTime }} à {{ endTime }}.'
                )
                    ->atPath('startTime')
                    ->setParameter('{{ startTime }}', $existingSlot->getStartTime()->format('H:i'))
                    ->setParameter('{{ endTime }}', $existingSlot->getEndTime()->format('H:i'))
                    ->setCode('SLOT_OVERLAP')
                    ->addViolation();
                break;
            }
        }
    }

    /**
     * Valide que la date n'est pas dans le passé
     */
    private function validateDateNotPast(AvailableSlot $slot): void
    {
        $date = $slot->getDate();
        $startTime = $slot->getStartTime();

        if (!$date || !$startTime) {
            return;
        }

        $slotDateTime = $slot->getStartDateTime();
        $now = new \DateTime();

        if ($slotDateTime && $slotDateTime < $now) {
            $this->context->buildViolation('Le créneau ne peut pas être dans le passé.')
                ->atPath('date')
                ->setCode('DATE_IN_PAST')
                ->addViolation();
        }
    }

    /**
     * Valide la durée minimale du créneau
     */
    private function validateMinimumDuration(AvailableSlot $slot): void
    {
        $duration = $slot->getDuration();

        if ($duration === null) {
            return;
        }

        $minimumDuration = 30; // 30 minutes minimum

        if ($duration < $minimumDuration) {
            $this->context->buildViolation(
                'La durée du créneau doit être d\'au moins {{ min }} minutes.'
            )
                ->atPath('duration')
                ->setParameter('{{ min }}', (string) $minimumDuration)
                ->setCode('DURATION_TOO_SHORT')
                ->addViolation();
        }

        // Vérifier aussi la durée maximale raisonnable
        $maximumDuration = 480; // 8 heures maximum

        if ($duration > $maximumDuration) {
            $this->context->buildViolation(
                'La durée du créneau ne peut pas dépasser {{ max }} minutes.'
            )
                ->atPath('duration')
                ->setParameter('{{ max }}', (string) $maximumDuration)
                ->setCode('DURATION_TOO_LONG')
                ->addViolation();
        }
    }

    /**
     * Valide la capacité du créneau
     */
    private function validateCapacity(AvailableSlot $slot): void
    {
        $capacity = $slot->getCapacity();
        $bookedCount = $slot->getBookedCount();

        // Le nombre de réservations ne peut pas dépasser la capacité
        if ($bookedCount > $capacity) {
            $this->context->buildViolation(
                'Le nombre de réservations ({{ booked }}) ne peut pas dépasser la capacité ({{ capacity }}).'
            )
                ->atPath('bookedCount')
                ->setParameter('{{ booked }}', (string) $bookedCount)
                ->setParameter('{{ capacity }}', (string) $capacity)
                ->setCode('BOOKED_EXCEEDS_CAPACITY')
                ->addViolation();
        }

        // Si le créneau est marqué comme réservé, vérifier la cohérence
        if ($slot->isBooked() && $bookedCount < $capacity) {
            $this->context->buildViolation(
                'Le créneau ne peut pas être marqué comme réservé si la capacité n\'est pas atteinte.'
            )
                ->atPath('isBooked')
                ->setCode('INCONSISTENT_BOOKED_STATUS')
                ->addViolation();
        }
    }

    /**
     * Valide le prix personnalisé si défini
     */
    private function validateCustomPrice(AvailableSlot $slot): void
    {
        $customPrice = $slot->getCustomPrice();

        if ($customPrice === null) {
            return;
        }

        $price = (float) $customPrice;

        if ($price < 0) {
            $this->context->buildViolation('Le prix ne peut pas être négatif.')
                ->atPath('customPrice')
                ->setCode('NEGATIVE_PRICE')
                ->addViolation();
        }

        // Vérifier un prix maximum raisonnable (ex: 500€)
        $maxPrice = 500.0;
        if ($price > $maxPrice) {
            $this->context->buildViolation(
                'Le prix ne peut pas dépasser {{ max }}€.'
            )
                ->atPath('customPrice')
                ->setParameter('{{ max }}', (string) $maxPrice)
                ->setCode('PRICE_TOO_HIGH')
                ->addViolation();
        }

        // Vérifier un prix minimum raisonnable (ex: 5€)
        $minPrice = 5.0;
        if ($price > 0 && $price < $minPrice) {
            $this->context->buildViolation(
                'Le prix doit être d\'au moins {{ min }}€.'
            )
                ->atPath('customPrice')
                ->setParameter('{{ min }}', (string) $minPrice)
                ->setCode('PRICE_TOO_LOW')
                ->addViolation();
        }
    }

    /**
     * Valide la cohérence de l'état bloqué
     */
    private function validateBlockedState(AvailableSlot $slot): void
    {
        // Si le créneau est bloqué, une raison devrait être fournie
        if ($slot->isBlocked() && !$slot->getBlockReason()) {
            $this->context->buildViolation(
                'Une raison doit être fournie pour un créneau bloqué.'
            )
                ->atPath('blockReason')
                ->setCode('MISSING_BLOCK_REASON')
                ->addViolation();
        }

        // Un créneau ne peut pas être à la fois bloqué et réservé
        if ($slot->isBlocked() && $slot->isBooked()) {
            $this->context->buildViolation(
                'Un créneau ne peut pas être à la fois bloqué et réservé.'
            )
                ->atPath('isBlocked')
                ->setCode('BLOCKED_AND_BOOKED')
                ->addViolation();
        }

        // Si le créneau n'est pas bloqué, il ne devrait pas avoir de raison de blocage
        if (!$slot->isBlocked() && $slot->getBlockReason()) {
            $this->context->buildViolation(
                'Un créneau non bloqué ne devrait pas avoir de raison de blocage.'
            )
                ->atPath('blockReason')
                ->setCode('UNNECESSARY_BLOCK_REASON')
                ->addViolation();
        }
    }

    /**
     * Valide la cohérence de la géolocalisation
     */
    private function validateLocation(AvailableSlot $slot): void
    {
        $latitude = $slot->getLatitude();
        $longitude = $slot->getLongitude();

        // Si l'un est défini, l'autre doit l'être aussi
        if (($latitude !== null && $longitude === null) || 
            ($latitude === null && $longitude !== null)) {
            $this->context->buildViolation(
                'La latitude et la longitude doivent être définies ensemble.'
            )
                ->atPath('location')
                ->setCode('INCOMPLETE_COORDINATES')
                ->addViolation();
            return;
        }

        // Valider les plages de latitude et longitude
        if ($latitude !== null) {
            $lat = (float) $latitude;
            if ($lat < -90 || $lat > 90) {
                $this->context->buildViolation(
                    'La latitude doit être entre -90 et 90.'
                )
                    ->atPath('latitude')
                    ->setCode('INVALID_LATITUDE')
                    ->addViolation();
            }
        }

        if ($longitude !== null) {
            $lon = (float) $longitude;
            if ($lon < -180 || $lon > 180) {
                $this->context->buildViolation(
                    'La longitude doit être entre -180 et 180.'
                )
                    ->atPath('longitude')
                    ->setCode('INVALID_LONGITUDE')
                    ->addViolation();
            }
        }

        // Pour la France métropolitaine, vérifier les coordonnées approximatives
        if ($latitude !== null && $longitude !== null) {
            $lat = (float) $latitude;
            $lon = (float) $longitude;

            // France métropolitaine approximativement : lat 41-51, lon -5-10
            if (($lat < 41 || $lat > 51) || ($lon < -5 || $lon > 10)) {
                // Avertissement mais pas d'erreur car peut être DOM-TOM
                // Cette validation pourrait être ajustée selon les besoins
            }
        }
    }

    /**
     * Valide les règles métier pour les créneaux prioritaires
     */
    private function validatePrioritySlot(AvailableSlot $slot): void
    {
        if (!$slot->isPriority()) {
            return;
        }

        // Un créneau prioritaire ne devrait pas être bloqué
        if ($slot->isBlocked()) {
            $this->context->buildViolation(
                'Un créneau prioritaire ne peut pas être bloqué.'
            )
                ->atPath('isPriority')
                ->setCode('PRIORITY_SLOT_BLOCKED')
                ->addViolation();
        }

        // Vérifier qu'il n'y a pas trop de créneaux prioritaires pour ce prestataire
        $prestataire = $slot->getPrestataire();
        $date = $slot->getDate();

        if ($prestataire && $date) {
            $prioritySlots = $this->availableSlotRepository->findPrioritySlotsByPrestataireAndDate(
                $prestataire,
                $date
            );

            $maxPrioritySlots = 3; // Maximum 3 créneaux prioritaires par jour
            $currentCount = count($prioritySlots);

            // Ne pas compter le créneau actuel s'il existe déjà
            if ($slot->getId()) {
                foreach ($prioritySlots as $existingSlot) {
                    if ($existingSlot->getId() === $slot->getId()) {
                        $currentCount--;
                        break;
                    }
                }
            }

            if ($currentCount >= $maxPrioritySlots) {
                $this->context->buildViolation(
                    'Vous ne pouvez pas avoir plus de {{ max }} créneaux prioritaires par jour.'
                )
                    ->atPath('isPriority')
                    ->setParameter('{{ max }}', (string) $maxPrioritySlots)
                    ->setCode('TOO_MANY_PRIORITY_SLOTS')
                    ->addViolation();
            }
        }
    }

    /**
     * Valide les règles pour les créneaux avec expiration
     */
    private function validateExpirationDate(AvailableSlot $slot): void
    {
        $expiresAt = $slot->getExpiresAt();

        if (!$expiresAt) {
            return;
        }

        $now = new \DateTime();

        // La date d'expiration doit être dans le futur
        if ($expiresAt <= $now) {
            $this->context->buildViolation(
                'La date d\'expiration doit être dans le futur.'
            )
                ->atPath('expiresAt')
                ->setCode('EXPIRATION_IN_PAST')
                ->addViolation();
        }

        // La date d'expiration doit être avant ou égale à la date du créneau
        $slotDateTime = $slot->getStartDateTime();
        if ($slotDateTime && $expiresAt > $slotDateTime) {
            $this->context->buildViolation(
                'La date d\'expiration ne peut pas être après la date du créneau.'
            )
                ->atPath('expiresAt')
                ->setCode('EXPIRATION_AFTER_SLOT')
                ->addViolation();
        }
    }

    /**
     * Valide les créneaux récurrents générés automatiquement
     */
    private function validateAutomaticSlot(AvailableSlot $slot): void
    {
        // Un créneau automatique (non manuel) doit avoir une disponibilité source
        if (!$slot->isManual() && !$slot->getSourceAvailability()) {
            $this->context->buildViolation(
                'Un créneau généré automatiquement doit avoir une disponibilité source.'
            )
                ->atPath('sourceAvailability')
                ->setCode('MISSING_SOURCE_AVAILABILITY')
                ->addViolation();
        }

        // Un créneau manuel ne devrait pas avoir de disponibilité source
        if ($slot->isManual() && $slot->getSourceAvailability()) {
            $this->context->buildViolation(
                'Un créneau manuel ne devrait pas avoir de disponibilité source.'
            )
                ->atPath('sourceAvailability')
                ->setCode('MANUAL_WITH_SOURCE')
                ->addViolation();
        }
    }

    /**
     * Valide les règles métier pour les créneaux avec service type
     */
    private function validateServiceType(AvailableSlot $slot): void
    {
        $serviceType = $slot->getServiceType();
        
        if (!$serviceType) {
            return;
        }

        $prestataire = $slot->getPrestataire();
        
        if (!$prestataire) {
            return;
        }

        // Vérifier que le prestataire propose ce type de service
        $prestataireCategories = $prestataire->getServiceCategories();
        $categoryFound = false;

        foreach ($prestataireCategories as $category) {
            if ($category->getSlug() === $serviceType || 
                $category->getName() === $serviceType) {
                $categoryFound = true;
                break;
            }
        }

        if (!$categoryFound) {
            $this->context->buildViolation(
                'Le prestataire ne propose pas le service "{{ serviceType }}".'
            )
                ->atPath('serviceType')
                ->setParameter('{{ serviceType }}', $serviceType)
                ->setCode('SERVICE_NOT_OFFERED')
                ->addViolation();
        }
    }

    /**
     * Valide les horaires de travail standard (ex: pas de créneaux de nuit)
     */
    private function validateBusinessHours(AvailableSlot $slot): void
    {
        $startTime = $slot->getStartTime();
        $endTime = $slot->getEndTime();

        if (!$startTime || !$endTime) {
            return;
        }

        $startHour = (int) $startTime->format('H');
        $endHour = (int) $endTime->format('H');

        // Horaires de travail raisonnables : 6h - 22h
        $minHour = 6;
        $maxHour = 22;

        if ($startHour < $minHour) {
            $this->context->buildViolation(
                'L\'heure de début ne peut pas être avant {{ min }}h.'
            )
                ->atPath('startTime')
                ->setParameter('{{ min }}', (string) $minHour)
                ->setCode('START_TIME_TOO_EARLY')
                ->addViolation();
        }

        if ($endHour > $maxHour || ($endHour === 0 && (int) $endTime->format('i') > 0)) {
            $this->context->buildViolation(
                'L\'heure de fin ne peut pas être après {{ max }}h.'
            )
                ->atPath('endTime')
                ->setParameter('{{ max }}', (string) $maxHour)
                ->setCode('END_TIME_TOO_LATE')
                ->addViolation();
        }
    }
}