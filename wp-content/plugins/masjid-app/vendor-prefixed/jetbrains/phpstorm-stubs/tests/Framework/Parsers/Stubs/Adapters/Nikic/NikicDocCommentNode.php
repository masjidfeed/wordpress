<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Adapters\Nikic;

use Masjid_App\Dependencies\PhpParser\Comment\Doc;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Nodes\DocCommentNode;
/**
 * Adapter for nikic/php-parser Doc comment nodes.
 */
class NikicDocCommentNode implements DocCommentNode
{
    private Doc $docComment;
    public function __construct(Doc $docComment)
    {
        $this->docComment = $docComment;
    }
    public function getText(): string
    {
        return $this->docComment->getText();
    }
}
