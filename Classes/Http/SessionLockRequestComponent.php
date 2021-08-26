<?php

namespace DigiComp\FlowSessionLock\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;

class SessionLockRequestComponent implements ComponentInterface
{
    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="session")
     * @var array
     */
    protected $sessionSettings;

    /**
     * @Flow\Inject(lazy=false)
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject(name="DigiComp.FlowSessionLock:LockFactory")
     * @var LockFactory
     */
    protected $lockFactory;

    /**
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        $sessionCookieName = $this->sessionSettings['name'];
        $request = $componentContext->getHttpRequest();
        $cookies = $request->getCookieParams();

        if (!isset($cookies[$sessionCookieName])) {
            return;
        }

        $sessionIdentifier = $cookies[$sessionCookieName];

        $key = new Key(
            'session-' . $sessionIdentifier
        ); //TODO: sessionIdentifier might be wrong, probably it should probably be storage identifier

        $lock = $this->lockFactory->createLockFromKey($key, 300, false);

        $componentContext->setParameter(SessionLockRequestComponent::class, 'sessionLock', $lock);

        $this->logger->debug('SessionLock: Get ' . $key);
        $lock->acquire(true);
        $this->logger->debug('SessionLock: Acquired ' . $key);
    }
}
