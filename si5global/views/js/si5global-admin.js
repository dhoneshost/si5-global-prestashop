/**
 * SI5 Global - revisão na página do produto (PrestaShop).
 * @author SI5 @copyright 2026 SI5 @license AFL-3.0
 */
(function () {
  'use strict';
  var ROTULOS = { nome: 'Nome', marca: 'Marca', peso: 'Peso', imagem: 'Foto', ncm: 'NCM', cest: 'CEST' };
  var sugerido = null;

  function el(id) { return document.getElementById(id); }
  function texto(v) { var d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; }
  function msg(t, tipo) { var m = el('si5g-msg'); m.className = 'si5g-msg alert alert-' + (tipo || 'info'); m.textContent = t; }

  function chamar(acao, corpo) {
    var raiz = el('si5g');
    var dados = new FormData();
    dados.append('ajax', '1');
    dados.append('action', acao);
    dados.append('id_product', raiz.getAttribute('data-produto'));
    Object.keys(corpo || {}).forEach(function (k) {
      [].concat(corpo[k]).forEach(function (v) { dados.append(k, v); });
    });
    return fetch(raiz.getAttribute('data-url') + '&ajax=1&action=' + acao, { method: 'POST', body: dados, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function linha(campo, atual, novo) {
    var vazio = novo === '' || novo == null;
    var cel = campo === 'imagem' && !vazio
      ? '<img src="' + texto(novo) + '" alt="" class="si5g-foto" referrerpolicy="no-referrer">'
      : texto(vazio ? '—' : novo);
    return '<tr><th>' + ROTULOS[campo] + '</th><td>' + texto(atual || '—') + '</td><td>' + cel + '</td>' +
      '<td class="text-center">' + (vazio ? '' : '<input type="checkbox" name="si5g-campo" value="' + campo + '">') + '</td></tr>';
  }

  function consultar() {
    msg('Consultando o SI5 Global…');
    el('si5g-revisao').hidden = true;
    chamar('consultar').then(function (r) {
      if (!r.ok) { msg(r.erro, 'warning'); return; }
      sugerido = r.sugerido;
      var a = r.atual, s = r.sugerido;
      el('si5g-linhas').innerHTML = [
        linha('nome', a.nome, s.nome), linha('marca', a.marca, s.marca), linha('peso', a.peso, s.peso),
        linha('imagem', a.imagem ? 'já tem foto (a nova vira capa)' : '', s.imagem_url),
        linha('ncm', a.ncm, s.ncm), linha('cest', a.cest, s.cest)
      ].join('');
      el('si5g-ref').textContent = 'NCM e CEST são sugestões do catálogo' + (s.ncm_descricao ? ' (NCM: ' + s.ncm_descricao + ')' : '') + '. Confirme com seu contador.';
      el('si5g-revisao').hidden = false;
      msg('Marque o que quer substituir e clique em Aplicar.');
    }).catch(function () { msg('Falha ao consultar. Tente novamente.', 'danger'); });
  }

  function aplicar() {
    var campos = [].slice.call(document.querySelectorAll('input[name="si5g-campo"]:checked')).map(function (c) { return c.value; });
    if (!campos.length) { msg('Marque pelo menos um campo.', 'warning'); return; }
    msg('Aplicando…');
    chamar('aplicar', { 'campos[]': campos, sugerido: JSON.stringify(sugerido) }).then(function (r) {
      if (!r.ok) { msg(r.erro, 'danger'); return; }
      el('si5g-revisao').hidden = true;
      msg('Atualizado: ' + r.alterados.join(', ') + '. Recarregue a página para ver.', 'success');
    }).catch(function () { msg('Falha ao aplicar. Tente novamente.', 'danger'); });
  }

  document.addEventListener('click', function (e) {
    if (e.target.id === 'si5g-consultar') consultar();
    else if (e.target.id === 'si5g-aplicar') aplicar();
    else if (e.target.id === 'si5g-cancelar') { el('si5g-revisao').hidden = true; msg(''); }
  });
})();
