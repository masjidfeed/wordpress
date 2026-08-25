<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\Formatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\NodeMessage;
/** @api */
final class LocaleMessageFormatter implements MessageFormatter
{
    public function __construct(private string $locale)
    {
    }
    /** @pure */
    public function format(NodeMessage $message): NodeMessage
    {
        return $message->withLocale($this->locale);
    }
}
