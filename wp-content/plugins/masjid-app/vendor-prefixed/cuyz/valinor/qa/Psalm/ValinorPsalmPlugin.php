<?php

namespace Masjid_App\Dependencies\CuyZ\Valinor\QA\Psalm;

use Masjid_App\Dependencies\CuyZ\Valinor\QA\Psalm\Plugin\ArgumentsMapperPsalmPlugin;
use Masjid_App\Dependencies\CuyZ\Valinor\QA\Psalm\Plugin\TreeMapperPsalmPlugin;
use Masjid_App\Dependencies\Psalm\Plugin\PluginEntryPointInterface;
use Masjid_App\Dependencies\Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;
class ValinorPsalmPlugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $api, ?SimpleXMLElement $config = null): void
    {
        require_once __DIR__ . '/Plugin/TreeMapperPsalmPlugin.php';
        require_once __DIR__ . '/Plugin/ArgumentsMapperPsalmPlugin.php';
        $api->registerHooksFromClass(TreeMapperPsalmPlugin::class);
        $api->registerHooksFromClass(ArgumentsMapperPsalmPlugin::class);
    }
}
