<?php
/**
 * Lê os valores atuais do produto e grava só os campos que o administrador marcou na revisão.
 *
 * @author    SI5
 * @copyright 2026 SI5
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Si5GlobalAplicador
{
    const CAMPOS = array('nome', 'marca', 'peso', 'imagem', 'ncm', 'cest');

    /** GTIN do produto: EAN-13, depois UPC, depois a referência (quando for um código válido). */
    public static function gtinDoProduto(Product $p)
    {
        foreach (array($p->ean13, $p->upc, $p->reference) as $valor) {
            $g = Si5GlobalClient::gtin($valor);
            if ($g !== null) {
                return $g;
            }
        }

        return null;
    }

    /** Valores atuais, para a coluna "Atual" da revisão. */
    public static function atuais(Product $p, $idLang)
    {
        $nome = is_array($p->name) ? ($p->name[$idLang] ?? reset($p->name)) : $p->name;

        return array(
            'nome' => (string) $nome,
            'marca' => $p->id_manufacturer ? (string) Manufacturer::getNameById((int) $p->id_manufacturer) : '',
            'peso' => (float) $p->weight > 0 ? rtrim(rtrim(number_format((float) $p->weight, 3, ',', ''), '0'), ',') . ' ' . Configuration::get('PS_WEIGHT_UNIT') : '',
            'imagem' => Image::getCover((int) $p->id) ? 'sim' : '',
            'ncm' => self::valorCaracteristica($p, 'NCM', $idLang),
            'cest' => self::valorCaracteristica($p, 'CEST', $idLang),
        );
    }

    /** Converte o peso bruto do catálogo (gramas) para a unidade da loja. */
    public static function pesoNaUnidade($gramas)
    {
        $unidade = strtolower(trim((string) Configuration::get('PS_WEIGHT_UNIT')));
        if ($unidade === 'g') {
            return round($gramas, 2);
        }
        if (in_array($unidade, array('lb', 'lbs'), true)) {
            return round($gramas / 453.59237, 3);
        }

        return round($gramas / 1000, 3);
    }

    /**
     * Grava os campos escolhidos. $dados = sugestões revisadas (as mesmas mostradas na janela), $campos = marcados.
     *
     * @return string[] campos alterados
     */
    public static function aplicar(Product $p, array $dados, array $campos, Si5GlobalClient $cliente)
    {
        $campos = array_values(array_intersect(self::CAMPOS, $campos));
        $alterados = array();
        $langs = Language::getLanguages(false);
        $idLangPadrao = (int) Configuration::get('PS_LANG_DEFAULT');

        if (in_array('nome', $campos, true)) {
            $nome = trim(Tools::substr(strip_tags((string) ($dados['nome'] ?? '')), 0, 128));
            if ($nome === '' || !Validate::isCatalogName($nome)) {
                throw new Exception('Nome sugerido inválido para o PrestaShop.');
            }
            foreach ($langs as $l) {
                $p->name[(int) $l['id_lang']] = $nome;
                if (empty($p->link_rewrite[(int) $l['id_lang']])) {
                    $p->link_rewrite[(int) $l['id_lang']] = Tools::str2url($nome);
                }
            }
            $alterados[] = 'nome';
        }

        if (in_array('marca', $campos, true) && trim((string) ($dados['marca'] ?? '')) !== '') {
            $marca = trim(Tools::substr(strip_tags((string) $dados['marca']), 0, 64));
            $idMarca = (int) Manufacturer::getIdByName($marca);
            if (!$idMarca) {
                $m = new Manufacturer();
                $m->name = $marca;
                $m->active = 1;
                if (!$m->add()) {
                    throw new Exception('Não foi possível criar a marca.');
                }
                $idMarca = (int) $m->id;
            }
            $p->id_manufacturer = $idMarca;
            $alterados[] = 'marca';
        }

        if (in_array('peso', $campos, true) && !empty($dados['peso_gramas']) && (float) $dados['peso_gramas'] > 0) {
            $p->weight = self::pesoNaUnidade((float) $dados['peso_gramas']);
            $alterados[] = 'peso';
        }

        if ($alterados && !$p->update()) {
            throw new Exception('O PrestaShop recusou a gravação do produto.');
        }

        if (in_array('imagem', $campos, true) && !empty($dados['imagem_url'])) {
            self::adicionarImagem($p, $cliente->baixarImagem((string) $dados['imagem_url']));
            $alterados[] = 'foto';
        }

        foreach (array('ncm' => array('NCM', 8), 'cest' => array('CEST', 7)) as $campo => list($rotulo, $tamanho)) {
            $valor = preg_replace('/\D/', '', (string) ($dados[$campo] ?? ''));
            if (in_array($campo, $campos, true) && strlen($valor) === $tamanho) {
                self::gravarCaracteristica($p, $rotulo, $valor, $idLangPadrao);
                $alterados[] = $rotulo;
            }
        }

        return $alterados;
    }

    /** Adiciona a foto como capa (as fotos antigas ficam). WebP/PNG são convertidos para JPEG pelo GD. */
    private static function adicionarImagem(Product $p, array $foto)
    {
        list($bytes) = $foto;
        $img = @imagecreatefromstring($bytes);
        if (!$img) {
            throw new Exception('O PHP desta loja não consegue ler a foto (GD sem suporte a WebP?).');
        }
        $tmp = tempnam(_PS_TMP_IMG_DIR_, 'si5');
        imagejpeg($img, $tmp, 92);
        imagedestroy($img);

        $imagem = new Image();
        $imagem->id_product = (int) $p->id;
        $imagem->position = Image::getHighestPosition((int) $p->id) + 1;
        $imagem->cover = true;
        foreach (Language::getLanguages(false) as $l) {
            $imagem->legend[(int) $l['id_lang']] = Tools::substr(is_array($p->name) ? (string) ($p->name[(int) $l['id_lang']] ?? '') : (string) $p->name, 0, 128);
        }
        Image::deleteCover((int) $p->id);
        if (!$imagem->add()) {
            @unlink($tmp);
            throw new Exception('Não foi possível registrar a foto.');
        }
        $destino = $imagem->getPathForCreation();
        $ok = ImageManager::resize($tmp, $destino . '.jpg');
        foreach (ImageType::getImagesTypes('products') as $tipo) {
            $ok = $ok && ImageManager::resize($tmp, $destino . '-' . stripslashes($tipo['name']) . '.jpg', (int) $tipo['width'], (int) $tipo['height']);
        }
        @unlink($tmp);
        if (!$ok) {
            $imagem->delete();
            throw new Exception('Não foi possível gerar as miniaturas da foto.');
        }
    }

    private static function idCaracteristica($rotulo, $idLang, $criar)
    {
        foreach (Feature::getFeatures($idLang) as $f) {
            if (strcasecmp(trim((string) $f['name']), $rotulo) === 0) {
                return (int) $f['id_feature'];
            }
        }
        if (!$criar) {
            return 0;
        }
        $f = new Feature();
        foreach (Language::getLanguages(false) as $l) {
            $f->name[(int) $l['id_lang']] = $rotulo;
        }

        return $f->add() ? (int) $f->id : 0;
    }

    private static function valorCaracteristica(Product $p, $rotulo, $idLang)
    {
        $idF = self::idCaracteristica($rotulo, $idLang, false);
        if (!$idF) {
            return '';
        }
        foreach (Product::getFrontFeaturesStatic($idLang, (int) $p->id) as $f) {
            if ((int) $f['id_feature'] === $idF) {
                return (string) $f['value'];
            }
        }

        return '';
    }

    /** NCM/CEST como característica do produto (valor próprio do produto, não compartilhado). */
    private static function gravarCaracteristica(Product $p, $rotulo, $valor, $idLang)
    {
        $idF = self::idCaracteristica($rotulo, $idLang, true);
        if (!$idF) {
            throw new Exception('Não foi possível criar a característica ' . $rotulo . '.');
        }
        Db::getInstance()->delete('feature_product', 'id_product = ' . (int) $p->id . ' AND id_feature = ' . (int) $idF);
        $idValor = (int) FeatureValue::addFeatureValueImport($idF, $valor, (int) $p->id, $idLang, true);
        Product::addFeatureProductImport((int) $p->id, $idF, $idValor);
    }
}
