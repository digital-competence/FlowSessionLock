<?php

declare(strict_types=1);

namespace DigiComp\FlowSessionLock\Http;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;


class SessionLockRequestMiddleware implements MiddlewareInterface
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
     * @Flow\InjectConfiguration(package="DigiComp.FlowSessionLock", path="secondsToWait")
     * @var int
     */
    protected int $secondsToWait;

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionCookieName = $this->sessionSettings['name'];

        $cookies = $request->getCookieParams();
        if (!isset($cookies[$sessionCookieName])) {
            return $handler->handle($request);
        }

        // TODO: sessionIdentifier might be wrong, probably it should probably be storage identifier
        $key = new Key('session-' . $cookies[$sessionCookieName]);

        $lock = $this->lockFactory->createLockFromKey($key, $this->timeToLive, $this->autoRelease);

        $request = $request->withAttribute(SessionLockRequestMiddleware::class . '.' . static::PARAMETER_NAME, $lock);

        $this->logger->debug('SessionLock: Try to get "' . $key . '"');
        $timedOut = \time() + $this->secondsToWait;
        while (!$lock->acquire()) {
            if (\time() >= $timedOut) {
                throw new LockAcquiringException(
                    'Could not acquire the lock for "' . $key . '" in ' . $this->secondsToWait . ' seconds.',
                    1652687960
                );
            }
            \usleep(100000);
        }
        $this->logger->debug('SessionLock: Acquired "' . $key . '"');
        return $handler->handle($request);
    }
}
