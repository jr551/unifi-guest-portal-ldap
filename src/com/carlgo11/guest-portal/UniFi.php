<?php

namespace com\carlgo11\guestportal;

use UniFi_API\Client as UniFi_API;

require_once __DIR__ . '/../../../../vendor/autoload.php';

class UniFi
{

    private UniFi_API $unifi_connection;

    public function __construct()
    {
        $this->unifi_connection = new UniFi_API($_ENV['unifi_user'], $_ENV['unifi_password'], $_ENV['unifi_url'], $_ENV['unifi_site'], $_ENV['unifi_version'], $_ENV['unifi_tls']);
        return $this->unifi_connection->login();
    }

    public function authorizeGuest(string $MACAddress, Voucher $voucher)
    {
        $this->unifi_connection->authorize_guest($MACAddress, $voucher->duration, $voucher->speed_limit, $voucher->speed_limit);
//        $this->unifi_connection->authorize_guest();
    }

    public function listClients(): array
    {
        $clients = [];
        foreach ($this->unifi_connection->list_clients() as $client) {
            $client = get_object_vars($client);
            if ($client['is_guest']) $clients[] = $client;
        }
        return $clients;
    }

}