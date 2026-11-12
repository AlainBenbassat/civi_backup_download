<?php

declare(strict_types=1);

namespace Drupal\civi_backup_download\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BackupDownloadController extends ControllerBase {

  private const FILENAME_PATTERN = '/^civi-db_\d{8}_\d{6}\.zip$/';

  public function download(): BinaryFileResponse {
    $dir = Settings::get('crm_backup_path');
    if (!is_string($dir) || $dir === '' || !is_dir($dir)) {
      $this->getLogger('civi_backup_download')->error('Setting crm_backup_path is missing or is not a directory.');
      throw new NotFoundHttpException();
    }

    $path = $this->findLatestBackup(rtrim($dir, '/'));
    if ($path === NULL) {
      $this->getLogger('civi_backup_download')->warning('No backup file found in %dir.', ['%dir' => $dir]);
      throw new NotFoundHttpException();
    }

    $this->getLogger('civi_backup_download')->notice('Backup %file downloaded by %user.', [
      '%file' => basename($path),
      '%user' => $this->currentUser()->getAccountName(),
    ]);

    $response = new BinaryFileResponse($path, 200, [
      'Content-Type' => 'application/zip',
      'X-Content-Type-Options' => 'nosniff',
    ], FALSE);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($path));

    return $response;
  }

  /**
   * Returns the full path of the newest backup, or NULL if there is none.
   */
  private function findLatestBackup(string $dir): ?string {
    $entries = scandir($dir);
    if ($entries === FALSE) {
      return NULL;
    }

    $backups = array_values(array_filter(
      $entries,
      static fn(string $name): bool => preg_match(self::FILENAME_PATTERN, $name) === 1,
    ));
    if ($backups === []) {
      return NULL;
    }

    // Ymd_His timestamps sort chronologically as plain strings.
    rsort($backups, SORT_STRING);
    $path = $dir . '/' . $backups[0];

    return is_file($path) && is_readable($path) ? $path : NULL;
  }

}

