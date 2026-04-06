<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\RenderException;
use Minoo\Domain\Newsletter\Service\NewsletterRenderer;
use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(NewsletterRenderer::class)]
final class NewsletterRendererTest extends TestCase
{
    #[Test]
    public function process_failure_throws_render_exception(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: fn (array $cmd) => $this->failingProcess(),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $this->expectException(RenderException::class);
        $renderer->render($edition);
    }

    #[Test]
    public function zero_byte_output_throws(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: fn (array $cmd) => $this->successfulProcessThatTouchesEmptyFile($cmd),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/zero-byte/');
        $renderer->render($edition);
    }

    #[Test]
    public function successful_render_returns_pdf_artifact_with_hash(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/wiikwemkoong', 0775, true);
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: function (array $cmd) {
                // Extract --out=... path from cmd
                $outArg = null;
                foreach ($cmd as $arg) {
                    if (str_starts_with((string) $arg, '--out=')) {
                        $outArg = substr((string) $arg, 6);
                        break;
                    }
                }
                // Write fake PDF content
                return new \Symfony\Component\Process\Process(['sh', '-c', "printf 'FAKEPDF' > " . escapeshellarg((string) $outArg)]);
            },
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $artifact = $renderer->render($edition);

        $this->assertSame(7, $artifact->bytes); // length of 'FAKEPDF'
        $this->assertSame(hash('sha256', 'FAKEPDF'), $artifact->sha256);
        $this->assertFileExists($artifact->path);
    }

    #[Test]
    public function timeout_throws_render_exception(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/wiikwemkoong', 0775, true);
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 1,
            processFactory: fn(array $cmd) => new \Symfony\Component\Process\Process(['sleep', '3']),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $this->expectException(\Minoo\Domain\Newsletter\Exception\RenderException::class);
        $this->expectExceptionMessageMatches('/timed out/');
        $renderer->render($edition);
    }

    private function failingProcess(): Process
    {
        return new Process(['false']);
    }

    /**
     * @param list<string> $cmd
     */
    private function successfulProcessThatTouchesEmptyFile(array $cmd): Process
    {
        $outArgs = array_values(array_filter(
            $cmd,
            static fn ($a) => str_starts_with((string) $a, '--out='),
        ));
        $out = isset($outArgs[0]) ? explode('=', (string) $outArgs[0], 2)[1] : '/tmp/empty.pdf';

        return new Process(['touch', $out]);
    }
}
