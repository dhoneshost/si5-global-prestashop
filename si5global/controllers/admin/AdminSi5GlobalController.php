<?php
/**
 * Chamadas AJAX da página do produto: consultar (só lê) e aplicar (grava os campos marcados).
 * Login, permissão e token de segurança do painel são verificados pelo próprio ModuleAdminController.
 *
 * @author    SI5
 * @copyright 2026 SI5
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'si5global/classes/Si5GlobalClient.php';
require_once _PS_MODULE_DIR_ . 'si5global/classes/Si5GlobalAplicador.php';

class AdminSi5GlobalController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    private function cliente()
    {
        return new Si5GlobalClient((string) Configuration::get('SI5GLOBAL_TOKEN'), 'SI5Global-PrestaShop/' . $this->module->version);
    }

    private function produto()
    {
        $id = (int) Tools::getValue('id_product');
        $p = $id ? new Product($id, false) : null;
        if (!$p || !Validate::isLoadedObject($p)) {
            throw new Exception('Produto não encontrado.');
        }
        // mesma permissão de quem edita produtos no painel
        if (!$this->context->employee->can('edit', 'AdminProducts')) {
            throw new Exception('Sem permissão para editar produtos.');
        }

        return $p;
    }

    private function responder(array $dados, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        exit(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function ajaxProcessConsultar()
    {
        try {
            if (!Tools::isSubmit('id_product') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Requisição inválida.');
            }
            $p = $this->produto();
            $gtin = Si5GlobalAplicador::gtinDoProduto($p);
            if ($gtin === null) {
                throw new Exception('Informe um EAN-13 ou UPC válido no produto (ou na referência) e salve.');
            }
            $sugestao = $this->cliente()->consultar($gtin);
            $idLang = (int) $this->context->language->id;
            $sugestao['peso'] = $sugestao['peso_gramas'] ? Si5GlobalAplicador::pesoNaUnidade($sugestao['peso_gramas']) . ' ' . Configuration::get('PS_WEIGHT_UNIT') : '';
            $this->responder(array('ok' => true, 'atual' => Si5GlobalAplicador::atuais($p, $idLang), 'sugerido' => $sugestao));
        } catch (Exception $e) {
            $this->responder(array('ok' => false, 'erro' => $e->getMessage()), 200);
        }
    }

    public function ajaxProcessAplicar()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Requisição inválida.');
            }
            $p = $this->produto();
            $dados = json_decode((string) Tools::getValue('sugerido'), true);
            $campos = Tools::getValue('campos');
            if (!is_array($dados) || !is_array($campos) || !$campos) {
                throw new Exception('Marque pelo menos um campo para aplicar.');
            }
            $alterados = Si5GlobalAplicador::aplicar($p, $dados, $campos, $this->cliente());
            $this->responder(array('ok' => true, 'alterados' => $alterados));
        } catch (Exception $e) {
            $this->responder(array('ok' => false, 'erro' => $e->getMessage()), 200);
        }
    }
}
