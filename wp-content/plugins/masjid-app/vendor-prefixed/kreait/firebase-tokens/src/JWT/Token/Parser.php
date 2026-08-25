<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Token;

use Masjid_App\Dependencies\Kreait\Firebase\JWT\Util;
use Masjid_App\Dependencies\Lcobucci\JWT\Decoder;
use Masjid_App\Dependencies\Lcobucci\JWT\Parser as ParserInterface;
use Masjid_App\Dependencies\Lcobucci\JWT\Token;
use Masjid_App\Dependencies\Lcobucci\JWT\Token\Parser as SecureParser;
final class Parser implements ParserInterface
{
    private ParserInterface $parser;
    public function __construct(public readonly Decoder $decoder)
    {
        if (Util::authEmulatorHost() !== '') {
            $this->parser = new InsecureParser($decoder);
        } else {
            $this->parser = new SecureParser($decoder);
        }
    }
    public function parse(string $jwt): Token
    {
        return $this->parser->parse($jwt);
    }
}
