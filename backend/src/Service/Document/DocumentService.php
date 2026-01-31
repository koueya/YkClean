<?php

namespace App\Service\Document;

use App\Entity\Document\Document;
use App\Entity\User\Prestataire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des documents
 * 
 * Gère l'upload, la validation et la suppression des documents des prestataires
 * (KBIS, assurance, carte d'identité, diplômes, etc.)
 */
class DocumentService
{
    // Types MIME autorisés
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Extensions autorisées
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'
    ];

    // Taille maximale par type de document (en octets)
    private const MAX_FILE_SIZES = [
        'identity_card' => 5 * 1024 * 1024,      // 5 MB
        'kbis' => 5 * 1024 * 1024,               // 5 MB
        'insurance' => 10 * 1024 * 1024,         // 10 MB
        'diploma' => 5 * 1024 * 1024,            // 5 MB
        'criminal_record' => 5 * 1024 * 1024,    // 5 MB
        'tax_certificate' => 5 * 1024 * 1024,    // 5 MB
        'bank_details' => 5 * 1024 * 1024,       // 5 MB
        'other' => 10 * 1024 * 1024,             // 10 MB
        'default' => 10 * 1024 * 1024,           // 10 MB
    ];

    private string $uploadDirectory;
    private string $publicDirectory;

    public function __construct(
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
        // Récupérer les chemins depuis les paramètres
        $this->uploadDirectory = $this->params->get('kernel.project_dir') . '/var/uploads/documents';
        $this->publicDirectory = '/uploads/documents';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    /**
     * Valide un fichier uploadé
     * 
     * @param UploadedFile $file
     * @param string|null $documentType
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateFile(UploadedFile $file, ?string $documentType = null): array
    {
        // Vérifier si le fichier est valide
        if (!$file->isValid()) {
            return [
                'valid' => false,
                'message' => 'Le fichier est invalide ou corrompu.',
            ];
        }

        // Vérifier le type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Type de fichier non autorisé (%s). Types acceptés : PDF, JPG, PNG, WEBP, DOC, DOCX',
                    $mimeType
                ),
            ];
        }

        // Vérifier l'extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Extension de fichier non autorisée (%s). Extensions acceptées : %s',
                    $extension,
                    implode(', ', self::ALLOWED_EXTENSIONS)
                ),
            ];
        }

        // Vérifier la taille
        $maxSize = self::MAX_FILE_SIZES[$documentType] ?? self::MAX_FILE_SIZES['default'];
        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Fichier trop volumineux (%s). Taille maximale : %s',
                    $this->formatFileSize($file->getSize()),
                    $this->formatFileSize($maxSize)
                ),
            ];
        }

        // Vérifier le nom du fichier
        $filename = $file->getClientOriginalName();
        if (strlen($filename) > 255) {
            return [
                'valid' => false,
                'message' => 'Le nom du fichier est trop long (maximum 255 caractères).',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Fichier valide',
        ];
    }

    /**
     * Upload un document
     * 
     * @param UploadedFile $file
     * @param Prestataire $prestataire
     * @param string $documentType
     * @return array ['success' => bool, 'path' => string, 'url' => string, 'message' => string]
     */
    public function uploadDocument(
        UploadedFile $file,
        Prestataire $prestataire,
        string $documentType
    ): array {
        try {
            // Valider le fichier
            $validation = $this->validateFile($file, $documentType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            // Créer le sous-dossier pour le prestataire
            $prestataireDirectory = $this->uploadDirectory . '/prestataire_' . $prestataire->getId();
            if (!is_dir($prestataireDirectory)) {
                mkdir($prestataireDirectory, 0755, true);
            }

            // Générer un nom de fichier unique et sécurisé
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
            $newFilename = sprintf(
                '%s-%s-%s.%s',
                $documentType,
                $safeFilename,
                uniqid(),
                $extension
            );

            // Déplacer le fichier
            $file->move($prestataireDirectory, $newFilename);

            // Chemins relatifs pour stockage en base
            $relativePath = '/prestataire_' . $prestataire->getId() . '/' . $newFilename;
            $publicUrl = $this->publicDirectory . $relativePath;

            $this->logger->info('Document uploaded successfully', [
                'prestataire_id' => $prestataire->getId(),
                'document_type' => $documentType,
                'filename' => $newFilename,
            ]);

            return [
                'success' => true,
                'path' => $relativePath,
                'url' => $publicUrl,
                'filename' => $newFilename,
                'message' => 'Document uploadé avec succès',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to upload document', [
                'prestataire_id' => $prestataire->getId(),
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Supprime un fichier physique
     * 
     * @param string $filePath Chemin relatif du fichier
     * @return bool
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            $fullPath = $this->uploadDirectory . $filePath;

            if (file_exists($fullPath)) {
                unlink($fullPath);
                
                $this->logger->info('File deleted', [
                    'path' => $filePath,
                ]);

                return true;
            }

            $this->logger->warning('File not found for deletion', [
                'path' => $filePath,
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete file', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Supprime un document (entité et fichier)
     * 
     * @param Document $document
     * @return bool
     */
    public function deleteDocument(Document $document): bool
    {
        $filePath = $document->getFilePath();
        
        if ($filePath) {
            return $this->deleteFile($filePath);
        }

        return true;
    }

    /**
     * Vérifie si un fichier existe
     * 
     * @param string $filePath
     * @return bool
     */
    public function fileExists(string $filePath): bool
    {
        $fullPath = $this->uploadDirectory . $filePath;
        return file_exists($fullPath);
    }

    /**
     * Obtient le chemin complet d'un fichier
     * 
     * @param string $relativePath
     * @return string
     */
    public function getFullPath(string $relativePath): string
    {
        return $this->uploadDirectory . $relativePath;
    }

    /**
     * Obtient l'URL publique d'un fichier
     * 
     * @param string $relativePath
     * @return string
     */
    public function getPublicUrl(string $relativePath): string
    {
        return $this->publicDirectory . $relativePath;
    }

    /**
     * Formate une taille de fichier en unité lisible
     * 
     * @param int $bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $size = $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Obtient la taille maximale autorisée pour un type de document
     * 
     * @param string $documentType
     * @return int Taille en octets
     */
    public function getMaxFileSize(string $documentType): int
    {
        return self::MAX_FILE_SIZES[$documentType] ?? self::MAX_FILE_SIZES['default'];
    }

    /**
     * Obtient la taille maximale autorisée formatée
     * 
     * @param string $documentType
     * @return string
     */
    public function getMaxFileSizeFormatted(string $documentType): string
    {
        return $this->formatFileSize($this->getMaxFileSize($documentType));
    }

    /**
     * Obtient la liste des types MIME autorisés
     * 
     * @return array
     */
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Obtient la liste des extensions autorisées
     * 
     * @return array
     */
    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * Nettoie les anciens fichiers d'un prestataire (garde le plus récent par type)
     * 
     * @param Prestataire $prestataire
     * @param array $documents Documents à traiter
     * @return int Nombre de fichiers supprimés
     */
    public function cleanupOldDocuments(Prestataire $prestataire, array $documents): int
    {
        $documentsByType = [];
        
        // Grouper par type
        foreach ($documents as $document) {
            $type = $document->getType();
            if (!isset($documentsByType[$type])) {
                $documentsByType[$type] = [];
            }
            $documentsByType[$type][] = $document;
        }

        $deletedCount = 0;

        // Pour chaque type, supprimer les anciens fichiers (garder le plus récent)
        foreach ($documentsByType as $type => $docs) {
            if (count($docs) <= 1) {
                continue;
            }

            // Trier par date d'upload décroissante
            usort($docs, function($a, $b) {
                return $b->getUploadedAt() <=> $a->getUploadedAt();
            });

            // Garder le premier (le plus récent), supprimer les autres
            array_shift($docs);

            foreach ($docs as $oldDoc) {
                if ($this->deleteFile($oldDoc->getFilePath())) {
                    $deletedCount++;
                }
            }
        }

        $this->logger->info('Cleaned up old documents', [
            'prestataire_id' => $prestataire->getId(),
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Calcule l'espace disque utilisé par un prestataire
     * 
     * @param Prestataire $prestataire
     * @return int Taille en octets
     */
    public function calculateStorageUsed(Prestataire $prestataire): int
    {
        $prestataireDirectory = $this->uploadDirectory . '/prestataire_' . $prestataire->getId();
        
        if (!is_dir($prestataireDirectory)) {
            return 0;
        }

        $totalSize = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($prestataireDirectory)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Vérifie si un document est une image
     * 
     * @param Document $document
     * @return bool
     */
    public function isImage(Document $document): bool
    {
        $imageMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        return in_array($document->getMimeType(), $imageMimeTypes);
    }

    /**
     * Vérifie si un document est un PDF
     * 
     * @param Document $document
     * @return bool
     */
    public function isPdf(Document $document): bool
    {
        return $document->getMimeType() === 'application/pdf';
    }

    /**
     * Génère une miniature pour une image (optionnel)
     * 
     * @param string $imagePath
     * @param int $width
     * @param int $height
     * @return string|null Chemin de la miniature
     */
    public function createThumbnail(string $imagePath, int $width = 200, int $height = 200): ?string
    {
        // TODO: Implémenter la génération de miniatures
        // Utiliser GD ou Imagine pour créer des miniatures
        
        $this->logger->info('Thumbnail creation not yet implemented');
        return null;
    }

    /**
     * Obtient les métadonnées d'un fichier
     * 
     * @param string $filePath
     * @return array|null
     */
    public function getFileMetadata(string $filePath): ?array
    {
        $fullPath = $this->uploadDirectory . $filePath;

        if (!file_exists($fullPath)) {
            return null;
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        
        return [
            'size' => filesize($fullPath),
            'mime_type' => $fileInfo->file($fullPath),
            'modified_at' => filemtime($fullPath),
            'readable' => is_readable($fullPath),
            'writable' => is_writable($fullPath),
        ];
    }
}