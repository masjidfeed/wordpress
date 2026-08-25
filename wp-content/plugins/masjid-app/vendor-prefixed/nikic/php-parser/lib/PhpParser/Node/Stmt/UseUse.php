<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Stmt;

use Masjid_App\Dependencies\PhpParser\Node\UseItem;
require __DIR__ . '/../UseItem.php';
if (\false) {
    /**
     * For classmap-authoritative support.
     *
     * @deprecated use \PhpParser\Node\UseItem instead.
     */
    class UseUse extends UseItem
    {
    }
}
