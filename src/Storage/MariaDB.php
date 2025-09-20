<?php

namespace Carlgo11\Guest_Portal\Storage;

use Carlgo11\Guest_Portal\Voucher;
use DateTime;
use Exception;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

class MariaDB implements iStorage
{
    private int $users = -1;
    private mysqli $mysql;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!extension_loaded('mysqli')) {
            throw new Exception('MySQLi not enabled on the server', 501);
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->mysql = mysqli_init();
            $host = $_ENV['MYSQL_HOST'] ?? 'localhost';
            $user = $_ENV['MYSQL_USER'] ?? '';
            $password = $_ENV['MYSQL_PASSWORD'] ?? '';
            $database = $_ENV['MYSQL_DATABASE'] ?? '';
            $port = (int)($_ENV['MYSQL_PORT'] ?? 3306);

            if (!$this->mysql->real_connect($host, $user, $password, $database, $port)) {
                throw new Exception('Could not connect to database.');
            }
        } catch (mysqli_sql_exception $exception) {
            throw new Exception('Database connection failed: ' . $exception->getMessage(), 500, $exception);
        }
    }

    public function __destruct()
    {
        if (isset($this->mysql)) {
            $this->mysql->close();
        }
    }

    /**
     * @throws Exception
     */
    public function fetchVoucher(string $code): Voucher
    {
        $statement = $this->mysql->prepare('SELECT `duration`, `uses`, `expiry`, `speed_limit` FROM `vouchers` WHERE `id` = ?');
        $statement->bind_param('s', $code);
        $statement->execute();
        $result = $this->fetchFirstRow($statement->get_result());
        $statement->close();

        if ($result === null) {
            throw new Exception('Code not found', 404);
        }

        $expiry = (new DateTime())->setTimestamp((int)$result['expiry']);
        $duration = (new DateTime())->setTimestamp((int)$result['duration']);

        return new Voucher($code, $duration, (int)$result['uses'], $expiry, (int)$result['speed_limit']);
    }

    public function uploadVoucher(Voucher $voucher): bool
    {
        $statement = $this->mysql->prepare('INSERT INTO `vouchers` (`id`, `uses`, `expiry`, `duration`, `speed_limit`) VALUES (?, ?, ?, ?, ?)');
        $id = (string)$voucher->id;
        $uses = (int)$voucher->uses;
        $expiry = $voucher->expiry->getTimestamp();
        $duration = $voucher->duration->getTimestamp();
        $speedLimit = (int)$voucher->speed_limit;
        $statement->bind_param('siiii', $id, $uses, $expiry, $duration, $speedLimit);
        $result = $statement->execute();
        $statement->close();

        return $result;
    }

    public function removeVoucher(Voucher $voucher): bool
    {
        $statement = $this->mysql->prepare('DELETE FROM `vouchers` WHERE `id` = ?');
        $id = (string)$voucher->id;
        $statement->bind_param('s', $id);
        $result = $statement->execute();
        $statement->close();

        return $result;
    }

    public function updateUses(Voucher $voucher, int $newUses): bool
    {
        $statement = $this->mysql->prepare('UPDATE `vouchers` SET `uses` = ? WHERE `id` = ?');
        $id = (string)$voucher->id;
        $statement->bind_param('is', $newUses, $id);
        $result = $statement->execute();
        $statement->close();

        return $result;
    }

    public function getPassword(string $username): ?string
    {
        $statement = $this->mysql->prepare('SELECT `password` FROM `users` WHERE `username` = ?');
        $statement->bind_param('s', $username);
        $statement->execute();
        $result = $this->fetchFirstRow($statement->get_result());
        $statement->close();

        return $result['password'] ?? null;
    }

    public function createUser(string $username, string $hash): bool
    {
        $statement = $this->mysql->prepare('INSERT INTO `users` (`username`, `password`) VALUES (?, ?)');
        $statement->bind_param('ss', $username, $hash);
        $result = $statement->execute();
        $statement->close();

        if ($result) {
            $this->users = -1;
        }

        return $result;
    }

    public function userAmount(): int
    {
        if ($this->users >= 0) {
            return $this->users;
        }

        $statement = $this->mysql->prepare('SELECT COUNT(*) AS total FROM `users`');
        $statement->execute();
        $result = $this->fetchFirstRow($statement->get_result());
        $statement->close();

        $this->users = (int)($result['total'] ?? 0);

        return $this->users;
    }

    private function fetchFirstRow(?mysqli_result $result): ?array
    {
        if ($result === null) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }
}

