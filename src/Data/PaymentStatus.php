<?php

declare(strict_types=1);

namespace IgniteLabs\BmlConnect\Data;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public static function fromBml(string $status): self
    {
        return match (strtoupper($status)) {
            'READY' => self::INITIATED,
            'PENDING' => self::PENDING,
            'SUCCESS' => self::SUCCEEDED,
            'FAIL' => self::FAILED,
            'CANCELLED' => self::CANCELLED,
            'EXPIRED' => self::EXPIRED,
            default => self::FAILED, // Fallback for unknown statuses in payment contexts
        };
    }

    /** Payment was captured successfully. Safe to fulfill the order. */
    public function isSucceeded(): bool
    {
        return $this === self::SUCCEEDED;
    }

    /** Payment was declined, errored, or otherwise not completed. */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /** Payment is actively being processed by the bank. */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * The transaction has reached a final, non-reversible state.
     * No further status changes are expected from BML.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }

    /**
     * The transaction is still in-flight and you should poll or wait for a
     * webhook before taking action.
     */
    public function requiresPolling(): bool
    {
        return in_array($this, [self::INITIATED, self::PENDING], true);
    }
}
