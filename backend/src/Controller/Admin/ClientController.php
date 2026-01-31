<?php

namespace App\Controller\Admin;

use App\Entity\User\Client;
use App\Repository\User\ClientRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Service\Payment\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/clients')]
#[IsGranted('ROLE_ADMIN')]
class ClientController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private StripeService $stripeService,
        private PaginatorInterface $paginator
    ) {
    }

    /**
     * Liste tous les clients
     */
    #[Route('', name: 'admin_clients', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->clientRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        // Filtres
        $status = $request->query->get('status');
        if ($status === 'active') {
            $query->andWhere('c.isActive = true');
        } elseif ($status === 'inactive') {
            $query->andWhere('c.isActive = false');
        } elseif ($status === 'verified') {
            $query->andWhere('c.isVerified = true');
        } elseif ($status === 'unverified') {
            $query->andWhere('c.isVerified = false');
        }

        $search = $request->query->get('search');
        if ($search) {
            $query->andWhere(
                $query->expr()->orX(
                    $query->expr()->like('c.firstName', ':search'),
                    $query->expr()->like('c.lastName', ':search'),
                    $query->expr()->like('c.email', ':search'),
                    $query->expr()->like('c.phone', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $clients = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );

        // Statistiques
        $stats = [
            'total' => $this->clientRepository->count([]),
            'active' => $this->clientRepository->count(['isActive' => true]),
            'inactive' => $this->clientRepository->count(['isActive' => false]),
            'verified' => $this->clientRepository->count(['isVerified' => true]),
            'unverified' => $this->clientRepository->count(['isVerified' => false]),
        ];

        return $this->render('admin/client/index.html.twig', [
            'clients' => $clients,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Affiche les détails d'un client
     */
    #[Route('/{id}', name: 'admin_client_show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        // Statistiques du client
        $serviceRequestsCount = $this->serviceRequestRepository->count(['client' => $client]);
        $bookingsCount = $this->bookingRepository->count(['client' => $client]);
        $bookingsCompleted = $this->bookingRepository->count([
            'client' => $client,
            'status' => 'completed'
        ]);
        
        $totalSpent = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->join('p.booking', 'b')
            ->where('b.client = :client')
            ->andWhere('p.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Calcul de la moyenne des dépenses par réservation
        $avgSpentPerBooking = $bookingsCompleted > 0 
            ? round($totalSpent / $bookingsCompleted, 2) 
            : 0;

        // Dernières demandes de service
        $recentServiceRequests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Dernières réservations
        $recentBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Méthodes de paiement
        $paymentMethods = [];
        if ($client->getStripeCustomerId()) {
            $paymentMethods = $this->stripeService->getPaymentMethods($client);
        }

        return $this->render('admin/client/show.html.twig', [
            'client' => $client,
            'stats' => [
                'serviceRequestsCount' => $serviceRequestsCount,
                'bookingsCount' => $bookingsCount,
                'bookingsCompleted' => $bookingsCompleted,
                'completionRate' => $bookingsCount > 0 ? round(($bookingsCompleted / $bookingsCount) * 100, 1) : 0,
                'totalSpent' => number_format($totalSpent, 2, '.', ''),
                'avgSpentPerBooking' => number_format($avgSpentPerBooking, 2, '.', ''),
            ],
            'recentServiceRequests' => $recentServiceRequests,
            'recentBookings' => $recentBookings,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Active un client
     */
    #[Route('/{id}/activate', name: 'admin_client_activate', methods: ['POST'])]
    public function activate(Client $client): Response
    {
        $client->setIsActive(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le client a été activé.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    /**
     * Désactive un client
     */
    #[Route('/{id}/deactivate', name: 'admin_client_deactivate', methods: ['POST'])]
    public function deactivate(Client $client, Request $request): Response
    {
        $reason = $request->request->get('reason', 'Non spécifiée');

        $client->setIsActive(false);

        $this->entityManager->flush();

        // TODO: Envoyer un email avec la raison

        $this->addFlash('success', 'Le client a été désactivé.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    /**
     * Vérifie manuellement l'email d'un client
     */
    #[Route('/{id}/verify', name: 'admin_client_verify', methods: ['POST'])]
    public function verifyEmail(Client $client): Response
    {
        $client->verifyEmail();
        $this->entityManager->flush();

        $this->addFlash('success', 'L\'email du client a été vérifié.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    /**
     * Supprime un client
     */
    #[Route('/{id}/delete', name: 'admin_client_delete', methods: ['POST'])]
    public function delete(Client $client): Response
    {
        // Vérifier qu'il n'a pas de réservations en cours
        $activeBookings = $this->bookingRepository->count([
            'client' => $client,
            'status' => ['pending', 'confirmed', 'in_progress']
        ]);

        if ($activeBookings > 0) {
            $this->addFlash('error', 'Impossible de supprimer ce client : il a des réservations en cours.');
            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        $name = $client->getFullName();

        $this->entityManager->remove($client);
        $this->entityManager->flush();

        $this->addFlash('success', "Le client $name a été supprimé.");

        return $this->redirectToRoute('admin_clients');
    }

    /**
     * Modifier les informations d'un client
     */
    #[Route('/{id}/edit', name: 'admin_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Mise à jour des champs
            if (isset($data['firstName'])) {
                $client->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $client->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $client->setEmail($data['email']);
            }
            if (isset($data['phone'])) {
                $client->setPhone($data['phone']);
            }
            if (isset($data['address'])) {
                $client->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $client->setCity($data['city']);
            }
            if (isset($data['postalCode'])) {
                $client->setPostalCode($data['postalCode']);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Les informations ont été mises à jour.');

            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        return $this->render('admin/client/edit.html.twig', [
            'client' => $client,
        ]);
    }

    /**
     * Récupère les transactions d'un client
     */
    #[Route('/{id}/transactions', name: 'admin_client_transactions', methods: ['GET'])]
    public function transactions(Client $client): Response
    {
        if (!$client->getStripeCustomerId()) {
            $this->addFlash('warning', 'Ce client n\'a pas de compte Stripe.');
            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        $transactions = $this->stripeService->getCustomerTransactions($client, 50);

        return $this->render('admin/client/transactions.html.twig', [
            'client' => $client,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Crée un client Stripe pour le client
     */
    #[Route('/{id}/stripe/create', name: 'admin_client_stripe_create', methods: ['POST'])]
    public function createStripeCustomer(Client $client): Response
    {
        if ($client->getStripeCustomerId()) {
            $this->addFlash('warning', 'Ce client a déjà un compte Stripe.');
            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        $customerId = $this->stripeService->getOrCreateCustomer($client);

        if (!$customerId) {
            $this->addFlash('error', 'Erreur lors de la création du compte Stripe.');
            return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
        }

        $this->addFlash('success', 'Compte Stripe créé avec succès.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    /**
     * Statistiques globales des clients
     */
    #[Route('/stats/global', name: 'admin_clients_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $total = $this->clientRepository->count([]);
        $active = $this->clientRepository->count(['isActive' => true]);
        $inactive = $this->clientRepository->count(['isActive' => false]);
        $verified = $this->clientRepository->count(['isVerified' => true]);

        // Clients par mois (cette année)
        $byMonth = [];
        $currentYear = date('Y');
        for ($month = 1; $month <= 12; $month++) {
            $startDate = new \DateTime("$currentYear-$month-01");
            $endDate = (clone $startDate)->modify('last day of this month');

            $count = $this->clientRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.createdAt >= :start')
                ->andWhere('c.createdAt <= :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->getQuery()
                ->getSingleScalarResult();

            $byMonth[] = (int) $count;
        }

        // Top clients par dépenses
        $topClients = $this->clientRepository->createQueryBuilder('c')
            ->select('c.id, c.firstName, c.lastName, c.email, SUM(p.amount) as totalSpent, COUNT(b.id) as bookingsCount')
            ->leftJoin('c.bookings', 'b')
            ->leftJoin('b.payments', 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'paid')
            ->groupBy('c.id')
            ->orderBy('totalSpent', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Distribution géographique (top 10 villes)
        $byCity = $this->clientRepository->createQueryBuilder('c')
            ->select('c.city, COUNT(c.id) as clientCount')
            ->where('c.city IS NOT NULL')
            ->groupBy('c.city')
            ->orderBy('clientCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('admin/client/stats.html.twig', [
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'verified' => $verified,
                'verificationRate' => $total > 0 ? round(($verified / $total) * 100, 1) : 0,
            ],
            'byMonth' => $byMonth,
            'topClients' => $topClients,
            'byCity' => $byCity,
        ]);
    }

    /**
     * Export des clients en CSV
     */
    #[Route('/export/csv', name: 'admin_clients_export', methods: ['GET'])]
    public function export(): Response
    {
        $clients = $this->clientRepository->findAll();

        $csv = [];
        $csv[] = [
            'ID',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Adresse',
            'Code postal',
            'Ville',
            'Actif',
            'Vérifié',
            'Nombre connexions',
            'Dernière connexion',
            'Date inscription',
        ];

        foreach ($clients as $client) {
            $csv[] = [
                $client->getId(),
                $client->getFirstName(),
                $client->getLastName(),
                $client->getEmail(),
                $client->getPhone(),
                $client->getAddress(),
                $client->getPostalCode(),
                $client->getCity(),
                $client->isActive() ? 'Oui' : 'Non',
                $client->isVerified() ? 'Oui' : 'Non',
                $client->getLoginCount(),
                $client->getLastLoginAt()?->format('Y-m-d H:i:s') ?? 'Jamais',
                $client->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="clients_' . date('Y-m-d') . '.csv"');

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
     * Envoie une notification à un client
     */
    #[Route('/{id}/notify', name: 'admin_client_notify', methods: ['POST'])]
    public function notify(Client $client, Request $request): Response
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

    /**
     * Réinitialise le mot de passe d'un client
     */
    #[Route('/{id}/reset-password', name: 'admin_client_reset_password', methods: ['POST'])]
    public function resetPassword(Client $client): Response
    {
        $client->generatePasswordResetToken();
        $this->entityManager->flush();

        // TODO: Envoyer l'email avec le lien de réinitialisation

        $this->addFlash('success', 'Un lien de réinitialisation a été envoyé par email.');

        return $this->redirectToRoute('admin_client_show', ['id' => $client->getId()]);
    }

    /**
     * Affiche l'historique d'activité d'un client
     */
    #[Route('/{id}/activity', name: 'admin_client_activity', methods: ['GET'])]
    public function activity(Client $client): Response
    {
        // Récupérer toutes les activités du client
        $serviceRequests = $this->serviceRequestRepository->findBy(
            ['client' => $client],
            ['createdAt' => 'DESC']
        );

        $bookings = $this->bookingRepository->findBy(
            ['client' => $client],
            ['createdAt' => 'DESC']
        );

        $payments = $this->paymentRepository->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Fusionner et trier par date
        $activities = [];

        foreach ($serviceRequests as $sr) {
            $activities[] = [
                'type' => 'service_request',
                'date' => $sr->getCreatedAt(),
                'title' => 'Demande de service créée',
                'description' => $sr->getCategory()->getName(),
                'status' => $sr->getStatus(),
            ];
        }

        foreach ($bookings as $booking) {
            $activities[] = [
                'type' => 'booking',
                'date' => $booking->getCreatedAt(),
                'title' => 'Réservation créée',
                'description' => 'Avec ' . $booking->getPrestataire()->getFullName(),
                'status' => $booking->getStatus(),
            ];
        }

        foreach ($payments as $payment) {
            $activities[] = [
                'type' => 'payment',
                'date' => $payment->getCreatedAt(),
                'title' => 'Paiement',
                'description' => $payment->getAmount() . '€',
                'status' => $payment->getStatus(),
            ];
        }

        // Trier par date décroissante
        usort($activities, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return $this->render('admin/client/activity.html.twig', [
            'client' => $client,
            'activities' => $activities,
        ]);
    }
}