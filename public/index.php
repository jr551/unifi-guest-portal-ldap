<?php
declare(strict_types=1);

use Carlgo11\Guest_Portal\GuestPortal;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

function sendResponse(mixed $message, int $code = 200): void
{
    http_response_code($code);
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

/**
 * @throws Exception
 */
function resolveSite(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));
    $site = $segments[1] ?? 'default';

    if ($site === 'index.php') {
        $site = 'default';
    }

    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $site)) {
        throw new Exception('Invalid site identifier', 400);
    }

    return $site;
}

switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
    case 'GET':
        $loader = new FilesystemLoader([__DIR__ . '/../templates', __DIR__ . '/../templates/index']);
        $twig = new Environment($loader);
        echo $twig->render('index.twig', [
            'lang' => language(),
            'background' => !empty($_ENV['BG_SEASONAL'])
        ]);
        break;

    case 'POST':
        try {
            $data = readJsonInput();

            $ap = isset($data['ap']) ? filter_var($data['ap'], FILTER_VALIDATE_MAC, FILTER_NULL_ON_FAILURE) : null;
            $mac = isset($data['mac']) ? filter_var($data['mac'], FILTER_VALIDATE_MAC, FILTER_NULL_ON_FAILURE) : null;
            $rawCode = isset($data['code']) ? preg_replace('/\D+/', '', (string)$data['code']) : '';
            $site = resolveSite();
            $timestamp = isset($data['t']) ? filter_var($data['t'], FILTER_VALIDATE_INT) : false;

            if ($ap === null || $mac === null || empty($rawCode) || $timestamp === false) {
                throw new Exception('Invalid request', 400);
            }

            if (strlen($rawCode) !== 10) {
                throw new Exception('Invalid code format', 400);
            }

            $age = abs(time() - (int)$timestamp);
            if ($age > 300) {
                throw new Exception('Login session expired. Rejoin the network', 412);
            }

            $guestportal = new GuestPortal($site);
            $voucher = $guestportal->validateCode($rawCode);

            if ($guestportal->useVoucher($voucher, $mac, $ap)) {
                sendResponse(['status' => 'ok']);
            }

            throw new Exception('Invalid voucher', 401);
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

