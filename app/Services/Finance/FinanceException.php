<?php

namespace App\Services\Finance;

use RuntimeException;

/**
 * A finance action that cannot go ahead; the message is safe to show.
 */
class FinanceException extends RuntimeException
{
}
