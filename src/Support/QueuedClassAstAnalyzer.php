<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

final class QueuedClassAstAnalyzer
{
    public function analyze(DiscoveredQueuedClass $discovered): QueuedClassStaticMetadata
    {
        $class = $discovered->classNode;
        $isListenerShaped = $this->isListenerShapedPath($discovered->filePath);

        $hasOnQueueAttribute = false;
        $literalQueueFromAttr = null;
        $literalConnectionFromAttr = null;

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isLaravelQueueAttribute($attr)) {
                    $hasOnQueueAttribute = true;
                    $literalQueueFromAttr ??= $this->firstStringAttributeArg($attr);
                }
                if ($this->isLaravelConnectionAttribute($attr)) {
                    $literalConnectionFromAttr ??= $this->firstStringAttributeArg($attr);
                }
            }
        }

        $hasPublicQueuePropertyDefault = false;
        $literalQueueFromProperty = null;
        $literalConnectionFromProperty = null;

        foreach ($class->getProperties() as $property) {
            if (($property->flags & Modifiers::PUBLIC) === 0) {
                continue;
            }
            foreach ($property->props as $prop) {
                $n = $prop->name->toString();
                if ($n === 'queue' && $prop->default instanceof String_) {
                    $hasPublicQueuePropertyDefault = true;
                    $literalQueueFromProperty ??= $prop->default->value;
                }
                if ($n === 'connection' && $prop->default instanceof String_) {
                    $literalConnectionFromProperty ??= $prop->default->value;
                }
            }
        }

        $construct = $this->findConstructor($class);
        $literalQueueFromCtor = null;
        $hasOnQueueCallInConstructor = false;
        if ($construct !== null) {
            foreach ($construct->params as $param) {
                if (($param->flags & Modifiers::PUBLIC) === 0) {
                    continue;
                }
                if ($param->var->name === 'queue' && $param->default instanceof String_) {
                    $hasPublicQueuePropertyDefault = true;
                    $literalQueueFromProperty ??= $param->default->value;
                }
                if ($param->var->name === 'connection' && $param->default instanceof String_) {
                    $literalConnectionFromProperty ??= $param->default->value;
                }
            }

            $literalQueueFromCtor = $this->firstOnQueueLiteralInConstructor($construct);
            $hasOnQueueCallInConstructor = $this->constructorHasAnyOnQueueCall($construct);
        }

        $literalQueue = $literalQueueFromAttr ?? $literalQueueFromProperty ?? $literalQueueFromCtor;
        $literalConnection = $literalConnectionFromAttr ?? $literalConnectionFromProperty;

        [$literalTimeout, $timeoutIsDynamic, $timeoutLineNumbers] = $this->analyzeTimeout($class);

        return new QueuedClassStaticMetadata(
            $discovered->fqn,
            $discovered->filePath,
            $literalQueue,
            $literalConnection,
            $literalTimeout,
            $timeoutIsDynamic,
            $isListenerShaped,
            $hasOnQueueAttribute,
            $hasPublicQueuePropertyDefault,
            $hasOnQueueCallInConstructor,
            $timeoutLineNumbers,
        );
    }

    private function isListenerShapedPath(string $filePath): bool
    {
        $normalized = str_replace('\\', '/', $filePath);

        return str_contains($normalized, '/Listeners/');
    }

    private function isLaravelQueueAttribute(Attribute $attr): bool
    {
        $name = $attr->name->toString();

        if ($name === 'Illuminate\\Queue\\Attributes\\Queue') {
            return true;
        }

        // Older Laravel releases used OnQueue; keep recognizing it when present.
        return $this->attributeNameEndsWith($attr, 'OnQueue');
    }

    private function isLaravelConnectionAttribute(Attribute $attr): bool
    {
        $name = $attr->name->toString();

        if ($name === 'Illuminate\\Queue\\Attributes\\Connection') {
            return true;
        }

        return $this->attributeNameEndsWith($attr, 'OnConnection');
    }

    private function attributeNameEndsWith(Attribute $attr, string $suffix): bool
    {
        $name = $attr->name->toString();

        return str_ends_with($name, '\\'.$suffix) || $name === $suffix;
    }

    private function firstStringAttributeArg(Attribute $attr): ?string
    {
        if ($attr->args === []) {
            return null;
        }
        $arg = $attr->args[0];
        if (! $arg instanceof Arg) {
            return null;
        }
        if ($arg->value instanceof String_) {
            return $arg->value->value;
        }

        return null;
    }

    private function findConstructor(Class_ $class): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                return $method;
            }
        }

        return null;
    }

    private function constructorHasAnyOnQueueCall(ClassMethod $construct): bool
    {
        $finder = new NodeFinder();
        $calls = $finder->findInstanceOf($construct->stmts ?? [], MethodCall::class);
        foreach ($calls as $call) {
            if ($this->isThisMethodCall($call, 'onqueue')) {
                return true;
            }
        }

        return false;
    }

    private function firstOnQueueLiteralInConstructor(ClassMethod $construct): ?string
    {
        $finder = new NodeFinder();
        $calls = $finder->findInstanceOf($construct->stmts ?? [], MethodCall::class);
        foreach ($calls as $call) {
            if (! $this->isThisMethodCall($call, 'onqueue')) {
                continue;
            }
            $s = $this->firstStringArgFromMethodCall($call);
            if ($s !== null) {
                return $s;
            }
        }

        return null;
    }

    private function isThisMethodCall(MethodCall $call, string $lowerMethodName): bool
    {
        if (! $call->var instanceof Variable || $call->var->name !== 'this') {
            return false;
        }
        if (! $call->name instanceof Identifier) {
            return false;
        }

        return strtolower($call->name->toString()) === $lowerMethodName;
    }

    private function firstStringArgFromMethodCall(MethodCall $call): ?string
    {
        if ($call->args === []) {
            return null;
        }
        $first = $call->args[0];
        if (! $first instanceof Arg) {
            return null;
        }
        if ($first->value instanceof String_) {
            return $first->value->value;
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: bool, 2: list<positive-int>}
     */
    private function analyzeTimeout(Class_ $class): array
    {
        $literal = null;
        $dynamic = false;
        $lines = [];

        foreach ($class->getProperties() as $property) {
            if (($property->flags & Modifiers::PUBLIC) === 0) {
                continue;
            }
            foreach ($property->props as $prop) {
                if ($prop->name->toString() !== 'timeout') {
                    continue;
                }
                $line = max(1, $prop->getStartLine() ?? 1);
                if ($prop->default === null) {
                    $dynamic = true;
                    $lines[] = $line;

                    continue;
                }
                if ($prop->default instanceof LNumber) {
                    $literal = (int) $prop->default->value;
                    $lines[] = $line;
                } else {
                    $dynamic = true;
                    $lines[] = max(1, $prop->default->getStartLine() ?? $line);
                }
            }
        }

        $construct = $this->findConstructor($class);
        if ($construct !== null) {
            foreach ($construct->params as $param) {
                if ($param->var->name !== 'timeout') {
                    continue;
                }
                if (($param->flags & Modifiers::PUBLIC) === 0) {
                    continue;
                }
                $line = max(1, $param->getStartLine() ?? 1);
                if ($param->default instanceof LNumber) {
                    $literal ??= (int) $param->default->value;
                    $lines[] = $line;
                } else {
                    $dynamic = true;
                    $lines[] = $line;
                }
            }
        }

        $timeoutMethod = null;
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === 'timeout') {
                $timeoutMethod = $method;
                break;
            }
        }

        if ($timeoutMethod !== null && ($timeoutMethod->flags & Modifiers::PUBLIC) !== 0) {
            $fromMethod = $this->literalTimeoutFromMethodBody($timeoutMethod);
            if ($fromMethod !== null) {
                $literal ??= $fromMethod;
                $lines[] = max(1, $timeoutMethod->getStartLine() ?? 1);
            } else {
                $dynamic = true;
                $lines[] = max(1, $timeoutMethod->getStartLine() ?? 1);
            }
        }

        $lines = array_values(array_unique(array_filter($lines, fn (int $n) => $n > 0)));

        return [$literal, $dynamic, $lines];
    }

    private function literalTimeoutFromMethodBody(ClassMethod $method): ?int
    {
        $stmts = $method->stmts ?? [];
        $finder = new NodeFinder();
        $returns = $finder->findInstanceOf($stmts, Return_::class);
        if (count($returns) !== 1) {
            return null;
        }
        $expr = $returns[0]->expr;
        if ($expr instanceof LNumber) {
            return (int) $expr->value;
        }

        return null;
    }
}
