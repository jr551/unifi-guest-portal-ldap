<?php

namespace com\carlgo11\guestportal;

use DateTime;
use Exception;

class Voucher
{
    private int $uuid;
    private int $uses;
    private DateTime|null $expiry;
    private int|null $duration;
    private int|null $speed_limit;

    /**
     * @throws Exception if provided input is in an unexpected/invalid format.
     */
    public function __construct(int $uuid = NULL, int $uses = 1, DateTime $expiry = NULL, int $duration = NULL, int $speed_limit = NULL)
    {
        // Validate UUID
        if ($uuid === NULL) $this->uuid = $this->generateUUID();
        else {
            if (strlen($uuid) === 10) $this->uuid = $uuid;
            else throw new Exception("UUID input invalid");
        }

        // Validate uses
        if ($uses >= 0 && $uses < 255)
            $this->uses = $uses;
        else throw new Exception("Uses input invalid");

        // Validate (voucher) expiry date
        if ($expiry !== NULL) {
            if ($expiry > new DateTime()) $this->expiry = $expiry;
            else throw new Exception("Expiry date is in the past");
        } else {
            $this->expiry = new DateTime('+1 day');
        }

        // Validate (session) duration
        if ($duration === NULL || $duration > 0) $this->duration = $duration;

        // Validate (transfer) speed limit
        if ($speed_limit === NULL || $speed_limit > 0) {
            $this->speed_limit = $speed_limit;
        }
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if (isset($this->{$name}))
            return $this->{$name};
        else throw new Exception('No such variable found');
    }

    /**
     * @throws Exception
     */
    private function generateUUID(): int
    {
        return (int)str_pad(random_int(0, 99999_99999), 10, '0');

    }

    public function __toString()
    {
        return (string)$this->uuid;
    }
}