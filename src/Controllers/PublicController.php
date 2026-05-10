<?php
declare(strict_types=1);

namespace Devithor\Controllers;

use Devithor\Controllers\Admin\CertificateController;
use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Routes that the public web hits (no auth). Right now: certificate
 * verification by number. Returns a polished HTML page so it can be
 * shared on LinkedIn etc.
 */
final class PublicController
{
    public function verifyCertificate(Request $req): never
    {
        $number = trim((string) ($req->params['number'] ?? ''));
        $cert = Database::one(
            'SELECT c.*, t.html_template, t.css, u.full_name AS user_name, co.title AS course_title
             FROM certificates c
             LEFT JOIN certificate_templates t ON t.id = c.template_id
             LEFT JOIN users   u  ON u.id  = c.user_id
             LEFT JOIN courses co ON co.id = c.course_id
             WHERE c.certificate_number = ?',
            [$number],
        );

        if (!$cert) {
            http_response_code(404);
            echo $this->errorPage('Certificate not found', 'No certificate matches that number. Double-check the link.');
            exit;
        }
        if (!empty($cert['revoked_at'])) {
            http_response_code(410);
            echo $this->errorPage('Certificate revoked', 'This certificate was revoked on ' . substr((string) $cert['revoked_at'], 0, 10) . '.');
            exit;
        }

        // Render the certificate inline for the verifier's browser.
        $tpl = ['html_template' => $cert['html_template'] ?? '', 'css' => $cert['css'] ?? ''];
        if ($tpl['html_template'] === '') {
            $defaultTpl = Database::one('SELECT html_template, css FROM certificate_templates WHERE is_default = 1');
            $tpl = $defaultTpl ?: $tpl;
        }
        echo CertificateController::render($tpl, [
            'user_name'          => (string) ($cert['user_name'] ?? $cert['user_name_snapshot']),
            'course_title'       => (string) ($cert['course_title'] ?? $cert['course_title_snapshot']),
            'certificate_number' => (string) $cert['certificate_number'],
            'issued_date'        => date('F j, Y', strtotime((string) $cert['issued_at'])),
            'score'              => $cert['score_pct'] !== null ? number_format((float) $cert['score_pct'], 1) . '%' : '—',
        ]);
        exit;
    }

    private function errorPage(string $title, string $body): string
    {
        return "<!doctype html><html><head><meta charset='utf-8'><title>$title · Devithor</title><style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0A0E1A;color:#EEF1FA;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{max-width:480px;padding:40px;background:#151B30;border-radius:14px;border:1px solid rgba(124,92,255,0.18);text-align:center}h1{margin:0 0 8px;color:#7C5CFF}p{color:#8893B8;margin:0}</style></head><body><div class='box'><h1>$title</h1><p>$body</p></div></body></html>";
    }
}
