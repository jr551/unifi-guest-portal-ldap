<?php

namespace Carlgo11\Guest_Portal;

use DateTimeImmutable;
use Exception;
use UniFi_API\Client as UniFi_API;

class UniFi
{
    private UniFi_API $unifi_connection;

    /**
     * @throws Exception
     */
    public function __construct(string $site)
    {
        $username = $_ENV['UNIFI_USER'] ?? '';
        $password = $_ENV['UNIFI_PASSWORD'] ?? '';
        $url = $_ENV['UNIFI_URL'] ?? '';
        $version = $_ENV['UNIFI_VERSION'] ?? 'v6';
        $verifyCert = filter_var($_ENV['UNIFI_VERIFY_CERT'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $verifyCert = $verifyCert ?? true;

        $this->unifi_connection = new UniFi_API($username, $password, $url, $site, $version, $verifyCert);

        if ($this->unifi_connection->login() !== true) {
            throw new Exception('Unable to access Unifi system.', 503);
        }
    }

    public function __destruct()
    {
        if (isset($this->unifi_connection)) {
            $this->unifi_connection->logout();
        }
    }

    /**
     * @throws Exception
     */
    public function authorizeGuest(string $macAddress, Voucher $voucher, ?string $ap = null): bool
    {
        $now = new DateTimeImmutable();
        $sessionExpiry = DateTimeImmutable::createFromMutable($voucher->duration);
        $secondsRemaining = max(0, $sessionExpiry->getTimestamp() - $now->getTimestamp());

        if ($secondsRemaining === 0) {
            throw new Exception('Voucher session has already expired.', 400);
        }

        $minutes = (int)max(1, ceil($secondsRemaining / 60));
        $speedLimit = (int)$voucher->speed_limit * 1024;

        return $this->unifi_connection->authorize_guest(
            mac: $macAddress,
            minutes: $minutes,
            up: $speedLimit,
            down: $speedLimit,
            megabytes: null,
            ap_mac: $ap
        );
    }

    public function isOnline(string $mac): bool
    {
        $clients = $this->unifi_connection->list_clients($mac);

        return is_array($clients) && !empty($clients);
    }
}

