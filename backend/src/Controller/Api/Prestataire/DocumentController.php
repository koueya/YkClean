<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\Document\Document;
use App\Entity\User\Prestataire;
use App\Enum\DocumentType;
use App\Enum\DocumentStatus;
use App\Repository\Document\DocumentRepository;
use App\Service\Document\DocumentService;
use App\Service\Notification\NotificationService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/documents', name: 'api_prestataire_document_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class DocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private DocumentService $documentService,
        private NotificationService $notificationService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Liste tous les documents du prestataire
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $type = $request->query->get('type');
        $status = $request->query->get('status');

        $qb = $this->documentRepository->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $status);
        }

        $documents = $qb->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $documents,
        ], Response::HTTP_OK, [], ['groups' => ['document:read']]);
    }

    /**
     * Récupère les documents requis et leur statut
     */
    #[Route('/required', name: 'required', methods: ['GET'])]
    public function getRequiredDocuments(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $requiredDocuments = [
            DocumentType::IDENTITY_CARD->value => 'Carte d\'identité',
            DocumentType::KBIS->value => 'Extrait KBIS',
            DocumentType::INSURANCE->value => 'Assurance professionnelle',
            DocumentType::DIPLOMA->value => 'Diplômes (optionnel)',
        ];

        $documents = [];
        foreach ($requiredDocuments as $type => $label) {
            $existingDoc = $this->documentRepository->findOneBy([
                'prestataire' => $prestataire,
                'type' => $type,
            ], ['createdAt' => 'DESC']);

            $documents[] = [
                'type' => $type,
                'label' => $label,
                'required' => $type !== DocumentType::DIPLOMA->value,
                'uploaded' => $existingDoc !== null,
                'status' => $existingDoc?->getStatus(),
                'expiresAt' => $existingDoc?->getExpiresAt()?->format('Y-m-d'),
                'rejectionReason' => $existingDoc?->getRejectionReason(),
                'document' => $existingDoc,
            ];
        }

        $completionRate = $this->calculateCompletionRate($documents);

        return $this->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'completionRate' => $completionRate,
                'isComplete' => $completionRate === 100,
            ],
        ], Response::HTTP_OK, [], ['groups' => ['document:read']]);
    }

    /**
     * Affiche un document spécifique
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Document $document): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if ($document->getPrestataire()->getId() !== $prestataire->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $document,
        ], Response::HTTP_OK, [], ['groups' => ['document:read', 'document:detail']]);
    }

    /**
     * Upload un nouveau document
     */
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        $type = $request->request->get('type');
        $expiresAt = $request->request->get('expires_at');

        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier fourni',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$type) {
            return $this->json([
                'success' => false,
                'message' => 'Le type de document est requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le type est valide
        try {
            $documentType = DocumentType::from($type);
        } catch (\ValueError $e) {
            return $this->json([
                'success' => false,
                'message' => 'Type de document invalide',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du fichier
        $validationResult = $this->documentService->validateFile($file);
        if (!$validationResult['valid']) {
            return $this->json([
                'success' => false,
                'message' => $validationResult['message'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Créer le document
            $document = new Document();
            $document->setPrestataire($prestataire);
            $document->setType($type);
            $document->setStatus(DocumentStatus::PENDING->value);
            $document->setOriginalFilename($file->getClientOriginalName());
            $document->setMimeType($file->getMimeType());
            $document->setFileSize($file->getSize());

            if ($expiresAt) {
                $document->setExpiresAt(new \DateTime($expiresAt));
            }

            // Upload le fichier
            $uploadResult = $this->documentService->uploadDocument($file, $prestataire, $type);
            
            if (!$uploadResult['success']) {
                return $this->json([
                    'success' => false,
                    'message' => $uploadResult['message'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $document->setFilePath($uploadResult['path']);
            $document->setFileUrl($uploadResult['url']);

            // Validation
            $errors = $this->validator->validate($document);
            if (count($errors) > 0) {
                // Supprimer le fichier uploadé en cas d'erreur de validation
                $this->documentService->deleteFile($uploadResult['path']);

                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Document uploaded', [
                'prestataire_id' => $prestataire->getId(),
                'document_id' => $document->getId(),
                'type' => $type,
            ]);

            // Notifier l'admin pour vérification
            $this->notificationService->notifyNewDocumentUploaded($document);

            return $this->json([
                'success' => true,
                'message' => 'Document uploadé avec succès',
                'data' => $document,
            ], Response::HTTP_CREATED, [], ['groups' => ['document:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to upload document', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remplace un document existant
     */
    #[Route('/{id}/replace', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Document $document): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if ($document->getPrestataire()->getId() !== $prestataire->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        $expiresAt = $request->request->get('expires_at');

        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier fourni',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du fichier
        $validationResult = $this->documentService->validateFile($file);
        if (!$validationResult['valid']) {
            return $this->json([
                'success' => false,
                'message' => $validationResult['message'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Sauvegarder l'ancien chemin pour suppression ultérieure
            $oldFilePath = $document->getFilePath();

            // Upload le nouveau fichier
            $uploadResult = $this->documentService->uploadDocument(
                $file,
                $prestataire,
                $document->getType()
            );

            if (!$uploadResult['success']) {
                return $this->json([
                    'success' => false,
                    'message' => $uploadResult['message'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Mettre à jour le document
            $document->setFilePath($uploadResult['path']);
            $document->setFileUrl($uploadResult['url']);
            $document->setOriginalFilename($file->getClientOriginalName());
            $document->setMimeType($file->getMimeType());
            $document->setFileSize($file->getSize());
            $document->setStatus(DocumentStatus::PENDING->value);
            $document->setRejectionReason(null);
            $document->setVerifiedAt(null);
            $document->setVerifiedBy(null);

            if ($expiresAt) {
                $document->setExpiresAt(new \DateTime($expiresAt));
            }

            $this->entityManager->flush();

            // Supprimer l'ancien fichier
            if ($oldFilePath) {
                $this->documentService->deleteFile($oldFilePath);
            }

            $this->logger->info('Document replaced', [
                'prestataire_id' => $prestataire->getId(),
                'document_id' => $document->getId(),
            ]);

            // Notifier l'admin
            $this->notificationService->notifyDocumentReplaced($document);

            return $this->json([
                'success' => true,
                'message' => 'Document remplacé avec succès',
                'data' => $document,
            ], Response::HTTP_OK, [], ['groups' => ['document:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to replace document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du remplacement du document',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un document
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if ($document->getPrestataire()->getId() !== $prestataire->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], Response::HTTP_FORBIDDEN);
        }

        // Ne pas permettre la suppression de documents validés
        if ($document->getStatus() === DocumentStatus::APPROVED->value) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer un document validé',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $filePath = $document->getFilePath();
            
            $this->entityManager->remove($document);
            $this->entityManager->flush();

            // Supprimer le fichier physique
            if ($filePath) {
                $this->documentService->deleteFile($filePath);
            }

            $this->logger->info('Document deleted', [
                'prestataire_id' => $prestataire->getId(),
                'document_id' => $document->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Document supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du document',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Télécharge un document
     */
    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if ($document->getPrestataire()->getId() !== $prestataire->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            return $this->documentService->downloadDocument($document);
        } catch (\Exception $e) {
            $this->logger->error('Failed to download document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du document',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère l'URL de prévisualisation d'un document
     */
    #[Route('/{id}/preview', name: 'preview', methods: ['GET'])]
    public function preview(Document $document): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if ($document->getPrestataire()->getId() !== $prestataire->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $previewUrl = $this->documentService->getPreviewUrl($document);

            return $this->json([
                'success' => true,
                'data' => [
                    'url' => $previewUrl,
                    'type' => $document->getMimeType(),
                    'canPreview' => $this->documentService->canPreview($document),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get preview URL', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de l\'aperçu',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifie les documents expirés ou à expirer prochainement
     */
    #[Route('/check-expiration', name: 'check_expiration', methods: ['GET'])]
    public function checkExpiration(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $now = new \DateTime();
        $in30Days = (clone $now)->modify('+30 days');

        // Documents expirés
        $expiredDocuments = $this->documentRepository->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt < :now')
            ->andWhere('d.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('status', DocumentStatus::APPROVED->value)
            ->getQuery()
            ->getResult();

        // Documents expirant bientôt
        $expiringDocuments = $this->documentRepository->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt BETWEEN :now AND :in30Days')
            ->andWhere('d.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('in30Days', $in30Days)
            ->setParameter('status', DocumentStatus::APPROVED->value)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'expired' => $expiredDocuments,
                'expiringSoon' => $expiringDocuments,
                'hasExpired' => count($expiredDocuments) > 0,
                'hasExpiringSoon' => count($expiringDocuments) > 0,
            ],
        ], Response::HTTP_OK, [], ['groups' => ['document:read']]);
    }

    /**
     * Obtient le statut de vérification du compte
     */
    #[Route('/verification-status', name: 'verification_status', methods: ['GET'])]
    public function verificationStatus(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $requiredTypes = [
            DocumentType::IDENTITY_CARD->value,
            DocumentType::KBIS->value,
            DocumentType::INSURANCE->value,
        ];

        $documentsStatus = [];
        $allApproved = true;
        $hasPending = false;
        $hasRejected = false;

        foreach ($requiredTypes as $type) {
            $document = $this->documentRepository->findOneBy([
                'prestataire' => $prestataire,
                'type' => $type,
            ], ['createdAt' => 'DESC']);

            if (!$document) {
                $allApproved = false;
                $documentsStatus[$type] = [
                    'uploaded' => false,
                    'status' => null,
                ];
            } else {
                $documentsStatus[$type] = [
                    'uploaded' => true,
                    'status' => $document->getStatus(),
                    'expiresAt' => $document->getExpiresAt()?->format('Y-m-d'),
                ];

                if ($document->getStatus() !== DocumentStatus::APPROVED->value) {
                    $allApproved = false;
                }

                if ($document->getStatus() === DocumentStatus::PENDING->value) {
                    $hasPending = true;
                }

                if ($document->getStatus() === DocumentStatus::REJECTED->value) {
                    $hasRejected = true;
                }
            }
        }

        $verificationStatus = 'incomplete';
        if ($allApproved) {
            $verificationStatus = 'approved';
        } elseif ($hasPending) {
            $verificationStatus = 'pending';
        } elseif ($hasRejected) {
            $verificationStatus = 'rejected';
        }

        return $this->json([
            'success' => true,
            'data' => [
                'status' => $verificationStatus,
                'isApproved' => $prestataire->isApproved(),
                'documents' => $documentsStatus,
                'message' => $this->getVerificationMessage($verificationStatus),
            ],
        ]);
    }

    /**
     * Obtient les types de documents acceptés
     */
    #[Route('/types', name: 'types', methods: ['GET'])]
    public function getDocumentTypes(): JsonResponse
    {
        $types = [];
        
        foreach (DocumentType::cases() as $type) {
            $types[] = [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'required' => $type->isRequired(),
                'maxSize' => $type->maxSize(),
                'allowedExtensions' => $type->allowedExtensions(),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Calcule le taux de complétion des documents
     */
    private function calculateCompletionRate(array $documents): int
    {
        $required = array_filter($documents, fn($doc) => $doc['required']);
        $uploaded = array_filter($required, fn($doc) => $doc['uploaded']);

        if (count($required) === 0) {
            return 100;
        }

        return (int) round((count($uploaded) / count($required)) * 100);
    }

    /**
     * Retourne un message selon le statut de vérification
     */
    private function getVerificationMessage(string $status): string
    {
        return match($status) {
            'approved' => 'Votre compte est vérifié et actif',
            'pending' => 'Vos documents sont en cours de vérification',
            'rejected' => 'Certains documents ont été refusés, veuillez les remplacer',
            'incomplete' => 'Veuillez télécharger tous les documents requis',
            default => 'Statut inconnu',
        };
    }
}