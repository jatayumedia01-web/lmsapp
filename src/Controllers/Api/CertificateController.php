<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/** Student-facing certificate list. */
final class CertificateController
{
    public function myList(Request $req): never
    {
        $user = $req->params['user'];
        $rows = Database::all(
            'SELECT c.*, t.name AS template_name
             FROM certificates c
             LEFT JOIN certificate_templates t ON t.id = c.template_id
             WHERE c.user_id = ? AND c.revoked_at IS NULL
             ORDER BY c.issued_at DESC',
            [$user['id']],
        );

        Response::json([
            'certificates' => array_map(fn (array $r) => [
                'id'                 => $r['id'],
                'certificate_number' => $r['certificate_number'],
                'course_title'       => $r['course_title_snapshot'],
                'score_pct'          => $r['score_pct'] !== null ? (float) $r['score_pct'] : null,
                'issued_at'          => $r['issued_at'],
                'verify_url'         => 'https://apptesting.in/verify/' . rawurlencode((string) $r['certificate_number']),
                'template_name'      => $r['template_name'] ?? 'Default',
            ], $rows),
        ]);
    }
}
