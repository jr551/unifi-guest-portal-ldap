<?php

namespace Carlgo11\Guest_Portal;

use Carlgo11\Guest_Portal\Storage\Storage;
use DateTime;
use Exception;

class GuestPortal
{
    private string $site;

    public function __construct(string $site = 'default')
    {
        $this->site = $site;
    }

    public function validateCode(string|int $code): Voucher
    {
        $digitsOnly = preg_replace('/\D+/', '', (string)$code);
        if ($digitsOnly === null || strlen($digitsOnly) !== 10) {
            throw new Exception('Invalid code format', 400);
        }

        $db = new Storage();

        return $db->fetchVoucher($digitsOnly);
    }

    public function useVoucher(Voucher $voucher, string $mac, ?string $ap = null): bool
    {
        $uniFi = new UniFi($this->site);
        if (!$uniFi->isOnline($mac)) {
            throw new Exception('Client not connected to WLAN', 412);
        }

        if ($uniFi->authorizeGuest($mac, $voucher, $ap)) {
            $db = new Storage();
            if ($voucher->uses <= 1) {
                $db->removeVoucher($voucher);
            } else {
                $db->updateUses($voucher, $voucher->uses - 1);
            }

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function createVoucher(int $uses, DateTime $expiry, DateTime $duration): ?string
    {
        $voucher = new Voucher(null, $duration, $uses, $expiry);
        $db = new Storage();

        if ($db->uploadVoucher($voucher)) {
            return $voucher->id;
        }

        return null;
    }
}

