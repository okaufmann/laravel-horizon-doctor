<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

final class QueuedClassDiscovery
{
    public function __construct(
        private readonly Parser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $config  queued_class_paths, queued_class_exclude_patterns
     * @return list<DiscoveredQueuedClass>
     */
    public function discover(string $basePath, array $config): array
    {
        $paths = $config['queued_class_paths'] ?? [];
        if (! is_array($paths)) {
            return [];
        }

        $excludePatterns = $config['queued_class_exclude_patterns'] ?? [];
        if (! is_array($excludePatterns)) {
            $excludePatterns = [];
        }

        $files = [];
        foreach ($paths as $relative) {
            if (! is_string($relative) || $relative === '') {
                continue;
            }

            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            $full = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($normalized, DIRECTORY_SEPARATOR);
            if (! is_dir($full)) {
                continue;
            }

            $finder = Finder::create()
                ->in($full)
                ->files()
                ->name('*.php')
                ->exclude('vendor')
                ->exclude('node_modules')
                ->exclude('storage');

            foreach ($excludePatterns as $pattern) {
                if (is_string($pattern) && $pattern !== '') {
                    $finder->notPath($pattern);
                }
            }

            foreach ($finder as $file) {
                $real = $file->getRealPath();
                if (is_string($real)) {
                    $files[$real] = $real;
                }
            }
        }

        $byFqn = [];
        foreach ($files as $filePath) {
            $code = @file_get_contents($filePath);
            if (! is_string($code)) {
                continue;
            }

            try {
                $ast = $this->parser->parse($code);
            } catch (Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            /** @var list<Node> $ast */
            $ast = $traverser->traverse($ast);

            foreach ($this->extractQueuedClasses($ast, $filePath) as $discovered) {
                $byFqn[$discovered->fqn] = $discovered;
            }
        }

        return array_values($byFqn);
    }

    /**
     * @param  list<Node>  $ast
     * @return list<DiscoveredQueuedClass>
     */
    private function extractQueuedClasses(array $ast, string $filePath): array
    {
        $out = [];
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                $namespace = $node->name !== null ? $node->name->toString() : '';
                foreach ($node->stmts ?? [] as $stmt) {
                    if ($stmt instanceof Class_ && $stmt->name !== null) {
                        $short = $stmt->name->toString();
                        $fqn = $namespace !== '' ? $namespace.'\\'.$short : $short;
                        if ($this->isQueuedClass($stmt, $fqn)) {
                            $out[] = new DiscoveredQueuedClass($fqn, $filePath, $stmt);
                        }
                    }
                }
            } elseif ($node instanceof Class_ && $node->name !== null) {
                $short = $node->name->toString();
                if ($this->isQueuedClass($node, $short)) {
                    $out[] = new DiscoveredQueuedClass($short, $filePath, $node);
                }
            }
        }

        return $out;
    }

    private function isQueuedClass(Class_ $class, string $fqn): bool
    {
        if ($class->isAnonymous()) {
            return false;
        }

        foreach ($class->implements as $interface) {
            if ($this->nameIsShouldQueue($interface)) {
                return true;
            }
        }

        if (class_exists($fqn)) {
            try {
                $ref = new ReflectionClass($fqn);

                return $ref->implementsInterface(ShouldQueue::class);
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    private function nameIsShouldQueue(Name $name): bool
    {
        return $name->toString() === 'Illuminate\Contracts\Queue\ShouldQueue';
    }
}
