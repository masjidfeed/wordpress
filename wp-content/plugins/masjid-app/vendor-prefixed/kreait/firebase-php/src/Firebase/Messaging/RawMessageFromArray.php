<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Messaging;

use Masjid_App\Dependencies\Beste\Json;
/**
 * @phpstan-import-type MessageInputShape from Message
 * @phpstan-import-type MessageOutputShape from Message
 */
final class RawMessageFromArray implements Message
{
    /**
     * @param MessageInputShape $data
     */
    public function __construct(private readonly array $data)
    {
    }
    public function jsonSerialize(): array
    {
        return Json::decode(Json::encode($this->data), \true);
    }
}
