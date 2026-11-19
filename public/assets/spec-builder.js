/*
 * ModInspect Build page (LS-15). jQuery + Bootstrap + Chart.js only.
 * This file only DISPLAYS: which parts are chosen, which candidate parts fit (answered by the server, POST /build/preview, from the same PHP checks the
 * saved page and the save button use), the summary and the radar. There are no compatibility rules in here.
 */
(function ($) {
  'use strict';
  var cfg = window.MI_BUILD || {};
  if (!cfg.mode) { return; }   // the footer loads this file on every public page; only the Build page sets MI_BUILD
  var ROLES = ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'];
  var LABEL = {cpu: 'CPU', motherboard: 'เมนบอร์ด', gpu: 'การ์ดจอ', ram: 'RAM', storage: 'ที่เก็บข้อมูล', psu: 'พาวเวอร์ซัพพลาย', case: 'เคส', cooling: 'ชุดระบายความร้อน'};
  var chart = null, lastValues = [];

  // the site's "calm motion" setting (and the visitor's reduced-motion preference) turns every animation here off
  if ($('html').hasClass('motion-calm')) { $.fx.off = true; }
  var calm = $.fx.off;

  function money(n) { return '฿' + Math.round(n).toLocaleString('th-TH'); }
  function countUp($el, from, to) {
    if (calm || from === to) { $el.text(money(to)); return; }
    $({n: from}).animate({n: to}, {duration: 450, step: function (v) { $el.text(money(v)); }, complete: function () { $el.text(money(to)); }});
  }

  // ---- radar of the six spec-based stats (data comes in a data-axes attribute of the summary) ----
  function drawRadar($root) {
    var $c = $root.find('canvas.js-radar');
    if (!$c.length || typeof Chart === 'undefined') { return; }
    var axes = $c.data('axes') || [], labels = axes.map(function (a) { return a.l; }), values = axes.map(function (a) { return a.v; });
    var css = getComputedStyle(document.body), color = (css.getPropertyValue('--ci-primary') || '#bbf246').trim();
    Chart.defaults.color = (css.getPropertyValue('--ci-text-2') || '#94a3b8').trim();
    Chart.defaults.borderColor = (css.getPropertyValue('--ci-border') || '#334155').trim();
    var start = lastValues.length === values.length ? lastValues.slice() : values.map(function () { return 0; });   // morph from the previous shape
    if (chart) { chart.destroy(); }
    chart = new Chart($c[0], {
      type: 'radar',
      data: {labels: labels, datasets: [{data: start, spanGaps: true, borderColor: color, backgroundColor: color + '55', borderWidth: 2, pointRadius: 3, pointBackgroundColor: color}]},
      options: {responsive: true, maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}},
        scales: {r: {min: 0, max: 100, ticks: {display: false, stepSize: 25}, pointLabels: {font: {size: 12, weight: '600'}}}}}
    });
    chart.options.animation = {duration: calm ? 0 : 600};
    chart.data.datasets[0].data = values;
    chart.update();
    lastValues = values.map(function (v) { return v === null ? 0 : v; });
  }

  if (cfg.mode === 'saved') { $(function () { drawRadar($('.mi-summary')); }); return; }
  if (cfg.mode !== 'live') { return; }

  // ---- live builder ----
  var sel = $.extend({}, cfg.initial), cand = {}, active = 'cpu', seq = 0, timer = null, prevTotal = 0;
  var $form = $('#spec-builder-form'), $grid = $('#part-grid');
  var FLAG = {unknown: ['text-warning', 'fa-question-circle', 'ตรวจความเข้ากันไม่ได้ (ข้อมูลในแคตตาล็อกไม่ครบ)'], warn: ['text-warning', 'fa-exclamation-triangle', 'ใช้ได้แต่ควรระวัง: '], fail: ['text-danger', 'fa-ban', 'ใช้ด้วยกันไม่ได้: ']};

  function $card(id) { return $grid.find('.mi-part-col[data-id="' + id + '"]'); }
  function status(role, id) { return (cand[role] && cand[role][id]) ? cand[role][id] : ['ok', '']; }
  function chosenCount() { return ROLES.filter(function (r) { return sel[r]; }).length; }

  function renderList(animateRole) {
    ROLES.forEach(function (role) {
      var id = sel[role], $row = $('.mi-part-row[data-role="' + role + '"]'), $thumb = $row.find('.mi-thumb');
      if (!$thumb.data('icon')) { $thumb.data('icon', $thumb.html()); }
      if (id) {
        var $c = $card(id), basis = $c.data('basis');
        $row.find('.js-name').text($c.find('.js-title').text()).removeClass('text-muted');
        $row.find('.js-price').text(basis === 'none' ? 'ยังไม่มีราคา' : $c.find('.js-priceval').text() + ' · ' + $c.find('.js-basis').text()).toggleClass('text-warning', basis === 'new_ref');
        $thumb.html($c.find('.js-thumb').html());
        $row.find('.js-pick').prop('hidden', true); $row.find('.js-remove').prop('hidden', false);
        if (animateRole === role) { $row.hide().fadeIn(260); }
      } else {
        $row.find('.js-name').text('ยังไม่ได้เลือก').addClass('text-muted'); $row.find('.js-price').text('').removeClass('text-warning');
        $thumb.html($thumb.data('icon')); $row.find('.js-pick').prop('hidden', false); $row.find('.js-remove').prop('hidden', true);
      }
    });
    $('#selected-inputs').html(ROLES.filter(function (r) { return sel[r]; }).map(function (r) { return '<input type="hidden" name="parts[' + r + ']" value="' + sel[r] + '">'; }).join(''));
    $('.js-role').each(function () { var r = $(this).data('role'); $(this).toggleClass('btn-lime', r === active).toggleClass('btn-ghost', r !== active); $(this).find('.js-done').prop('hidden', !sel[r]); });
    $('.js-count').text(chosenCount() + '/8');
  }

  function applyFilters(fade) {
    var brand = $('#f-brand').val(), q = $.trim($('#f-q').val()).toLowerCase(), sort = $('#f-sort').val(), only = $('#f-compat').prop('checked');
    var $all = $grid.find('.mi-part-col[data-role="' + active + '"]');
    $grid.find('.mi-part-col').not($all).prop('hidden', true);
    var cards = [], hiddenIncompat = 0;
    $all.each(function () {
      var $c = $(this), id = $c.data('id'), st = status(active, id), chosen = sel[active] === id, $flag = $c.find('.js-flag'), $add = $c.find('.js-add');
      if (chosen) { $flag.html('<span class="text-success"><i class="fas fa-check-circle" aria-hidden="true"></i> เลือกอยู่</span>'); }
      else if (st[0] === 'ok') { $flag.empty(); }
      else { $flag.html('<span class="' + FLAG[st[0]][0] + '"><i class="fas ' + FLAG[st[0]][1] + '" aria-hidden="true"></i> </span>').find('span').append(document.createTextNode(FLAG[st[0]][2] + (st[0] === 'unknown' ? '' : st[1]))); }
      $add.prop('disabled', chosen || st[0] === 'fail').text(chosen ? 'เลือกอยู่' : (sel[active] ? 'เปลี่ยนเป็นรุ่นนี้' : 'เพิ่มลงสเปก'));
      var visible = (!brand || $c.data('brand') === brand) && (!q || String($c.data('name')).indexOf(q) > -1);
      if (visible && only && st[0] === 'fail' && !chosen) { hiddenIncompat++; visible = false; }
      cards.push({$c: $c, visible: visible});
    });
    var rank = {used: 0, new_ref: 1, none: 2};
    cards.sort(function (a, b) {
      var pa = a.$c.data('price'), pb = b.$c.data('price'), na = a.$c.data('basis') === 'none', nb = b.$c.data('basis') === 'none';
      if (sort === 'name') { return String(a.$c.data('name')).localeCompare(String(b.$c.data('name'))); }
      if (sort === 'asc' || sort === 'desc') { if (na !== nb) { return na ? 1 : -1; } return sort === 'asc' ? pa - pb : pb - pa; }
      var ra = rank[a.$c.data('basis')], rb = rank[b.$c.data('basis')];
      return ra !== rb ? ra - rb : (b.$c.data('conf') - a.$c.data('conf')) || (pa - pb);
    });
    var shown = 0;
    cards.forEach(function (o) {
      $grid.append(o.$c);   // reorders in place
      if (o.visible) { shown++; if (o.$c.prop('hidden')) { o.$c.prop('hidden', false); if (fade) { o.$c.hide().fadeIn(180); } } } else { o.$c.prop('hidden', true); }
    });
    $('.js-found').text('พบ ' + shown + ' รุ่น' + (hiddenIncompat ? ' · ซ่อน ' + hiddenIncompat + ' รุ่นที่ใช้ด้วยกันไม่ได้' : ''));
    $('#part-empty').prop('hidden', shown > 0);
  }

  function setRole(role, scroll) {
    active = role;
    $('#picker-title').text('เลือก ' + LABEL[role]);
    var brands = {};
    $grid.find('.mi-part-col[data-role="' + role + '"]').each(function () { brands[$(this).data('brand')] = 1; });
    $('#f-brand').html('<option value="">ทุกยี่ห้อ</option>' + Object.keys(brands).sort().map(function (b) { return $('<option>').val(b).text(b)[0].outerHTML; }).join(''));
    $('#f-q').val('');
    $grid.find('.mi-part-col').prop('hidden', true);   // so the new category fades in
    renderList(); applyFilters(true);
    if (scroll && window.innerWidth < 992) { $('html, body').animate({scrollTop: $('#picker').offset().top - 90}, 250); }
  }

  function nextEmpty(from) {
    var i = ROLES.indexOf(from);
    for (var k = 1; k <= ROLES.length; k++) { var r = ROLES[(i + k) % ROLES.length]; if (!sel[r]) { return r; } }
    return null;
  }

  function preview() {
    var my = ++seq, data = {_token: $form.find('input[name="_token"]').val(), budget: $('#spec-budget').val()};
    ROLES.forEach(function (r) { if (sel[r]) { data['parts[' + r + ']'] = sel[r]; } });
    $.ajax({url: cfg.previewUrl, method: 'POST', data: data, dataType: 'json'}).done(function (res) {
      if (my !== seq) { return; }   // an older answer arriving late
      var $pane = $('#summary-pane');
      $pane.html(res.summary_html).hide().fadeIn(160);
      countUp($pane.find('.js-total'), prevTotal, res.expected); countUp($('.js-side-total'), prevTotal, res.expected); prevTotal = res.expected;
      drawRadar($pane);
      cand = res.candidates || {}; applyFilters(false);
      var n = res.chosen, ok = n > 0 && !res.blocked;
      $('.js-save').prop('disabled', !ok);
      $('.js-save-hint').text(n === 0 ? 'เลือกอย่างน้อย 1 ชิ้นเพื่อบันทึก' : (res.blocked ? 'มีชิ้นที่ใช้ด้วยกันไม่ได้ ดูรายการด้านขวา' : 'บันทึกแล้วได้ลิงก์ส่งต่อสเปกนี้'));
    }).fail(function (xhr) {
      if (my !== seq) { return; }
      $('.js-save-hint').text(xhr.status === 429 ? 'ส่งคำขอบ่อยเกินไป รอสักครู่' : 'อัปเดตสรุปไม่ได้ ลองใหม่อีกครั้ง');
    });
  }
  function schedulePreview() { clearTimeout(timer); timer = setTimeout(preview, 120); }

  // ---- events ----
  $form.on('click', '.js-role, .js-pick', function () { setRole($(this).data('role'), true); });
  $form.on('click', '.js-add', function () {
    var role = $(this).data('role'), id = $(this).data('id');
    sel[role] = id; renderList(role); schedulePreview();
    var next = nextEmpty(role);
    if (next) { setTimeout(function () { setRole(next, false); }, 350); } else { applyFilters(false); }
  });
  $form.on('click', '.js-remove', function () { var role = $(this).data('role'); delete sel[role]; schedulePreview(); setRole(role, true); });
  $form.on('click', '.js-reset', function () { sel = {}; cand = {}; schedulePreview(); setRole('cpu', false); });
  $('#f-brand, #f-sort, #f-compat').on('change', function () { applyFilters(true); });
  $('#f-q').on('input', function () { applyFilters(false); }).on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); } });
  $('#spec-budget').on('input', schedulePreview);

  $(function () {
    var first = ROLES.filter(function (r) { return !sel[r]; })[0] || 'cpu';
    setRole(first, false);
    if (chosenCount() > 0) { preview(); }
  });
})(jQuery);
