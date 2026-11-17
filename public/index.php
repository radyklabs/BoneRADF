<?php declare (strict_types=1);

// These messages are visible only if the framework cannot load at all.
$heading = 'Internal System Error';
$message = 'An unexpected error occurred. Please contact the Administrator.';

// Confirm that the three built-in functions we depend on are available.
// Each is captured into a variable reference so we never call them
// by literal name — a defence-in-depth measure.
if (!is_callable ('call_user_func', false, $call)
    || !is_callable ('define', false, $lock_as)
    || !is_callable ('defined', false, $is_locked_as))
{
    die ("<h1>{$heading}</h1><p>{$message}</p>");
}

// `basepath` is the resolved /public/ directory. Every path in the
// framework derives from it. `sep` caches the directory separator.
$lock_as ('basepath', $call ('realpath', __DIR__));

if (!$is_locked_as ('sep')) $lock_as ('sep', DIRECTORY_SEPARATOR);

// name => directory (relative to the project root, one level above /public/)
$directories = [
    'system'    => 'src',
    'modules'   => 'modules'
];

try
{
    // The caller file lives one level above /public/. It wires up the
    // autoloader, error handler, and returns the Bootstrap class name.
    @require $call ('implode', '.', [$call ('dirname', basepath).sep, 'config']);

    if (isset ($bootkey) && class_exists ($bootkey))
    {
        $bootkey::init ()
            ->handle (\Boneradf\Kernel\Request::fromGlobals ())
            ->emit ();
    }
}

catch (Throwable $th)
{
    error_log ($th);
    // Headless contract holds even on boot failure: emit a JSON envelope.
    http_response_code (500);
    header ('Content-Type: application/json; charset=utf-8');
    echo json_encode ([
        'error' => [
            'code'    => 'BOOT_FAILED',
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
