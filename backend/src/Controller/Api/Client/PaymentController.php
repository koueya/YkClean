<?php

namespace App\Controller\Api\Client;

use App\Entity\Payment\Payment;
use App\Entity\Payment\Invoice;
use App\Entity\User\Client;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;  // CORRECTION : Ajout
use App\Repository\Payment\InvoiceRepository;   // CORRECTION : Bon namespace (Payment, pas Quote)
use App\Service\Payment\PaymentService;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/client/payments')]
#[IsGranted('ROLE_CLIENT')]
class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,        // Ajouté
        private InvoiceRepository $invoiceRepository,        // Namespace corrigé
        private BookingRepository $bookingRepository,
        private PaymentService $paymentService,
        private NotificationService $notificationService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger                      // Ajouté pour logs
    ) {
    }

    /**
     * Get all payments for the authenticated client
     */
    #[Route('', name: 'api_client_payments_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        // Filters
        $status = $request->query->get('status');
        $method = $request->query->get('method');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));

        // Build query
        $queryBuilder = $this->paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $queryBuilder->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        if ($method) {
            $queryBuilder->andWhere('p.method = :method')
                ->setParameter('method', $method);
        }

        if ($startDate) {
            $queryBuilder->andWhere('p.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('p.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Apply sorting and pagination
        $payments = $queryBuilder
            ->orderBy('p.' . $sortBy, $sortOrder)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Payment $payment) {
            return $this->formatPayment($payment);
        }, $payments);

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get a specific payment by ID
     */
    #[Route('/{id}', name: 'api_client_payment_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return $this->json([
                'error' => 'Payment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership through booking
        $booking = $payment->getBooking();
        if (!$booking || $booking->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatPayment($payment, true)
        ]);
    }

    /**
     * Create a payment intent for a booking
     */
    #[Route('/create-intent', name: 'api_client_payment_create_intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['booking_id'])) {
            return $this->json([
                'error' => 'Booking ID is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->bookingRepository->find($data['booking_id']);

        if (!$booking) {
            return $this->json([
                'error' => 'Booking not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($booking->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $paymentIntent = $this->paymentService->createPaymentIntent($booking, $data);

            return $this->json([
                'success' => true,
                'data' => [
                    'clientSecret' => $paymentIntent['client_secret'],
                    'paymentIntentId' => $paymentIntent['id'],
                    'amount' => $paymentIntent['amount'],
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create payment intent', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to create payment intent',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Confirm a payment
     */
    #[Route('/{id}/confirm', name: 'api_client_payment_confirm', methods: ['POST'])]
    public function confirmPayment(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return $this->json([
                'error' => 'Payment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        $booking = $payment->getBooking();
        if (!$booking || $booking->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->paymentService->confirmPayment($payment);

            return $this->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => $this->formatPayment($payment)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to confirm payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to confirm payment',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request a refund
     */
    #[Route('/{id}/refund', name: 'api_client_payment_refund', methods: ['POST'])]
    public function requestRefund(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return $this->json([
                'error' => 'Payment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        $booking = $payment->getBooking();
        if (!$booking || $booking->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;
        $amount = $data['amount'] ?? null;

        try {
            $this->paymentService->requestRefund($payment, $reason, $amount);

            return $this->json([
                'success' => true,
                'message' => 'Refund requested successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to request refund', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to request refund',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get payment methods for the client
     */
    #[Route('/methods', name: 'api_client_payment_methods', methods: ['GET'])]
    public function getPaymentMethods(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $methods = $this->paymentService->getClientPaymentMethods($client);

            return $this->json([
                'success' => true,
                'data' => $methods
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve payment methods',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add a payment method
     */
    #[Route('/methods', name: 'api_client_payment_method_add', methods: ['POST'])]
    public function addPaymentMethod(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $method = $this->paymentService->addPaymentMethod($client, $data);

            return $this->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => $method
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to add payment method',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove a payment method
     */
    #[Route('/methods/{methodId}', name: 'api_client_payment_method_remove', methods: ['DELETE'])]
    public function removePaymentMethod(string $methodId): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->paymentService->removePaymentMethod($client, $methodId);

            return $this->json([
                'success' => true,
                'message' => 'Payment method removed successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to remove payment method',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Set default payment method
     */
    #[Route('/methods/{methodId}/default', name: 'api_client_payment_method_set_default', methods: ['PUT'])]
    public function setDefaultPaymentMethod(string $methodId): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->paymentService->setDefaultPaymentMethod($client, $methodId);

            return $this->json([
                'success' => true,
                'message' => 'Default payment method updated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to set default payment method',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get all invoices for the client
     */
    #[Route('/invoices', name: 'api_client_invoices_list', methods: ['GET'])]
    public function listInvoices(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $status = $request->query->get('status');

        $queryBuilder = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $queryBuilder->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        $total = count($queryBuilder->getQuery()->getResult());

        $invoices = $queryBuilder
            ->orderBy('i.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Invoice $invoice) {
            return $this->formatInvoice($invoice);
        }, $invoices);

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get a specific invoice
     */
    #[Route('/invoices/{id}', name: 'api_client_invoice_get', methods: ['GET'])]
    public function getInvoice(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $invoice = $this->invoiceRepository->find($id);

        if (!$invoice) {
            return $this->json([
                'error' => 'Invoice not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($invoice->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatInvoice($invoice, true)
        ]);
    }

    /**
     * Download invoice PDF
     */
    #[Route('/invoices/{id}/download', name: 'api_client_invoice_download', methods: ['GET'])]
    public function downloadInvoice(int $id): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $invoice = $this->invoiceRepository->find($id);

        if (!$invoice) {
            return $this->json([
                'error' => 'Invoice not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($invoice->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $pdfContent = $this->paymentService->generateInvoicePdf($invoice);
            $filename = 'invoice-' . $invoice->getInvoiceNumber() . '.pdf';

            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate invoice PDF', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to generate invoice PDF'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Format payment for JSON response
     */
    private function formatPayment(Payment $payment, bool $detailed = false): array
    {
        $booking = $payment->getBooking();

        $data = [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'status' => $payment->getStatus(),
            'method' => $payment->getMethod(),
            'createdAt' => $payment->getCreatedAt()?->format('c'),
        ];

        if ($detailed) {
            $data['stripePaymentIntentId'] = $payment->getStripePaymentIntentId();
            $data['transactionId'] = $payment->getTransactionId();
            $data['paidAt'] = $payment->getPaidAt()?->format('c');
            $data['refundedAt'] = $payment->getRefundedAt()?->format('c');
            $data['refundedAmount'] = $payment->getRefundedAmount();
            $data['failureReason'] = $payment->getFailureReason();
            $data['booking'] = [
                'id' => $booking->getId(),
                'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                'prestataire' => [
                    'id' => $booking->getPrestataire()->getId(),
                    'firstName' => $booking->getPrestataire()->getFirstName(),
                    'lastName' => $booking->getPrestataire()->getLastName(),
                ],
            ];
        }

        return $data;
    }

    /**
     * Format invoice for JSON response
     */
    private function formatInvoice(Invoice $invoice, bool $detailed = false): array
    {
        $data = [
            'id' => $invoice->getId(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'amount' => $invoice->getAmount(),
            'totalAmount' => $invoice->getTotalAmount(),
            'status' => $invoice->getStatus(),
            'dueDate' => $invoice->getDueDate()?->format('c'),
            'createdAt' => $invoice->getCreatedAt()?->format('c'),
        ];

        if ($detailed) {
            $booking = $invoice->getBooking();
            $payment = $invoice->getPayment();

            $data['taxAmount'] = $invoice->getTaxAmount();
            $data['paidAt'] = $invoice->getPaidAt()?->format('c');
            $data['lineItems'] = $invoice->getLineItems();
            $data['notes'] = $invoice->getNotes();
            $data['booking'] = [
                'id' => $booking->getId(),
                'scheduledDate' => $booking->getScheduledDate()?->format('c'),
            ];
            $data['payment'] = $payment ? [
                'id' => $payment->getId(),
                'method' => $payment->getMethod(),
                'status' => $payment->getStatus(),
            ] : null;
        }

        return $data;
    }
}