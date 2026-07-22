<?php

if (!function_exists('isInstitutionalEmail')) {

    /**
     * Determines whether the supplied email is a valid
     * STI College Dasmariñas institutional email.
     */
    function isInstitutionalEmail(string $email): bool
    {
        $email = trim(strtolower($email));

        return
            filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
            str_ends_with($email, '@dasmarinas.sti.edu.ph');
    }
}