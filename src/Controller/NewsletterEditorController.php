<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Newsletter\Exception\RenderException;
use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Domain\Newsletter\Service\NewsletterRenderer;
use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterEditorController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly EditionLifecycle $lifecycle,
        private readonly NewsletterAssembler $assembler,
        private readonly NewsletterRenderer $renderer,
    ) {
    }

    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = $storage->loadMultiple();

        return new Response($this->twig->render('newsletter/editor/list.html.twig', [
            'editions' => $editions,
            'flashes' => Flash::pull(),
        ]));
    }

    public function create(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $communityId = $request->request->get('community_id') ?: null;
        $publishDate = (string) $request->request->get('publish_date');

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');

        // Auto-assign volume + issue number for this community.
        $existingForCommunity = array_filter(
            $storage->loadMultiple(),
            fn ($e) => (string) $e->get('community_id') === (string) $communityId,
        );
        $maxIssue = 0;
        foreach ($existingForCommunity as $e) {
            $maxIssue = max($maxIssue, (int) $e->get('issue_number'));
        }
        $nextIssue = $maxIssue + 1;

        $edition = $storage->create([
            'community_id' => $communityId,
            'volume' => 1,
            'issue_number' => $nextIssue,
            'publish_date' => $publishDate,
            'status' => 'draft',
            'created_by' => $account->id(),
            'headline' => sprintf('Issue %d', $nextIssue),
        ]);
        $storage->save($edition);

        Flash::success('New newsletter edition created.');

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function assemble(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $this->assembler->assemble($edition);

        $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);

        if ((string) $edition->get('status') === 'draft') {
            Flash::error('No content found for this date window. Try again after submissions arrive.');
        } else {
            Flash::success('Edition assembled — review the queue.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn ($i) => (int) $i->get('edition_id') === (int) $edition->id(),
        );

        $bySection = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;
        }

        return new Response($this->twig->render('newsletter/editor/newsroom.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'flashes' => Flash::pull(),
        ]));
    }

    public function approve(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $this->lifecycle->approve($edition, (int) $account->id());
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            Flash::success('Edition approved. You can now generate the PDF.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function generate(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $artifact = $this->renderer->render($edition);
            $this->lifecycle->markGenerated($edition, $artifact->path, $artifact->sha256);
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            Flash::success(sprintf('PDF generated (%d bytes).', $artifact->bytes));
        } catch (RenderException $e) {
            Flash::error('Render failed: ' . $e->getMessage());
        } catch (\DomainException $e) {
            Flash::error('This edition cannot be generated in its current state. Refresh and try again.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    private function loadEditionOrFail(mixed $id): EntityInterface
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $storage->load((int) $id);
        if ($edition === null) {
            throw new \RuntimeException('Newsletter edition not found.');
        }

        return $edition;
    }
}
