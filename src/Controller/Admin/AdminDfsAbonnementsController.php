<?php
/**
 * DFS Abonnements — Contrôleur principal BO
 *
 * @author    Cyrille Mohr - Digital Food System
 * @copyright 2025 Digital Food System
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace DfsAbonnements\Controller\Admin;

use Configuration;
use Dfs_Abonnements;
use Order;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Validate;

class AdminDfsAbonnementsController extends PrestaShopAdminController
{
    public function __construct(
        private readonly GridFactoryInterface $orderGridFactory
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(Request $request): Response
    {
        /** @var Dfs_Abonnements|null $module */
        $module = \Module::getInstanceByName('dfs_abonnements');

        if (!$module) {
            $this->addFlash('error', 'Module DFS Abonnements introuvable.');
            return $this->render('@Modules/dfs_abonnements/views/templates/admin/abonnements.html.twig', [
                'orderGrid'         => null,
                'abonnementOrders'  => [],
                'uniqueEmailsCount' => 0,
                'statusIds'         => [],
                'logs'              => [],
                'mailSentCount'     => 0,
            ]);
        }

        $statusIds = $module->getStatusIds();

        // Traitement du formulaire d'envoi groupé "abonnement prêt"
        $mailSentCount = 0;
        if ($request->isMethod('POST') && $request->request->get('send_pret_mail')) {
            $mailSentCount = $this->handleSendPretMail($module);
            if ($mailSentCount > 0) {
                $this->addFlash('success', sprintf('%d mail(s) envoyé(s) avec succès.', $mailSentCount));
            } else {
                $this->addFlash('info', 'Aucun mail envoyé (déjà envoyé ou aucune commande éligible).');
            }
        }

        // Renvoi individuel "abonnement terminé"
        if ($request->isMethod('POST') && $request->request->get('resend_termine_mail')) {
            $idOrder = (int) $request->request->get('resend_order_id');
            if ($idOrder) {
                $this->handleResendTermineMail($module, $idOrder);
            }
        }

        // Construction de la grille native de manière totalement sécurisée.
        // On instancie manuellement les filtres pour éviter tout crash du routeur si le resolver PS9 échoue.
        $orderGrid = null;
        try {
            $filters   = new OrderFilters(['filters' => []], $request);
            $orderGrid = $this->orderGridFactory->getGrid($filters);
            $presentedGrid = $this->presentGrid($orderGrid);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du chargement de la grille : ' . $e->getMessage());
            $presentedGrid = null;
        }

        $abonnementOrders = $module->getAbonnementOrders();
        $uniqueEmails = [];
        foreach ($abonnementOrders as $o) {
            if (!empty($o['email'])) {
                $uniqueEmails[$o['email']] = true;
            }
        }
        $uniqueEmailsCount = count($uniqueEmails);

        return $this->render('@Modules/dfs_abonnements/views/templates/admin/abonnements.html.twig', [
            'orderGrid'        => $presentedGrid,
            'abonnementOrders' => $abonnementOrders,
            'uniqueEmailsCount'=> $uniqueEmailsCount,
            'statusIds'        => $statusIds,
            'logs'             => $module->getLogs(100),
            'mailSentCount'    => $mailSentCount,
            'enableSidebar'    => true,
            'layoutTitle'      => 'Abonnements',
            'layoutHeaderToolbarBtn' => [],
        ]);
    }


    private function handleSendPretMail(Dfs_Abonnements $module): int
    {
        $orders = $module->getAbonnementOrders();
        $sent   = 0;

        foreach ($orders as $row) {
            $order = new Order((int) $row['id_order']);
            if (!Validate::isLoadedObject($order)) {
                continue;
            }
            if ($module->sendMailAbonnementPret($order)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function handleResendTermineMail(Dfs_Abonnements $module, int $idOrder): void
    {
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            $this->addFlash('error', 'Commande #' . $idOrder . ' introuvable.');
            return;
        }

        // Supprimer le log existant pour permettre le renvoi
        \Db::getInstance()->execute(
            "DELETE FROM `" . _DB_PREFIX_ . "dfs_abonnements_log`
             WHERE `id_order` = " . (int) $idOrder . "
             AND `action` = 'termine_email_sent'"
        );

        // Re-déclencher l'envoi via la méthode publique interne
        $module->sendMailAbonnementTerminePublic($order);
        $this->addFlash('success', 'Mail "abonnement terminé" renvoyé pour la commande #' . $idOrder . '.');
    }
}
