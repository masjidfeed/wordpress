<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\HasCode;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\HasParameters;
use RuntimeException;
use function implode;
/** @internal */
final class CannotParseToDateTime extends RuntimeException implements ErrorMessage, HasCode, HasParameters
{
    private string $body = 'Value {source_value} does not match any of the following formats: {formats}.';
    /** @var array<string, string> */
    private array $parameters;
    /**
     * @param non-empty-list<non-empty-string> $formats
     */
    public function __construct(array $formats)
    {
        $this->parameters = ['formats' => '`' . implode('`, `', $formats) . '`'];
        // @infection-ignore-all
        parent::__construct($this->body);
    }
    public function body(): string
    {
        return $this->body;
    }
    public function code(): string
    {
        return 'cannot_parse_datetime_format';
    }
    public function parameters(): array
    {
        return $this->parameters;
    }
}
