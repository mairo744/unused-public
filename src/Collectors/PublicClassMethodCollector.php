<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Collectors;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Reflection\ResolvedMethodReflection;
use TomasVotruba\UnusedPublic\ApiDocStmtAnalyzer;
use TomasVotruba\UnusedPublic\Configuration;
use TomasVotruba\UnusedPublic\MethodTypeDetector;
use TomasVotruba\UnusedPublic\PublicClassMethodMatcher;

/**
 * @implements Collector<ClassMethod, array{class-string, string, int}|null>
 */
final class PublicClassMethodCollector implements Collector
{
    /**
     * @var string[]
     */
    private const SKIPPED_TYPES = [
        // symfony
        'Symfony\Component\EventDispatcher\EventSubscriberInterface',
        'Twig\Extension\ExtensionInterface',
        'Symfony\Bundle\FrameworkBundle\Controller\Controller',
        // laravel
        'Illuminate\Console\Command',
        'Livewire\Component',
        'Illuminate\Http\Request',
        'Illuminate\Contracts\Mail\Mailable',
        'Illuminate\Contracts\Queue\ShouldQueue',
        'Illuminate\Support\ServiceProvider',
    ];

    public function __construct(
        private readonly ApiDocStmtAnalyzer       $apiDocStmtAnalyzer,
        private readonly PublicClassMethodMatcher $publicClassMethodMatcher,
        private readonly MethodTypeDetector       $methodMatcher,
        private readonly Configuration            $configuration,
    ) {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return array<array{class-string, string, int}>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (! $this->configuration->shouldCollectMethods()) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        // skip acceptance tests, codeception
        if (str_ends_with($classReflection->getName(), 'Cest')) {
            return null;
        }

        if ($this->methodMatcher->isTestMethod($node, $scope)) {
            return null;
        }

        if ($this->methodMatcher->isTraitMethod($node, $scope)) {
            return null;
        }

        if ($this->publicClassMethodMatcher->shouldSkipClassMethod($node)) {
            return null;
        }

        if ($this->apiDocStmtAnalyzer->isApiDoc($node, $classReflection)) {
            return null;
        }

        foreach (self::SKIPPED_TYPES as $skippedType) {
            if ($classReflection->isSubclassOf($skippedType)) {
                return null;
            }
        }

        if ($this->publicClassMethodMatcher->shouldSkipClassReflection($classReflection)) {
            return null;
        }

        $methodName = $node->name->toString();

        // is this method required by parent contract? skip it
        if ($this->publicClassMethodMatcher->isUsedByParentClassOrInterface($classReflection, $methodName)) {
            return null;
        }

        return [$classReflection->getName(), $methodName, $node->getLine()];
    }


}
