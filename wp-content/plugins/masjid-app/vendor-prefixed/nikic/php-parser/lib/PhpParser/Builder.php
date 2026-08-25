<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser;

interface Builder
{
    /**
     * Returns the built node.
     *
     * @return Node The built node
     */
    public function getNode(): Node;
}
