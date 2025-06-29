<?php

declare(strict_types=1);

namespace ReactInspector\Filesystem;

use Composer\InstalledVersions;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Version;
use React\Filesystem\AdapterInterface;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\NodeInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function assert;
use function is_string;
use function OpenTelemetry\Instrumentation\hook;
use function sprintf;

final class FilesystemInstrumentation
{
    public const string NAME = 'reactphp';

    /**
     * The name of the Composer package.
     *
     * @see https://getcomposer.org/doc/04-schema.md#name
     */
    private const string COMPOSER_NAME = 'react-inspector/filesystem';

    /**
     * Name of this instrumentation library which provides the instrumentation for Bunny.
     *
     * @see https://opentelemetry.io/docs/specs/otel/glossary/#instrumentation-library
     */
    private const string INSTRUMENTATION_LIBRARY_NAME = 'io.opentelemetry.contrib.php.react-filesystem';

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            self::INSTRUMENTATION_LIBRARY_NAME,
            InstalledVersions::getPrettyVersion(self::COMPOSER_NAME),
            Version::VERSION_1_32_0->url(),
        );

        hook(
            AdapterInterface::class,
            'detect',
            pre: static function (
                AdapterInterface $filesystem,
                array $params,
                string $class,
                string $function,
                string|null $filename,
                int|null $lineno,
            ) use ($instrumentation): void {
                [$path] = $params;
                assert(is_string($path));

                $parentContext = Context::getCurrent();

                $spanBuilder = $instrumentation
                    ->tracer()
                    ->spanBuilder('Detect: ' . $path)
                    ->setParent($parentContext)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    // code
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                    ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno);

                $span    = $spanBuilder->startSpan();
                $context = $span->storeInContext($parentContext);

                Context::storage()->attach($context);
            },
            post: static function (
                AdapterInterface $filesystem,
                array $params,
                PromiseInterface $promise,
            ): PromiseInterface {
                $scope = Context::storage()->scope();
                if (! $scope instanceof ContextStorageScopeInterface) {
                    return $promise;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if (! $span->isRecording()) {
                    return $promise;
                }

                /** @phpstan-ignore argument.type */
                return $promise->then(static function (NodeInterface $node) use ($span): NodeInterface {
                    $span->end();

                    return $node;
                }, static function (Throwable $exception) use ($span): never {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                    $span->end();

                    /** @phpstan-ignore shipmonk.checkedExceptionInCallable */
                    throw $exception;
                });
            },
        );

        hook(
            DirectoryInterface::class,
            'ls',
            pre: static function (
                DirectoryInterface $dir,
                array $params,
                string $class,
                string $function,
                string|null $filename,
                int|null $lineno,
            ) use ($instrumentation): array {
                $parentContext = Context::getCurrent();

                $spanBuilder = $instrumentation
                    ->tracer()
                    ->spanBuilder('LS: ' . $dir->path() . $dir->name())
                    ->setParent($parentContext)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    // code
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                    ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno);

                $span    = $spanBuilder->startSpan();
                $context = $span->storeInContext($parentContext);

                Context::storage()->attach($context);

                return $params;
            },
            post: static function (
                DirectoryInterface $dir,
                array $params,
                PromiseInterface $promise,
            ): PromiseInterface {
                $scope = Context::storage()->scope();
                if (! $scope instanceof ContextStorageScopeInterface) {
                    return $promise;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if (! $span->isRecording()) {
                    return $promise;
                }

                /** @phpstan-ignore argument.type */
                return $promise->then(static function (array $contents) use ($span): array {
                    $span->end();

                    return $contents;
                }, static function (Throwable $exception) use ($span): never {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                    $span->end();

                    /** @phpstan-ignore shipmonk.checkedExceptionInCallable */
                    throw $exception;
                });
            },
        );

        hook(
            FileInterface::class,
            'getContents',
            pre: static function (
                FileInterface $file,
                array $params,
                string $class,
                string $function,
                string|null $filename,
                int|null $lineno,
            ) use ($instrumentation): array {
                $parentContext = Context::getCurrent();

                $spanBuilder = $instrumentation
                    ->tracer()
                    ->spanBuilder('Get Contents: ' . $file->path() . $file->name())
                    ->setParent($parentContext)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    // code
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                    ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno);

                $span    = $spanBuilder->startSpan();
                $context = $span->storeInContext($parentContext);

                Context::storage()->attach($context);

                return $params;
            },
            post: static function (
                FileInterface $file,
                array $params,
                PromiseInterface $promise,
            ): PromiseInterface {
                $scope = Context::storage()->scope();
                if (! $scope instanceof ContextStorageScopeInterface) {
                    return $promise;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if (! $span->isRecording()) {
                    return $promise;
                }

                /** @phpstan-ignore argument.type */
                return $promise->then(static function (string $contents) use ($span): string {
                    $span->end();

                    return $contents;
                }, static function (Throwable $exception) use ($span): never {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                    $span->end();

                    /** @phpstan-ignore shipmonk.checkedExceptionInCallable */
                    throw $exception;
                });
            },
        );
    }
}
