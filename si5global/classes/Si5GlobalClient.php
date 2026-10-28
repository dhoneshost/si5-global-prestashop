<?php
/**
 * SI5 Global - cliente da API (mesmo codigo nos modulos PrestaShop, OpenCart e Magento).
 *
 * Envia a API apenas o codigo de barras e o token da loja, com um user-agent fixo (sem o endereco da loja).
 * Fotos so sao baixadas dos servidores do SI5 Global, sem redirecionamento e com limite de tamanho.
 *
 * @license AFL-3.0 / OSL-3.0 (ver LICENSE do repositorio)
 */
if (!defined('_PS_VERSION_') && !defined('SI5GLOBAL_STANDALONE')) {
    exit;
}

class Si5GlobalClient
{
    const API = 'https://global.si5.com.br/api/produtos/';
    const HOSTS_IMAGEM = array('global.si5.com.br', 'cdn2.si5.com.br', 'cdn3.si5.com.br');
    const LIMITE_JSON = 262144;
    const LIMITE_IMAGEM = 5242880;

    /** @var string */
    private $token;
    /** @var string */
    private $agente;

    public function __construct($token, $agente)
    {
        $this->token = trim((string) $token);
        $this->agente = (string) $agente;
    }

    /**
     * Normaliza e valida um GTIN (EAN-8, UPC-A, EAN-13, GTIN-14). Devolve o codigo ou null.
     * Prefixos de circulacao restrita (20-29, 040-049, 200-299 no EAN-13) nao sao consultados.
     */
    public static function gtin($valor)
    {
        $codigo = preg_replace('/\s+/', '', (string) $valor);
        if (!preg_match('/^(\d{8}|\d{12,14})$/', $codigo) || trim($codigo, '0') === '') {
            return null;
        }
        $unidade = strlen($codigo) === 14 ? substr($codigo, 1) : (strlen($codigo) === 12 ? '0' . $codigo : $codigo);
        $prefixo = (int) substr($unidade, 0, 3);
        if (strlen($unidade) === 13 && (($prefixo >= 20 && $prefixo <= 29) || ($prefixo >= 40 && $prefixo <= 49) || ($prefixo >= 200 && $prefixo <= 299))) {
            return null;
        }
        $soma = 0;
        $semDigito = strrev(substr($codigo, 0, -1));
        for ($i = 0, $n = strlen($semDigito); $i < $n; $i++) {
            $soma += (int) $semDigito[$i] * ($i % 2 === 0 ? 3 : 1);
        }
        if ((10 - $soma % 10) % 10 !== (int) substr($codigo, -1)) {
            return null;
        }

        return $codigo;
    }

    /**
     * Consulta o produto. Devolve array com as sugestoes, ou lanca Exception com mensagem amigavel (sem token/URL).
     */
    public function consultar($codigo)
    {
        $gtin = self::gtin($codigo);
        if ($gtin === null) {
            throw new Exception('Código de barras inválido ou de circulação restrita.');
        }
        if ($this->token === '' || !preg_match('/^[!-~]{1,4096}$/', $this->token)) {
            throw new Exception('Configure o token da API do SI5 Global nas configurações do módulo.');
        }
        // o catalogo guarda UPC-A (12 digitos) como EAN-13 com zero a esquerda
        $consulta = strlen($gtin) === 12 ? '0' . $gtin : $gtin;
        list($status, $tipo, $corpo) = $this->http(self::API . $consulta, array(
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ), self::LIMITE_JSON);

        if ($status === 404) {
            throw new Exception('Produto não encontrado no catálogo SI5 Global.');
        }
        if ($status === 401) {
            throw new Exception('Token do SI5 Global inválido ou expirado.');
        }
        if ($status === 429) {
            throw new Exception('Limite de consultas do plano atingido. Tente mais tarde.');
        }
        if ($status !== 200 || stripos($tipo, 'application/json') !== 0) {
            throw new Exception('O SI5 Global não respondeu como esperado. Tente novamente.');
        }
        $d = json_decode($corpo, true, 32);
        if (!is_array($d) || !isset($d['codbar']) || str_pad((string) $d['codbar'], 14, '0', STR_PAD_LEFT) !== str_pad($gtin, 14, '0', STR_PAD_LEFT)) {
            throw new Exception('Resposta inválida do SI5 Global.');
        }
        $texto = function ($chave) use ($d) {
            return isset($d[$chave]) && is_string($d[$chave]) ? trim(mb_substr($d[$chave], 0, 2048)) : '';
        };
        $peso = isset($d['peso']) && is_numeric($d['peso']) && (float) $d['peso'] > 0 && (float) $d['peso'] < 100000000 ? (float) $d['peso'] : null;
        $nome = $texto('produto');
        if ($nome === '') {
            throw new Exception('Resposta inválida do SI5 Global.');
        }

        return array(
            'gtin' => $gtin,
            'nome' => $nome,
            'marca' => $texto('marca'),
            'categoria' => $texto('categoria'),
            'peso_gramas' => $peso,
            'imagem_url' => $texto('imagem_url'),
            'ncm' => preg_replace('/\D/', '', $texto('ncm')),
            'ncm_descricao' => $texto('ncm_descricao'),
            'cest' => preg_replace('/\D/', '', $texto('cest_codigo')),
        );
    }

    /** Baixa a foto (so dos hosts do SI5 Global). Devolve [bytes, extensao] ou lanca Exception. */
    public function baixarImagem($url)
    {
        $p = parse_url((string) $url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || !in_array(strtolower($p['host'] ?? ''), self::HOSTS_IMAGEM, true)
            || isset($p['port']) || isset($p['user']) || isset($p['query']) || !preg_match('#^/img/[0-9/]+\.(webp|png|jpe?g)$#', $p['path'] ?? '')) {
            throw new Exception('Endereço de imagem não permitido.');
        }
        list($status, $tipo, $corpo) = $this->http($url, array('Accept: image/*'), self::LIMITE_IMAGEM);
        $mime = strtolower(trim(explode(';', $tipo)[0]));
        $ext = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        if ($status !== 200 || !isset($ext[$mime])) {
            throw new Exception('Não foi possível baixar a foto.');
        }
        $assinaturaOk = ($mime === 'image/png' && strpos($corpo, "\x89PNG\r\n\x1a\n") === 0)
            || ($mime === 'image/jpeg' && strpos($corpo, "\xff\xd8\xff") === 0)
            || ($mime === 'image/webp' && substr($corpo, 0, 4) === 'RIFF' && substr($corpo, 8, 4) === 'WEBP');
        if (!$assinaturaOk) {
            throw new Exception('Formato de foto não permitido.');
        }

        return array($corpo, $ext[$mime]);
    }

    /** @return array{0:int,1:string,2:string} */
    private function http($url, array $cabecalhos, $limite)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('A extensão cURL do PHP é necessária.');
        }
        $corpo = '';
        $ch = curl_init($url);
        $estourou = false;
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_USERAGENT => $this->agente,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($ch, $parte) use (&$corpo, $limite, &$estourou) {
                $corpo .= $parte;
                if (strlen($corpo) > $limite) {
                    $estourou = true;

                    return 0;
                }

                return strlen($parte);
            },
        ));
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $erro = curl_errno($ch);
        curl_close($ch);
        if ($estourou) {
            throw new Exception('Resposta maior que o permitido.');
        }
        if ($erro) {
            throw new Exception('Falha de rede ao consultar o SI5 Global. Tente novamente.');
        }

        return array($status, $tipo, $corpo);
    }
}
