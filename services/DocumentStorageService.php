<?php

declare(strict_types=1);

class DocumentStorageService
{
    public const MAX_FILE_SIZE_BYTES = 10485760;

    private const ALLOWED_EXTENSIONS = [
        'pdf' => [
            'application/pdf',
            'application/x-pdf',
        ],
        'doc' => [
            'application/msword',
            'application/vnd.ms-word',
            'application/vnd.ms-office',
            'application/x-ole-storage',
            'application/x-cdf',
            'application/octet-stream',
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ],
    ];

    private string $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->storageRoot = $storageRoot
            ?? dirname(__DIR__)
                . '/storage/private/agreement-documents';
    }

    public function store(array $uploadedFile): array
    {
        $error = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $this->uploadErrorMessage($error)
            );
        }

        $temporaryPath = (string) ($uploadedFile['tmp_name'] ?? '');

        if (
            $temporaryPath === ''
            || !is_uploaded_file($temporaryPath)
        ) {
            throw new InvalidArgumentException(
                'The uploaded file could not be verified'
            );
        }

        $originalName = $this->safeOriginalName(
            (string) ($uploadedFile['name'] ?? '')
        );
        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if (!array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            throw new InvalidArgumentException(
                'Only PDF, DOC, and DOCX files are allowed'
            );
        }

        $fileSize = filesize($temporaryPath);

        if (
            $fileSize === false
            || $fileSize <= 0
            || $fileSize > self::MAX_FILE_SIZE_BYTES
        ) {
            throw new InvalidArgumentException(
                'The document must be larger than 0 bytes and no more than 10 MB'
            );
        }

        $mimeType = $this->detectMimeType($temporaryPath);

        if (!in_array(
            $mimeType,
            self::ALLOWED_EXTENSIONS[$extension],
            true
        )) {
            throw new InvalidArgumentException(
                'The document content does not match its file extension'
            );
        }

        $this->verifySignature($temporaryPath, $extension);
        $checksum = hash_file('sha256', $temporaryPath);

        if ($checksum === false) {
            throw new RuntimeException(
                'The document checksum could not be calculated'
            );
        }

        $storageKey = sprintf(
            '%s/%s/%s.%s',
            date('Y'),
            date('m'),
            bin2hex(random_bytes(32)),
            $extension
        );
        $absolutePath = $this->pathForNewKey($storageKey);
        $directory = dirname($absolutePath);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0750, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'The private document directory could not be created'
            );
        }

        if (!move_uploaded_file($temporaryPath, $absolutePath)) {
            throw new RuntimeException(
                'The document could not be moved into private storage'
            );
        }

        @chmod($absolutePath, 0640);

        return [
            'file_name' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'file_size_bytes' => (int) $fileSize,
            'sha256_checksum' => $checksum,
        ];
    }

    public function absolutePath(string $storageKey): ?string
    {
        if (!$this->isValidStorageKey($storageKey)) {
            return null;
        }

        $path = $this->storageRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
        $realPath = realpath($path);
        $realRoot = realpath($this->storageRoot);

        if (
            $realPath === false
            || $realRoot === false
            || !is_file($realPath)
            || !str_starts_with(
                $realPath,
                $realRoot . DIRECTORY_SEPARATOR
            )
        ) {
            return null;
        }

        return $realPath;
    }

    public function delete(string $storageKey): bool
    {
        $absolutePath = $this->absolutePath($storageKey);

        return $absolutePath !== null
            && unlink($absolutePath);
    }

    public static function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED_EXTENSIONS);
    }

    private function pathForNewKey(string $storageKey): string
    {
        if (!$this->isValidStorageKey($storageKey)) {
            throw new RuntimeException('Invalid document storage key');
        }

        return $this->storageRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function isValidStorageKey(string $storageKey): bool
    {
        return preg_match(
            '#^\d{4}/\d{2}/[a-f0-9]{64}\.(pdf|doc|docx)$#',
            $storageKey
        ) === 1;
    }

    private function safeOriginalName(string $fileName): string
    {
        $normalized = basename(str_replace('\\', '/', $fileName));
        $normalized = str_replace(
            ["\0", "\r", "\n", '"'],
            '',
            $normalized
        );
        $normalized = trim($normalized);

        if ($normalized === '' || $normalized === '.' || $normalized === '..') {
            throw new InvalidArgumentException(
                'The document file name is invalid'
            );
        }

        if (strlen($normalized) > 255) {
            $extension = pathinfo($normalized, PATHINFO_EXTENSION);
            $baseName = pathinfo($normalized, PATHINFO_FILENAME);
            $suffix = $extension === '' ? '' : '.' . $extension;
            $normalized = substr(
                $baseName,
                0,
                255 - strlen($suffix)
            ) . $suffix;
        }

        return $normalized;
    }

    private function detectMimeType(string $path): string
    {
        if (!class_exists('finfo')) {
            throw new RuntimeException(
                'The PHP Fileinfo extension is required for document uploads'
            );
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($path);

        if (!is_string($mimeType) || $mimeType === '') {
            throw new RuntimeException(
                'The document MIME type could not be detected'
            );
        }

        return strtolower($mimeType);
    }

    private function verifySignature(
        string $path,
        string $extension
    ): void {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'The uploaded document could not be inspected'
            );
        }

        $signature = fread($handle, 8);
        fclose($handle);

        $valid = match ($extension) {
            'pdf' => str_starts_with((string) $signature, '%PDF-'),
            'doc' => (string) $signature
                === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
            'docx' => str_starts_with((string) $signature, "PK\x03\x04"),
            default => false,
        };

        if (!$valid) {
            throw new InvalidArgumentException(
                'The document signature does not match its file extension'
            );
        }

        if ($extension === 'docx') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException(
                    'The PHP Zip extension is required to validate DOCX files'
                );
            }

            $archive = new ZipArchive();
            $opened = $archive->open($path);
            $hasWordDocument = $opened === true
                && $archive->locateName('word/document.xml') !== false
                && $archive->locateName('[Content_Types].xml') !== false
                && $archive->locateName('word/vbaProject.bin') === false;

            if ($opened === true) {
                $archive->close();
            }

            if (!$hasWordDocument) {
                throw new InvalidArgumentException(
                    'The DOCX file is invalid or contains macros'
                );
            }
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'The document exceeds the permitted upload size',
            UPLOAD_ERR_PARTIAL =>
                'The document upload was interrupted',
            UPLOAD_ERR_NO_FILE =>
                'Choose a document to upload',
            default =>
                'The document upload failed',
        };
    }
}
