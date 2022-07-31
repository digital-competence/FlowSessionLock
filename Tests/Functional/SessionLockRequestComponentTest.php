<?php

namespace DigiComp\FlowSessionLock\Tests\Functional;

use DigiComp\FlowSessionLock\Tests\Functional\Fixtures\Controller\ExampleController;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Http\Cookie;
use Neos\Flow\Mvc\Routing\Route;
use Neos\Flow\Tests\FunctionalTestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;

class SessionLockRequestComponentTest extends FunctionalTestCase
{
    protected ServerRequestFactoryInterface $serverRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverRequestFactory = $this->objectManager->get(ServerRequestFactoryInterface::class);
        $route = new Route();
        $route->setName('Functional Test - SessionRequestComponent::Restricted');
        $route->setUriPattern('test/sessionlock/{@action}');
        $route->setDefaults([
            '@package' => 'DigiComp.FlowSessionLock',
            '@subpackage' => 'Tests\Functional\Fixtures',
            '@controller' => 'Example',
            '@action' => 'protected',
            '@format' => 'html',
        ]);
        $route->setAppendExceedingArguments(true);
        $this->router->addRoute($route);
    }

    public function expectedDuration(): array
    {
        $parallelChecker = function ($allRequests, $oneRequest) {
            self::assertGreaterThan(ExampleController::CONTROLLER_TIME, $oneRequest * 1000);
            self::assertLessThan(ExampleController::CONTROLLER_TIME * 4, $allRequests * 1000);
        };
        return [
            [
                'http://localhost/test/sessionlock/protected',
                function ($allRequests, $oneRequest) {
                    self::assertGreaterThan(ExampleController::CONTROLLER_TIME, $oneRequest * 1000);
                    self::assertGreaterThan(ExampleController::CONTROLLER_TIME * 4, $allRequests * 1000);
                },
            ],
            [
                'http://localhost/test/sessionlock/unprotectedbyannotation',
                $parallelChecker,
            ],
            [
                'http://localhost/test/sessionlock/unprotectedbyconfiguration',
                $parallelChecker,
            ],
        ];
    }

    /**
     * @dataProvider expectedDuration
     * @test
     */
    public function itDoesNotAllowToEnterMoreThanOneWithTheSameSession(string $url, \Closure $checker): void
    {
        // Functional tests are currently broken, until a version containing
        // https://github.com/neos/flow-development-collection/commit/bebfc4e6566bc4ba2ba28330344105adb2d6ada0
        // gets released
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', new Uri($url));
        $start = \microtime(true);
        $response = $this->browser->sendRequest($request);
        $neededForOne = \microtime(true) - $start;

        $sessionCookies = \array_map(static function ($cookie) {
            return Cookie::createFromRawSetCookieHeader($cookie);
        }, $response->getHeader('Set-Cookie'));
        self::assertNotEmpty($sessionCookies);

        $cookies = \array_reduce($sessionCookies, static function ($out, $cookie) {
            $out[$cookie->getName()] = $cookie->getValue();
            return $out;
        }, []);
        $nextRequest = $this->serverRequestFactory
            ->createServerRequest('GET', new Uri($url))
            ->withCookieParams($cookies);
        $childs = [];
        $start = \microtime(true);
        for ($i = 0; $i < 4; $i++) {
            $child = \pcntl_fork();
            if ($child === 0) {
                $this->browser->sendRequest($nextRequest);
                exit();
            }
            $childs[] = $child;
        }
        foreach ($childs as $child) {
            \pcntl_waitpid($child, $status);
        }
        $neededForAll = \microtime(true) - $start;

        $checker($neededForAll, $neededForOne);
    }
}
