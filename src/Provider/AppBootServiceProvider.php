<?php

declare(strict_types=1);

namespace App\Provider;

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use App\Domain\Newsletter\Service\NewsletterDispatcher;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Domain\Newsletter\ValueObject\SectionQuota;
use App\Entity\Community;
use App\Entity\Contributor;
use App\Entity\CrosswordPuzzle;
use App\Entity\CulturalCollection;
use App\Entity\CulturalGroup;
use App\Entity\DailyChallenge;
use App\Entity\DialectRegion;
use App\Entity\DictionaryEntry;
use App\Entity\ElderSupportRequest;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\ExampleSentence;
use App\Entity\FeaturedItem;
use App\Entity\GameSession;
use App\Entity\IngestLog;
use App\Entity\Leader;
use App\Entity\NewsletterEdition;
use App\Entity\NewsletterItem;
use App\Entity\NewsletterSubmission;
use App\Entity\OralHistory;
use App\Entity\OralHistoryCollection;
use App\Entity\OralHistoryType;
use App\Entity\Post;
use App\Entity\ResourcePerson;
use App\Entity\Speaker;
use App\Entity\Teaching;
use App\Entity\TeachingType;
use App\Entity\Volunteer;
use App\Entity\WordPart;
use App\Feed\EntityLoaderService;
use App\Feed\FeedAssembler;
use App\Feed\FeedAssemblerInterface;
use App\Feed\FeedItemFactory;
use App\Feed\Scoring\AffinityCache;
use App\Feed\Scoring\AffinityCalculator;
use App\Feed\Scoring\DecayCalculator;
use App\Feed\Scoring\DiversityReranker;
use App\Feed\Scoring\EngagementCalculator;
use App\Feed\Scoring\FeedScorer;
use App\Ingestion\EntityMapper\NcArticleToEventMapper;
use App\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use App\Support\Command\GenealogyDemoSeedCommand;
use App\Support\Command\MailTestCommand;
use App\Support\Command\MessagingDigestCommand;
use App\Support\MessageDigestCommand;
use App\Support\NewsletterMailer;
use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Support\NorthCloudCommunityDictionaryClient;
use App\Twig\AccountDisplayTwigExtension;
use App\Twig\DateTwigExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\I18n\Language;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\Translator;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\I18n\Twig\TranslationTwigExtension;
use Waaseyaa\Mail\MailerInterface;
use Waaseyaa\Media\UploadHandler;
use Waaseyaa\NorthCloud\Client\NorthCloudClient as PackageNorthCloudClient;
use Waaseyaa\NorthCloud\Command\NcSyncCommand;
use Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcSyncService;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;
use Waaseyaa\Mcp\Auth\BearerTokenAuth;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\SSR\ThemeServiceProvider;

class AppBootServiceProvider extends AppCoreServiceProvider
{
    public static function groupBusinessBundleFields(): array
        {
            return [
                new FieldDefinition(
                    name: 'slug',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'URL Slug',
                ),
                new FieldDefinition(
                    name: 'description',
                    type: 'text_long',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Description',
                ),
                new FieldDefinition(
                    name: 'url',
                    type: 'uri',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Website',
                    description: 'External website URL.',
                ),
                new FieldDefinition(
                    name: 'region',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Region',
                    description: 'Geographic region.',
                ),
                new FieldDefinition(
                    name: 'community_id',
                    type: 'entity_reference',
                    settings: ['target_type' => 'community'],
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Community',
                ),
                new FieldDefinition(
                    name: 'phone',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Phone',
                    description: 'Business phone number in E.164 format.',
                ),
                new FieldDefinition(
                    name: 'email',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Email',
                ),
                new FieldDefinition(
                    name: 'address',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Address',
                    description: 'Physical address.',
                ),
                new FieldDefinition(
                    name: 'booking_url',
                    type: 'uri',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Booking URL',
                    description: 'External booking link.',
                ),
                new FieldDefinition(
                    name: 'media_id',
                    type: 'entity_reference',
                    settings: ['target_type' => 'media'],
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Image',
                ),
                new FieldDefinition(
                    name: 'copyright_status',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    defaultValue: 'unknown',
                    label: 'Copyright Status',
                    description: 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                ),
                new FieldDefinition(
                    name: 'consent_public',
                    type: 'boolean',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    defaultValue: 1,
                    label: 'Public Consent',
                    description: 'Whether this content may be shown on public pages.',
                ),
                new FieldDefinition(
                    name: 'consent_ai_training',
                    type: 'boolean',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    defaultValue: 0,
                    label: 'AI Training Consent',
                    description: 'Whether this content may be used for AI training. Default: no.',
                ),
                new FieldDefinition(
                    name: 'source',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Source',
                    description: 'Provenance tag (e.g. manual:russell:2026-03-15).',
                ),
                new FieldDefinition(
                    name: 'verified_at',
                    type: 'datetime',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Verified At',
                    description: 'When this record was last verified.',
                ),
                new FieldDefinition(
                    name: 'social_posts',
                    type: 'text_long',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Social Posts',
                    description: 'JSON array of recent social media posts.',
                ),
                new FieldDefinition(
                    name: 'latitude',
                    type: 'float',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Latitude',
                    description: 'Geocoded from address, or community fallback.',
                ),
                new FieldDefinition(
                    name: 'longitude',
                    type: 'float',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Longitude',
                    description: 'Geocoded from address, or community fallback.',
                ),
                new FieldDefinition(
                    name: 'coordinate_source',
                    type: 'string',
                    targetEntityTypeId: 'group',
                    targetBundle: 'business',
                    label: 'Coordinate Source',
                    description: 'How coordinates were obtained: address or community.',
                ),
            ];
        }

