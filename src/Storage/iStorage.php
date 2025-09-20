<?php

namespace Carlgo11\Guest_Portal\Storage;

use Carlgo11\Guest_Portal\Voucher;
use Exception;

interface iStorage
{
    /**
     * Initiate database connection.
     *
     * @throws Exception Throws exception if unable to connect to database.
     */
    public function __construct();

    /**
     * Close database connection.
     */
    public function __destruct();

    /**
     * Fetch voucher data from storage.
     *
     * @param int $code voucher code.
     */
    public function fetchVoucher(string $code): Voucher;

    /**
     * Upload a {@link Voucher} to storage.
     */
    public function uploadVoucher(Voucher $voucher): bool;

    /**
     * Remove a {@link Voucher} from storage.
     */
    public function removeVoucher(Voucher $voucher): bool;

    public function updateUses(Voucher $voucher, int $newUses): bool;

    /**
     * Get password hash for a user.
     */
    public function getPassword(string $username): ?string;

    /**
     * Create a new user.
     */
    public function createUser(string $username, string $hash): bool;

    /**
     * Get amount of users as an integer.
     */
    public function userAmount(): int;
}

