<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private string $environment;

    public function __construct(LoggerInterface $logger, string $environment)
    {
        $this->logger = $logger;
        $this->environment = $environment;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Logger l'exception
        $this->logException($exception, $request);

        // Vérifier si c'est une requête API (JSON)
        $isApiRequest = $this->isApiRequest($request);

        if ($isApiRequest) {
            $response = $this->createApiResponse($exception);
            $event->setResponse($response);
        }
    }

    private function createApiResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = $this->getStatusCode($exception);
        $error = $this->getErrorData($exception, $statusCode);

        return new JsonResponse($error, $statusCode);
    }

    private function getStatusCode(\Throwable $exception): int
    {
        // Exceptions HTTP de Symfony
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        // Exceptions spécifiques
        if ($exception instanceof NotFoundHttpException || $exception instanceof EntityNotFoundException) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($exception instanceof UnauthorizedHttpException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($exception instanceof BadRequestHttpException || $exception instanceof ValidationFailedException) {
            return Response::HTTP_BAD_REQUEST;
        }

        if ($exception instanceof UniqueConstraintViolationException) {
            return Response::HTTP_CONFLICT;
        }

        // Par défaut, erreur serveur
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function getErrorData(\Throwable $exception, int $statusCode): array
    {
        $error = [
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $this->getErrorMessage($exception, $statusCode),
                'type' => $this->getErrorType($exception),
            ],
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
        ];

        // Ajouter des détails en mode développement
        if ($this->environment === 'dev') {
            $error['error']['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatTrace($exception->getTrace()),
            ];
        }

        // Ajouter les erreurs de validation si disponibles
        if ($exception instanceof ValidationFailedException) {
            $error['error']['violations'] = $this->getValidationErrors($exception);
        }

        // Ajouter les détails pour les erreurs de contrainte unique
        if ($exception instanceof UniqueConstraintViolationException) {
            $error['error']['message'] = 'Une ressource avec ces données existe déjà';
            $error['error']['field'] = $this->extractDuplicateField($exception);
        }

        return $error;
    }

    private function getErrorMessage(\Throwable $exception, int $statusCode): string
    {
        // Messages personnalisés selon le type d'exception
        if ($exception instanceof NotFoundHttpException || $exception instanceof EntityNotFoundException) {
            return 'Ressource non trouvée';
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return 'Accès refusé';
        }

        if ($exception instanceof UnauthorizedHttpException) {
            return 'Authentification requise';
        }

        if ($exception instanceof ValidationFailedException) {
            return 'Erreur de validation des données';
        }

        if ($exception instanceof UniqueConstraintViolationException) {
            return 'Cette ressource existe déjà';
        }

        // En production, on masque les détails des erreurs serveur
        if ($statusCode >= 500) {
            if ($this->environment === 'prod') {
                return 'Une erreur interne est survenue';
            }
        }

        // Retourner le message de l'exception
        return $exception->getMessage() ?: 'Une erreur est survenue';
    }

    private function getErrorType(\Throwable $exception): string
    {
        if ($exception instanceof NotFoundHttpException || $exception instanceof EntityNotFoundException) {
            return 'NOT_FOUND';
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return 'FORBIDDEN';
        }

        if ($exception instanceof UnauthorizedHttpException) {
            return 'UNAUTHORIZED';
        }

        if ($exception instanceof ValidationFailedException) {
            return 'VALIDATION_ERROR';
        }

        if ($exception instanceof BadRequestHttpException) {
            return 'BAD_REQUEST';
        }

        if ($exception instanceof UniqueConstraintViolationException) {
            return 'DUPLICATE_RESOURCE';
        }

        if ($exception instanceof HttpExceptionInterface) {
            return 'HTTP_ERROR';
        }

        return 'INTERNAL_ERROR';
    }

    private function getValidationErrors(ValidationFailedException $exception): array
    {
        $violations = [];
        
        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'invalidValue' => $this->sanitizeValue($violation->getInvalidValue()),
            ];
        }

        return $violations;
    }

    private function sanitizeValue($value): mixed
    {
        // Ne pas retourner les valeurs sensibles
        if (is_string($value) && (
            str_contains(strtolower($value), 'password') ||
            str_contains(strtolower($value), 'token') ||
            str_contains(strtolower($value), 'secret')
        )) {
            return '[REDACTED]';
        }

        // Limiter la taille des valeurs retournées
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }

        return $value;
    }

    private function extractDuplicateField(UniqueConstraintViolationException $exception): ?string
    {
        // Tenter d'extraire le nom du champ dupliqué depuis le message
        $message = $exception->getMessage();
        
        // Patterns communs pour les contraintes uniques
        $patterns = [
            '/UNIQUE constraint failed: \w+\.(\w+)/',
            '/Duplicate entry .+ for key \'(\w+)\'/',
            '/duplicate key value violates unique constraint "(\w+)"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function formatTrace(array $trace): array
    {
        // Limiter la trace à 5 entrées en mode dev
        return array_slice(array_map(function ($item) {
            return [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
                'class' => $item['class'] ?? null,
            ];
        }, $trace), 0, 5);
    }

    private function logException(\Throwable $exception, $request): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ];

        // Logger selon la sévérité
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            
            if ($statusCode >= 500) {
                $this->logger->error('Server error occurred', $context);
            } elseif ($statusCode >= 400) {
                $this->logger->warning('Client error occurred', $context);
            } else {
                $this->logger->info('HTTP exception occurred', $context);
            }
        } else {
            // Erreur non HTTP = erreur critique
            $this->logger->critical('Unhandled exception occurred', $context);
        }
    }

    private function isApiRequest($request): bool
    {
        // Vérifier si c'est une route API
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return true;
        }

        // Vérifier si le client attend du JSON
        $acceptHeader = $request->headers->get('Accept', '');
        if (str_contains($acceptHeader, 'application/json')) {
            return true;
        }

        // Vérifier si le Content-Type est JSON
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }
}