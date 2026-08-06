<?php

/**
 * Validates a password against APRISM's password policy.
 *
 * @param string $password
 * @return array
 */
function validatePasswordStrength(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (strlen($password) > 64) {
        $errors[] = 'Password must not exceed 64 characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    if ($password !== trim($password)) {
        $errors[] = 'Password cannot begin or end with spaces.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}