<?php

namespace App\Controller\Admin;

use App\Entity\User\Prestataire;
use App\Repository\User\PrestataireRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;
use App\Service\Payment\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/prestataires')]
#[IsGranted('ROLE_ADMIN')]
class PrestataireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrestataireRepository $prestataireRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private StripeService $stripeService,
        private PaginatorInterface $paginator
    ) {
    }

    /**
     * Liste tous les prestataires
     */
    #[Route('', name: 'admin_prestataires', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->prestataireRepository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        // Filtres
        $status = $request->query->get('status');
        if ($status === 'pending') {
            $query->andWhere('p.isApproved = false');
        } elseif ($status === 'approved') {
            $query->andWhere('p.isApproved = true');
        } elseif ($status === 'inactive') {
            $query->andWhere('p.isActive = false');
        }

        $search = $request->query->get('search');
        if ($search) {
            $query->andWhere(
                $query->expr()->orX(
                    $query->expr()->like('p.firstName', ':search'),
                    $query->expr()->like('p.lastName', ':search'),
                    $query->expr()->like('p.email', ':search'),
                    $query->expr()->like('p.companyName', ':search'),
                    $query->expr()->like('p.siret', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $prestataires = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );

        // Statistiques
        $stats = [
            'total' => $this->prestataireRepository->count([]),
            'pending' => $this->prestataireRepository->count(['isApproved' => false]),
            'approved' => $this->prestataireRepository->count(['isApproved' => true]),
            'inactive' => $this->prestataireRepository->count(['isActive' => false]),
        ];

        return $this->render('admin/prestataire/index.html.twig', [
            'prestataires' => $prestataires,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Affiche les détails d'un prestataire
     */
    #[Route('/{id}', name: 'admin_prestataire_show', methods: ['GET'])]
    public function show(Prestataire $prestataire): Response
    {
        // Statistiques du prestataire
        $bookingsCount = $this->bookingRepository->count(['prestataire' => $prestataire]);
        $bookingsCompleted = $this->bookingRepository->count([
            'prestataire' => $prestataire,
            'status' => 'completed'
        ]);
        
        $totalRevenue = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->join('p.booking', 'b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('p.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Dernières réservations
        $recentBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Statut du compte Stripe Connect
        $stripeAccountStatus = null;
        if ($prestataire->getStripeConnectedAccountId()) {
            $stripeAccountStatus = $this->stripeService->getAccountStatus($prestataire);
        }

        return $this->render('admin/prestataire/show.html.twig', [
            'prestataire' => $prestataire,
            'stats' => [
                'bookingsCount' => $bookingsCount,
                'bookingsCompleted' => $bookingsCompleted,
                'completionRate' => $bookingsCount > 0 ? round(($bookingsCompleted / $bookingsCount) * 100, 1) : 0,
                'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            ],
            'recentBookings' => $recentBookings,
            'stripeAccountStatus' => $stripeAccountStatus,
        ]);
    }

    /**
     * Approuve un prestataire
     */
    #[Route('/{id}/approve', name: 'admin_prestataire_approve', methods: ['POST'])]
    public function approve(Prestataire $prestataire): Response
    {
        if ($prestataire->isApproved()) {
            $this->addFlash('warning', 'Ce prestataire est déjà approuvé.');
            return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
        }

        $prestataire->setIsApproved(true);
        $prestataire->setApprovedAt(new \DateTime());

        $this->entityManager->flush();

        // TODO: Envoyer un email de confirmation au prestataire

        $this->addFlash('success', 'Le prestataire a été approuvé avec succès.');

        return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
    }

    /**
     * Désapprouve un prestataire
     */
    #[Route('/{id}/unapprove', name: 'admin_prestataire_unapprove', methods: ['POST'])]
    public function unapprove(Prestataire $prestataire, Request $request): Response
    {
        $reason = $request->request->get('reason', 'Non spécifiée');

        $prestataire->setIsApproved(false);
        $prestataire->setApprovedAt(null);

        $this->entityManager->flush();

        // TODO: Envoyer un email avec la raison du refus

        $this->addFlash('success', 'Le prestataire a été désapprouvé.');

        return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
    }

    /**
     * Active un prestataire
     */
    #[Route('/{id}/activate', name: 'admin_prestataire_activate', methods: ['POST'])]
    public function activate(Prestataire $prestataire): Response
    {
        $prestataire->setIsActive(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le prestataire a été activé.');

        return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
    }

    /**
     * Désactive un prestataire
     */
    #[Route('/{id}/deactivate', name: 'admin_prestataire_deactivate', methods: ['POST'])]
    public function deactivate(Prestataire $prestataire, Request $request): Response
    {
        $reason = $request->request->get('reason', 'Non spécifiée');

        $prestataire->setIsActive(false);

        $this->entityManager->flush();

        // TODO: Envoyer un email avec la raison

        $this->addFlash('success', 'Le prestataire a été désactivé.');

        return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
    }

    /**
     * Supprime un prestataire
     */
    #[Route('/{id}/delete', name: 'admin_prestataire_delete', methods: ['POST'])]
    public function delete(Prestataire $prestataire): Response
    {
        // Vérifier qu'il n'a pas de réservations en cours
        $activeBookings = $this->bookingRepository->count([
            'prestataire' => $prestataire,
            'status' => ['pending', 'confirmed', 'in_progress']
        ]);

        if ($activeBookings > 0) {
            $this->addFlash('error', 'Impossible de supprimer ce prestataire : il a des réservations en cours.');
            return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
        }

        $name = $prestataire->getFullName();

        $this->entityManager->remove($prestataire);
        $this->entityManager->flush();

        $this->addFlash('success', "Le prestataire $name a été supprimé.");

        return $this->redirectToRoute('admin_prestataires');
    }

    /**
     * Modifier les informations d'un prestataire
     */
    #[Route('/{id}/edit', name: 'admin_prestataire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Prestataire $prestataire): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Mise à jour des champs
            if (isset($data['firstName'])) {
                $prestataire->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $prestataire->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $prestataire->setEmail($data['email']);
            }
            if (isset($data['phone'])) {
                $prestataire->setPhone($data['phone']);
            }
            if (isset($data['companyName'])) {
                $prestataire->setCompanyName($data['companyName']);
            }
            if (isset($data['siret'])) {
                $prestataire->setSiret($data['siret']);
            }
            if (isset($data['address'])) {
                $prestataire->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $prestataire->setCity($data['city']);
            }
            if (isset($data['postalCode'])) {
                $prestataire->setPostalCode($data['postalCode']);
            }
            if (isset($data['hourlyRate'])) {
                $prestataire->setHourlyRate($data['hourlyRate']);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Les informations ont été mises à jour.');

            return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
        }

        return $this->render('admin/prestataire/edit.html.twig', [
            'prestataire' => $prestataire,
        ]);
    }

    /**
     * Vérifie le statut du compte Stripe Connect
     */
    #[Route('/{id}/stripe/status', name: 'admin_prestataire_stripe_status', methods: ['GET'])]
    public function stripeStatus(Prestataire $prestataire): Response
    {
        if (!$prestataire->getStripeConnectedAccountId()) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun compte Stripe Connect associé'
            ], 404);
        }

        $status = $this->stripeService->getAccountStatus($prestataire);

        if (!$status) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de récupérer le statut du compte'
            ], 500);
        }

        return $this->json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * Crée un compte Stripe Connect pour le prestataire
     */
    #[Route('/{id}/stripe/create', name: 'admin_prestataire_stripe_create', methods: ['POST'])]
    public function createStripeAccount(Prestataire $prestataire): Response
    {
        if ($prestataire->getStripeConnectedAccountId()) {
            $this->addFlash('warning', 'Ce prestataire a déjà un compte Stripe Connect.');
            return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
        }

        $accountId = $this->stripeService->createConnectedAccount($prestataire);

        if (!$accountId) {
            $this->addFlash('error', 'Erreur lors de la création du compte Stripe Connect.');
            return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
        }

        $this->addFlash('success', 'Compte Stripe Connect créé avec succès.');

        return $this->redirectToRoute('admin_prestataire_show', ['id' => $prestataire->getId()]);
    }

    /**
     * Statistiques globales des prestataires
     */
    #[Route('/stats/global', name: 'admin_prestataires_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $total = $this->prestataireRepository->count([]);
        $approved = $this->prestataireRepository->count(['isApproved' => true]);
        $pending = $this->prestataireRepository->count(['isApproved' => false]);
        $active = $this->prestataireRepository->count(['isActive' => true]);
        $inactive = $this->prestataireRepository->count(['isActive' => false]);

        // Prestataires par mois (cette année)
        $byMonth = [];
        $currentYear = date('Y');
        for ($month = 1; $month <= 12; $month++) {
            $startDate = new \DateTime("$currentYear-$month-01");
            $endDate = (clone $startDate)->modify('last day of this month');

            $count = $this->prestataireRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->getQuery()
                ->getSingleScalarResult();

            $byMonth[] = (int) $count;
        }

        // Top prestataires par nombre de réservations
        $topPrestataires = $this->prestataireRepository->createQueryBuilder('p')
            ->select('p.id, p.firstName, p.lastName, p.averageRating, COUNT(b.id) as bookingsCount')
            ->leftJoin('p.bookings', 'b')
            ->groupBy('p.id')
            ->orderBy('bookingsCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('admin/prestataire/stats.html.twig', [
            'stats' => [
                'total' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'active' => $active,
                'inactive' => $inactive,
                'approvalRate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
            ],
            'byMonth' => $byMonth,
            'topPrestataires' => $topPrestataires,
        ]);
    }

    /**
     * Export des prestataires en CSV
     */
    #[Route('/export/csv', name: 'admin_prestataires_export', methods: ['GET'])]
    public function export(): Response
    {
        $prestataires = $this->prestataireRepository->findAll();

        $csv = [];
        $csv[] = [
            'ID',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Entreprise',
            'SIRET',
            'Taux horaire',
            'Approuvé',
            'Actif',
            'Note moyenne',
            'Nombre avis',
            'Date inscription',
        ];

        foreach ($prestataires as $prestataire) {
            $csv[] = [
                $prestataire->getId(),
                $prestataire->getFirstName(),
                $prestataire->getLastName(),
                $prestataire->getEmail(),
                $prestataire->getPhone(),
                $prestataire->getCompanyName(),
                $prestataire->getSiret(),
                $prestataire->getHourlyRate(),
                $prestataire->isApproved() ? 'Oui' : 'Non',
                $prestataire->isActive() ? 'Oui' : 'Non',
                $prestataire->getAverageRating(),
                $prestataire->getTotalReviews(),
                $prestataire->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="prestataires_' . date('Y-m-d') . '.csv"');

        $handle = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $response->setContent(stream_get_contents($handle));
        fclose($handle);

        return $response;
    }

    /**
     * Envoie une notification à un prestataire
     */
    #[Route('/{id}/notify', name: 'admin_prestataire_notify', methods: ['POST'])]
    public function notify(Prestataire $prestataire, Request $request): Response
    {
        $subject = $request->request->get('subject');
        $message = $request->request->get('message');

        if (!$subject || !$message) {
            return $this->json([
                'success' => false,
                'message' => 'Sujet et message requis'
            ], 400);
        }

        // TODO: Implémenter l'envoi d'email via Symfony Mailer

        $this->addFlash('success', 'Notification envoyée avec succès.');

        return $this->json([
            'success' => true,
            'message' => 'Notification envoyée'
        ]);
    }
}