<?php

namespace DigiComp\FlowSessionLock\Tests\Functional\Fixtures\Controller;

use DigiComp\FlowSessionLock\Annotations as FlowSessionLock;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

class ExampleController extends ActionController
{
    public const CONTROLLER_TIME = 200;

    /**
     * @Flow\Session(autoStart=true);
     * @return string
     */
    public function protectedAction()
    {
        \usleep(static::CONTROLLER_TIME * 1000);
        return 'Hello World!';
    }

    /**
     * @Flow\Session(autoStart=true);
     * @FlowSessionLock\ReadOnly
     * @return string
     */
    public function unprotectedByAnnotationAction()
    {
        \usleep(static::CONTROLLER_TIME * 1000);
        return 'Hello World!';
    }

    /**
     * @Flow\Session(autoStart=true);
     * @return string
     */
    public function unprotectedByConfigurationAction()
    {
        \usleep(static::CONTROLLER_TIME * 1000);
        return 'Hello World!';
    }
}
