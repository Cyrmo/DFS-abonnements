<?php
/**
 * DFS Abonnements — Contrôleur de configuration
 *
 * @author    Cyrille Mohr - Digital Food System
 * @copyright 2025 Digital Food System
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace DfsAbonnements\Controller\Admin;

use Configuration;
use Dfs_Abonnements;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminDfsAbonnementsConfigController extends PrestaShopAdminController
{
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(Request $request): Response
    {
        $confirmations = [];
        $errors        = [];

        if ($request->isMethod('POST') && $request->request->get('submitDfsAbonnementsConfig')) {
            [$confirmations, $errors] = $this->saveConfig($request);
        }

        $config = [
            'prod_1m'         => (int) Configuration::get(Dfs_Abonnements::CFG_ID_PRODUCT_1M),
            'prod_3m'         => (int) Configuration::get(Dfs_Abonnements::CFG_ID_PRODUCT_3M),
            'prod_6m'         => (int) Configuration::get(Dfs_Abonnements::CFG_ID_PRODUCT_6M),
            'prod_12m'        => (int) Configuration::get(Dfs_Abonnements::CFG_ID_PRODUCT_12M),
            'promo_6m'        => (float) Configuration::get(Dfs_Abonnements::CFG_PROMO_6M),
            'promo_12m'       => (float) Configuration::get(Dfs_Abonnements::CFG_PROMO_12M),
            'promo_validity'  => (int) Configuration::get(Dfs_Abonnements::CFG_PROMO_VALIDITY_MONTHS),
            'status_abonnement' => (int) Configuration::get(Dfs_Abonnements::CFG_STATUS_ABONNEMENT),
            'status_termine'  => (int) Configuration::get(Dfs_Abonnements::CFG_STATUS_TERMINE),
        ];

        /** @var Dfs_Abonnements|null $module */
        $module = \Module::getInstanceByName('dfs_abonnements');
        $logs   = $module ? $module->getLogs(20) : [];

        return $this->render('@Modules/dfs_abonnements/views/templates/admin/configure.html.twig', [
            'config'        => $config,
            'confirmations' => $confirmations,
            'errors'        => $errors,
            'logs'          => $logs,
            'enableSidebar' => true,
            'layoutTitle'   => 'DFS Abonnements — Configuration',
        ]);
    }

    private function saveConfig(Request $request): array
    {
        $confirmations = [];
        $errors        = [];

        $fields = [
            Dfs_Abonnements::CFG_ID_PRODUCT_1M         => 'prod_1m',
            Dfs_Abonnements::CFG_ID_PRODUCT_3M         => 'prod_3m',
            Dfs_Abonnements::CFG_ID_PRODUCT_6M         => 'prod_6m',
            Dfs_Abonnements::CFG_ID_PRODUCT_12M        => 'prod_12m',
            Dfs_Abonnements::CFG_PROMO_6M              => 'promo_6m',
            Dfs_Abonnements::CFG_PROMO_12M             => 'promo_12m',
            Dfs_Abonnements::CFG_PROMO_VALIDITY_MONTHS => 'promo_validity',
        ];

        foreach ($fields as $configKey => $formField) {
            $value = $request->request->get($formField);
            if ($value !== null) {
                Configuration::updateValue($configKey, $value);
            }
        }

        $confirmations[] = 'Configuration sauvegardée avec succès.';

        return [$confirmations, $errors];
    }
}
