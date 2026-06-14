<?php

declare(strict_types=1);

namespace App\Http\Controller\Account;

use App\Domain\Account\SavedWords;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\SSR\Flash\Flash;

/**
 * Personal word lists (#806): save/unsave dictionary entries and view them on
 * "My words". A member only ever touches their own saved words.
 */
final class SavedWordController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** POST /account/words/toggle — save or unsave a dictionary entry. */
    public function toggle(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $entryId = (int) $request->request->get('dictionary_entry_id', 0);
        $slug = trim((string) $request->request->get('slug', ''));
        $back = $slug !== '' ? '/language/' . $slug : '/account/words';

        if ($entryId <= 0) {
            return new RedirectResponse($back);
        }

        $storage = $this->entityTypeManager->getStorage('saved_word');
        $existingId = SavedWords::savedId($this->entityTypeManager, $account, $entryId);

        if ($existingId !== null) {
            $existing = $storage->load($existingId);
            if ($existing !== null) {
                $storage->delete([$existing]);
            }
            Flash::success('Removed from your words.');
            return new RedirectResponse($back);
        }

        // Denormalise a snapshot from the (public) dictionary entry.
        $entry = $this->entityTypeManager->getStorage('dictionary_entry')->load($entryId);
        $word = $entry !== null ? (string) $entry->get('word') : '';
        $definition = $entry !== null ? (string) $entry->get('definition') : '';
        if ($slug === '' && $entry !== null) {
            $slug = (string) $entry->get('slug');
        }

        $saved = $storage->create([
            'word' => $word,
            'user_id' => (int) $account->id(),
            'dictionary_entry_id' => $entryId,
            'slug' => $slug,
            'definition' => $definition,
            'created_at' => time(),
        ]);
        $storage->save($saved);

        Flash::success('Saved to your words.');
        return new RedirectResponse($slug !== '' ? '/language/' . $slug : '/account/words');
    }

    /** GET /account/words — the member's personal word list. */
    public function list(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/account/words.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/account/words',
            'words' => SavedWords::listFor($this->entityTypeManager, $account),
        ]));

        return new Response($html);
    }
}
