<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\HasCode;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ScalarType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
final class InvalidNodeValue implements ErrorMessage, HasCode
{
    private string $body = 'Value {source_value} does not match {expected_signature}.';
    private string $code = 'invalid_value';
    public static function from(Type $type): ErrorMessage
    {
        if ($type instanceof ScalarType) {
            return $type->errorMessage();
        }
        return new self();
    }
    public function body(): string
    {
        return $this->body;
    }
    public function code(): string
    {
        return $this->code;
    }
}
