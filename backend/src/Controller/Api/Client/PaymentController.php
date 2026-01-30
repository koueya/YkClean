<?php

namespace App\Controller\Api\Client;

use App\Entity\Payment\Payment;
use App\Entity\User\Client;
use App\Repository\PaymentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\BookingRepository;
use App\Service\PaymentService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/payments')]
#[IsGranted('ROLE_CLIENT')]
class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private InvoiceRepository $invoiceRepository,
        private BookingRepository $bookingRepository,
        private PaymentService $paymentService,
        private NotificationService $notificationService,
        private ValidatorInterface $validator
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
            try {
                $start = new \DateTimeImmutable($startDate);
                $queryBuilder->andWhere('p.createdAt >= :startDate')
                    ->setParameter('startDate', $start);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid startDate format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($endDate) {
            try {
                $end = new \DateTimeImmutable($endDate);
                $queryBuilder->andWhere('p.createdAt <= :endDate')
                    ->setParameter('endDate', $end);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid endDate format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Sorting
        $allowedSortFields = ['createdAt', 'amount', 'status'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('p.' . $sortBy, $sortOrder === 'ASC' ? 'ASC' : 'DESC');
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Get paginated results
        $payments = $queryBuilder
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
        if ($payment->getBooking()->getClient()->getId() !== $client->getId()) {
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
     * Create a payment for a booking
     */
    #[Route('', name: 'api_client_payment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (!isset($data['bookingId']) || !isset($data['method'])) {
            return $this->json([
                'error' => 'Booking ID and payment method are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->bookingRepository->find($data['bookingId']);

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

        // Check if booking already has a payment
        if ($booking->getPayment() && $booking->getPayment()->getStatus() === 'completed') {
            return $this->json([
                'error' => 'Booking already has a completed payment'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Process payment through payment service (Stripe, etc.)
            $payment = $this->paymentService->processPayment(
                $booking,
                $data['method'],
                $data['paymentToken'] ?? null,
                $data['saveCard'] ?? false
            );

            return $this->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => $this->formatPayment($payment, true)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get payment methods
     */
    #[Route('/methods', name: 'api_client_payment_methods_list', methods: ['GET'])]
    public function listPaymentMethods(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $paymentMethods = $this->paymentService->getClientPaymentMethods($client);

            return $this->json([
                'success' => true,
                'data' => $paymentMethods
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve payment methods',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add a new payment method
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

        if (!isset($data['paymentToken']) || !isset($data['type'])) {
            return $this->json([
                'error' => 'Payment token and type are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentMethod = $this->paymentService->addPaymentMethod(
                $client,
                $data['paymentToken'],
                $data['type'],
                $data['setAsDefault'] ?? false
            );

            return $this->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => $paymentMethod
            ], Response::HTTP_CREATED);

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
    #[Route('/methods/{methodId}/set-default', name: 'api_client_payment_method_set_default', methods: ['POST'])]
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

        $queryBuilder->orderBy('i.createdAt', 'DESC');

        $total = count($queryBuilder->getQuery()->getResult());

        $invoices = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function ($invoice) {
            return [
                'id' => $invoice->getId(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'amount' => $invoice->getAmount(),
                'status' => $invoice->getStatus(),
                'dueDate' => $invoice->getDueDate()?->format('c'),
                'paidAt' => $invoice->getPaidAt()?->format('c'),
                'createdAt' => $invoice->getCreatedAt()?->format('c'),
                'downloadUrl' => $this->generateUrl('api_client_invoice_download', ['id' => $invoice->getId()]),
            ];
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

        $booking = $invoice->getBooking();
        $payment = $invoice->getPayment();

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $invoice->getId(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'amount' => $invoice->getAmount(),
                'taxAmount' => $invoice->getTaxAmount(),
                'totalAmount' => $invoice->getTotalAmount(),
                'status' => $invoice->getStatus(),
                'dueDate' => $invoice->getDueDate()?->format('c'),
                'paidAt' => $invoice->getPaidAt()?->format('c'),
                'createdAt' => $invoice->getCreatedAt()?->format('c'),
                'booking' => [
                    'id' => $booking->getId(),
                    'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                    'serviceCategory' => $booking->getServiceCategory(),
                ],
                'payment' => $payment ? [
                    'id' => $payment->getId(),
                    'method' => $payment->getMethod(),
                    'status' => $payment->getStatus(),
                ] : null,
                'lineItems' => $invoice->getLineItems(),
                'downloadUrl' => $this->generateUrl('api_client_invoice_download', ['id' => $invoice->getId()]),
            ]
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
            return $this->json([
                'error' => 'Failed to generate invoice PDF',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request a refund for a payment
     */
    #[Route('/{id}/refund', name: 'api_client_payment_refund_request', methods: ['POST'])]
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
        if ($payment->getBooking()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if payment can be refunded
        if ($payment->getStatus() !== 'completed') {
            return $this->json([
                'error' => 'Only completed payments can be refunded'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($payment->getRefundStatus() !== null) {
            return $this->json([
                'error' => 'Refund already requested or processed'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? 'Client requested refund';
        $amount = $data['amount'] ?? $payment->getAmount();

        // Validate refund amount
        if ($amount > $payment->getAmount()) {
            return $this->json([
                'error' => 'Refund amount cannot exceed payment amount'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->paymentService->requestRefund($payment, $amount, $reason);

            return $this->json([
                'success' => true,
                'message' => 'Refund request submitted successfully. It will be processed within 5-7 business days.',
                'data' => [
                    'refundAmount' => $amount,
                    'refundReason' => $reason,
                    'refundStatus' => 'pending'
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to request refund',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get payment statistics
     */
    #[Route('/stats', name: 'api_client_payments_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $allPayments = $this->paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getResult();

        $stats = [
            'totalPayments' => count($allPayments),
            'totalSpent' => 0,
            'completedPayments' => 0,
            'pendingPayments' => 0,
            'failedPayments' => 0,
            'totalRefunded' => 0,
            'averagePaymentAmount' => 0,
            'paymentsByMethod' => [
                'card' => 0,
                'bank_transfer' => 0,
                'cash' => 0,
            ],
        ];

        foreach ($allPayments as $payment) {
            switch ($payment->getStatus()) {
                case 'completed':
                    $stats['completedPayments']++;
                    $stats['totalSpent'] += $payment->getAmount();
                    
                    $method = $payment->getMethod();
                    if (isset($stats['paymentsByMethod'][$method])) {
                        $stats['paymentsByMethod'][$method]++;
                    }
                    break;
                    
                case 'pending':
                    $stats['pendingPayments']++;
                    break;
                    
                case 'failed':
                    $stats['failedPayments']++;
                    break;
            }

            if ($payment->getRefundAmount() > 0) {
                $stats['totalRefunded'] += $payment->getRefundAmount();
            }
        }

        if ($stats['completedPayments'] > 0) {
            $stats['averagePaymentAmount'] = round($stats['totalSpent'] / $stats['completedPayments'], 2);
        }

        // Get spending by month (last 12 months)
        $monthlySpending = $this->paymentRepository->createQueryBuilder('p')
            ->select('YEAR(p.paidAt) as year, MONTH(p.paidAt) as month, SUM(p.amount) as total')
            ->leftJoin('p.booking', 'b')
            ->where('b.client = :client')
            ->andWhere('p.status = :status')
            ->andWhere('p.paidAt >= :startDate')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->setParameter('startDate', new \DateTimeImmutable('-12 months'))
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->getQuery()
            ->getResult();

        $stats['monthlySpending'] = $monthlySpending;

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get payment receipt
     */
    #[Route('/{id}/receipt', name: 'api_client_payment_receipt', methods: ['GET'])]
    public function getReceipt(int $id): Response
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
        if ($payment->getBooking()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Only completed payments have receipts
        if ($payment->getStatus() !== 'completed') {
            return $this->json([
                'error' => 'Receipt only available for completed payments'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $receiptContent = $this->paymentService->generateReceiptPdf($payment);
            $filename = 'receipt-' . $payment->getTransactionId() . '.pdf';

            return new Response($receiptContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate receipt',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retry a failed payment
     */
    #[Route('/{id}/retry', name: 'api_client_payment_retry', methods: ['POST'])]
    public function retryPayment(int $id, Request $request): JsonResponse
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
        if ($payment->getBooking()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Can only retry failed payments
        if ($payment->getStatus() !== 'failed') {
            return $this->json([
                'error' => 'Can only retry failed payments'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $newPayment = $this->paymentService->retryPayment(
                $payment,
                $data['paymentToken'] ?? null
            );

            return $this->json([
                'success' => true,
                'message' => 'Payment retry successful',
                'data' => $this->formatPayment($newPayment, true)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Payment retry failed',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Format payment data for response
     */
    private function formatPayment(Payment $payment, bool $detailed = false): array
    {
        $booking = $payment->getBooking();

        $data = [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'method' => $payment->getMethod(),
            'status' => $payment->getStatus(),
            'transactionId' => $payment->getTransactionId(),
            'createdAt' => $payment->getCreatedAt()?->format('c'),
            'paidAt' => $payment->getPaidAt()?->format('c'),
            'booking' => [
                'id' => $booking->getId(),
                'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                'serviceCategory' => $booking->getServiceCategory(),
            ],
        ];

        if ($detailed) {
            $data['description'] = $payment->getDescription();
            $data['currency'] = $payment->getCurrency();
            $data['platformFee'] = $payment->getPlatformFee();
            $data['prestataireAmount'] = $payment->getPrestataireAmount();
            
            if ($payment->getStatus() === 'failed') {
                $data['failureReason'] = $payment->getFailureReason();
                $data['failureCode'] = $payment->getFailureCode();
            }

            if ($payment->getRefundAmount() > 0) {
                $data['refund'] = [
                    'amount' => $payment->getRefundAmount(),
                    'status' => $payment->getRefundStatus(),
                    'reason' => $payment->getRefundReason(),
                    'processedAt' => $payment->getRefundProcessedAt()?->format('c'),
                ];
            }

            $data['receiptUrl'] = $this->generateUrl('api_client_payment_receipt', ['id' => $payment->getId()]);
        }

        return $data;
    }
}