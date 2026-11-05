<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Domain;
use App\Entity\User;
use App\Link\CsvLinkImporter;
use App\Link\ImportException;
use App\Repository\DomainRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CSV import of short links — the migration path from a grown YOURLS instance or an
 * Excel sheet. Reachable from the user list ("Import"), guarded like every admin page.
 *
 * Usage
 * -----
 * Pick a CSV file, an owner (default: yourself), a domain (default: the owner's
 * default domain) and whether duplicates should be skipped. "Vorschau" only parses
 * and validates and shows the detected columns, the counts and the first rows —
 * nothing is written. "Importieren" runs the same check and then writes; the file has
 * to be uploaded again because nothing is cached between the two steps.
 *
 * Accepted columns (header row mandatory, names case-insensitive):
 *   code/keyword/kürzel, target_url/url/ziel/long_url, title/titel, domain,
 *   redirect_type/redirect, active/aktiv, expires_at/ablauf; clicks/klicks is ignored.
 * Limits: 5 MB per file, 5000 rows per run, comma or semicolon separated, UTF-8
 * (BOM tolerated). Duplicates — a code already present on the target domain or
 * earlier in the same file — are skipped and counted when "skip duplicates" is on,
 * otherwise reported as errors. Codes are unique per domain.
 */
#[IsGranted('ADMIN_ACCESS')]
#[Route('/admin/import')]
final class ImportController extends AbstractController
{
    public function __construct(
        private readonly CsvLinkImporter $importer,
        private readonly UserRepository $users,
        private readonly DomainRepository $domains,
    ) {
    }

    #[Route('', name: 'app_admin_import', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $me): Response
    {
        $owner = $me;
        $skipDuplicates = true;
        $result = null;
        $imported = false;
        $domain = $this->defaultDomainFor($me);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin-import', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $owner = $this->users->find($request->request->getInt('owner')) ?? $me;
            $skipDuplicates = $request->request->getBoolean('skip_duplicates');
            $domain = $this->domains->find($request->request->getInt('domain')) ?? $this->defaultDomainFor($owner);

            $file = $request->files->get('file');
            $upload = $file instanceof UploadedFile ? $file : null;
            $error = $this->validateUpload($upload);
            if (null !== $error) {
                $this->addFlash('danger', $error);
            } elseif (null === $domain) {
                $this->addFlash('danger', 'import.error.no_domain');
            } elseif (null !== $upload) {
                $content = $upload->getContent();
                $import = 'import' === $request->request->getString('action');
                try {
                    $result = $import
                        ? $this->importer->import($content, $owner, $domain, $skipDuplicates)
                        : $this->importer->preview($content, $owner, $domain, $skipDuplicates);
                    if ($import) {
                        $imported = true;
                        $this->addFlash('success', 'flash.import_done');
                    }
                } catch (ImportException $e) {
                    $this->addFlash('danger', $e->getMessage());
                }
            }
        }

        return $this->render('admin/import/index.html.twig', [
            'users' => $this->users->findAllOrdered(),
            'domains' => $this->domains->findUsableBy($owner, $owner->isAdmin()),
            'owner' => $owner,
            'domain' => $domain,
            'skip_duplicates' => $skipDuplicates,
            'result' => $result,
            'imported' => $imported,
            'max_bytes' => CsvLinkImporter::MAX_BYTES,
            'max_rows' => CsvLinkImporter::MAX_ROWS,
        ]);
    }

    private function defaultDomainFor(User $owner): ?Domain
    {
        return $this->domains->findUsableBy($owner, $owner->isAdmin())[0] ?? null;
    }

    /** @return string|null translation key of the error, or null when the upload is fine */
    private function validateUpload(?UploadedFile $file): ?string
    {
        if (null === $file) {
            return 'import.error.no_file';
        }
        if (!$file->isValid()) {
            return \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? 'import.error.size'
                : 'import.error.no_file';
        }
        $size = $file->getSize();
        if (false !== $size && $size > CsvLinkImporter::MAX_BYTES) {
            return 'import.error.size';
        }
        if (!\in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return 'import.error.type';
        }

        return null;
    }
}
