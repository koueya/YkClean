<?php

namespace App\Service;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceCategory;
use Psr\Log\LoggerInterface;

class CommissionCalculator
{
    private LoggerInterface $logger;
    private float $defaultCommissionRate;
    private array $categoryRates;
    private array $volumeDiscounts;
    private array $loyaltyDiscounts;

    public function __construct(
        LoggerInterface $logger,
        float $defaultCommissionRate = 0.15, // 15% par défaut
        array $categoryRates = [],
        array $volumeDiscounts = [],
        array $loyaltyDiscounts = []
    ) {
        $this->logger = $logger;
        $this->defaultCommissionRate = $defaultCommissionRate;
        
        // Taux de commission par catégorie de service
        $this->categoryRates = array_merge([
            'nettoyage' => 0.15,        // 15%
            'repassage' => 0.12,        // 12%
            'menage_complet' => 0.18,   // 18%
            'vitres' => 0.14,           // 14%
            'jardinage' => 0.16,        // 16%
            'bricolage' => 0.17,        // 17%
        ], $categoryRates);

        // Remises par volume (nombre de services effectués)
        $this->volumeDiscounts = array_merge([
            ['min' => 0, 'max' => 10, 'discount' => 0.00],      // 0-10 services: 0%
            ['min' => 11, 'max' => 25, 'discount' => 0.05],     // 11-25 services: -5%
            ['min' => 26, 'max' => 50, 'discount' => 0.10],     // 26-50 services: -10%
            ['min' => 51, 'max' => 100, 'discount' => 0.15],    // 51-100 services: -15%
            ['min' => 101, 'max' => PHP_INT_MAX, 'discount' => 0.20], // 100+ services: -20%
        ], $volumeDiscounts);

        // Remises de fidélité (mois d'ancienneté)
        $this->loyaltyDiscounts = array_merge([
            ['min' => 0, 'max' => 3, 'discount' => 0.00],       // 0-3 mois: 0%
            ['min' => 4, 'max' => 6, 'discount' => 0.02],       // 4-6 mois: -2%
            ['min' => 7, 'max' => 12, 'discount' => 0.05],      // 7-12 mois: -5%
            ['min' => 13, 'max' => PHP_INT_MAX, 'discount' => 0.08], // 12+ mois: -8%
        ], $loyaltyDiscounts);
    }

