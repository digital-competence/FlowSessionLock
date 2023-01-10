<?php

declare(strict_types=1);

namespace DigiComp\FlowSessionLock\Aspects;

use DigiComp\FlowSessionLock\Http\SessionLockRequestMiddleware;
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
     * @Flow\Around("methodAnnotatedWith(DigiComp\FlowSessionLock\Annotations\Unlock) || filter(DigiComp\FlowSessionLock\Aspects\ReadOnlyFilter)")
     * @param JoinPointInterface $joinPoint
     * @return mixed
     */
    public function demoteLockToReadOnly(JoinPointInterface $joinPoint)
    {
        $activeRequestHandler = $this->bootstrap->getActiveRequestHandler();
        if (!$activeRequestHandler instanceof HttpRequestHandlerInterface) {
            $this->logger->debug('SessionLock: ' . \get_class($activeRequestHandler));

            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        $this->readOnly = true;

        /** @var Lock|null $lock */
        $lock = $activeRequestHandler->getHttpRequest()->getAttribute(
            SessionLockRequestMiddleware::class . '.' . SessionLockRequestMiddleware::PARAMETER_NAME
        );
        if ($lock !== null) {
            $this->logger->debug('SessionLock: Release, as this is marked read only.');
            $lock->release();
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    /**
     * @Flow\Around("method(Neos\Flow\Session\Session->shutdownObject())")
     * @param JoinPointInterface $joinPoint
     * @return mixed|void
     */
    public function doNotSaveSession(JoinPointInterface $joinPoint)
    {
        if ($this->readOnly) {
            return;
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
