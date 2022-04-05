<?php

namespace DigiComp\FlowSessionLock\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;

class SessionLockRequestComponent implements ComponentInterface
{
    public const PARAMETER_NAME = 'sessionLock';

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject(name="DigiComp.FlowSessionLock:LockFactory")
     * @var LockFactory
     */
    protected $lockFactory;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="session")
     * @var array
     */
    protected array $sessionSettings;

    /**
     * @Flow\InjectConfiguration(package="DigiComp.FlowSessionLock", path="timeToLive")
     * @var float
     */
    protected float $timeToLive;

    /**
     * @Flow\InjectConfiguration(package="DigiComp.FlowSessionLock", path="autoRelease")
     * @var bool
     */
    protected bool $autoRelease;

    /**
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        $sessionCookieName = $this->sessionSettings['name'];

        $cookies = $componentContext->getHttpRequest()->getCookieParams();
        if (!isset($cookies[$sessionCookieName])) {
            return;
        }

        // TODO: sessionIdentifier might be wrong, probably it should probably be storage identifier
        $key = new Key('session-' . $cookies[$sessionCookieName]);

        $lock = $this->lockFactory->createLockFromKey($key, $this->timeToLive, $this->autoRelease);

        $componentContext->setParameter(SessionLockRequestComponent::class, static::PARAMETER_NAME, $lock);

        $this->logger->debug('SessionLock: Get ' . $key);
        $lock->acquire(true);
        $this->logger->debug('SessionLock: Acquired ' . $key);
    }
}
