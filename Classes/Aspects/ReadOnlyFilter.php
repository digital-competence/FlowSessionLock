<?php

declare(strict_types=1);

namespace DigiComp\FlowSessionLock\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\Builder\ClassNameIndex;
use Neos\Flow\Aop\Exception as NeosFlowAopException;
use Neos\Flow\Aop\Exception\InvalidPointcutExpressionException;
use Neos\Flow\Aop\Pointcut\PointcutFilterComposite;
use Neos\Flow\Aop\Pointcut\PointcutFilterInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Security\Authorization\Privilege\Method\MethodTargetExpressionParser;

/**
 * @Flow\Proxy(false)
 * @Flow\Scope("singleton")
 */
class ReadOnlyFilter implements PointcutFilterInterface
{
    /**
     * @var ConfigurationManager
     */
    protected ConfigurationManager $configurationManager;

    /**
     * @var MethodTargetExpressionParser
     */
    protected MethodTargetExpressionParser $methodTargetExpressionParser;

    /**
     * @var PointcutFilterComposite[]
     */
    protected ?array $pointcutFilterComposites = null;

    /**
     * @param ConfigurationManager $configurationManager
     */
    public function injectConfigurationManager(ConfigurationManager $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @param MethodTargetExpressionParser $methodTargetExpressionParser
     */
    public function injectMethodTargetExpressionParser(MethodTargetExpressionParser $methodTargetExpressionParser): void
    {
        $this->methodTargetExpressionParser = $methodTargetExpressionParser;
    }

    /**
     * @inheritDoc
     * @throws InvalidConfigurationTypeException
     * @throws InvalidPointcutExpressionException
     * @throws NeosFlowAopException
     */
    public function matches($className, $methodName, $methodDeclaringClassName, $pointcutQueryIdentifier): bool
    {
        $this->buildPointcutFilters();

        foreach ($this->pointcutFilterComposites as $pointcutFilterComposite) {
            if (
                $pointcutFilterComposite->matches(
                    $className,
                    $methodName,
                    $methodDeclaringClassName,
                    $pointcutQueryIdentifier
                )
            ) {
                return true;
            }
        }

        return false;
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
     * @throws InvalidConfigurationTypeException
     * @throws InvalidPointcutExpressionException
     * @throws NeosFlowAopException
     */
    public function reduceTargetClassNames(ClassNameIndex $classNameIndex): ClassNameIndex
    {
        $this->buildPointcutFilters();

        $result = new ClassNameIndex();

        foreach ($this->pointcutFilterComposites as $pointcutFilterComposite) {
            $result->applyUnion($pointcutFilterComposite->reduceTargetClassNames($classNameIndex));
        }

        return $result;
    }

    /**
     * @throws InvalidConfigurationTypeException
     * @throws InvalidPointcutExpressionException
     * @throws NeosFlowAopException
     */
    protected function buildPointcutFilters(): void
    {
        if ($this->pointcutFilterComposites !== null) {
            return;
        }

        $this->pointcutFilterComposites = [];
        foreach (
            $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'DigiComp.FlowSessionLock.readOnlyExpressions'
            ) as $key => $pointcutExpression
        ) {
            if ($pointcutExpression === null) {
                continue;
            }

            $this->pointcutFilterComposites[] = $this->methodTargetExpressionParser->parse(
                $pointcutExpression,
                'Settings.yaml at "DigiComp.FlowSessionLock.readOnlyExpressions", key: "' . $key . '"'
            );
        }
    }
}
