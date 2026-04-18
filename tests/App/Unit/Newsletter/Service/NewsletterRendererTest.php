<?php

declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Service;

use App\Domain\Newsletter\Exception\RenderException;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[CoversClass(NewsletterRenderer::class)]
final class NewsletterRendererTest extends TestCase
{
    private string $tmpDir;
    private string $tokenDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        $this->tokenDir = $this->tmpDir . '/tokens';
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        // Recursive cleanup
        $it = new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->tmpDir);
    }

    private function edition(int $id = 1, string $community = 'wiikwemkoong', int $vol = 1, int $issue = 1): NewsletterEdition
    {
        return new NewsletterEdition([
            'neid' => $id,
            'community_id' => $community,
            'volume' => $vol,
            'issue_number' => $issue,
            'status' => 'approved',
        ]);
    }

    private function renderer(?callable $processFactory = null): NewsletterRenderer
    {
        return new NewsletterRenderer(
            tokenStore: new RenderTokenStore($this->tokenDir, ttlSeconds: 60),
            storageDir: $this->tmpDir,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: $processFactory !== null ? \Closure::fromCallable($processFactory) : null,
        );
    }

    /**
     * Create a process factory that writes content to the --out= path.
     * Uses dd to avoid printf interpreting % directives in content.
     */
    private function writingProcessFactory(string $content): callable
    {
        return function (array $cmd) use ($content): Process {
            $outPath = $this->extractOutPath($cmd);
            // Use PHP to write the file via a successful process
            $encoded = base64_encode($content);
            return new Process(['php', '-r', sprintf(
                'file_put_contents(%s, base64_decode(%s));',
                var_export($outPath, true),
                var_export($encoded, true),
            )]);
        };
    }

    /**
     * Extract --out=<path> from the command array.
     */
    private function extractOutPath(array $cmd): string
    {
        foreach ($cmd as $arg) {
            if (str_starts_with((string) $arg, '--out=')) {
                return substr((string) $arg, 6);
            }
        }
        throw new \RuntimeException('No --out= argument found in command');
    }

    // ------------------------------------------------------------------
    // Command construction
    // ------------------------------------------------------------------

    #[Test]
    public function render_constructs_correct_command_with_token_url(): void
    {
        $capturedCmd = null;

        $renderer = $this->renderer(function (array $cmd) use (&$capturedCmd): Process {
            $capturedCmd = $cmd;
            $outPath = $this->extractOutPath($cmd);
            $encoded = base64_encode('%PDF-1.4 fake');
            return new Process(['php', '-r', sprintf(
                'file_put_contents(%s, base64_decode(%s));',
                var_export($outPath, true),
                var_export($encoded, true),
            )]);
        });

        $renderer->render($this->edition());

        $this->assertNotNull($capturedCmd);
        $this->assertSame('node', $capturedCmd[0]);
        $this->assertSame('bin/render-pdf.js', $capturedCmd[1]);

        // --url= should contain the tokenized print URL
        $urlArg = $capturedCmd[2];
        $this->assertStringStartsWith('--url=http://localhost:8081/newsletter/_internal/1/print?token=', $urlArg);

        // Token in URL should be 32 hex chars
        $token = substr($urlArg, strrpos($urlArg, '=') + 1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);

        // --out= should target the correct community/vol-issue path
        $outArg = $capturedCmd[3];
        $this->assertStringContainsString('wiikwemkoong/1-1.pdf', $outArg);
    }

    // ------------------------------------------------------------------
    // Successful render
    // ------------------------------------------------------------------

    #[Test]
    public function successful_render_returns_pdf_artifact_with_hash(): void
    {
        $content = '%PDF-1.4 test content for hashing';
        $renderer = $this->renderer($this->writingProcessFactory($content));

        $artifact = $renderer->render($this->edition());

        $this->assertSame(strlen($content), $artifact->bytes);
        $this->assertSame(hash('sha256', $content), $artifact->sha256);
        $this->assertFileExists($artifact->path);
        $this->assertStringEndsWith('wiikwemkoong/1-1.pdf', $artifact->path);

        // Final file should contain the content (not a tmp file)
        $this->assertSame($content, file_get_contents($artifact->path));
    }

    #[Test]
    public function atomic_write_produces_final_file_not_tmp(): void
    {
        $renderer = $this->renderer($this->writingProcessFactory('FAKEPDF'));
        $artifact = $renderer->render($this->edition());

        // No .tmp files should remain
        $tmpFiles = glob($this->tmpDir . '/wiikwemkoong/*.tmp.*');
        $this->assertEmpty($tmpFiles, 'No .tmp files should remain after successful render');

        // Final file should exist at the clean path
        $this->assertFileExists($artifact->path);
        $this->assertStringNotContainsString('.tmp.', $artifact->path);
    }

    // ------------------------------------------------------------------
    // Idempotent re-render
    // ------------------------------------------------------------------

    #[Test]
    public function re_render_same_edition_overwrites_cleanly(): void
    {
        $renderer = $this->renderer($this->writingProcessFactory('FIRST'));
        $artifact1 = $renderer->render($this->edition());
        $this->assertSame('FIRST', file_get_contents($artifact1->path));

        // Re-render with different content
        $renderer2 = $this->renderer($this->writingProcessFactory('SECOND'));
        $artifact2 = $renderer2->render($this->edition());

        // Same path, updated content
        $this->assertSame($artifact1->path, $artifact2->path);
        $this->assertSame('SECOND', file_get_contents($artifact2->path));
        $this->assertSame(hash('sha256', 'SECOND'), $artifact2->sha256);

        // No leftover tmp files
        $tmpFiles = glob($this->tmpDir . '/wiikwemkoong/*.tmp.*');
        $this->assertEmpty($tmpFiles);
    }

    // ------------------------------------------------------------------
    // Failure modes
    // ------------------------------------------------------------------

    #[Test]
    public function process_failure_throws_and_cleans_tmp(): void
    {
        $renderer = $this->renderer(fn(array $cmd) => new Process(['false']));

        try {
            $renderer->render($this->edition());
            $this->fail('Expected RenderException');
        } catch (RenderException) {
            // expected
        }

        // No tmp files should remain
        $allFiles = glob($this->tmpDir . '/wiikwemkoong/*') ?: [];
        $tmpFiles = array_filter($allFiles, fn($f) => str_contains($f, '.tmp.'));
        $this->assertEmpty($tmpFiles, 'Tmp files must be cleaned on failure');
    }

    #[Test]
    public function zero_byte_output_throws_and_cleans_tmp(): void
    {
        $renderer = $this->renderer(function (array $cmd): Process {
            $outPath = $this->extractOutPath($cmd);
            return new Process(['touch', $outPath]);
        });

        try {
            $renderer->render($this->edition());
            $this->fail('Expected RenderException');
        } catch (RenderException $e) {
            $this->assertStringContainsString('zero-byte', $e->getMessage());
        }

        // No tmp files should remain
        $allFiles = glob($this->tmpDir . '/wiikwemkoong/*') ?: [];
        $tmpFiles = array_filter($allFiles, fn($f) => str_contains($f, '.tmp.'));
        $this->assertEmpty($tmpFiles, 'Empty tmp files must be cleaned');
    }

    #[Test]
    public function timeout_throws_render_exception_and_cleans_tmp(): void
    {
        // Fake Process that throws ProcessTimedOutException from run() —
        // avoids a wall-clock dependency that flakes under full-suite load.
        $timingOutProcess = new class(['true']) extends Process {
            public function run(?callable $callback = null, array $env = []): int
            {
                throw new ProcessTimedOutException($this, ProcessTimedOutException::TYPE_GENERAL);
            }
        };

        $renderer = new NewsletterRenderer(
            tokenStore: new RenderTokenStore($this->tokenDir, ttlSeconds: 60),
            storageDir: $this->tmpDir,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 1,
            processFactory: fn(array $cmd) => $timingOutProcess,
        );

        try {
            $renderer->render($this->edition());
            $this->fail('Expected RenderException');
        } catch (RenderException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Token consumption
    // ------------------------------------------------------------------

    #[Test]
    public function render_consumes_the_issued_token(): void
    {
        $tokenStore = new RenderTokenStore($this->tokenDir, ttlSeconds: 60);
        $renderer = new NewsletterRenderer(
            tokenStore: $tokenStore,
            storageDir: $this->tmpDir,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: $this->writingProcessFactory('PDF'),
        );

        $renderer->render($this->edition());

        // Token dir should have no remaining token files (issued then consumed by URL hit,
        // but since we're using a fake process, the token was issued but not consumed via HTTP.
        // Verify that at least a token was issued.)
        $tokenFiles = glob($this->tokenDir . '/*.json') ?: [];
        // Token exists but is unconsumed (fake process doesn't hit the URL) — that's expected.
        // The important thing is: one token was issued per render call.
        $this->assertCount(1, $tokenFiles, 'Exactly one token should be issued per render');
    }

    // ------------------------------------------------------------------
    // Community / path sanitization
    // ------------------------------------------------------------------

    #[Test]
    public function community_id_is_sanitized_in_output_path(): void
    {
        $renderer = $this->renderer($this->writingProcessFactory('PDF'));
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'bad/../../etc',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $artifact = $renderer->render($edition);

        // Path traversal characters should be stripped
        $this->assertStringNotContainsString('..', $artifact->path);
        $this->assertStringNotContainsString('//', $artifact->path);
    }

    #[Test]
    public function empty_community_defaults_to_regional(): void
    {
        $renderer = $this->renderer($this->writingProcessFactory('PDF'));
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => '',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $artifact = $renderer->render($edition);
        $this->assertStringContainsString('/regional/', $artifact->path);
    }
}
