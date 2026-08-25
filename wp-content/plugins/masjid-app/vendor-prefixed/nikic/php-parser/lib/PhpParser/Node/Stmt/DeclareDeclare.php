<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Stmt;

use Masjid_App\Dependencies\PhpParser\Node\DeclareItem;
require __DIR__ . '/../DeclareItem.php';
if (\false) {
    /**
     * For classmap-authoritative support.
     *
     * @deprecated use \PhpParser\Node\DeclareItem instead.
     */
    class DeclareDeclare extends DeclareItem
    {
    }
}
