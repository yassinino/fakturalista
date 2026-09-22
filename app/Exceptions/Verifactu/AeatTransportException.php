<?php

namespace App\Exceptions\Verifactu;

/**
 * DNS/connect/timeout failure reaching AEAT - never received any
 * response at all. See docs/verifactu-aeat-connectivity.md §7.
 */
class AeatTransportException extends \RuntimeException
{
}
