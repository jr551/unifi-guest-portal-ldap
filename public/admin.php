<?php
declare(strict_types=1);

use Carlgo11\Guest_Portal\GuestPortal;
use Carlgo11\Guest_Portal\Storage\Storage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

function sendResponse(mixed $message, int $code = 200): void
{
    http_response_code($code);

    if ($code === 204 || $message === null) {
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        echo json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $exception) {
        error_log('JSON encoding failed: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }

    exit;
}

/**
 * @throws JsonException
 */
function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        throw new RuntimeException('Unable to read request body.', 500);
    }

    if ($raw === '') {
        throw new Exception('Empty request body', 400);
    }

    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => $secureCookie,
        'use_strict_mode' => true,
    ]);
}

function requireAuthentication(): void
{
    startSecureSession();

    if (empty($_SESSION['user'])) {
        $redirectUrl = '/auth';
        $current = $_SERVER['REQUEST_URI'] ?? '';
        if ($current !== '') {
            $redirectUrl .= '?url=' . rawurlencode($current);
        }

        header('Location: ' . $redirectUrl, true, 302);
        exit;
    }
}

/**
 * @throws Exception
 */
function toDateTime(int $timestamp): DateTime
{
    $date = DateTimeImmutable::createFromFormat('U', (string)$timestamp);
    if ($date === false) {
        throw new Exception('Invalid timestamp value', 400);
    }

    return DateTime::createFromImmutable($date);
}

$storage = new Storage();
requireAuthentication();

switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
    case 'GET':
        $loader = new FilesystemLoader([__DIR__ . '/../templates', __DIR__ . '/../templates/admin']);
        $twig = new Environment($loader);
        echo $twig->render('admin.twig');
        break;

    case 'POST':
        try {
            $data = readJsonInput();
            $type = isset($data['type']) ? strtolower((string)$data['type']) : '';

            switch ($type) {
                case 'voucher':
                    $uses = filter_var($data['uses'] ?? null, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1, 'max_range' => 254],
                    ]);
                    $expiryTs = filter_var($data['expiry'] ?? null, FILTER_VALIDATE_INT);
                    $durationTs = filter_var($data['duration'] ?? null, FILTER_VALIDATE_INT);

                    if ($uses === false || $expiryTs === false || $durationTs === false) {
                        throw new Exception('Invalid voucher parameters', 400);
                    }

                    $guestPortal = new GuestPortal();
                    $voucherId = $guestPortal->createVoucher($uses, toDateTime($expiryTs), toDateTime($durationTs));

                    if ($voucherId === null) {
                        throw new Exception('Unable to create voucher');
                    }

                    sendResponse(['voucher' => $voucherId], 201);
                    break;

                case 'user':
                    $username = isset($data['username']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$data['username']) : '';
                    if ($username === '' || strlen($username) > 32) {
                        throw new Exception('Invalid username', 400);
                    }

                    $password = isset($data['password']) ? (string)$data['password'] : '';
                    if (strlen($password) < 12) {
                        throw new Exception('Password must be at least 12 characters long', 400);
                    }

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    if ($hash === false || !$storage->createUser($username, $hash)) {
                        throw new Exception('Unable to create user');
                    }

                    sendResponse(null, 201);
                    break;

                default:
                    throw new Exception('Unsupported request type', 400);
            }
        } catch (JsonException $exception) {
            error_log('Invalid JSON payload: ' . $exception->getMessage());
            sendResponse(['error' => 'Invalid JSON payload'], 400);
        } catch (Exception $exception) {
            $code = $exception->getCode() ?: 500;
            error_log($exception->getMessage());
            sendResponse(['error' => $exception->getMessage()], $code);
        }
        break;
}

