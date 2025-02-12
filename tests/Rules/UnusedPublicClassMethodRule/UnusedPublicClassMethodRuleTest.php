<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule;

use Iterator;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TomasVotruba\UnusedPublic\Collectors\AttributeCallableCollector;
use TomasVotruba\UnusedPublic\Collectors\CallUserFuncCollector;
use TomasVotruba\UnusedPublic\Collectors\FormTypeClassCollector;
use TomasVotruba\UnusedPublic\Collectors\MethodCallCollector;
use TomasVotruba\UnusedPublic\Collectors\PublicClassMethodCollector;
use TomasVotruba\UnusedPublic\Collectors\StaticMethodCallCollector;
use TomasVotruba\UnusedPublic\Enum\RuleTips;
use TomasVotruba\UnusedPublic\Rules\UnusedPublicClassMethodRule;
use TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\Fixture\Interface\InterfaceWithExtraMethod;
use TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\Fixture\StaticPublicMethod;
use TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\Fixture\UsedInTestCaseOnly;
use TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\Source\Caller2;
use TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\Source\SomeEnum;

final class UnusedPublicClassMethodRuleTest extends RuleTestCase
{
    /**
     * @param string[] $filePaths
     * @param mixed[] $expectedErrorMessagesWithLines
     */
    #[DataProvider('provideData')]
    public function testRule(array $filePaths, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse($filePaths, $expectedErrorMessagesWithLines);
    }

    public static function provideData(): Iterator
    {
        yield [[__DIR__ . '/Fixture/Symfony/SkipRequiredMethodCall.php'], []];

        yield [[
            __DIR__ . '/Fixture/Symfony/SomeFormType.php',
            __DIR__ . '/Fixture/Symfony/SkipEntityGetterSetters.php',
        ], []];

        yield [[__DIR__ . '/Fixture/SkipSymfonyValidatorMethod.php'], []];
        yield [[__DIR__ . '/Fixture/SkipLocallyUsedPublicMethod.php'], []];

        $errorMessage = sprintf(
            UnusedPublicClassMethodRule::ERROR_MESSAGE,
            InterfaceWithExtraMethod::class,
            'extraMethod'
        );
        yield [[__DIR__ . '/Fixture/Interface/InterfaceWithExtraMethod.php'],
            [[$errorMessage, 15, RuleTips::SOLUTION_MESSAGE]],
        ];

        $errorMessage = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, StaticPublicMethod::class, 'runHere');
        yield [[
            __DIR__ . '/Source/StaticCalls.php',
            __DIR__ . '/Fixture/StaticPublicMethod.php',
        ], [[$errorMessage, 9, RuleTips::SOLUTION_MESSAGE]]];

        // public methods expected
        yield [[__DIR__ . '/Fixture/SkipTestPublicMethod.php'], []];
        yield [[__DIR__ . '/Fixture/SkipTestCasePublicMethod.php'], []];
        yield [[__DIR__ . '/Fixture/Controller/SkipControllerMethod.php'], []];
        yield [[__DIR__ . '/Fixture/Controller/SkipNoRoutingControllerMethod.php'], []];

        // method required by parent
        yield [[__DIR__ . '/Fixture/SkipParentMethodOverride.php'], []];
        yield [[__DIR__ . '/Fixture/Interface/SkipImplementsInterfaceCoveredByContract.php'], []];

        yield [[__DIR__ . '/Fixture/SkipClassWithAttribute.php'], []];
        yield [[__DIR__ . '/Fixture/SkipPublicApiClassMethod.php'], []];
        yield [[__DIR__ . '/Fixture/Interface/SkipInterfaceMethod.php'], []];

        yield [[__DIR__ . '/Fixture/SkipUsedPublicMethod.php', __DIR__ . '/Source/ClassMethodCaller.php'], []];

        yield [[__DIR__ . '/Source/CallbackMethodCaller.php', __DIR__ . '/Fixture/SkipPublicCallbackMethod.php'], []];

        yield [[
            __DIR__ . '/Fixture/SkipNullableUsedPublicMethod.php', __DIR__ . '/Source/NullableClassMethodCaller.php', ],
            [],
        ];

        yield [[__DIR__ . '/Fixture/SkipPublicMethodInTwigExtension.php'], []];

        $errorMessage = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, UsedInTestCaseOnly::class, 'useMe');
        yield [[
            __DIR__ . '/Fixture/UsedInTestCaseOnly.php',
            __DIR__ . '/Source/TestCaseUser.php',
        ], [[$errorMessage, 9, RuleTips::SOLUTION_MESSAGE]]];

        yield [[__DIR__ . '/Fixture/SkipTestAnnotationMethod.php'], []];
        yield [[__DIR__ . '/Fixture/SkipTestCaseAnnotationMethod.php'], []];

        // parent abstract method used by child call
        yield [[
            __DIR__ . '/Fixture/SkipChildUsedPublicMethod.php',
            __DIR__ . '/Source/Repository/AbstractRepository.php',
            __DIR__ . '/Source/Repository/ChildRepository.php',
        ], []];

        // laravel
        yield [[__DIR__ . '/Fixture/Laravel/SkipCommandHandle.php'], []];

        // Union types
        yield [[
            __DIR__ . '/Fixture/SkipUnionCallerMethod.php',
            __DIR__ . '/Source/Caller1.php',
            __DIR__ . '/Source/Caller2.php',
        ], []];
        yield [[
            __DIR__ . '/Fixture/SkipNativeUnionCallerMethod.php',
            __DIR__ . '/Source/Caller1.php',
            __DIR__ . '/Source/Caller2.php',
        ], []];
        $errorMessage = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, Caller2::class, 'callIt');
        yield [[
            __DIR__ . '/Fixture/SkipMixedUnionCallerMethod.php',
            __DIR__ . '/Source/Caller1.php',
            __DIR__ . '/Source/Caller2.php',
        ], [[$errorMessage, 9, RuleTips::SOLUTION_MESSAGE]]];

        // traits
        yield [[__DIR__ . '/Fixture/SkipTraitMethod.php', __DIR__ . '/Source/SomeTrait.php'], []];

        // enums
        $errorMessage1 = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, SomeEnum::class, 'unused');
        $errorMessage2 = sprintf(UnusedPublicClassMethodRule::ERROR_MESSAGE, SomeEnum::class, 'unusedStatic');
        yield [[
            __DIR__ . '/Fixture/EnumMethod.php',
            __DIR__ . '/Source/SomeEnum.php',
        ], [[$errorMessage1, 9, RuleTips::SOLUTION_MESSAGE], [$errorMessage2, 13, RuleTips::SOLUTION_MESSAGE]]];

        yield [[__DIR__ . '/Fixture/CaseInsensitiveMethodName.php', __DIR__ . '/Source/Caller1.php'], []];
        yield [[__DIR__ . '/Fixture/CaseInsensitiveClassName.php', __DIR__ . '/Source/Caller1.php'], []];
    }

    /**
     * @return string[]
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/configured_rule.neon'];
    }

    /**
     * @return array<Collector>
     */
    protected function getCollectors(): array
    {
        return [
            self::getContainer()->getByType(PublicClassMethodCollector::class),
            self::getContainer()->getByType(MethodCallCollector::class),
            self::getContainer()->getByType(StaticMethodCallCollector::class),
            self::getContainer()->getByType(AttributeCallableCollector::class),
            self::getContainer()->getByType(CallUserFuncCollector::class),
            self::getContainer()->getByType(FormTypeClassCollector::class),
        ];
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(UnusedPublicClassMethodRule::class);
    }
}
