<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanStrictReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NumericReturnTypeFromStrictScalarReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnCastRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictConstantReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnDirectArrayRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNullableTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationBasedOnParentClassMethodRector;
use Rector\Config\Level\TypeDeclarationDocblocksLevel;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamArrayDocblockBasedOnCallableNativeFuncCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnArrayDocblockBasedOnArrayMapRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnDocblockForScalarArrayFromAssignsRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\AddReturnDocblockDataProviderRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\AddVarArrayDocblockFromDimFetchAssignRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\ClassMethodArrayDocblockParamFromLocalCallsRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\DocblockVarArrayFromGetterReturnRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\DocblockVarArrayFromPropertyDefaultsRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\DocblockVarFromParamDocblockInConstructorRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockBasedOnArrayMapRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockFromAssignsParamToParamReferenceRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockFromDataProviderRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockFromDimFetchAccessRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddReturnDocblockForArrayDimAssignedObjectRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddReturnDocblockForCommonObjectDenominatorRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddReturnDocblockForDimFetchArrayFromAssignsRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddReturnDocblockForJsonArrayRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\DocblockGetterReturnArrayFromPropertyDocblockVarRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\DocblockReturnArrayFromDirectArrayInstanceRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
    ])
    ->withBootstrapFiles([__DIR__ . '/analysis-bootstrap.php'])
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_85)
    ->withPhpSets(php85: true)
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        BoolReturnTypeFromBooleanStrictReturnsRector::class,
        NumericReturnTypeFromStrictScalarReturnsRector::class,
        ReturnTypeFromReturnNewRector::class,
        ReturnTypeFromReturnCastRector::class,
        ReturnTypeFromStrictConstantReturnRector::class,
        ReturnTypeFromReturnDirectArrayRector::class,
        ReturnNullableTypeRector::class,
        AddReturnTypeDeclarationBasedOnParentClassMethodRector::class,
        // Phase 2.4 — iterable value type docblocks
        DocblockVarArrayFromPropertyDefaultsRector::class,
        AddParamArrayDocblockFromDataProviderRector::class,
        AddReturnDocblockDataProviderRector::class,
        AddParamArrayDocblockFromDimFetchAccessRector::class,
        ClassMethodArrayDocblockParamFromLocalCallsRector::class,
        AddParamArrayDocblockBasedOnArrayMapRector::class,
        AddParamArrayDocblockFromAssignsParamToParamReferenceRector::class,
        AddParamArrayDocblockBasedOnCallableNativeFuncCallRector::class,
        AddReturnDocblockForCommonObjectDenominatorRector::class,
        AddReturnArrayDocblockBasedOnArrayMapRector::class,
        AddReturnDocblockForScalarArrayFromAssignsRector::class,
        DocblockReturnArrayFromDirectArrayInstanceRector::class,
        AddReturnDocblockForArrayDimAssignedObjectRector::class,
        AddReturnDocblockForJsonArrayRector::class,
        DocblockVarFromParamDocblockInConstructorRector::class,
        DocblockVarArrayFromGetterReturnRector::class,
        AddVarArrayDocblockFromDimFetchAssignRector::class,
        DocblockGetterReturnArrayFromPropertyDocblockVarRector::class,
        AddReturnDocblockForDimFetchArrayFromAssignsRector::class,
    ])
    ->withSkip([
        __DIR__ . '/vendor',
    ]);
