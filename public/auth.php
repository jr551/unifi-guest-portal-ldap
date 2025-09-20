<?php
declare(strict_types=1);

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

/**
 * @throws JsonException
 */
function language(): array
{
    $lang = $_ENV['LANG'] ?? 'en';
    $lang = preg_replace('/[^a-z0-9_-]/i', '', $lang) ?: 'en';
    $path = sprintf('%s/../language_%s.json', __DIR__, $lang);
    if (!is_file($path)) {
        throw new RuntimeException('Language pack not found.', 500);
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Unable to read language pack.', 500);
    }

    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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

function startAuthenticatedSession(string $username): void
{
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    session_write_close();
    sendResponse(null, 204);
}

$db = new Storage();
$firstLogin = $db->userAmount() === 0;

switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
    case 'GET':
        $loader = new FilesystemLoader([__DIR__ . '/../templates', __DIR__ . '/../templates/auth']);
        $twig = new Environment($loader);
        echo $twig->render('auth.twig', ['lang' => language(), 'first_login' => $firstLogin]);
        break;

    case 'POST':
        try {
            $data = readJsonInput();
            $username = isset($data['username']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$data['username']) : '';
            if ($username === '' || strlen($username) > 32) {
                throw new Exception('Invalid username', 400);
            }

            $password = isset($data['password']) ? (string)$data['password'] : '';
            if ($password === '') {
                throw new Exception('Password is required', 400);
            }

            if ($firstLogin) {
                if (strlen($password) < 12) {
                    throw new Exception('Password must be at least 12 characters long', 400);
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($hash === false || !$db->createUser($username, $hash)) {
                    throw new Exception('Unable to create user');
                }

                startAuthenticatedSession($username);
            } else {
                $storedHash = $db->getPassword($username);
                if ($storedHash === null || !password_verify($password, $storedHash)) {
                    throw new Exception('Invalid username or password', 400);
                }

                startAuthenticatedSession($username);
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

