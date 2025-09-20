<?php

namespace Carlgo11\Guest_Portal\Storage;

use Carlgo11\Guest_Portal\Voucher;
use Exception;

class Storage
{
    private Redis|MariaDB $database;

    public function __construct()
    {
        $driver = strtolower((string)($_ENV['DATABASE'] ?? 'mysql'));

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                $this->database = new MariaDB();
                break;
            case 'redis':
                $this->database = new Redis();
                break;
            default:
                throw new Exception('No supported database specified');
        }
    }

    public function fetchVoucher(string $code): Voucher
    {
        return $this->database->fetchVoucher($code);
    }

    public function uploadVoucher(Voucher $voucher): bool
    {
        return $this->database->uploadVoucher($voucher);
    }

    public function removeVoucher(Voucher $voucher): bool
    {
        return $this->database->removeVoucher($voucher);
    }

    public function updateUses(Voucher $voucher, int $newUses): bool
    {
        return $this->database->updateUses($voucher, $newUses);
    }

    public function getPassword(string $username): ?string
    {
        return $this->database->getPassword($username);
    }

    public function createUser(string $username, string $hash): bool
    {
        return $this->database->createUser($username, $hash);
    }

    public function userAmount(): int
    {
        return $this->database->userAmount();
    }
}

