<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly RenderTokenStore $tokens,
    ) {}

    /**
     * Internal endpoint hit by Playwright during PDF generation.
     * Public route, but requires a single-use one-time token.
     */
    public function printPreview(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $editionId = (int) ($params['id'] ?? 0);

        if (! $this->tokens->consume($token, $editionId)) {
            return new Response('Gone', 410);
        }

        $editionStorage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $editionStorage->load($editionId);
        if ($edition === null) {
            return new Response('Not found', 404);
        }

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn($i) => (int) $i->get('edition_id') === $editionId,
        );

        $bySection = [];
        $sourceEntities = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;

            $srcType = (string) $item->get('source_type');
            $srcId = (int) $item->get('source_id');
            if ($srcType !== '' && $srcId > 0) {
                $src = $this->entityTypeManager->getStorage($srcType)->load($srcId);
                if ($src !== null) {
                    $sourceEntities[$item->id()] = $src;
                }
            }
        }

        return new Response($this->twig->render('newsletter/edition.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'source_entities' => $sourceEntities,
        ]));
    }
}
