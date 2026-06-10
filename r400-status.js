/* r400-status.js — JS-Spiegel des Status-Cockpits für die per JS gerenderten
   Karten in crm.php. Identische Logik und identisches Markup wie r400_status.php,
   nutzt dasselbe SVG-Sprite (muss einmal im DOM liegen).

   Verwendung in renderCard(l):
     const states = r4StageStates(l);
     ... r4StatusCockpit(states, 'card') ...

   Erwartete Felder am Lead-Objekt (aus api.php get_leads):
     target_url, email | phone | phone_mobile,
     has_quick, has_deep,
     phase_3_contacted_at, phase_4_engaged_at,
     phase_5_implemented_at, phase_6_closed_at
*/
(function () {
  function truthy(v) { return v !== undefined && v !== null && v !== '' && v !== 0 && v !== '0'; }

  function r4StageStates(l) {
    l = l || {};
    var contact = truthy(l.email) || truthy(l.phone) || truthy(l.phone_mobile);

    var eingang = !truthy(l.target_url) ? 'grau' : (contact ? 'gruen' : 'schwarz');
    var quick   = truthy(l.has_quick) ? 'gruen' : (truthy(l.target_url) ? 'schwarz' : 'grau');
    var psi     = truthy(l.has_deep)  ? 'gruen' : (truthy(l.has_quick)  ? 'schwarz' : 'grau');

    var anruf;
    if (truthy(l.phase_4_engaged_at))       anruf = 'gruen';
    else if (truthy(l.phase_3_contacted_at)) anruf = 'schwarz';
    else if (truthy(l.has_deep))             anruf = 'faellig';
    else                                     anruf = 'grau';

    var faktura;
    if (truthy(l.phase_6_closed_at))         faktura = 'gruen';
    else if (truthy(l.phase_5_implemented_at)) faktura = 'rot';
    else if (truthy(l.phase_4_engaged_at))   faktura = 'schwarz';
    else                                     faktura = 'grau';

    return { eingang: eingang, quick: quick, psi: psi, anruf: anruf, faktura: faktura };
  }

  var BOXES = ['eingang', 'quick', 'psi', 'anruf', 'faktura'];
  var OK = { grau: 1, schwarz: 1, gruen: 1, rot: 1, faellig: 1 };

  function r4StatusCockpit(states, variant) {
    states = states || {};
    variant = variant === 'header' ? 'header' : 'card';
    var html = '<div class="r4-cockpit r4-cockpit--' + variant + '">';
    for (var i = 0; i < BOXES.length; i++) {
      var key = BOXES[i];
      var st = states[key] || 'grau';
      if (!OK[st]) st = 'grau';
      html += '<span class="r4ic r4ic--' + st + '" title="' + key + '">'
            + '<svg class="r4ic__icon"><use href="#r4-' + key + '"/></svg>'
            + '<span class="r4ic__label">' + key + '</span>'
            + '</span>';
    }
    return html + '</div>';
  }

  window.r4StageStates = r4StageStates;
  window.r4StatusCockpit = r4StatusCockpit;
})();
