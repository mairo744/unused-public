<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Collectors;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use TomasVotruba\UnusedPublic\ClassTypeDetector;
use TomasVotruba\UnusedPublic\Configuration;

/**
 * @implements Collector<FuncCall, array<string>|null>
 */
final class CallUserFuncCollector implements Collector
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly ClassTypeDetector $classTypeDetector,
    ) {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return string[]|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (! $this->configuration->shouldCollectMethods()) {
            return null;
        }

        // unable to resolve method name
        if ($node->name instanceof Expr) {
            return null;
        }

        if (strtolower($node->name->toString()) !== 'call_user_func') {
            return null;
        }

        $args = $node->getArgs();
        if (count($args) < 1) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection instanceof ClassReflection) {
            // skip calls in tests, as they are not used in production
            if ($this->classTypeDetector->isTestClass($classReflection)) {
                return null;
            }
        }

        $callableType = $scope->getType($args[0]->value);
        if (! $callableType instanceof ConstantArrayType) {
            return null;
        }

        $typeAndMethodNames = $callableType->findTypeAndMethodNames();
        if ($typeAndMethodNames === []) {
            return null;
        }

        $classMethodReferences = [];
        foreach ($typeAndMethodNames as $typeAndMethodName) {
            $objectClassNames = $typeAndMethodName->getType()
                ->getObjectClassNames();
            foreach ($objectClassNames as $objectClassName) {
                $classMethodReferences[] = $objectClassName . '::' . $typeAndMethodName->getMethod();
            }
        }

        return $classMethodReferences;
    }
}
