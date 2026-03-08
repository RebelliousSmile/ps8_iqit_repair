<?php
/**
 * SC IQIT Repair - PrestaShop 8 Module
 *
 * @author    Scriptami
 * @copyright Scriptami
 * @license   Academic Free License version 3.0
 */

declare(strict_types=1);

namespace ScIqitRepair\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use ScIqitRepair\Service\IqitFixerDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Admin controller for IQIT module diagnostics and repairs
 */
class IqitRepairController extends FrameworkBundleAdminController
{
    /**
     * Known iqit* modules to detect, with their human-readable names
     */
    private const IQIT_MODULES = [
        'iqitsizechart' => 'IQIT Size Chart',
        'iqitmegamenu' => 'IQIT Mega Menu',
        'iqitthemeeditor' => 'IQIT Theme Editor',
        'iqitreviews' => 'IQIT Reviews',
        'iqitwishlist' => 'IQIT Wishlist',
        'iqitcompare' => 'IQIT Compare',
        'iqitsearch' => 'IQIT Search',
        'iqitextendedproduct' => 'IQIT Extended Product',
    ];

    /**
     * Available fix types with their metadata
     */
    private const AVAILABLE_FIXES = [
        [
            'type' => 'sizechart_shop',
            'module' => 'iqitsizechart',
            'icon' => 'straighten',
            'title' => 'Size Chart Shop Associations',
            'description' => 'Diagnose and repair missing entries in iqitsizechart_shop table',
            'severity' => 'high',
        ],
    ];

    private IqitFixerDispatcher $dispatcher;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        IqitFixerDispatcher $dispatcher,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->dispatcher = $dispatcher;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Index action: list available fixers with iqit module detection
     *
     * @AdminSecurity(
     *     "is_granted('read', request.get('_legacy_controller'))",
     *     message="You do not have permission to access this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    public function indexAction(): Response
    {
        // Detect installed iqit* modules (graceful degradation)
        $detectedModules = [];
        foreach (self::IQIT_MODULES as $moduleName => $label) {
            $detectedModules[] = [
                'name' => $moduleName,
                'label' => $label,
                'installed' => class_exists('Module') && \Module::isInstalled($moduleName),
            ];
        }

        // Filter fixes to only those whose required module is installed
        $installedModuleNames = array_column(
            array_filter($detectedModules, fn ($m) => $m['installed']),
            'name'
        );

        $fixes = array_map(function (array $fix) use ($installedModuleNames): array {
            $fix['module_available'] = in_array($fix['module'], $installedModuleNames, true);

            return $fix;
        }, self::AVAILABLE_FIXES);

        return $this->render(
            '@Modules/sc_iqit_repair/views/templates/admin/index.html.twig',
            [
                'layoutTitle' => $this->trans('IQIT Repair', 'Modules.Sciqitrepair.Admin'),
                'enableSidebar' => true,
                'help_link' => false,
                'detected_modules' => $detectedModules,
                'fixes' => $fixes,
            ]
        );
    }

    /**
     * Preview a fix (dry-run)
     *
     * @AdminSecurity(
     *     "is_granted('read', request.get('_legacy_controller'))",
     *     message="You do not have permission to access this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    public function previewAction(Request $request, string $type): Response
    {
        $result = $this->dispatcher->preview($type);
        $acceptsJson = $request->headers->get('Accept') === 'application/json';

        if ($acceptsJson) {
            return new JsonResponse($result);
        }

        $response = $this->render(
            '@Modules/sc_iqit_repair/views/templates/admin/preview.html.twig',
            [
                'layoutTitle' => $this->trans('Preview: %type%', 'Modules.Sciqitrepair.Admin', ['%type%' => $type]),
                'enableSidebar' => true,
                'help_link' => false,
                'type' => $type,
                'result' => $result,
                'csrfToken' => $this->csrfTokenManager->getToken('sc_iqit_repair_fix_' . $type)->getValue(),
            ]
        );
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Apply a fix
     *
     * @AdminSecurity(
     *     "is_granted('update', request.get('_legacy_controller'))",
     *     message="You do not have permission to modify this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    public function applyAction(Request $request, string $type): Response
    {
        $token = $request->request->get('_token');
        $expectedToken = 'sc_iqit_repair_fix_' . $type;

        if (!$this->csrfTokenManager->isTokenValid(
            new \Symfony\Component\Security\Csrf\CsrfToken($expectedToken, $token)
        )) {
            $this->addFlash('error', $this->trans('Invalid CSRF token', 'Modules.Sciqitrepair.Admin'));

            return $this->redirectToRoute('sc_iqit_repair_index');
        }

        $result = $this->dispatcher->apply($type);

        $acceptsJson = $request->headers->get('Accept') === 'application/json';

        if ($acceptsJson) {
            return new JsonResponse($result);
        }

        if ($result['success'] ?? false) {
            $this->addFlash('success', $this->trans('Fix applied successfully', 'Modules.Sciqitrepair.Admin'));
        } else {
            $this->addFlash(
                'error',
                $this->trans(
                    'Error applying fix: %error%',
                    'Modules.Sciqitrepair.Admin',
                    ['%error%' => $result['error'] ?? 'Unknown']
                )
            );
        }

        return $this->render(
            '@Modules/sc_iqit_repair/views/templates/admin/result.html.twig',
            [
                'layoutTitle' => $this->trans('Result: %type%', 'Modules.Sciqitrepair.Admin', ['%type%' => $type]),
                'enableSidebar' => true,
                'help_link' => false,
                'type' => $type,
                'result' => $result,
            ]
        );
    }
}
