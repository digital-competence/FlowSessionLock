<?php

namespace DigiComp\FlowSessionLock;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Utility\Files;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        parent::boot($bootstrap);
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(
            ConfigurationManager::class,
            'configurationManagerReady',
            function (ConfigurationManager $configurationManager) {
                $lockStoreDir = $configurationManager->getConfiguration(
                    ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                    'DigiComp.FlowSessionLock.lockStoreDir'
                );
                if (is_string($lockStoreDir)) {
                    Files::createDirectoryRecursively($lockStoreDir);
                }
            }
        );
    }
}
