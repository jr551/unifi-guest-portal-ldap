<?php

namespace Carlgo11\Guest_Portal;

use DateTime;
use Exception;

class Voucher
{
    private string $id;
    private int $uses;
    private DateTime $expiry;
    private DateTime $duration;
    private int $speed_limit;

    /**
     * @throws Exception if provided input is in an unexpected/invalid format.
     */
    public function __construct(?string $code, DateTime $duration, int $uses = 1, ?DateTime $expiry = null, int $speed_limit = 0)
    {
        $this->id = $this->resolveCode($code);
        $this->uses = $this->resolveUses($uses);
        $this->expiry = $this->resolveExpiry($expiry);
        $this->duration = $this->resolveDuration($duration);
        $this->speed_limit = $this->resolveSpeedLimit($speed_limit);
    }

    public function __get(string $name): mixed
    {
        return $this->{$name} ?? null;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * @throws Exception
     */
    private function resolveCode(?string $code): string
    {
        if ($code === null) {
            return $this->generateCode();
        }

        $sanitized = preg_replace('/\D+/', '', $code);

        if ($sanitized === null || strlen($sanitized) !== 10) {
            throw new Exception('Voucher code invalid', 400);
        }

        return $sanitized;
    }

    /**
     * @throws Exception
     */
    private function resolveUses(int $uses): int
    {
        if ($uses < 0 || $uses > 254) {
            throw new Exception('Voucher uses invalid');
        }

        return $uses;
    }

    /**
     * @throws Exception
     */
    private function resolveExpiry(?DateTime $expiry): DateTime
    {
        if ($expiry === null) {
            return new DateTime('+1 day');
        }

        $clone = clone $expiry;
        if ($clone <= new DateTime()) {
            throw new Exception('Voucher has expired', 400);
        }

        return $clone;
    }

    /**
     * @throws Exception
     */
    private function resolveDuration(DateTime $duration): DateTime
    {
        $clone = clone $duration;
        if ($clone <= new DateTime()) {
            throw new Exception('Session expiry date is in the past', 400);
        }

        return $clone;
    }

    /**
     * @throws Exception
     */
    private function resolveSpeedLimit(int $speedLimit): int
    {
        if ($speedLimit < 0) {
            throw new Exception('Speed limit must be greater than or equal to 0', 400);
        }

        return $speedLimit;
    }

    /**
     * @throws Exception
     */
    private function generateCode(): string
    {
        return str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }
}

