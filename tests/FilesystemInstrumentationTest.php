<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Filesystem;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use React\Filesystem\AdapterInterface;
use React\Filesystem\Factory;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\NotExistInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function assert;
use function React\Async\await;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class FilesystemInstrumentationTest extends AsyncTestCase
{
    private ScopeInterface $scope;
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $storage;
    private TracerProvider $tracerProvider;
    private AdapterInterface $filesystem;
    private string $tempDir;
    private string $tempFile;

    #[Before]
    public function resetBeforeNextTest(): void
    {
        $this->storage        = new ArrayObject();
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage),
            ),
        );
        $this->scope          = Configurator::create()
            ->withTracerProvider($this->tracerProvider)
            ->withPropagator(new TraceContextPropagator())
            ->activate();

        $this->filesystem = Factory::create();
        $this->tempDir    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'react-inspector-filesystem-' . uniqid() . DIRECTORY_SEPARATOR;
        $this->tempFile   = $this->tempDir . 'test.txt';
        $node             = await($this->filesystem->detect($this->tempFile));
        assert($node instanceof NotExistInterface);
        $file = await($node->createFile());
        await($file->putContents('hello world'));
        $this->storage->exchangeArray([]);
    }

    #[After]
    public function detachScopeAfterTests(): void
    {
        unlink($this->tempFile);
        rmdir($this->tempDir);
        $this->scope->detach();
    }

    #[Test]
    public function detect(): void
    {
        self::assertCount(0, $this->storage);
        $node = await($this->filesystem->detect($this->tempFile));
        self::assertInstanceOf(FileInterface::class, $node);
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('Detect: ' . $this->tempFile, $span->getName());
        $codeFunctionName = $span->getAttributes()->get(CodeAttributes::CODE_FUNCTION_NAME);
        self::assertIsString($codeFunctionName);
        self::assertStringEndsWith('::detect', $codeFunctionName);
    }

    #[Test]
    public function ls(): void
    {
        self::assertCount(0, $this->storage);
        $node = await($this->filesystem->detect($this->tempDir));
        self::assertInstanceOf(DirectoryInterface::class, $node);
        $this->storage->exchangeArray([]);
        await($node->ls());
        self::assertGreaterThanOrEqual(1, $this->storage->count());
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('LS: ' . $node->path() . $node->name(), $span->getName());
        $codeFunctionName = $span->getAttributes()->get(CodeAttributes::CODE_FUNCTION_NAME);
        self::assertIsString($codeFunctionName);
        self::assertStringEndsWith('::ls', $codeFunctionName);
    }

    #[Test]
    public function getContents(): void
    {
        self::assertCount(0, $this->storage);
        $node = await($this->filesystem->detect($this->tempFile));
        self::assertInstanceOf(FileInterface::class, $node);
        $this->storage->exchangeArray([]);
        $contents = await($node->getContents());
        self::assertSame('hello world', $contents);
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('Get Contents: ' . $node->path() . $node->name(), $span->getName());
        $codeFunctionName = $span->getAttributes()->get(CodeAttributes::CODE_FUNCTION_NAME);
        self::assertIsString($codeFunctionName);
        self::assertStringEndsWith('::getContents', $codeFunctionName);
    }
}
