<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Admin surface for certificate templates + the issued-cert audit list.
 *
 * Issuance itself is automatic (triggered by the API when a course is
 * completed), but admins can also issue manually from a user's detail page.
 */
final class CertificateController
{
    public function index(Request $req): never
    {
        $issued = Database::all(
            'SELECT c.*, u.full_name, u.email, co.title AS course_title
             FROM certificates c
             LEFT JOIN users u   ON u.id  = c.user_id
             LEFT JOIN courses co ON co.id = c.course_id
             ORDER BY c.issued_at DESC LIMIT 200',
        );
        $templates = Database::all('SELECT * FROM certificate_templates ORDER BY is_default DESC, name ASC');
        Response::html(View::render('admin/certificates/index', [
            'issued'    => $issued,
            'templates' => $templates,
            'me'        => $req->params['user'],
            'page'      => 'certificates',
            'flash'     => $this->popFlash(),
        ]));
    }

    public function templateNew(Request $req): never
    {
        Response::html(View::render('admin/certificates/template_edit', [
            'template' => $this->blankTemplate(),
            'mode'     => 'create',
            'me'       => $req->params['user'],
            'page'     => 'certificates',
        ]));
    }

    public function templateCreate(Request $req): never
    {
        $name = trim((string) $req->input('name', ''));
        $html = (string) $req->input('html_template', '');
        if ($name === '' || $html === '') {
            $this->setFlash('Name and HTML are both required.', 'error');
            Response::redirect('/admin/certificates/templates/new');
        }
        $id = $req->input('id') ? (string) $req->input('id') : ('tpl_' . bin2hex(random_bytes(4)));
        $isDefault = $req->input('is_default') ? 1 : 0;
        if ($isDefault) Database::exec('UPDATE certificate_templates SET is_default = 0');
        Database::exec(
            'INSERT INTO certificate_templates (id, name, description, html_template, css, is_default)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $name, (string) $req->input('description', ''), $html,
             (string) $req->input('css', ''), $isDefault],
        );
        $this->setFlash('Template created.', 'success');
        Response::redirect('/admin/certificates');
    }

    public function templateEdit(Request $req): never
    {
        $tpl = Database::one('SELECT * FROM certificate_templates WHERE id = ?', [$req->params['id']]);
        if (!$tpl) Response::notFound();
        Response::html(View::render('admin/certificates/template_edit', [
            'template' => $tpl,
            'mode'     => 'edit',
            'me'       => $req->params['user'],
            'page'     => 'certificates',
        ]));
    }

    public function templateUpdate(Request $req): never
    {
        $tpl = Database::one('SELECT * FROM certificate_templates WHERE id = ?', [$req->params['id']]);
        if (!$tpl) Response::notFound();
        $isDefault = $req->input('is_default') ? 1 : 0;
        if ($isDefault) Database::exec('UPDATE certificate_templates SET is_default = 0 WHERE id <> ?', [$req->params['id']]);
        Database::exec(
            'UPDATE certificate_templates SET
                name = ?, description = ?, html_template = ?, css = ?, is_default = ?
             WHERE id = ?',
            [
                trim((string) $req->input('name', $tpl['name'])),
                (string) $req->input('description', ''),
                (string) $req->input('html_template', ''),
                (string) $req->input('css', ''),
                $isDefault,
                $req->params['id'],
            ],
        );
        $this->setFlash('Template updated.', 'success');
        Response::redirect('/admin/certificates');
    }

    public function templateDelete(Request $req): never
    {
        Database::exec('DELETE FROM certificate_templates WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Template deleted.', 'success');
        Response::redirect('/admin/certificates');
    }

    public function templatePreview(Request $req): never
    {
        $tpl = Database::one('SELECT * FROM certificate_templates WHERE id = ?', [$req->params['id']]);
        if (!$tpl) Response::notFound();
        $html = $this->render($tpl, [
            'user_name'          => 'Madhusudhan Reddy',
            'course_title'       => 'Mathematics — Class 10',
            'certificate_number' => 'DEV-DEMO-001',
            'issued_date'        => date('F j, Y'),
            'score'              => '92%',
        ]);
        echo $html;
        exit;
    }

    public function revoke(Request $req): never
    {
        Database::exec('UPDATE certificates SET revoked_at = NOW() WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Certificate revoked.', 'success');
        Response::redirect('/admin/certificates');
    }

    /** Render the inline HTML/CSS to a full HTML page with placeholder substitution. */
    public static function render(array $tpl, array $vars): string
    {
        $html = (string) $tpl['html_template'];
        foreach ($vars as $k => $v) {
            $html = str_replace('{{' . $k . '}}', htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $html);
        }
        $css = (string) ($tpl['css'] ?? '');
        return "<!doctype html><html><head><meta charset='utf-8'><style>body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;font-family:Georgia,serif}@media print{body{background:#fff}}\n$css</style></head><body>$html</body></html>";
    }

    private function blankTemplate(): array
    {
        return [
            'id' => '', 'name' => '', 'description' => '',
            'html_template' => '<div class="cert"><h1>Certificate</h1><p>Awarded to {{user_name}}</p></div>',
            'css' => '.cert { padding: 40px; text-align: center; background: white; border: 4px double #c9a961; width: 600px; }',
            'is_default' => 0,
        ];
    }

    private function setFlash(string $message, string $kind = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
    }

    private function popFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}
