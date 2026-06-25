<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\AnokiiBackfillStatusHandler;
use App\Console\IngestCorpusHandler;
use App\Console\MailTestHandler;
use App\Console\ProcessReelsHandler;
use App\Console\SeedTranslationBacklogHandler;
use App\Ingestion\Corpus\CorpusIngestor;
use App\Ingestion\Corpus\FfmpegReelFetcher;
use App\Ingestion\Corpus\LlmWhiteboardReader;
use App\Ingestion\Corpus\LocalFileReelFetcher;
use App\Ingestion\Corpus\ReelProcessor;
use App\Seed\TranslationBacklogSeeder;
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

        $this->singleton(AnokiiBackfillStatusHandler::class, function (): AnokiiBackfillStatusHandler {
            return new AnokiiBackfillStatusHandler($this->resolve(EntityTypeManager::class));
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

        $this->singleton(SeedTranslationBacklogHandler::class, function (): SeedTranslationBacklogHandler {
            return new SeedTranslationBacklogHandler(
                $this->resolve(TranslationBacklogSeeder::class),
                dirname(__DIR__, 2),
            );
        });

        $this->singleton(ProcessReelsHandler::class, function (): ProcessReelsHandler {
            $corpusPath = $this->corpusPath();
            return new ProcessReelsHandler(
                $this->resolve(EntityTypeManager::class),
                new ReelProcessor(
                    $this->resolve(EntityTypeManager::class),
                    new LocalFileReelFetcher($corpusPath),
                    new LlmWhiteboardReader($this->resolve(ProviderInterface::class)),
                ),
            );
        });
    }

    public function consoleCommands(): iterable
    {
        yield new HandlerCommand(
            name: 'anokii:backfill-status',
            description: 'Persist pipeline_status on legacy corpus example_sentence rows (#876)',
            handler: [AnokiiBackfillStatusHandler::class, 'execute'],
            options: [
                new HandlerOption(
                    name: 'dry-run',
                    mode: HandlerOptionMode::None,
                    description: 'Report what would be set without writing entities',
                ),
            ],
        );

        yield new HandlerCommand(
            name: 'anokii:process-reels',
            description: 'Process staged ingest reels: ffmpeg keyframe + opus, vision draft, advance to drafted (#877)',
            handler: [ProcessReelsHandler::class, 'execute'],
            options: [
                new HandlerOption(
                    name: 'limit',
                    mode: HandlerOptionMode::Required,
                    description: 'Process at most N reels per pass',
                ),
                new HandlerOption(
                    name: 'loop',
                    mode: HandlerOptionMode::None,
                    description: 'Run continuously as a worker (for systemd on the Pi)',
                ),
                new HandlerOption(
                    name: 'sleep',
                    mode: HandlerOptionMode::Required,
                    description: 'Seconds to sleep between empty passes in --loop (default 5)',
                ),
                new HandlerOption(
                    name: 'skip-vision',
                    mode: HandlerOptionMode::None,
                    description: 'Skip the vision LLM draft; advance with blank Ojibwe/English',
                ),
            ],
        );

        yield new HandlerCommand(
            name: 'lang:seed-backlog',
            description: 'Idempotently seed the demand-ranked English translation backlog into tm_backlog (#906)',
            handler: [SeedTranslationBacklogHandler::class, 'execute'],
        );

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
