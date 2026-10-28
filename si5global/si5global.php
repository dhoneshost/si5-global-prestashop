<?php
/**
 * SI5 Global para PrestaShop: completa o produto pelo código de barras (nome, marca, peso, foto, NCM e CEST),
 * sempre com revisão antes de gravar.
 *
 * @author    SI5
 * @copyright 2026 SI5
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/Si5GlobalClient.php';
require_once __DIR__ . '/classes/Si5GlobalAplicador.php';

class Si5Global extends Module
{
    const CFG_TOKEN = 'SI5GLOBAL_TOKEN';

    public function __construct()
    {
        $this->name = 'si5global';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'SI5';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7.7.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = 'SI5 Global';
        $this->description = 'Completa o produto pelo código de barras: nome, marca, peso, foto, NCM e CEST, com revisão antes de gravar.';
        $this->confirmUninstall = 'Remover o módulo SI5 Global? Os dados já gravados nos produtos continuam.';
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->instalarAba();
    }

    public function uninstall()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminSi5Global');
        if ($idTab) {
            (new Tab($idTab))->delete();
        }
        Configuration::deleteByName(self::CFG_TOKEN);

        return parent::uninstall();
    }

    /** Aba oculta: só recebe as chamadas AJAX da página do produto (a autenticação e o token do admin são do PrestaShop). */
    private function instalarAba()
    {
        if (Tab::getIdFromClassName('AdminSi5Global')) {
            return true;
        }
        $tab = new Tab();
        $tab->class_name = 'AdminSi5Global';
        $tab->module = $this->name;
        $tab->id_parent = -1;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'SI5 Global';
        }

        return (bool) $tab->add();
    }

    /** Página de configuração: token da API (só administradores com acesso ao módulo). */
    public function getContent()
    {
        $saida = '';
        if (Tools::isSubmit('submitSi5Global')) {
            $token = trim((string) Tools::getValue(self::CFG_TOKEN));
            if ($token !== '') {
                Configuration::updateValue(self::CFG_TOKEN, $token);
            }
            if (Tools::getValue('si5global_remover')) {
                Configuration::updateValue(self::CFG_TOKEN, '');
            }
            $saida .= $this->displayConfirmation('Configurações salvas.');
        }
        $temToken = (string) Configuration::get(self::CFG_TOKEN) !== '';

        $form = array('form' => array(
            'legend' => array('title' => 'SI5 Global', 'icon' => 'icon-barcode'),
            'description' => 'Crie uma conta e um token em global.si5.com.br (Minha conta). O plano gratuito permite 100 consultas por dia. '
                . 'A consulta envia ao SI5 Global apenas o código de barras do produto e este token.',
            'input' => array(
                array(
                    'type' => 'password',
                    'label' => 'Token da API',
                    'name' => self::CFG_TOKEN,
                    'desc' => $temToken ? 'Já existe um token salvo. Deixe em branco para manter.' : 'Cole o token criado em global.si5.com.br.',
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Apagar o token salvo',
                    'name' => 'si5global_remover',
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'si5_rem_on', 'value' => 1, 'label' => 'Sim'),
                        array('id' => 'si5_rem_off', 'value' => 0, 'label' => 'Não'),
                    ),
                ),
            ),
            'submit' => array('title' => 'Salvar', 'name' => 'submitSi5Global'),
        ));

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = array(self::CFG_TOKEN => '', 'si5global_remover' => 0);

        return $saida . $helper->generateForm(array($form));
    }

    /** Aba "Módulos" da página do produto: botão de consulta e janela de revisão. */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduto = (int) ($params['id_product'] ?? Tools::getValue('id_product'));
        if (!$idProduto) {
            return '<div class="alert alert-info">Salve o produto para consultar o SI5 Global.</div>';
        }
        $produto = new Product($idProduto, false, (int) $this->context->language->id);
        $this->context->smarty->assign(array(
            'si5_url' => $this->context->link->getAdminLink('AdminSi5Global'),
            'si5_id_produto' => $idProduto,
            'si5_gtin' => Si5GlobalAplicador::gtinDoProduto($produto),
            'si5_tem_token' => (string) Configuration::get(self::CFG_TOKEN) !== '',
            'si5_js' => $this->_path . 'views/js/si5global-admin.js?v=' . $this->version,
            'si5_css' => $this->_path . 'views/css/si5global-admin.css?v=' . $this->version,
            'si5_config_url' => $this->context->link->getAdminLink('AdminModules', true, array(), array('configure' => $this->name)),
        ));

        return $this->display(__FILE__, 'views/templates/admin/produto.tpl');
    }
}
