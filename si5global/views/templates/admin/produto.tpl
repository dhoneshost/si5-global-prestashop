{**
 * SI5 Global - bloco na aba "Módulos" da página do produto.
 *
 * @author    SI5
 * @copyright 2026 SI5
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<link rel="stylesheet" href="{$si5_css|escape:'html':'UTF-8'}">
<script src="{$si5_js|escape:'html':'UTF-8'}" defer></script>
<div class="si5g" id="si5g" data-url="{$si5_url|escape:'html':'UTF-8'}" data-produto="{$si5_id_produto|intval}">
  <h3 class="si5g-titulo">SI5 Global</h3>
  {if !$si5_tem_token}
    <div class="alert alert-warning">
      Configure o token da API em <a href="{$si5_config_url|escape:'html':'UTF-8'}">Módulos › SI5 Global</a>.
    </div>
  {elseif !$si5_gtin}
    <div class="alert alert-info">Informe um EAN-13 ou UPC válido no produto (ou na referência) e salve para consultar.</div>
  {else}
    <p>Código de barras: <strong>{$si5_gtin|escape:'html':'UTF-8'}</strong></p>
    <button type="button" class="btn btn-primary" id="si5g-consultar">Buscar no SI5 Global</button>
    <p class="help-block">Nada é gravado sem a sua revisão. Só o código de barras e o token vão para o SI5 Global.</p>
  {/if}

  <div class="si5g-revisao" id="si5g-revisao" hidden>
    <table class="table si5g-tabela">
      <thead><tr><th></th><th>Atual</th><th>Sugerido</th><th class="text-center">Substituir</th></tr></thead>
      <tbody id="si5g-linhas"></tbody>
    </table>
    <p class="si5g-ref" id="si5g-ref"></p>
    <button type="button" class="btn btn-success" id="si5g-aplicar">Aplicar campos marcados</button>
    <button type="button" class="btn btn-default" id="si5g-cancelar">Cancelar</button>
  </div>
  <div class="si5g-msg" id="si5g-msg" role="status" aria-live="polite"></div>
</div>
