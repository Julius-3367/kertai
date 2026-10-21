<?php

namespace App\Support;

use RuntimeException;

/**
 * A group rule stopped an action (not enough in the fund, payout already
 * approved, notice period not over). The message is written for the person
 * who clicked the button and is shown to them as-is.
 */
final class RuleViolation extends RuntimeException {}