    /**
     * Calculer la commission pour un booking
     */
    public function calculateCommission(Booking $booking, bool $applyDiscounts = true): array
    {
        $amount = $booking->getAmount();
        $prestataire = $booking->getPrestataire();
        $category = $booking->getServiceRequest()?->getCategory();

        // Taux de base selon la catégorie
        $baseRate = $this->getBaseCommissionRate($category);

        // Appliquer les réductions si activées
        $finalRate = $baseRate;
        $appliedDiscounts = [];

        if ($applyDiscounts) {
            // Remise par volume
            $volumeDiscount = $this->calculateVolumeDiscount($prestataire);
            if ($volumeDiscount > 0) {
                $finalRate -= $baseRate * $volumeDiscount;
                $appliedDiscounts['volume'] = [
                    'rate' => $volumeDiscount,
                    'amount' => $amount * $baseRate * $volumeDiscount,
                ];
            }

            // Remise de fidélité
            $loyaltyDiscount = $this->calculateLoyaltyDiscount($prestataire);
            if ($loyaltyDiscount > 0) {
                $finalRate -= $baseRate * $loyaltyDiscount;
                $appliedDiscounts['loyalty'] = [
                    'rate' => $loyaltyDiscount,
                    'amount' => $amount * $baseRate * $loyaltyDiscount,
                ];
            }

            // S'assurer que le taux final ne soit pas négatif
            $finalRate = max(0.05, $finalRate); // Minimum 5%
        }

        $commissionAmount = $amount * $finalRate;
        $prestataireAmount = $amount - $commissionAmount;

        $result = [
            'total_amount' => $amount,
            'base_commission_rate' => $baseRate,
            'final_commission_rate' => $finalRate,
            'commission_amount' => round($commissionAmount, 2),
            'prestataire_amount' => round($prestataireAmount, 2),
            'applied_discounts' => $appliedDiscounts,
            'discount_total' => round(($baseRate - $finalRate) * $amount, 2),
        ];

        $this->logger->info('Commission calculated', [
            'booking_id' => $booking->getId(),
            'prestataire_id' => $prestataire->getId(),
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Obtenir le taux de commission de base selon la catégorie
     */
    private function getBaseCommissionRate(?ServiceCategory $category): float
    {
        if (!$category) {
            return $this->defaultCommissionRate;
        }

        $categorySlug = strtolower($category->getSlug() ?? $category->getName());
        
        return $this->categoryRates[$categorySlug] ?? $this->defaultCommissionRate;
    }

    /**
     * Calculer la remise par volume (basée sur le nombre de services)
     */
    private function calculateVolumeDiscount(Prestataire $prestataire): float
    {
        $completedBookingsCount = $prestataire->getCompletedBookingsCount();

        foreach ($this->volumeDiscounts as $bracket) {
            if ($completedBookingsCount >= $bracket['min'] && 
                $completedBookingsCount <= $bracket['max']) {
                return $bracket['discount'];
            }
        }

        return 0.0;
    }

    /**
     * Calculer la remise de fidélité (basée sur l'ancienneté)
     */
    private function calculateLoyaltyDiscount(Prestataire $prestataire): float
    {
        $registrationDate = $prestataire->getCreatedAt();
        $now = new \DateTime();
        $monthsActive = $registrationDate->diff($now)->m + 
                       ($registrationDate->diff($now)->y * 12);

        foreach ($this->loyaltyDiscounts as $bracket) {
            if ($monthsActive >= $bracket['min'] && 
                $monthsActive <= $bracket['max']) {
                return $bracket['discount'];
            }
        }

        return 0.0;
    }

    /**
     * Calculer les commissions pour plusieurs bookings
     */
    public function calculateBulkCommissions(array $bookings, bool $applyDiscounts = true): array
    {
        $results = [];
        $totals = [
            'total_amount' => 0,
            'total_commission' => 0,
            'total_prestataire' => 0,
            'total_discounts' => 0,
            'count' => count($bookings),
        ];

        foreach ($bookings as $booking) {
            $calculation = $this->calculateCommission($booking, $applyDiscounts);
            $results[] = [
                'booking_id' => $booking->getId(),
                'calculation' => $calculation,
            ];

            $totals['total_amount'] += $calculation['total_amount'];
            $totals['total_commission'] += $calculation['commission_amount'];
            $totals['total_prestataire'] += $calculation['prestataire_amount'];
            $totals['total_discounts'] += $calculation['discount_total'];
        }

        return [
            'bookings' => $results,
            'totals' => $totals,
        ];
    }

    /**
     * Prévisualiser la commission avant création du booking
     */
    public function previewCommission(
        float $amount,
        Prestataire $prestataire,
        ?ServiceCategory $category = null
    ): array {
        $baseRate = $this->getBaseCommissionRate($category);
        $volumeDiscount = $this->calculateVolumeDiscount($prestataire);
        $loyaltyDiscount = $this->calculateLoyaltyDiscount($prestataire);

        $finalRate = $baseRate - ($baseRate * $volumeDiscount) - ($baseRate * $loyaltyDiscount);
        $finalRate = max(0.05, $finalRate);

        $commissionAmount = $amount * $finalRate;
        $prestataireAmount = $amount - $commissionAmount;

        return [
            'amount' => $amount,
            'base_rate' => $baseRate,
            'final_rate' => $finalRate,
            'commission' => round($commissionAmount, 2),
            'prestataire_receives' => round($prestataireAmount, 2),
            'discounts' => [
                'volume' => $volumeDiscount,
                'loyalty' => $loyaltyDiscount,
            ],
        ];
    }

    /**
     * Obtenir le palier de commission actuel d'un prestataire
     */
    public function getPrestataireCommissionTier(Prestataire $prestataire): array
    {
        $completedBookings = $prestataire->getCompletedBookingsCount();
        $monthsActive = $prestataire->getCreatedAt()->diff(new \DateTime())->m + 
                       ($prestataire->getCreatedAt()->diff(new \DateTime())->y * 12);

        $volumeDiscount = $this->calculateVolumeDiscount($prestataire);
        $loyaltyDiscount = $this->calculateLoyaltyDiscount($prestataire);

        // Trouver le prochain palier de volume
        $nextVolumeTier = null;
        foreach ($this->volumeDiscounts as $bracket) {
            if ($completedBookings < $bracket['min']) {
                $nextVolumeTier = [
                    'bookings_needed' => $bracket['min'] - $completedBookings,
                    'discount_rate' => $bracket['discount'],
                ];
                break;
            }
        }

        // Trouver le prochain palier de fidélité
        $nextLoyaltyTier = null;
        foreach ($this->loyaltyDiscounts as $bracket) {
            if ($monthsActive < $bracket['min']) {
                $nextLoyaltyTier = [
                    'months_needed' => $bracket['min'] - $monthsActive,
                    'discount_rate' => $bracket['discount'],
                ];
                break;
            }
        }

        return [
            'current' => [
                'completed_bookings' => $completedBookings,
                'months_active' => $monthsActive,
                'volume_discount' => $volumeDiscount,
                'loyalty_discount' => $loyaltyDiscount,
                'total_discount' => $volumeDiscount + $loyaltyDiscount,
            ],
            'next_tiers' => [
                'volume' => $nextVolumeTier,
                'loyalty' => $nextLoyaltyTier,
            ],
        ];
    }

    /**
     * Calculer les gains nets d'un prestataire pour une période
     */
    public function calculatePrestataireEarnings(
        Prestataire $prestataire,
        \DateTime $startDate,
        \DateTime $endDate
    ): array {
        // Cette méthode nécessiterait un repository pour récupérer les bookings
        // C'est juste une structure de retour exemple
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'gross_revenue' => 0, // Total des montants de bookings
            'commission_paid' => 0, // Total des commissions
            'net_earnings' => 0, // Revenue - Commission
            'bookings_count' => 0,
            'average_commission_rate' => 0,
        ];
    }

    /**
     * Simuler l'impact d'une modification des taux de commission
     */
    public function simulateRateChange(
        array $newRates,
        array $bookings
    ): array {
        $originalCalculations = [];
        $newCalculations = [];

        $originalTotals = [
            'commission' => 0,
            'prestataire' => 0,
        ];

        $newTotals = [
            'commission' => 0,
            'prestataire' => 0,
        ];

        foreach ($bookings as $booking) {
            // Calcul avec les taux actuels
            $original = $this->calculateCommission($booking);
            $originalCalculations[] = $original;
            $originalTotals['commission'] += $original['commission_amount'];
            $originalTotals['prestataire'] += $original['prestataire_amount'];

            // Calcul avec les nouveaux taux (temporairement)
            $oldRates = $this->categoryRates;
            $this->categoryRates = array_merge($this->categoryRates, $newRates);
            
            $new = $this->calculateCommission($booking);
            $newCalculations[] = $new;
            $newTotals['commission'] += $new['commission_amount'];
            $newTotals['prestataire'] += $new['prestataire_amount'];

            // Restaurer les anciens taux
            $this->categoryRates = $oldRates;
        }

        $impact = [
            'commission_difference' => $newTotals['commission'] - $originalTotals['commission'],
            'prestataire_difference' => $newTotals['prestataire'] - $originalTotals['prestataire'],
            'commission_change_percent' => $originalTotals['commission'] > 0 
                ? (($newTotals['commission'] - $originalTotals['commission']) / $originalTotals['commission']) * 100
                : 0,
        ];

        return [
            'original' => $originalTotals,
            'new' => $newTotals,
            'impact' => $impact,
            'bookings_count' => count($bookings),
        ];
    }

    /**
     * Obtenir les statistiques de commission pour la plateforme
     */
    public function getPlatformCommissionStats(\DateTime $startDate, \DateTime $endDate): array
    {
        // Cette méthode nécessiterait un repository
        // Structure de retour exemple
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_revenue' => 0,
            'total_commission' => 0,
            'average_commission_rate' => 0,
            'by_category' => [],
            'discounts_given' => [
                'volume' => 0,
                'loyalty' => 0,
                'total' => 0,
            ],
        ];
    }

    /**
     * Définir un taux de commission personnalisé pour un prestataire spécifique
     */
    public function setCustomRate(Prestataire $prestataire, float $customRate, ?string $reason = null): void
    {
        $prestataire->setCustomCommissionRate($customRate);
        
        if ($reason) {
            $prestataire->setCustomRateReason($reason);
        }

        $this->logger->info('Custom commission rate set', [
            'prestataire_id' => $prestataire->getId(),
            'rate' => $customRate,
            'reason' => $reason,
        ]);
    }

    /**
     * Obtenir tous les taux de commission configurés
     */
    public function getAllRates(): array
    {
        return [
            'default_rate' => $this->defaultCommissionRate,
            'category_rates' => $this->categoryRates,
            'volume_discounts' => $this->volumeDiscounts,
            'loyalty_discounts' => $this->loyaltyDiscounts,
        ];
    }

    /**
     * Calculer le breakeven point pour un prestataire
     * (Combien de services avant que les réductions compensent les commissions)
     */
    public function calculateBreakeven(float $averageBookingAmount): array
    {
        $breakdowns = [];
        $cumulativeEarnings = 0;
        $cumulativeCommissions = 0;

        for ($bookings = 1; $bookings <= 100; $bookings++) {
            $volumeDiscount = 0;
            foreach ($this->volumeDiscounts as $bracket) {
                if ($bookings >= $bracket['min'] && $bookings <= $bracket['max']) {
                    $volumeDiscount = $bracket['discount'];
                    break;
                }
            }

            $rate = $this->defaultCommissionRate * (1 - $volumeDiscount);
            $commission = $averageBookingAmount * $rate;
            $net = $averageBookingAmount - $commission;

            $cumulativeEarnings += $net;
            $cumulativeCommissions += $commission;

            if ($bookings % 10 === 0 || $bookings === 1) {
                $breakdowns[$bookings] = [
                    'bookings' => $bookings,
                    'commission_rate' => round($rate, 4),
                    'cumulative_earnings' => round($cumulativeEarnings, 2),
                    'cumulative_commissions' => round($cumulativeCommissions, 2),
                    'average_net_per_booking' => round($cumulativeEarnings / $bookings, 2),
                ];
            }
        }

        return $breakdowns;
    }
}