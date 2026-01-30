<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FileUploadService
{
    private LoggerInterface $logger;
    private SluggerInterface $slugger;
    private string $uploadDirectory;
    private string $publicDirectory;
    private array $allowedMimeTypes;
    private array $maxFileSizes;
    private array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private array $documentExtensions = ['pdf', 'doc', 'docx', 'txt'];

    public function __construct(
        LoggerInterface $logger,
        SluggerInterface $slugger,
        ParameterBagInterface $params,
        string $uploadDirectory = '/uploads',
        string $publicDirectory = '/public/uploads'
    ) {
        $this->logger = $logger;
        $this->slugger = $slugger;
        $this->uploadDirectory = $params->get('kernel.project_dir') . $uploadDirectory;
        $this->publicDirectory = $params->get('kernel.project_dir') . $publicDirectory;

        // Types MIME autorisés par catégorie
        $this->allowedMimeTypes = [
            'image' => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
            ],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ],
            'kbis' => [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
            ],
            'insurance' => [
                'application/pdf',
            ],
            'identity' => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'application/pdf',
            ],
        ];

        // Tailles maximales par catégorie (en octets)
        $this->maxFileSizes = [
            'image' => 5 * 1024 * 1024,        // 5 MB
            'document' => 10 * 1024 * 1024,    // 10 MB
            'kbis' => 5 * 1024 * 1024,         // 5 MB
            'insurance' => 10 * 1024 * 1024,   // 10 MB
            'identity' => 5 * 1024 * 1024,     // 5 MB
        ];
    }

    /**
     * Uploader un fichier
     */
    public function upload(
        UploadedFile $file,
        string $category = 'document',
        ?string $subdirectory = null
    ): ?array {
        // Validation du fichier
        $validation = $this->validateFile($file, $category);
        if (!$validation['valid']) {
            $this->logger->warning('File validation failed', [
                'original_name' => $file->getClientOriginalName(),
                'errors' => $validation['errors'],
            ]);
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        try {
            // Générer un nom de fichier unique et sécurisé
            $originalFilename = pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            );
            $safeFilename = $this->slugger->slug($originalFilename);
            $extension = $file->guessExtension();
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

            // Déterminer le répertoire de destination
            $destinationPath = $this->uploadDirectory . '/' . $category;
            if ($subdirectory) {
                $destinationPath .= '/' . $subdirectory;
            }

            // Créer le répertoire s'il n'existe pas
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Déplacer le fichier
            $file->move($destinationPath, $newFilename);

            // Générer l'URL publique
            $publicUrl = $this->generatePublicUrl($category, $newFilename, $subdirectory);

            // Informations du fichier
            $fileInfo = [
                'success' => true,
                'filename' => $newFilename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $destinationPath . '/' . $newFilename,
                'public_url' => $publicUrl,
                'size' => filesize($destinationPath . '/' . $newFilename),
                'mime_type' => mime_content_type($destinationPath . '/' . $newFilename),
                'extension' => $extension,
                'category' => $category,
                'uploaded_at' => new \DateTime(),
            ];

            // Si c'est une image, ajouter les dimensions
            if ($this->isImage($extension)) {
                $dimensions = $this->getImageDimensions($destinationPath . '/' . $newFilename);
                $fileInfo['width'] = $dimensions['width'];
                $fileInfo['height'] = $dimensions['height'];
            }

            $this->logger->info('File uploaded successfully', [
                'filename' => $newFilename,
                'category' => $category,
            ]);

            return $fileInfo;

        } catch (FileException $e) {
            $this->logger->error('File upload failed', [
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de l\'upload du fichier.'],
            ];
        }
    }

    /**
     * Uploader plusieurs fichiers
     */
    public function uploadMultiple(
        array $files,
        string $category = 'document',
        ?string $subdirectory = null
    ): array {
        $results = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->upload($file, $category, $subdirectory);
            }
        }

        return $results;
    }

    /**
     * Uploader une image de profil avec redimensionnement
     */
    public function uploadProfileImage(UploadedFile $file, int $userId): ?array
    {
        $result = $this->upload($file, 'image', 'profiles');

        if (!$result['success']) {
            return $result;
        }

        // Créer des miniatures
        $thumbnails = $this->createThumbnails(
            $result['path'],
            [
                'small' => 150,
                'medium' => 300,
                'large' => 600,
            ]
        );

        $result['thumbnails'] = $thumbnails;

        return $result;
    }

    /**
     * Valider un fichier
     */
    private function validateFile(UploadedFile $file, string $category): array
    {
        $errors = [];

        // Vérifier si le fichier est valide
        if (!$file->isValid()) {
            $errors[] = 'Le fichier est invalide.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Vérifier le type MIME
        $mimeType = $file->getMimeType();
        $allowedTypes = $this->allowedMimeTypes[$category] ?? $this->allowedMimeTypes['document'];

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = sprintf(
                'Type de fichier non autorisé. Types acceptés: %s',
                implode(', ', $allowedTypes)
            );
        }

        // Vérifier la taille
        $maxSize = $this->maxFileSizes[$category] ?? $this->maxFileSizes['document'];
        if ($file->getSize() > $maxSize) {
            $errors[] = sprintf(
                'Fichier trop volumineux. Taille maximale: %s',
                $this->formatFileSize($maxSize)
            );
        }

        // Vérifier l'extension
        $extension = $file->guessExtension();
        if (!$extension) {
            $errors[] = 'Impossible de déterminer l\'extension du fichier.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Supprimer un fichier
     */
    public function delete(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            $this->logger->warning('File not found for deletion', [
                'filepath' => $filepath,
            ]);
            return false;
        }

        try {
            unlink($filepath);

            // Supprimer les miniatures associées si c'est une image
            $this->deleteThumbnails($filepath);

            $this->logger->info('File deleted successfully', [
                'filepath' => $filepath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('File deletion failed', [
                'filepath' => $filepath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Créer des miniatures pour une image
     */
    private function createThumbnails(string $imagePath, array $sizes): array
    {
        $thumbnails = [];

        if (!$this->isImage(pathinfo($imagePath, PATHINFO_EXTENSION))) {
            return $thumbnails;
        }

        $pathInfo = pathinfo($imagePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        foreach ($sizes as $name => $maxSize) {
            try {
                $thumbnailPath = sprintf(
                    '%s/%s_%s.%s',
                    $directory,
                    $filename,
                    $name,
                    $extension
                );

                $this->resizeImage($imagePath, $thumbnailPath, $maxSize);

                $thumbnails[$name] = [
                    'path' => $thumbnailPath,
                    'url' => $this->generatePublicUrl(
                        basename(dirname($directory)),
                        basename($thumbnailPath),
                        basename($directory)
                    ),
                    'size' => $maxSize,
                ];

            } catch (\Exception $e) {
                $this->logger->error('Thumbnail creation failed', [
                    'image' => $imagePath,
                    'size' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $thumbnails;
    }

    /**
     * Redimensionner une image
     */
    private function resizeImage(string $sourcePath, string $destPath, int $maxSize): void
    {
        list($width, $height) = getimagesize($sourcePath);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Charger l'image source
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            case 'webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new \Exception('Format d\'image non supporté');
        }

        // Calculer les nouvelles dimensions
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = (int)(($height / $width) * $maxSize);
        } else {
            $newHeight = $maxSize;
            $newWidth = (int)(($width / $height) * $maxSize);
        }

        // Créer l'image redimensionnée
        $destination = imagecreatetruecolor($newWidth, $newHeight);

        // Préserver la transparence pour PNG et GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Redimensionner
        imagecopyresampled(
            $destination,
            $source,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // Sauvegarder
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($destination, $destPath, 90);
                break;
            case 'png':
                imagepng($destination, $destPath, 9);
                break;
            case 'gif':
                imagegif($destination, $destPath);
                break;
            case 'webp':
                imagewebp($destination, $destPath, 90);
                break;
        }

        imagedestroy($source);
        imagedestroy($destination);
    }

    /**
     * Supprimer les miniatures d'une image
     */
    private function deleteThumbnails(string $imagePath): void
    {
        $pathInfo = pathinfo($imagePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $pattern = sprintf('%s/%s_*.%s', $directory, $filename, $extension);
        $thumbnails = glob($pattern);

        foreach ($thumbnails as $thumbnail) {
            if (file_exists($thumbnail)) {
                unlink($thumbnail);
            }
        }
    }

    /**
     * Obtenir les dimensions d'une image
     */
    private function getImageDimensions(string $imagePath): array
    {
        list($width, $height) = getimagesize($imagePath);

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Vérifier si un fichier est une image
     */
    private function isImage(?string $extension): bool
    {
        return $extension && in_array(strtolower($extension), $this->imageExtensions);
    }

    /**
     * Générer l'URL publique d'un fichier
     */
    private function generatePublicUrl(
        string $category,
        string $filename,
        ?string $subdirectory = null
    ): string {
        $path = '/uploads/' . $category;
        if ($subdirectory) {
            $path .= '/' . $subdirectory;
        }
        $path .= '/' . $filename;

        return $path;
    }

    /**
     * Formater la taille d'un fichier
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Obtenir les informations d'un fichier
     */
    public function getFileInfo(string $filepath): ?array
    {
        if (!file_exists($filepath)) {
            return null;
        }

        $pathInfo = pathinfo($filepath);

        $info = [
            'filename' => $pathInfo['basename'],
            'path' => $filepath,
            'size' => filesize($filepath),
            'size_formatted' => $this->formatFileSize(filesize($filepath)),
            'mime_type' => mime_content_type($filepath),
            'extension' => $pathInfo['extension'] ?? null,
            'modified_at' => new \DateTime('@' . filemtime($filepath)),
        ];

        // Ajouter les dimensions si c'est une image
        if ($this->isImage($pathInfo['extension'] ?? null)) {
            $dimensions = $this->getImageDimensions($filepath);
            $info['width'] = $dimensions['width'];
            $info['height'] = $dimensions['height'];
        }

        return $info;
    }

    /**
     * Copier un fichier
     */
    public function copy(string $sourcePath, string $destinationPath): bool
    {
        try {
            if (!file_exists($sourcePath)) {
                return false;
            }

            $destDir = dirname($destinationPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            copy($sourcePath, $destinationPath);

            $this->logger->info('File copied successfully', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('File copy failed', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Déplacer un fichier
     */
    public function move(string $sourcePath, string $destinationPath): bool
    {
        try {
            if (!file_exists($sourcePath)) {
                return false;
            }

            $destDir = dirname($destinationPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            rename($sourcePath, $destinationPath);

            $this->logger->info('File moved successfully', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('File move failed', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Nettoyer les fichiers temporaires ou orphelins
     */
    public function cleanupOldFiles(string $directory, int $daysOld = 30): int
    {
        $count = 0;
        $threshold = time() - ($daysOld * 24 * 60 * 60);

        if (!is_dir($directory)) {
            return 0;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getMTime() < $threshold) {
                try {
                    unlink($file->getRealPath());
                    $count++;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to delete old file', [
                        'file' => $file->getRealPath(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->logger->info('Old files cleaned up', [
            'directory' => $directory,
            'count' => $count,
            'days_old' => $daysOld,
        ]);

        return $count;
    }

    /**
     * Obtenir l'usage du disque pour un répertoire
     */
    public function getDiskUsage(string $directory): array
    {
        $totalSize = 0;
        $fileCount = 0;

        if (!is_dir($directory)) {
            return [
                'total_size' => 0,
                'file_count' => 0,
                'formatted_size' => '0 B',
            ];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
                $fileCount++;
            }
        }

        return [
            'total_size' => $totalSize,
            'file_count' => $fileCount,
            'formatted_size' => $this->formatFileSize($totalSize),
        ];
    }

    /**
     * Valider une image (dimensions, taille, format)
     */
    public function validateImage(
        UploadedFile $file,
        ?int $minWidth = null,
        ?int $maxWidth = null,
        ?int $minHeight = null,
        ?int $maxHeight = null
    ): array {
        $validation = $this->validateFile($file, 'image');

        if (!$validation['valid']) {
            return $validation;
        }

        try {
            list($width, $height) = getimagesize($file->getPathname());

            $errors = [];

            if ($minWidth && $width < $minWidth) {
                $errors[] = sprintf('Largeur minimale requise: %dpx', $minWidth);
            }

            if ($maxWidth && $width > $maxWidth) {
                $errors[] = sprintf('Largeur maximale autorisée: %dpx', $maxWidth);
            }

            if ($minHeight && $height < $minHeight) {
                $errors[] = sprintf('Hauteur minimale requise: %dpx', $minHeight);
            }

            if ($maxHeight && $height > $maxHeight) {
                $errors[] = sprintf('Hauteur maximale autorisée: %dpx', $maxHeight);
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'width' => $width,
                'height' => $height,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Impossible de lire les dimensions de l\'image'],
            ];
        }
    }
}