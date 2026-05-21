<?php

namespace App\Models\Intelligence;

/**
 * entry Entered into a billing workflow
 * start
 * complete
 * comparison
 * error
 * preauthorize
 * wall
 * custom
 * lockout
 *
 * cart_add
 * cart_update
 * cart_replace
 * transaction_initiate
 * transaction_complete
 * transaction_cancel
 * // activity activity.start() )
 */
enum ActionType: int
{
    // Workflow
    case Entry = 0;
    case Start = 1;
    case Complete = 2;
    case Cancel = 3;
    case Error = 4;
    case Comparison = 10;
    case Preauthorize = 20;
    case Transaction = 30;
    case TransactionStart = 31;
    case Interaction = 51;
    case Custom = 99;

    public static function make($string): ?self
    {
        return match (strtolower($string)) {
            'entry' => self::Entry,
            'start' => self::Start,
            'complete' => self::Complete,
            'comparison' => self::Comparison,
            'cancel' => self::Cancel,
            'error' => self::Error,
            'preauthorize' => self::Preauthorize,
            'transaction' => self::Transaction,
            'transaction_initiate' => self::TransactionStart,
            'transaction_complete' => self::Complete,
            default => self::Custom,
        };
    }

    public function getKey(): ?string
    {
        return match($this) {
            self::Entry => 'entry',
            self::Start => 'start',
            self::Cancel => 'cancel',
            self::Complete => 'complete',
            self::Comparison => 'comparison',
            self::Error => 'error',
            self::Preauthorize => 'preauthorize',
            self::Interaction => 'interaction',
            self::Transaction, self::TransactionStart => 'transaction',
            self::Custom => 'custom',
        };
    }

    public function canStartActivities(): bool
    {
        return in_array($this, [
            self::Start,
            self::Comparison,
            self::Error,
            self::Preauthorize,
            self::Custom,
            self::Transaction,
            self::Interaction,
        ]);
    }

    public function canCompleteActivities(): bool
    {
        return in_array($this, [
            self::Complete,
        ]);
    }

    public function isInteraction(): bool
    {
        return in_array($this, [
            self::Start,
            self::Comparison,
            self::Cancel,
            self::Complete,
            self::Preauthorize,
            self::Transaction,
            self::Interaction,
            self::TransactionStart,
        ]);
    }

    public function isError()
    {
        return in_array($this, [
            self::Error,
        ]);
    }
}
