<?php

namespace Masjid_App\Dependencies\GuzzleHttp;

use Masjid_App\Dependencies\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
