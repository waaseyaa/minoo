<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\IngestCorpusHandler;
use App\Console\MailTestHandler;
use App\Ingestion\Corpus\CorpusIngestor;
use App\Ingestion\Corpus\FfmpegReelFetcher;
use App\Ingestion\Corpus\LlmWhiteboardReader;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\CLI\Command\HandlerArgument;
use Waaseyaa\CLI\Command\HandlerArgumentMode;
use Waaseyaa\CLI\Command\HandlerCommand;
use Waaseyaa\CLI\Command\HandlerOption;
use Waaseyaa\CLI\Command\HandlerOptionMode;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\ProvidesConsoleCommandsInterface;
use Waaseyaa\Mail\MailerInterface;

/**
 * Language-platform slimming (2026-06): messaging digest, genealogy demo
 * seed, and crisis OG asset commands removed with their surfaces.
 */
class AppCommandServiceProvider extends AppCoreServiceProvider implements ProvidesConsoleCommandsInterface
{
    public function register(): void
    {
        parent::register();

        $this->singleton(MailTestHandler::class, function (): MailTestHandler {
            [$configured, $fromAddress] = $this->mailConfigSnapshot();
            return new MailTestHandler(
                $this->resolve(MailerInterface::class),
                $configured,
                $fromAddress,
            );
        });

        $this->singleton(IngestCorpusHandler::class, function (): IngestCorpusHandler {
            $corpusPath = $this->corpusPath();
            return new IngestCorpusHandler(
                new CorpusIngestor(
                    $this->resolve(EntityTypeManager::class),
                    new FfmpegReelFetcher($corpusPath),
                    new LlmWhiteboardReader($this->resolve(ProviderInterface::class)),
                ),
            );
        });
    }

    public function consoleCommands(): iterable
    {
        yield new HandlerCommand(
            name: 'mail:test',
            description: 'Send a test email to verify SendGrid configuration',
            handler: [MailTestHandler::class, 'execute'],
            arguments: [
                new HandlerArgument(
                    name: 'email',
                    mode: HandlerArgumentMode::Required,
                    description: 'The address to send the test email to',
                ),
            ],
        );

        yield new HandlerCommand(
            name: 'ingest:corpus',
            description: 'Ingest reels from a manifest into example_sentence + speaker entities (port of code/ingest/ingest.py)',
            handler: [IngestCorpusHandler::class, 'execute'],
            arguments: [
                new HandlerArgument(
                    name: 'manifest',
                    mode: HandlerArgumentMode::Required,
                    description: 'Path to the reel manifest (lines: id,url[,YYYY-MM-DD])',
                ),
            ],
            options: [
                new HandlerOption(
                    name: 'dry-run',
                    mode: HandlerOptionMode::None,
                    description: 'Preview without fetching media or writing entities',
                ),
                new HandlerOption(
                    name: 'skip-vision',
                    mode: HandlerOptionMode::None,
                    description: 'Do not call the vision LLM; leave Ojibwe/English blank for manual transcription',
                ),
                new HandlerOption(
                    name: 'limit',
                    mode: HandlerOptionMode::Required,
                    description: 'Ingest at most N reels',
                ),
            ],
        );
    }

    /**
     * @return array{0: bool, 1: string} [configured, fromAddress]
     */
    private function mailConfigSnapshot(): array
    {
        $mailConfig = is_array($this->config['mail'] ?? null) ? $this->config['mail'] : [];
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? ''));
        $configured = trim((string) ($mailConfig['sendgrid_api_key'] ?? '')) !== ''
            && $fromAddress !== '';

        return [$configured, $fromAddress];
    }

    /**
     * Community-controlled corpus directory (never committed). Override with
     * MINOO_CORPUS_PATH; mirrors the controllers' default.
     */
    private function corpusPath(): string
    {
        $env = getenv('MINOO_CORPUS_PATH');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/\\');
        }

        return 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';
    }
}
