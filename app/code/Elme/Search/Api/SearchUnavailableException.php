<?php

declare(strict_types=1);

namespace Elme\Search\Api;

/**
 * The search engine could not answer (down, timeout, index missing, bad response).
 */
class SearchUnavailableException extends \RuntimeException
{
}
