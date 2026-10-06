<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A gateway refused or could not process a request. The message is safe to
 * show to the customer.
 */
class GatewayException extends RuntimeException
{
}
