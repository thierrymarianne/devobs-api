<?php

namespace App\Exception;

use Exception;

/**
 * @see https://en.wikipedia.org/wiki/Bottom_type
 *
 * Soft addition (in docblock) of this exception as a return value
 * of a method is another way how to highlight function calls,
 * which could raise exception at runtime.
 */
class ⊥ extends Exception
{
}