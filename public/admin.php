<?php

use Carlgo11\Guest_Portal\GuestPortal;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';
$gp = new GuestPortal();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $uses = filter_var($data['uses'], 257);
        $expiry = new DateTime('@' . filter_var($data['expiry'], 257, FILTER_NULL_ON_FAILURE));
        $duration = new DateTime('@' . filter_var($data['duration'], 257, FILTER_NULL_ON_FAILURE));
        if ($voucher = $gp->createVoucher($uses, $expiry, $duration)) {
            http_response_code(200);
            die(json_encode(['voucher' => $voucher]));
        } else throw new Exception("Unable to create voucher");
    } catch (Exception $e) {
        $error = ['error' => $e->getMessage()];
        http_response_code(500);
        die(json_encode($error));
    }
}

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);
$template = $twig->load('admin.twig');
echo $template->render();
