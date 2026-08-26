<?php

declare(strict_types=1);

final class ClassListSourceSession
{
    private const KEY = 'aprism_class_list_sources';
    private const TTL_SECONDS = 1800;
    private const METADATA_SUFFIX = '.source.json';

    /**
     * @param array<string, mixed> $file
     * @return array{token: string, expires_at: int}
     */
    public function storeUploadedFile(
        array $file,
        int $teacherId,
        int $operationalClassId,
        string $extension
    ): array {
        $extension = $this->validatedExtension($extension);

        $this->cleanupExpired();
        $this->discardForClass($teacherId, $operationalClassId);
        $this->storageDirectory(true);

        $token = bin2hex(random_bytes(32));
        $path = $this->sourcePath($token, $extension);

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $path)) {
            throw new RuntimeException(
                'The uploaded source could not be staged temporarily.'
            );
        }

        @chmod($path, 0600);

        $source = [
            'teacher_id' => $teacherId,
            'operational_class_id' => $operationalClassId,
            'extension' => $extension,
            'original_name' => basename(
                (string) ($file['name'] ?? ('class-list.' . $extension))
            ),
            'expires_at' => time() + self::TTL_SECONDS,
        ];

        try {
            $this->writeMetadata($token, $source);
        } catch (Throwable $e) {
            @unlink($path);

            throw new RuntimeException(
                'Temporary source storage is unavailable.',
                0,
                $e
            );
        }

        $_SESSION[self::KEY][$token] = $this->withPath($token, $source);

        return [
            'token' => $token,
            'expires_at' => (int) $source['expires_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(
        string $token,
        int $teacherId,
        int $operationalClassId
    ): array {
        if (!$this->validToken($token)) {
            throw new RuntimeException(
                'The temporary source is unavailable for this class.'
            );
        }

        $this->cleanupExpired();

        $sessionSource = $_SESSION[self::KEY][$token] ?? null;
        $metadataSource = $this->readMetadata($token);

        $source = is_array($metadataSource)
            ? $metadataSource
            : (is_array($sessionSource) ? $sessionSource : null);

        if (!is_array($source)) {
            throw new RuntimeException(
                'The temporary source is unavailable for this class.'
            );
        }

        $expiresAt = (int) ($source['expires_at'] ?? 0);

        if ($expiresAt < time()) {
            $this->discard($token);

            throw new RuntimeException(
                'The temporary source has expired. Upload the file again.'
            );
        }

        if (
            !hash_equals(
                (string) ($source['teacher_id'] ?? ''),
                (string) $teacherId
            )
            || (int) ($source['operational_class_id'] ?? 0)
            !== $operationalClassId
        ) {
            throw new RuntimeException(
                'The temporary source is unavailable for this class.'
            );
        }

        try {
            $extension = $this->validatedExtension(
                (string) ($source['extension'] ?? '')
            );
        } catch (RuntimeException) {
            $this->discard($token);

            throw new RuntimeException(
                'The temporary source is unavailable for this class.'
            );
        }

        $path = $this->sourcePath($token, $extension);

        if (!is_file($path) || !is_readable($path)) {
            $this->discard($token);

            throw new RuntimeException(
                'The temporary source has expired. Upload the file again.'
            );
        }

        $source = [
            'teacher_id' => $teacherId,
            'operational_class_id' => $operationalClassId,
            'path' => $path,
            'extension' => $extension,
            'original_name' => basename(
                (string) ($source['original_name'] ?? ('class-list.' . $extension))
            ),
            'expires_at' => $expiresAt,
        ];

        $_SESSION[self::KEY][$token] = $source;

        return $source;
    }

    /**
     * Return the short-lived, Teacher-reviewed state associated with this
     * staged source. It is not Student, SAE, or SCE data and is deleted with
     * the source on expiry, replacement, or discard.
     *
     * @return array<string, mixed>
     */
    public function getReviewState(
        string $token,
        int $teacherId,
        int $operationalClassId
    ): array {
        $this->get($token, $teacherId, $operationalClassId);
        $metadata = $this->readMetadata($token);
        $state = is_array($metadata) ? ($metadata['review_state'] ?? []) : [];

        return is_array($state) ? $state : [];
    }

    /**
     * Save bounded review input only after the calling endpoint has validated
     * its contract. The metadata sidecar remains token-, Teacher-, and
     * Operational-Class-bound; it is never authoritative institutional data.
     *
     * @param array<string, mixed> $reviewState
     */
    public function saveReviewState(
        string $token,
        int $teacherId,
        int $operationalClassId,
        array $reviewState
    ): void {
        $this->get($token, $teacherId, $operationalClassId);

        $encoded = json_encode(
            $reviewState,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (strlen($encoded) > 262144) {
            throw new RuntimeException('Temporary review state is too large.');
        }

        $metadata = $this->readMetadata($token);

        if (!is_array($metadata)) {
            throw new RuntimeException('Temporary source metadata is unavailable.');
        }

        $metadata['review_state'] = $reviewState;
        $this->writeMetadata($token, $metadata);
    }

    public function clearReviewState(
        string $token,
        int $teacherId,
        int $operationalClassId
    ): void {
        $this->get($token, $teacherId, $operationalClassId);
        $metadata = $this->readMetadata($token);

        if (!is_array($metadata)) {
            return;
        }

        unset($metadata['review_state']);
        $this->writeMetadata($token, $metadata);
    }

    private function discardForClass(
        int $teacherId,
        int $operationalClassId
    ): void {
        $this->cleanupExpired();

        $sources = [];

        foreach ($_SESSION[self::KEY] ?? [] as $token => $source) {
            if (is_array($source)) {
                $sources[(string) $token] = $source;
            }
        }

        foreach ($this->metadataSources() as $token => $source) {
            $sources[$token] = $source;
        }

        foreach ($sources as $token => $source) {
            if (
                (int) ($source['teacher_id'] ?? 0) === $teacherId
                && (int) ($source['operational_class_id'] ?? 0)
                === $operationalClassId
            ) {
                $this->discard($token);
            }
        }
    }

    private function cleanupExpired(): void
    {
        $sources = [];

        foreach ($_SESSION[self::KEY] ?? [] as $token => $source) {
            if (is_array($source)) {
                $sources[(string) $token] = $source;
            }
        }

        foreach ($this->metadataSources() as $token => $source) {
            $sources[$token] = $source;
        }

        foreach ($sources as $token => $source) {
            if ((int) ($source['expires_at'] ?? 0) < time()) {
                $this->discard($token);
            }
        }
    }

    private function discard(string $token): void
    {
        if (!$this->validToken($token)) {
            return;
        }

        foreach (['xlsx', 'xls', 'csv'] as $extension) {
            $path = $this->sourcePath($token, $extension);

            if (is_file($path)) {
                @unlink($path);
            }
        }

        $metadataPath = $this->metadataPath($token);

        if (is_file($metadataPath)) {
            @unlink($metadataPath);
        }

        unset($_SESSION[self::KEY][$token]);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function writeMetadata(string $token, array $source): void
    {
        $metadataPath = $this->metadataPath($token);
        $temporaryPath = $metadataPath . '.' . bin2hex(random_bytes(8)) . '.tmp';

        $encoded = json_encode(
            array_filter([
                'teacher_id' => (int) $source['teacher_id'],
                'operational_class_id' => (int) $source['operational_class_id'],
                'extension' => (string) $source['extension'],
                'original_name' => (string) $source['original_name'],
                'expires_at' => (int) $source['expires_at'],
                'review_state' => is_array($source['review_state'] ?? null)
                    ? $source['review_state']
                    : null,
            ], static fn(mixed $value): bool => $value !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write temporary source metadata.');
        }

        @chmod($temporaryPath, 0600);

        if (!rename($temporaryPath, $metadataPath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to finalize temporary source metadata.');
        }

        @chmod($metadataPath, 0600);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $token): ?array
    {
        if (!$this->validToken($token)) {
            return null;
        }

        $path = $this->metadataPath($token);

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function metadataSources(): array
    {
        $directory = $this->storageDirectory(false);

        if (!is_dir($directory)) {
            return [];
        }

        $sources = [];
        $files = glob(
            $directory . DIRECTORY_SEPARATOR . '*' . self::METADATA_SUFFIX
        ) ?: [];

        foreach ($files as $file) {
            $name = basename($file);

            if (!str_ends_with($name, self::METADATA_SUFFIX)) {
                continue;
            }

            $token = substr($name, 0, -strlen(self::METADATA_SUFFIX));

            if (!$this->validToken($token)) {
                continue;
            }

            $source = $this->readMetadata($token);

            if (is_array($source)) {
                $sources[$token] = $source;
            }
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function withPath(string $token, array $source): array
    {
        $extension = $this->validatedExtension(
            (string) ($source['extension'] ?? '')
        );

        $source['path'] = $this->sourcePath($token, $extension);

        return $source;
    }

    private function storageDirectory(bool $create): string
    {
        $directory = __DIR__ . '/../../storage/class_list_sources';

        if (
            $create
            && !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Temporary source storage is unavailable.'
            );
        }

        return $directory;
    }

    private function sourcePath(string $token, string $extension): string
    {
        return $this->storageDirectory(false)
            . DIRECTORY_SEPARATOR
            . $token
            . '.'
            . $extension;
    }

    private function metadataPath(string $token): string
    {
        return $this->storageDirectory(false)
            . DIRECTORY_SEPARATOR
            . $token
            . self::METADATA_SUFFIX;
    }

    private function validToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function validatedExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw new RuntimeException('Unsupported temporary source type.');
        }

        return $extension;
    }
}