# SI5 Global para PrestaShop

Módulo que completa o produto pelo **código de barras (EAN/UPC)**: nome, marca, peso, foto, **NCM e CEST**, a partir do catálogo [SI5 Global](https://global.si5.com.br), com milhões de produtos do varejo brasileiro.

*English summary below.*

## Como funciona

1. Na edição do produto, aba **Módulos › SI5 Global**, clique em **Buscar no SI5 Global**.
2. O módulo mostra lado a lado o que o produto tem hoje e o que o catálogo sugere.
3. Marque só o que quer trocar e clique em **Aplicar campos marcados**. Nada é gravado sem essa revisão.

O que é gravado em cada campo:

| Campo | Onde fica no PrestaShop |
|---|---|
| Nome | Nome do produto, em todos os idiomas |
| Marca | Marca (fabricante); é criada se ainda não existir |
| Peso | Peso do produto, convertido para a unidade da loja (kg, g ou lb) |
| Foto | Nova imagem de capa; as fotos atuais continuam no produto |
| NCM e CEST | Características (*features*) "NCM" e "CEST" do produto |

O código de barras é lido do campo EAN-13, do UPC ou da referência (quando ela é um código válido). Aceita EAN-8, EAN-13, UPC-A e GTIN-14.

NCM e CEST são **sugestões do catálogo**. Confirme com seu contador antes de emitir notas.

## Dados enviados

O módulo consulta `https://global.si5.com.br/api/produtos/{código}` enviando **apenas o código de barras e o seu token**. A foto é baixada só dos servidores de imagem do SI5 Global, sem o token e sem o endereço da loja. Nenhum outro dado (clientes, pedidos, preços) sai da loja.

## Instalação

1. Baixe o `si5global.zip` em [Releases](../../releases).
2. Em *Módulos › Gerenciador de módulos › Enviar um módulo*, envie o `.zip`.
3. Crie uma conta e um token em global.si5.com.br (*Minha conta*). O plano gratuito tem uma cota diária de consultas (veja os limites de cada plano em global.si5.com.br).
4. Em *Configurar* do módulo, cole o token e salve.

Requisitos: PrestaShop 1.7.7 ou mais novo, PHP 7.2+ com cURL e GD.

Testado no PrestaShop 8.2.8 (página de produto nova) com PHP 8.1.

## English

PrestaShop module that fills product name, brand, weight, picture and the Brazilian fiscal codes **NCM / CEST** from the barcode (EAN/UPC), using the SI5 Global catalogue. Every change is reviewed in the product page before it is saved. Only the barcode and your API token are sent to the API.

## Licença

[AFL-3.0](LICENSE.md). © 2026 SI5.
