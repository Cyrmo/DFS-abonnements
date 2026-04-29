<?php
/**
 * DFS Abonnements — Classe principale
 *
 * @author    Cyrille Mohr - Digital Food System
 * @copyright 2025 Digital Food System
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

// Autoloader PSR-4 manuel (pas besoin de composer install sur le serveur)
spl_autoload_register(function (string $class): void {
    $prefix  = 'DfsAbonnements\\';
    $baseDir = __DIR__ . '/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

class Dfs_Abonnements extends Module
{
    // =========================================================
    // CONSTANTES DE CONFIGURATION
    // =========================================================

    const CFG_ID_PRODUCT_1M         = 'DFS_ABONNEMENTS_PROD_1M';
    const CFG_ID_PRODUCT_3M         = 'DFS_ABONNEMENTS_PROD_3M';
    const CFG_ID_PRODUCT_6M         = 'DFS_ABONNEMENTS_PROD_6M';
    const CFG_ID_PRODUCT_12M        = 'DFS_ABONNEMENTS_PROD_12M';
    const CFG_PROMO_6M              = 'DFS_ABONNEMENTS_PROMO_6M';
    const CFG_PROMO_12M             = 'DFS_ABONNEMENTS_PROMO_12M';
    const CFG_PROMO_VALIDITY_MONTHS = 'DFS_ABONNEMENTS_PROMO_VALIDITY';
    const CFG_STATUS_ABONNEMENT     = 'DFS_ABONNEMENTS_STATUS_ID';
    const CFG_STATUS_TERMINE        = 'DFS_ABONNEMENTS_STATUS_TERMINE_ID';

    // Flag interne utilisé par le hook de filtre de grille
    public static bool $filterGridActive = false;

    // =========================================================
    // CONSTRUCTEUR
    // =========================================================

    public function __construct()
    {
        $this->name          = 'dfs_abonnements';
        $this->tab           = 'administration';
        $this->version       = '1.0.0';
        $this->author        = 'Cyrille Mohr - Digital Food System';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->trans(
            'DFS Abonnements',
            [],
            'Modules.Dfsabonnements.Admin'
        );
        $this->description = $this->trans(
            'Gestion des commandes d\'abonnement Les Fromages Gourmands.',
            [],
            'Modules.Dfsabonnements.Admin'
        );
    }

    // =========================================================
    // INSTALLATION
    // =========================================================

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installOrderStates()
            && $this->installTab()
            && $this->installConfig()
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionOrderGridQueryBuilderModifier');
    }

    private function installSql(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function installOrderStates(): bool
    {
        // État "Abonnement"
        if (!(int) Configuration::get(self::CFG_STATUS_ABONNEMENT)) {
            $state             = new OrderState();
            $state->color      = '#8E24AA';
            $state->send_email = false;
            $state->module_name = $this->name;
            $state->hidden     = false;
            $state->delivery   = false;
            $state->logable    = true;
            $state->invoice    = false;
            $state->paid       = true;

            foreach (Language::getLanguages(false) as $lang) {
                $state->name[$lang['id_lang']] = 'Abonnement';
            }

            if (!$state->add()) {
                return false;
            }

            Configuration::updateValue(self::CFG_STATUS_ABONNEMENT, (int) $state->id);
        }

        // État "Abonnement terminé"
        if (!(int) Configuration::get(self::CFG_STATUS_TERMINE)) {
            $state             = new OrderState();
            $state->color      = '#616161';
            $state->send_email = false;
            $state->module_name = $this->name;
            $state->hidden     = false;
            $state->delivery   = false;
            $state->logable    = true;
            $state->invoice    = false;
            $state->paid       = true;

            foreach (Language::getLanguages(false) as $lang) {
                $state->name[$lang['id_lang']] = 'Abonnement terminé';
            }

            if (!$state->add()) {
                return false;
            }

            Configuration::updateValue(self::CFG_STATUS_TERMINE, (int) $state->id);
        }

        return true;
    }

    private function installTab(): bool
    {
        // Tab principal "Abonnements" sous AdminParentOrders
        $idParent = (int) Tab::getIdFromClassName('AdminParentOrders');

        $tab             = new Tab();
        $tab->class_name = 'AdminDfsAbonnements';
        $tab->route_name = 'dfs_abonnements_index';
        $tab->id_parent  = $idParent;
        $tab->module     = $this->name;
        $tab->active     = true;
        $tab->icon       = 'subscriptions';
        $tab->position   = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Abonnements';
        }

        if (!$tab->add()) {
            return false;
        }

        // Tab caché pour la config
        $tabConfig             = new Tab();
        $tabConfig->class_name = 'AdminDfsAbonnementsConfig';
        $tabConfig->route_name = 'dfs_abonnements_config';
        $tabConfig->id_parent  = -1;
        $tabConfig->module     = $this->name;
        $tabConfig->active     = true;

        foreach (Language::getLanguages(false) as $lang) {
            $tabConfig->name[$lang['id_lang']] = 'DFS Abonnements Config';
        }

        return $tabConfig->add();
    }

    private function installConfig(): bool
    {
        $defaults = [
            self::CFG_ID_PRODUCT_1M         => 23,
            self::CFG_ID_PRODUCT_3M         => 24,
            self::CFG_ID_PRODUCT_6M         => 25,
            self::CFG_ID_PRODUCT_12M        => 26,
            self::CFG_PROMO_6M              => 30,
            self::CFG_PROMO_12M             => 75,
            self::CFG_PROMO_VALIDITY_MONTHS => 12,
        ];

        foreach ($defaults as $key => $value) {
            if (!Configuration::get($key)) {
                Configuration::updateValue($key, $value);
            }
        }

        return true;
    }

    // =========================================================
    // DÉSINSTALLATION
    // =========================================================

    public function uninstall(): bool
    {
        return $this->uninstallSql()
            && $this->uninstallTab()
            && $this->uninstallOrderStates()
            && $this->uninstallConfig()
            && parent::uninstall();
    }

    private function uninstallSql(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (str_starts_with($query, '--')) {
                continue;
            }
            Db::getInstance()->execute($query);
        }
        return true;
    }

    private function uninstallTab(): bool
    {
        foreach (['AdminDfsAbonnements', 'AdminDfsAbonnementsConfig'] as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }
        return true;
    }

    private function uninstallOrderStates(): bool
    {
        // On ne supprime PAS les états pour préserver l'historique des commandes existantes.
        // On les désactive simplement pour qu'ils n'apparaissent plus dans la liste.
        foreach ([self::CFG_STATUS_ABONNEMENT, self::CFG_STATUS_TERMINE] as $configKey) {
            $idState = (int) Configuration::get($configKey);
            if ($idState) {
                $state = new OrderState($idState);
                if (Validate::isLoadedObject($state)) {
                    $state->deleted = true;
                    $state->update();
                }
            }
        }
        return true;
    }

    private function uninstallConfig(): bool
    {
        $keys = [
            self::CFG_ID_PRODUCT_1M,
            self::CFG_ID_PRODUCT_3M,
            self::CFG_ID_PRODUCT_6M,
            self::CFG_ID_PRODUCT_12M,
            self::CFG_PROMO_6M,
            self::CFG_PROMO_12M,
            self::CFG_PROMO_VALIDITY_MONTHS,
            self::CFG_STATUS_ABONNEMENT,
            self::CFG_STATUS_TERMINE,
        ];

        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    // =========================================================
    // PAGE DE CONFIGURATION
    // =========================================================

    public function getContent(): string
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminDfsAbonnementsConfig')
        );
        return '';
    }

    // =========================================================
    // HOOK : Filtre de grille (uniquement sur notre page)
    // =========================================================

    public function hookActionOrderGridQueryBuilderModifier(array $params): void
    {
        // N'intervenir QUE sur la page Abonnements du module
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/abonnements') === false) {
            return;
        }

        $idAbonnement = (int) Configuration::get(self::CFG_STATUS_ABONNEMENT);
        $idTermine    = (int) Configuration::get(self::CFG_STATUS_TERMINE);

        if (!$idAbonnement || !$idTermine) {
            return;
        }

        /** @var \Doctrine\DBAL\Query\QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];
        $countQueryBuilder  = $params['count_query_builder'];

        $statusIds = implode(',', [(int) $idAbonnement, (int) $idTermine]);

        $searchQueryBuilder->andWhere('o.`current_state` IN (' . $statusIds . ')');
        $countQueryBuilder->andWhere('o.`current_state` IN (' . $statusIds . ')');
    }

    // =========================================================
    // HOOK : Changement de statut de commande
    // =========================================================

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $newStatus = $params['newOrderStatus'] ?? null;
        $idOrder   = (int) ($params['id_order'] ?? 0);

        if (!$newStatus || !$idOrder) {
            return;
        }

        $idStatusAbonnement = (int) Configuration::get(self::CFG_STATUS_ABONNEMENT);
        $idStatusTermine    = (int) Configuration::get(self::CFG_STATUS_TERMINE);

        // Passage en état "Abonnement" → génération code promo + log
        if ((int) $newStatus->id === $idStatusAbonnement) {
            $this->handleAbonnementStatus($idOrder);
        }

        // Passage en état "Abonnement terminé" → email automatique
        if ((int) $newStatus->id === $idStatusTermine) {
            $this->handleAbonnementTermineStatus($idOrder);
        }
    }

    // =========================================================
    // LOGIQUE : État "Abonnement"
    // =========================================================

    private function handleAbonnementStatus(int $idOrder): void
    {
        try {
            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $promoAmount = $this->getPromoAmountForOrder($order);

            if ($promoAmount <= 0) {
                return;
            }

            // Anti-doublon : vérifier si le code promo a déjà été généré
            if ($this->hasLog($idOrder, 'promo_generated')) {
                return;
            }

            $voucherCode = $this->generateVoucherCode($order, $promoAmount);

            if ($voucherCode === null) {
                return;
            }

            $this->addLog($idOrder, (int) $order->id_customer, 'promo_generated', $voucherCode);
            $this->sendMailPromo($order, $voucherCode, $promoAmount);

        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'DFS Abonnements [handleAbonnementStatus] Erreur: ' . $e->getMessage(),
                3, null, 'Order', $idOrder
            );
        }
    }

    private function getPromoAmountForOrder(Order $order): float
    {
        $id6m  = (int) Configuration::get(self::CFG_ID_PRODUCT_6M);
        $id12m = (int) Configuration::get(self::CFG_ID_PRODUCT_12M);

        foreach ($order->getProducts() as $product) {
            $idProduct = (int) $product['id_product'];
            if ($idProduct === $id6m) {
                return (float) Configuration::get(self::CFG_PROMO_6M);
            }
            if ($idProduct === $id12m) {
                return (float) Configuration::get(self::CFG_PROMO_12M);
            }
        }

        return 0.0;
    }

    private function generateVoucherCode(Order $order, float $amount): ?string
    {
        $validityMonths = (int) Configuration::get(self::CFG_PROMO_VALIDITY_MONTHS) ?: 12;
        $dateFrom       = date('Y-m-d H:i:s');
        $dateTo         = date('Y-m-d H:i:s', strtotime("+{$validityMonths} months"));
        $code           = 'ABONNEMENT-' . $order->id . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        $cartRule                   = new CartRule();
        $cartRule->code             = $code;
        $cartRule->id_customer      = (int) $order->id_customer;
        $cartRule->date_from        = $dateFrom;
        $cartRule->date_to          = $dateTo;
        $cartRule->quantity         = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->reduction_amount = $amount;
        $cartRule->reduction_tax    = true;
        $cartRule->reduction_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $cartRule->minimum_amount   = 0;
        $cartRule->active           = true;
        $cartRule->highlight        = true;

        foreach (Language::getLanguages(false) as $lang) {
            $cartRule->name[$lang['id_lang']] = 'Code abonnement - Commande ' . $order->reference;
        }

        if (!$cartRule->add()) {
            PrestaShopLogger::addLog(
                'DFS Abonnements: Impossible de créer le CartRule pour commande #' . $order->id,
                3, null, 'Order', $order->id
            );
            return null;
        }

        return $code;
    }

    // =========================================================
    // LOGIQUE : État "Abonnement terminé"
    // =========================================================

    private function handleAbonnementTermineStatus(int $idOrder): void
    {
        try {
            // Anti-doublon
            if ($this->hasLog($idOrder, 'termine_email_sent')) {
                return;
            }

            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $this->sendMailAbonnementTermine($order);

        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'DFS Abonnements [handleAbonnementTermineStatus] Erreur: ' . $e->getMessage(),
                3, null, 'Order', $idOrder
            );
        }
    }

    // =========================================================
    // ENVOI EMAILS
    // =========================================================

    public function sendMailAbonnementPret(Order $order): bool
    {
        // Anti-doublon par commande
        if ($this->hasLog($order->id, 'pret_email_sent')) {
            return false;
        }

        $result = $this->sendModuleMail($order, 'dfs_abonnement_pret', 'Votre abonnement est prêt', []);

        if ($result) {
            $this->addLog((int) $order->id, (int) $order->id_customer, 'pret_email_sent', '');
        }

        return $result;
    }

    private function sendMailAbonnementTermine(Order $order): void
    {
        $this->sendMailAbonnementTerminePublic($order);
    }

    public function sendMailAbonnementTerminePublic(Order $order): void
    {
        $result = $this->sendModuleMail($order, 'dfs_abonnement_termine', 'Votre abonnement est terminé', []);

        if ($result) {
            $this->addLog((int) $order->id, (int) $order->id_customer, 'termine_email_sent', '');
        }
    }

    private function sendMailPromo(Order $order, string $code, float $amount): void
    {
        $validityMonths = (int) Configuration::get(self::CFG_PROMO_VALIDITY_MONTHS) ?: 12;
        $expiryDate     = date('d/m/Y', strtotime("+{$validityMonths} months"));

        $result = $this->sendModuleMail($order, 'dfs_abonnement_promo', 'Votre code promo abonnement', [
            '{voucher_code}'   => $code,
            '{voucher_amount}' => number_format($amount, 2, ',', ' ') . ' €',
            '{voucher_expiry}' => $expiryDate,
        ]);

        if ($result) {
            $this->addLog((int) $order->id, (int) $order->id_customer, 'promo_email_sent', $code);
        }
    }

    private function sendModuleMail(Order $order, string $template, string $subject, array $extraVars): bool
    {
        $idLang   = (int) $order->id_lang;
        $customer = new Customer((int) $order->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            return false;
        }

        // Nom du produit abonnement dans la commande
        $abonnementName = '';
        foreach ($order->getProducts() as $product) {
            $abonnementName = $product['product_name'];
            break;
        }

        $templateVars = array_merge([
            '{firstname}'       => $customer->firstname,
            '{lastname}'        => $customer->lastname,
            '{order_reference}' => $order->reference,
            '{abonnement_name}' => $abonnementName,
            '{shop_url}'        => $this->context->shop->getBaseURL(true),
            '{shop_name}'       => Configuration::get('PS_SHOP_NAME'),
        ], $extraVars);

        $mailDir = __DIR__ . '/mails/';

        return (bool) Mail::send(
            $idLang,
            $template,
            Mail::l($subject, $idLang),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            $mailDir
        );
    }

    // =========================================================
    // JOURNALISATION
    // =========================================================

    public function addLog(int $idOrder, int $idCustomer, string $action, string $detail): void
    {
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'dfs_abonnements_log`
             (`id_order`, `id_customer`, `action`, `detail`, `date_add`)
             VALUES ('
            . (int) $idOrder . ', '
            . (int) $idCustomer . ', '
            . "'" . pSQL($action) . "', "
            . "'" . pSQL($detail) . "', "
            . "NOW())"
        );
    }

    public function hasLog(int $idOrder, string $action): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT `id_log` FROM `' . _DB_PREFIX_ . 'dfs_abonnements_log`
             WHERE `id_order` = ' . (int) $idOrder . "
             AND `action` = '" . pSQL($action) . "'"
        );
    }

    public function getLogs(int $limit = 50): array
    {
        return Db::getInstance()->executeS(
            'SELECT l.*, o.`reference`
             FROM `' . _DB_PREFIX_ . 'dfs_abonnements_log` l
             LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = l.`id_order`
             ORDER BY l.`id_log` DESC
             LIMIT ' . (int) $limit
        ) ?: [];
    }

    // =========================================================
    // UTILITAIRES PUBLICS (utilisés par le contrôleur)
    // =========================================================

    public function getStatusIds(): array
    {
        return [
            'abonnement' => (int) Configuration::get(self::CFG_STATUS_ABONNEMENT),
            'termine'    => (int) Configuration::get(self::CFG_STATUS_TERMINE),
        ];
    }

    public function getAbonnementOrders(): array
    {
        $ids = $this->getStatusIds();

        if (!$ids['abonnement']) {
            return [];
        }

        return Db::getInstance()->executeS(
            'SELECT o.`id_order`, o.`reference`, o.`id_customer`,
                    c.`firstname`, c.`lastname`, c.`email`,
                    o.`current_state`, o.`date_add`
             FROM `' . _DB_PREFIX_ . 'orders` o
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = o.`id_customer`
             WHERE o.`current_state` = ' . (int) $ids['abonnement'] . '
             ORDER BY o.`date_add` DESC'
        ) ?: [];
    }
}
