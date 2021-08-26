<?php

namespace DigiComp\FlowSessionLock\Aspects;

use DigiComp\FlowSessionLock\Http\SessionLockRequestComponent;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Lock;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ReadOnlyAspect
{
    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected bool $readOnly = false;

    /**
     * @Flow\Around("methodAnnotatedWith(DigiComp\FlowSessionLock\Annotations\ReadOnly) || filter(DigiComp\FlowSessionLock\Aspects\ReadOnlyFilter)")
     * @param JoinPointInterface $joinPoint
     *
     * @return void
     */
    public function demoteLockToReadOnly(JoinPointInterface $joinPoint)
    {
        $handler = $this->bootstrap->getActiveRequestHandler();
        if (! $handler instanceof HttpRequestHandlerInterface) {
            $this->logger->debug(\get_class($handler));
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }
        $componentContext = $handler->getComponentContext();
        /** @var Lock $lock */
        $lock = $componentContext->getParameter(SessionLockRequestComponent::class, 'sessionLock');
        $this->readOnly = true;
        if ($lock) {
            $this->logger->debug('SessionLock: Release, as this is marked read only');
            $lock->release();
        }
        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    /**
     * @Flow\Around("method(Neos\Flow\Session\Session->shutdownObject())")
     *
     * @param JoinPointInterface $joinPoint
     */
    public function doNotSaveSession(JoinPointInterface $joinPoint)
    {
        if ($this->readOnly) {
            return;
        }
        $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
