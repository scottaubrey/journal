<?php

namespace eLife\Journal\Guzzle;

use eLife\Journal\Etoc\Newsletter;
use GuzzleHttp\Promise\PromiseInterface;

interface UnsubscribeOptOutReasonInterface
{
    public function record($reasons = [], string $other = null, string $identifier = null);
}