    public function boot(): void
        {
            // =====================================================================
            // --- Groups: register business-bundle fields ---
            // =====================================================================

            /** @var EntityTypeManager $etm */
            $etm = $this->resolve(EntityTypeManager::class);
            $etm->addBundleFields('group', 'business', self::groupBusinessBundleFields());

            $fieldRegistry = $etm->getFieldRegistry();
            $fieldRegistry->mergeCoreFields('user', [
                'genealogy_product_enabled' => new FieldDefinition(
                    name: 'genealogy_product_enabled',
                    type: 'boolean',
                    cardinality: 1,
                    settings: [],
                    targetEntityTypeId: 'user',
                    defaultValue: false,
                    label: 'Genealogy product enabled',
                    description: 'User opt-in for genealogy SSR routes (C3).',
                    stored: FieldStorage::Data,
                ),
            ]);
            foreach (['genealogy_person', 'genealogy_family', 'genealogy_event', 'genealogy_tree'] as $genealogyTypeId) {
                $fieldRegistry->mergeCoreFields($genealogyTypeId, [
                    'minoo_community_anchor' => new FieldDefinition(
                        name: 'minoo_community_anchor',
                        type: 'integer',
                        cardinality: 1,
                        settings: ['not_null' => false],
                        targetEntityTypeId: $genealogyTypeId,
                        label: 'Community anchor',
                        description: 'Optional Minoo-only reference to a community entity id (stored in _data).',
                        stored: FieldStorage::Data,
                    ),
                ]);
            }

            // =====================================================================
            // --- I18n: Translation Twig extension ---
            // =====================================================================

            /** @var TranslatorInterface $translator */
            $translator = $this->resolve(TranslatorInterface::class);
            /** @var LanguageManagerInterface $manager */
            $manager = $this->resolve(LanguageManagerInterface::class);

            $extension = new TranslationTwigExtension($translator, $manager);
            $twig = SsrServiceProvider::getTwigEnvironment();
            if ($twig !== null) {
                $twig->addExtension($extension);
            }

            // =====================================================================
            // --- Search: SearchTwigExtension ---
            // =====================================================================

            $twigForSearch = SsrServiceProvider::getTwigEnvironment();
            if ($twigForSearch !== null) {
                /** @var SearchProviderInterface $searchProvider */
                $searchProvider = $this->resolve(SearchProviderInterface::class);
                $baseTopics = (array) ($this->config['search']['base_topics'] ?? []);
                $twigForSearch->addExtension(new SearchTwigExtension($searchProvider, $baseTopics));
            }

            // =====================================================================
            // --- Flash: DateTwigExtension + AccountDisplayTwigExtension ---
            // =====================================================================

            $twigForFlash = ThemeServiceProvider::getTwigEnvironment();
            if ($twigForFlash !== null) {
                $twigForFlash->addExtension(new DateTwigExtension());
                $twigForFlash->addExtension(new AccountDisplayTwigExtension());
            }

            // =====================================================================
            // --- Chat: chat_enabled global ---
            // =====================================================================

            $twigForChat = ThemeServiceProvider::getTwigEnvironment();
            if ($twigForChat !== null) {
                $chatConfig = $this->loadChatConfig();
                $twigForChat->addGlobal('chat_enabled', $chatConfig['enabled'] ?? false);
            }

            // =====================================================================
            // --- Feed Scoring: AffinityCache invalidation on entity events ---
            // =====================================================================

            $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
            $affinityCache = $this->resolve(AffinityCache::class);

            $invalidate = static function (EntityEvent $event) use ($affinityCache): void {
                $entity = $event->entity;
                if (in_array($entity->getEntityTypeId(), ['reaction', 'comment', 'follow'], true)) {
                    $userId = $entity->get('user_id');
                    if ($userId !== null) {
                        $affinityCache->invalidate((int) $userId);
                    }
                }
            };

            $dispatcher->addListener(EntityEvents::POST_SAVE->value, $invalidate);
            $dispatcher->addListener(EntityEvents::POST_DELETE->value, $invalidate);

            // =====================================================================
            // --- Games: game_session updated_at on PRE_SAVE ---
            // =====================================================================

            $dispatcher->addListener(EntityEvents::PRE_SAVE->value, static function (EntityEvent $event): void {
                if ($event->entity->getEntityTypeId() === 'game_session') {
                    $event->entity->set('updated_at', time());
                }
            });

            // =====================================================================
            // --- Twig: site_base_url for og:image / og:url absolute URLs ---
            // =====================================================================

            $siteBase = rtrim((string) ($this->config['mail']['base_url'] ?? 'https://minoo.live'), '/');
            /** @var array<int, Environment> $twigTargets */
            $twigTargets = [];
            foreach ([SsrServiceProvider::getTwigEnvironment(), ThemeServiceProvider::getTwigEnvironment()] as $env) {
                if ($env instanceof Environment) {
                    $twigTargets[spl_object_id($env)] = $env;
                }
            }
            foreach ($twigTargets as $env) {
                $env->addGlobal('site_base_url', $siteBase);
            }
        }

    private function loadChatConfig(): array
        {
            $path = dirname(__DIR__, 2) . '/config/ai-chat.php';

            if (!file_exists($path)) {
                return ['enabled' => false];
            }

            return require $path;
        }
}
