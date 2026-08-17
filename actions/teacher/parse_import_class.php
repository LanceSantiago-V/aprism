<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TEACHER
];

require_once __DIR__ . '/../../auth/session_guard.php';

require_once __DIR__ . '/../../includes/import/import_engine.php';


/*
|--------------------------------------------------------------------------
| JSON Response Helper
|--------------------------------------------------------------------------
*/

function respondJson(
    bool $success,
    string $message,
    array $data = []
): never {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respondJson(
        false,
        'Invalid import request.'
    );

}


/*
|--------------------------------------------------------------------------
| Source
|--------------------------------------------------------------------------
*/

$source =
    trim(
        (string) (
            $_POST['source']
            ?? ''
        )
    );


if (
    !in_array(
        $source,
        [
            'excel',
            'csv'
        ],
        true
    )
) {

    respondJson(
        false,
        'This import source is not supported yet.'
    );

}


/*
|--------------------------------------------------------------------------
| Uploaded File
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['import_file'])
) {

    respondJson(
        false,
        'No import file was received.'
    );

}


$file =
    $_FILES['import_file'];


if (
    !isset($file['error']) ||
    $file['error'] !== UPLOAD_ERR_OK
) {

    respondJson(
        false,
        'The import file could not be uploaded.'
    );

}


if (
    !isset($file['tmp_name']) ||
    !is_uploaded_file(
        $file['tmp_name']
    )
) {

    respondJson(
        false,
        'The uploaded file could not be verified.'
    );

}


/*
|--------------------------------------------------------------------------
| Extension Validation
|--------------------------------------------------------------------------
*/

$extension =
    strtolower(
        pathinfo(
            (string) $file['name'],
            PATHINFO_EXTENSION
        )
    );


$allowedExtensions = [

    'excel' => [
        'xlsx',
        'xls',
    ],

    'csv' => [
        'csv',
    ],

];


if (
    !in_array(
        $extension,
        $allowedExtensions[$source],
        true
    )
) {

    respondJson(
        false,
        'The uploaded file does not match the selected import source.'
    );

}


/*
|--------------------------------------------------------------------------
| File Size
|--------------------------------------------------------------------------
|
| Keep the initial implementation practical.
| The server-side PHP upload configuration remains authoritative.
|
*/

if (
    isset($file['size']) &&
    (int) $file['size'] <= 0
) {

    respondJson(
        false,
        'The uploaded file is empty.'
    );

}


/*
|--------------------------------------------------------------------------
| Parse Through Import Engine
|--------------------------------------------------------------------------
*/

try {

    $result =
        ImportEngine::parseFile(
            $file['tmp_name']
        );

} catch (Throwable $e) {

    error_log(
        '[APRISM Parse Import Class] ' .
        $e->getMessage()
    );

    respondJson(
        false,
        $e instanceof RuntimeException
        ? $e->getMessage()
        : 'APRISM could not read the uploaded file.'
    );

}


/*
|--------------------------------------------------------------------------
| Parser Errors
|--------------------------------------------------------------------------
*/

if (
    !empty($result['errors'])
) {

    $messages = [];

    foreach (
        $result['errors']
        as $error
    ) {

        if (
            is_array($error) &&
            isset($error['message'])
        ) {

            $messages[] =
                (string) 
                $error['message'];

        } else {

            $messages[] =
                (string) 
                $error;

        }

    }


    respondJson(
        false,
        implode(
            ' ',
            $messages
        )
    );

}


/*
|--------------------------------------------------------------------------
| Rows
|--------------------------------------------------------------------------
*/

$rows =
    $result['rows']
    ?? [];


if (
    empty($rows)
) {

    respondJson(
        false,
        'No class information was found in the uploaded file.'
    );

}


/*
|--------------------------------------------------------------------------
| Current My Classes Confirmation
|--------------------------------------------------------------------------
|
| The current confirmation UI handles one operational class at a time.
|
| We therefore require exactly one normalized row for this first
| implementation connection.
|
| The Import Engine itself remains capable of producing multiple rows.
|
*/

if (
    count($rows) !== 1
) {

    respondJson(
        false,
        'The uploaded file contains multiple class records. The current Import Classes confirmation workflow supports one class at a time. Please import a file containing one class record.'
    );

}


$row =
    $rows[0];


/*
|--------------------------------------------------------------------------
| Attach Source Metadata
|--------------------------------------------------------------------------
*/

$row['source'] =
    $source;

$row['source_file_name'] =
    (string) $file['name'];


/*
|--------------------------------------------------------------------------
| Return Normalized Record
|--------------------------------------------------------------------------
*/

respondJson(
    true,
    'Class information extracted successfully.',
    [
        'source' =>
            $source,

        'file_name' =>
            (string) $file['name'],

        'row' =>
            $row,
    ]
);