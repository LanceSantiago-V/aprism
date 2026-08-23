<?php

declare(strict_types=1);

final class ClassListSourceSession
{
    private const KEY = 'aprism_class_list_sources';
    private const TTL_SECONDS = 1800;

    /**
     * Store one temporary uploaded source for this Teacher and Operational Class.
     *
     * @param array<string, mixed> $file
     * @return array{token: string, expires_at: int}
     */
    public function storeUploadedFile(
        array $file,
        int $teacherId,
        int $operationalClassId,
        string $extension
    ): array {
        $this->discardForClass($teacherId, $operationalClassId);

        $directory = __DIR__ . '/../../storage/class_list_sources';

        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Temporary source storage is unavailable.'
            );
        }

        $token = bin2hex(random_bytes(32));
        $path = $directory . DIRECTORY_SEPARATOR . $token . '.' . $extension;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $path)) {
            throw new RuntimeException(
                'The uploaded source could not be staged temporarily.'
            );
        }

        @chmod($path, 0600);

        $_SESSION[self::KEY][$token] = [
            'teacher_id' => $teacherId,
            'operational_class_id' => $operationalClassId,
            'path' => $path,
            'extension' => $extension,
            'original_name' => (string) (
                $file['name'] ?? ('class-list.' . $extension)
            ),
            'expires_at' => time() + self::TTL_SECONDS,
        ];

        return [
            'token' => $token,
            'expires_at' => $_SESSION[self::KEY][$token]['expires_at'],
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
        $this->cleanupExpired();

        $source = $_SESSION[self::KEY][$token] ?? null;

        if (
            !is_array($source)
            || !hash_equals(
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

        if (
            !is_file((string) ($source['path'] ?? ''))
            || !is_readable((string) ($source['path'] ?? ''))
        ) {
            unset($_SESSION[self::KEY][$token]);

            throw new RuntimeException(
                'The temporary source has expired. Upload the file again.'
            );
        }

        return $source;
    }

    private function discardForClass(
        int $teacherId,
        int $operationalClassId
    ): void {
        $this->cleanupExpired();

        foreach ($_SESSION[self::KEY] ?? [] as $token => $source) {
            if (
                (int) ($source['teacher_id'] ?? 0) === $teacherId
                && (int) ($source['operational_class_id'] ?? 0)
                === $operationalClassId
            ) {
                $this->discard((string) $token);
            }
        }
    }

    private function cleanupExpired(): void
    {
        foreach ($_SESSION[self::KEY] ?? [] as $token => $source) {
            if ((int) ($source['expires_at'] ?? 0) < time()) {
                $this->discard((string) $token);
            }
        }
    }

    private function discard(string $token): void
    {
        $source = $_SESSION[self::KEY][$token] ?? null;

        if (
            is_array($source)
            && isset($source['path'])
            && is_file((string) $source['path'])
        ) {
            @unlink((string) $source['path']);
        }

        unset($_SESSION[self::KEY][$token]);
    }
}