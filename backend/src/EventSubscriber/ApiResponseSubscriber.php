<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onKernelView', 30],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Transforme les réponses des controllers en JsonResponse formatée
     */
    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        // Ne traiter que les routes API
        if (!$this->isApiRequest($request)) {
            return;
        }

        // Si le controller a déjà retourné une Response, ne rien faire
        if ($result instanceof Response) {
            return;
        }

        // Déterminer le code de status selon la méthode HTTP
        $statusCode = $this->getStatusCodeFromMethod($request->getMethod());

        // Si le résultat est null (par exemple pour une suppression)
        if ($result === null) {
            $response = $this->createSuccessResponse(null, $statusCode, 'Opération effectuée avec succès');
        }
        // Si c'est déjà un tableau formaté avec 'success', 'data', etc.
        elseif (is_array($result) && isset($result['success'])) {
            $response = new JsonResponse($result, $statusCode);
        }
        // Sinon, encapsuler dans un format standardisé
        else {
            $response = $this->createSuccessResponse($result, $statusCode);
        }

        $event->setResponse($response);
    }

    /**
     * Ajoute des headers personnalisés aux réponses API
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Ne traiter que les routes API
        if (!$this->isApiRequest($request)) {
            return;
        }

        // Ajouter des headers CORS si nécessaire
        $this->addCorsHeaders($response, $request);

        // Ajouter des headers de sécurité
        $this->addSecurityHeaders($response);

        // Ajouter des headers de cache
        $this->addCacheHeaders($response, $request);

        // Ajouter header de version API
        $response->headers->set('X-API-Version', '1.0');

        // Ajouter timestamp de réponse
        $response->headers->set('X-Response-Time', (string) round(microtime(true) - $request->server->get('REQUEST_TIME_FLOAT'), 3));
    }

    /**
     * Crée une réponse JSON standardisée de succès
     */
    private function createSuccessResponse($data, int $statusCode = 200, ?string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        // Ajouter des métadonnées de pagination si présentes
        if (is_array($data) && isset($data['items']) && isset($data['pagination'])) {
            $response = [
                'success' => true,
                'data' => $data['items'],
                'pagination' => $data['pagination'],
                'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
            ];
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Détermine le code de status HTTP selon la méthode
     */
    private function getStatusCodeFromMethod(string $method): int
    {
        return match ($method) {
            'POST' => Response::HTTP_CREATED,           // 201
            'DELETE' => Response::HTTP_NO_CONTENT,      // 204
            'PUT', 'PATCH' => Response::HTTP_OK,        // 200
            default => Response::HTTP_OK,               // 200
        };
    }

    /**
     * Vérifie si c'est une requête API
     */
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

    /**
     * Ajoute les headers CORS
     */
    private function addCorsHeaders(Response $response, $request): void
    {
        // Autoriser les origines (à adapter selon votre configuration)
        $allowedOrigins = [
            'http://localhost:3000',      // React Native Metro
            'http://localhost:19006',     // Expo Web
            'http://localhost:8081',      // React Native Android
            'capacitor://localhost',      // Capacitor iOS
            'http://localhost',           // Capacitor Android
        ];

        $origin = $request->headers->get('Origin');

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');

        // Exposer certains headers au client
        $response->headers->set('Access-Control-Expose-Headers', 'X-Total-Count, X-Page, X-Per-Page, X-API-Version, X-Response-Time');
    }

    /**
     * Ajoute des headers de sécurité
     */
    private function addSecurityHeaders(Response $response): void
    {
        // Empêcher le navigateur de deviner le type MIME
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Protection XSS
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Empêcher l'affichage dans une iframe (protection clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Content Security Policy (adapté pour API)
        $response->headers->set('Content-Security-Policy', "default-src 'none'");

        // Forcer HTTPS (à activer en production)
        // $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /**
     * Ajoute des headers de cache
     */
    private function addCacheHeaders(Response $response, $request): void
    {
        $method = $request->getMethod();

        // Pas de cache pour les méthodes de modification
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            return;
        }

        // Cache court pour les GET (5 minutes)
        if ($method === 'GET') {
            // Vérifier si l'endpoint est cacheable
            $path = $request->getPathInfo();
            
            // Endpoints qui peuvent être cachés
            $cacheablePatterns = [
                '/api/service-categories',
                '/api/service-types',
            ];

            $isCacheable = false;
            foreach ($cacheablePatterns as $pattern) {
                if (str_starts_with($path, $pattern)) {
                    $isCacheable = true;
                    break;
                }
            }

            if ($isCacheable) {
                $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
                $response->headers->set('ETag', md5($response->getContent()));
            } else {
                // Pas de cache pour les données dynamiques
                $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            }
        }
    }

    /**
     * Crée une réponse de succès avec pagination
     */
    public static function createPaginatedResponse(
        array $items,
        int $total,
        int $page,
        int $limit
    ): array {
        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'count' => count($items),
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
                'hasMore' => ($page * $limit) < $total,
            ],
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
        ];
    }

    /**
     * Crée une réponse de succès simple
     */
    public static function createSimpleSuccessResponse(?string $message = null, array $additionalData = []): array
    {
        $response = [
            'success' => true,
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if (!empty($additionalData)) {
            $response['data'] = $additionalData;
        }

        return $response;
    }

    /**
     * Crée une réponse d'erreur (à utiliser dans les controllers)
     */
    public static function createErrorResponse(
        string $message,
        int $statusCode = 400,
        ?string $errorType = null,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $message,
                'type' => $errorType ?? 'ERROR',
            ],
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
        ];

        if (!empty($errors)) {
            $response['error']['details'] = $errors;
        }

        return new JsonResponse($response, $statusCode);
    }
}