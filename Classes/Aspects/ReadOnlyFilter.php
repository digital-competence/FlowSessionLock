<?php

namespace DigiComp\FlowSessionLock\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\Builder\ClassNameIndex;
use Neos\Flow\Aop\Pointcut\PointcutFilterComposite;
use Neos\Flow\Aop\Pointcut\PointcutFilterInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Security\Authorization\Privilege\Method\MethodTargetExpressionParser;

/**
 * @Flow\Proxy(false)
 * @Flow\Scope("singleton")
 */
class ReadOnlyFilter implements PointcutFilterInterface
{
    protected ConfigurationManager $configurationManager;

    protected MethodTargetExpressionParser $methodTargetExpressionParser;

    /**
     * @var PointcutFilterComposite[]
     */
    protected ?array $filters = null;

    public function injectConfigurationManager(ConfigurationManager $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

    public function injectMethodTargetExpressionParser(MethodTargetExpressionParser $methodTargetExpressionParser): void
    {
        $this->methodTargetExpressionParser = $methodTargetExpressionParser;
    }

    /**
     * @inheritDoc
     */
    public function matches($className, $methodName, $methodDeclaringClassName, $pointcutQueryIdentifier): bool
    {
        if ($this->filters === null) {
            $this->buildPointcutFilters();
        }

        $matchingFilters = \array_filter(
            $this->filters,
            function (PointcutFilterInterface $filter) use (
                $className,
                $methodName,
                $methodDeclaringClassName,
                $pointcutQueryIdentifier
            ): bool {
                return $filter->matches($className, $methodName, $methodDeclaringClassName, $pointcutQueryIdentifier);
            }
        );

        if ($matchingFilters === []) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasRuntimeEvaluationsDefinition(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getRuntimeEvaluationsDefinition(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function reduceTargetClassNames(ClassNameIndex $classNameIndex): ClassNameIndex
    {
        if ($this->filters === null) {
            $this->buildPointcutFilters();
        }

        $result = new ClassNameIndex();
        foreach ($this->filters as $filter) {
            $result->applyUnion($filter->reduceTargetClassNames($classNameIndex));
        }
        return $result;
    }

    protected function buildPointcutFilters(): void
    {
        $this->filters = [];
        $readOnlyExpressions = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'DigiComp.FlowSessionLock.readOnlyExpressions'
        ) ?? [];
        foreach ($readOnlyExpressions as $key => $pointcut) {
            if ($pointcut === null) {
                continue;
            }
            $this->filters[] = $this->methodTargetExpressionParser->parse(
                $pointcut,
                'Settings.yaml at "DigiComp.FlowSessionLock.readOnlyExpressions", key: "' . $key . '"'
            );
        }
    }
}
